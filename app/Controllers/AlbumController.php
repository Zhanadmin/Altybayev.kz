<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AlbumMediaService;
use App\Services\AlbumService;
use PDO;
use RuntimeException;

/**
 * TASK-018: фотоальбомы семей и мероприятий.
 *
 * Страницы:  /family/{id}/albums, /album/{id}, /events, /event/{id}
 * API (POST, CSRF): альбомы, мероприятия, загрузка по частям (init → chunk → complete), подпись/отметки, удаление, аватар.
 * Файлы:     GET /album/media?id=&v=thumb|full[&dl=1] — только после входа, с поддержкой Range для видео.
 */
class AlbumController
{
    private const MAX_UPLOADS_PER_HOUR = 400;
    private const MAX_ACTIVE_UPLOADS = 12;
    private const DISK_RESERVE_BYTES = 1024 * 1024 * 1024; // оставлять минимум 1 ГБ свободным

    private ?AlbumService $albums = null;
    private AlbumMediaService $media;

    public function __construct()
    {
        $this->media = new AlbumMediaService();
    }

    private function svc(): AlbumService
    {
        return $this->albums ??= new AlbumService();
    }

    private function lang(): string
    {
        return I18n::lang();
    }

    private function tr(string $kk, string $ru): string
    {
        return $this->lang() === 'ru' ? $ru : $kk;
    }

    private function notReadyPage(): void
    {
        Response::view('albums/not_ready', ['user' => Auth::user()]);
    }

    // ================================================================== страницы

    /** Альбомы семьи */
    public function familyAlbums(Request $request, string $id): void
    {
        if (!$this->svc()->isReady()) {
            $this->notReadyPage();
            return;
        }
        $family = $this->svc()->getFamily((int)$id);
        if (!$family) {
            $this->notFound();
            return;
        }
        $user = Auth::user();
        $albums = $this->svc()->familyAlbums((int)$id, $this->lang());
        Response::view('albums/family', [
            'user'          => $user,
            'family'        => $family,
            'albums'        => $albums,
            'canContribute' => $this->svc()->canContributeToFamily((int)$id, $user),
            'csrf'          => Session::csrfToken(),
        ]);
    }

    /** Страница альбома (семьи или мероприятия) */
    public function show(Request $request, string $id): void
    {
        if (!$this->svc()->isReady()) {
            $this->notReadyPage();
            return;
        }
        $album = $this->svc()->getAlbum((int)$id);
        if (!$album) {
            $this->notFound();
            return;
        }
        if ($album['owner_type'] === 'event') {
            Response::redirect('/event/' . (int)$album['owner_id']);
            return;
        }
        $family = $this->svc()->getFamily((int)$album['owner_id']);
        $this->renderAlbum($album, $family, null);
    }

    /** Список мероприятий + форма создания (в т.ч. из памятной даты) */
    public function events(Request $request): void
    {
        if (!$this->svc()->isReady()) {
            $this->notReadyPage();
            return;
        }
        $user = Auth::user();
        $prefill = $this->prefillFromDate($request);
        $diskFree = Auth::isAdmin() ? $this->storageFree() : null;

        Response::view('albums/events', [
            'user'        => $user,
            'events'      => $this->svc()->listEvents(),
            'people'      => $this->svc()->peopleList(),
            'families'    => $this->svc()->familiesList(),
            'canCreate'   => $this->canCreateEvent($user),
            'prefill'     => $prefill,
            'csrf'        => Session::csrfToken(),
            'diskFree'    => $diskFree !== false ? $diskFree : null,
            'videoMaxMb'  => (int)round(AlbumMediaService::videoMaxBytes() / 1048576),
        ]);
    }

    /** Страница мероприятия = его альбом */
    public function event(Request $request, string $id): void
    {
        if (!$this->svc()->isReady()) {
            $this->notReadyPage();
            return;
        }
        $event = $this->svc()->getEvent((int)$id);
        if (!$event) {
            $this->notFound();
            return;
        }
        $this->renderAlbum($event['album'], null, $event);
    }

    private function renderAlbum(array $album, ?array $family, ?array $event): void
    {
        $user = Auth::user();
        $owner = $event ?? ['created_by' => $album['created_by']];
        Response::view('albums/show', [
            'user'          => $user,
            'album'         => $album,
            'family'        => $family,
            'event'         => $event,
            'items'         => $this->svc()->albumMedia((int)$album['id']),
            'people'        => $this->svc()->peopleList(),
            'families'      => $event ? $this->svc()->familiesList() : [],
            'canContribute' => $this->svc()->canContributeToAlbum($album, $user),
            'canManage'     => AlbumService::canManage($event ? $event : $album, $user),
            'canEditAll'    => Auth::canEdit(),
            'csrf'          => Session::csrfToken(),
            'chunkSize'     => self::chunkSize(),
            'videoMaxMb'    => (int)round(AlbumMediaService::videoMaxBytes() / 1048576),
            'photoMaxMb'    => (int)round(AlbumMediaService::PHOTO_MAX_BYTES / 1048576),
        ]);
    }

    private function notFound(): void
    {
        http_response_code(404);
        Response::view('albums/not_ready', ['user' => Auth::user(), 'notFound' => true]);
    }

    /** Свободное место на диске хранилища (байты) или null */
    private function storageFree(): ?float
    {
        $dir = dirname($this->media->baseDir());
        $free = @disk_free_space(is_dir($dir) ? $dir : dirname(__DIR__, 2));
        return $free === false ? null : (float)$free;
    }

    private function canCreateEvent(?array $user): bool
    {
        // создают мероприятия и выбирают участников только editor/admin; участники-viewer добавляют в него фото
        return $user !== null && Auth::canEdit();
    }

    /** Предзаполнение из «Есте қаларлық күндер»: ?person_id=&date=&kind=birthday|memorial|wedding&marriage_id= */
    private function prefillFromDate(Request $request): ?array
    {
        $pid = (int)$request->get('person_id', 0);
        $mid = (int)$request->get('marriage_id', 0);
        $kind = (string)$request->get('kind', 'other');
        if (!in_array($kind, ['birthday', 'memorial', 'wedding', 'other'], true)) {
            $kind = 'other';
        }
        $date = self::validDate((string)$request->get('date', ''));
        if ($pid <= 0 && $mid <= 0 && $date === null) {
            return null;
        }
        $lang = $this->lang();
        $title = '';
        $people = [];
        $families = [];

        if ($pid > 0 && ($p = $this->svc()->getPerson($pid))) {
            $people[] = $pid;
            $first = trim((string)$p['first_name']);
            if ($kind === 'birthday') {
                $title = $lang === 'ru' ? "День рождения: {$first}" : I18n::kazakhGenitive($first) . ' туған күні';
            } elseif ($kind === 'memorial') {
                $title = $lang === 'ru' ? "День памяти: {$first}" : I18n::kazakhGenitive($first) . ' еске алу күні';
            } else {
                $title = $first;
            }
        }
        if ($mid > 0 && ($f = $this->svc()->getFamily($mid))) {
            $families[] = $mid;
            $h = trim((string)($f['husband']['first_name'] ?? ''));
            $w = trim((string)($f['wife']['first_name'] ?? ''));
            if ($title === '' && $h !== '' && $w !== '') {
                $title = $lang === 'ru' ? "Годовщина свадьбы: {$h} и {$w}" : "{$h} мен {$w} үйленген күні";
            } elseif ($title === '') {
                $title = $lang === 'ru' ? 'Годовщина свадьбы' : 'Үйленген күн';
            }
        }
        return [
            'title'    => $title,
            'date'     => $date,
            'kind'     => $kind,
            'person'   => $pid > 0 ? $pid : null,
            'people'   => $people,
            'families' => $families,
        ];
    }

    // ================================================================== API: альбомы и мероприятия

    /** Строковое значение POST (массив вместо строки → пусто) */
    private static function str(Request $request, string $key): string
    {
        $v = $request->post($key, '');
        return is_string($v) || is_int($v) ? (string)$v : '';
    }

    private function requireCsrf(Request $request): void
    {
        // запрос больше post_max_size: PHP очищает $_POST и $_FILES — это не «устаревшая страница»
        if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            Response::json(['success' => false, 'error' => $this->tr('Сұраныс сервер шегінен үлкен', 'Запрос больше лимита сервера')], 413);
        }
        $token = self::str($request, 'csrf_token') ?: (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'error' => $this->tr('Бет ескірді — жаңартыңыз', 'Страница устарела — обновите её')], 419);
        }
    }

    private function requireReady(): void
    {
        if (!$this->svc()->isReady()) {
            Response::json(['success' => false, 'error' => $this->tr('Альбомдар әлі қосылмаған (миграция керек)', 'Альбомы ещё не включены (нужна миграция)')], 503);
        }
    }

    private function fail(string $kk, string $ru, int $code = 400): void
    {
        Response::json(['success' => false, 'error' => $this->tr($kk, $ru)], $code);
    }

    /** POST /api/album/save — создать (owner_type=family, owner_id) или изменить (id) альбом */
    public function apiSaveAlbum(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $user = Auth::user();

        $title = self::cleanText(self::str($request, 'title'), 200);
        $date = self::validDate(self::str($request, 'album_date'));
        $desc = self::cleanText(self::str($request, 'description'), 2000);
        if ($title === '') {
            $this->fail('Альбом атауын жазыңыз', 'Укажите название альбома');
        }

        $id = (int)$request->post('id', 0);
        if ($id > 0) {
            $album = $this->svc()->getAlbum($id);
            if (!$album) {
                $this->fail('Альбом табылмады', 'Альбом не найден', 404);
            }
            if (!AlbumService::canManage($album, $user) && !Auth::canEdit()) {
                $this->fail('Құқық жоқ', 'Нет прав', 403);
            }
            $this->svc()->updateAlbum($id, $title, $date, $desc !== '' ? $desc : null, (int)$user['id']);
            Response::json(['success' => true, 'id' => $id]);
        }

        $familyId = (int)$request->post('owner_id', 0);
        if ($familyId <= 0 || !$this->svc()->getFamily($familyId)) {
            $this->fail('Отбасы табылмады', 'Семья не найдена', 404);
        }
        if (!$this->svc()->canContributeToFamily($familyId, $user)) {
            $this->fail('Альбом қосуға құқық жоқ', 'Нет прав добавлять альбом', 403);
        }
        $newId = $this->svc()->createAlbum('family', $familyId, $title, $date, $desc !== '' ? $desc : null, (int)$user['id']);
        Response::json(['success' => true, 'id' => $newId, 'url' => '/album/' . $newId]);
    }

    /** POST /api/album/delete — только пустой, создатель или админ */
    public function apiDeleteAlbum(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $user = Auth::user();
        $album = $this->svc()->getAlbum((int)$request->post('id', 0));
        if (!$album || $album['owner_type'] !== 'family') {
            $this->fail('Альбом табылмады', 'Альбом не найден', 404);
        }
        if (!AlbumService::canManage($album, $user)) {
            $this->fail('Жоюға құқық жоқ', 'Нет прав на удаление', 403);
        }
        try {
            $this->svc()->deleteAlbum((int)$album['id'], (int)$user['id']);
        } catch (RuntimeException $e) {
            $this->fail('Алдымен фото мен бейнелерді жойыңыз', 'Сначала удалите фото и видео из альбома', 409);
        }
        Response::json(['success' => true, 'url' => '/family/' . (int)$album['owner_id'] . '/albums']);
    }

    /** POST /api/event/save */
    public function apiSaveEvent(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $user = Auth::user();

        $title = self::cleanText(self::str($request, 'title'), 200);
        if ($title === '') {
            $this->fail('Іс-шара атауын жазыңыз', 'Укажите название мероприятия');
        }
        $data = [
            'title'       => $title,
            'event_date'  => self::validDate(self::str($request, 'event_date')),
            'place'       => self::cleanText(self::str($request, 'place'), 200) ?: null,
            'description' => self::cleanText(self::str($request, 'description'), 2000) ?: null,
        ];
        $people = self::idList($request->post('people', ''));
        $families = self::idList($request->post('families', ''));

        $id = (int)$request->post('id', 0);
        if ($id > 0) {
            $ev = $this->svc()->getEventRow($id);
            if (!$ev) {
                $this->fail('Іс-шара табылмады', 'Мероприятие не найдено', 404);
            }
            if (!AlbumService::canManage($ev, $user) && !Auth::canEdit()) {
                $this->fail('Өзгертуге құқық жоқ', 'Нет прав на изменение', 403);
            }
        } else {
            if (!$this->canCreateEvent($user)) {
                $this->fail('Іс-шара құруға құқық жоқ', 'Нет прав создавать мероприятие', 403);
            }
            $kind = self::str($request, 'kind');
            $data['kind'] = in_array($kind, ['birthday', 'memorial', 'wedding', 'other'], true) ? $kind : 'other';
            $src = (int)$request->post('source_person_id', 0);
            $data['source_person_id'] = $src > 0 && $this->svc()->getPerson($src) ? $src : null;
            // создатель-участник автоматически в списке людей
            if (!empty($user['person_id'])) {
                $people[] = (int)$user['person_id'];
            }
        }

        $eventId = $this->svc()->saveEvent($id > 0 ? $id : null, $data, $people, $families, (int)$user['id']);
        Response::json(['success' => true, 'id' => $eventId, 'url' => '/event/' . $eventId]);
    }

    /** POST /api/event/delete — только без фото, создатель или админ */
    public function apiDeleteEvent(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $user = Auth::user();
        $ev = $this->svc()->getEventRow((int)$request->post('id', 0));
        if (!$ev) {
            $this->fail('Іс-шара табылмады', 'Мероприятие не найдено', 404);
        }
        if (!AlbumService::canManage($ev, $user)) {
            $this->fail('Жоюға құқық жоқ', 'Нет прав на удаление', 403);
        }
        try {
            $this->svc()->deleteEvent((int)$ev['id'], (int)$user['id']);
        } catch (RuntimeException $e) {
            $this->fail('Алдымен фото мен бейнелерді жойыңыз', 'Сначала удалите фото и видео', 409);
        }
        Response::json(['success' => true, 'url' => '/events']);
    }

    // ================================================================== API: загрузка по частям

    /** Размер части: меньше лимитов PHP сервера (upload_max_filesize / post_max_size), от 512 КБ до 8 МБ */
    public static function chunkSize(): int
    {
        $lim = min(self::iniBytes((string)ini_get('upload_max_filesize')), self::iniBytes((string)ini_get('post_max_size')));
        if ($lim <= 0) {
            $lim = 2 * 1024 * 1024;
        }
        $size = $lim - 128 * 1024;
        if ($size < 512 * 1024) {
            return max(128 * 1024, $lim - 64 * 1024); // очень маленький лимит сервера — не превышаем его
        }
        return min(8 * 1024 * 1024, $size);
    }

    private static function iniBytes(string $v): int
    {
        $v = trim($v);
        if ($v === '') {
            return 0;
        }
        $n = (int)$v;
        $u = strtolower(substr($v, -1));
        return match ($u) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }

    /** POST /api/album/upload/init {album_id, name, size} */
    public function apiUploadInit(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $user = Auth::user();
        $uid = (int)$user['id'];

        $album = $this->svc()->getAlbum((int)$request->post('album_id', 0));
        if (!$album) {
            $this->fail('Альбом табылмады', 'Альбом не найден', 404);
        }
        if (!$this->svc()->canContributeToAlbum($album, $user)) {
            $this->fail('Бұл альбомға қосуға құқық жоқ (тек қарауға болады)', 'Нет прав добавлять в этот альбом (только просмотр)', 403);
        }

        $name = mb_substr(basename(str_replace('\\', '/', self::str($request, 'name'))), 0, 200);
        $size = (int)self::str($request, 'size');
        $kind = AlbumMediaService::kindByName($name);
        if ($kind === null) {
            $this->fail('Тек фото (JPEG, PNG, WebP) және бейне (MP4, MOV, WebM)', 'Только фото (JPEG, PNG, WebP) и видео (MP4, MOV, WebM)', 415);
        }
        if ($size <= 0) {
            $this->fail('Файл бос', 'Файл пустой', 400);
        }
        $max = AlbumMediaService::maxBytesFor($kind);
        if ($size > $max) {
            $mb = (int)round($max / 1048576);
            $this->fail("Файл тым үлкен (шегі {$mb} МБ)", "Файл слишком большой (лимит {$mb} МБ)", 413);
        }
        if ($this->svc()->uploadsLastHour($uid) >= self::MAX_UPLOADS_PER_HOUR) {
            $this->fail('Бір сағатқа шектеу — кейінірек жалғастырыңыз', 'Лимит на час исчерпан — продолжите позже', 429);
        }

        // лимит частоты начатых загрузок (в т.ч. отменённых) — по сессии, 600 в час
        $hourKey = 'album_inits_' . date('YmdH');
        $inits = (int)Session::get($hourKey, 0);
        if ($inits >= 600) {
            $this->fail('Бір сағатқа шектеу — кейінірек жалғастырыңыз', 'Лимит на час исчерпан — продолжите позже', 429);
        }
        Session::set($hourKey, $inits + 1);

        $tmp = $this->media->tmpDir();
        $this->media->cleanupTmp(6 * 3600);

        // активные загрузки: свои (моложе 2 часов) — для лимита; все — для резерва места на диске
        $active = 0;
        $pendingBytes = 0;
        foreach (glob($tmp . DIRECTORY_SEPARATOR . '*.json') ?: [] as $jf) {
            $m = json_decode((string)@file_get_contents($jf), true);
            if (!is_array($m)) {
                continue;
            }
            $pendingBytes += max(0, (int)($m['size'] ?? 0) - (int)($m['received'] ?? 0));
            if ((int)($m['user_id'] ?? 0) === $uid && (time() - (int)($m['started'] ?? 0)) < 7200) {
                $active++;
            }
        }
        if ($active >= self::MAX_ACTIVE_UPLOADS) {
            $this->fail('Тым көп қатар жүктеу — біраз күтіңіз', 'Слишком много одновременных загрузок — подождите', 429);
        }

        $free = $this->storageFree();
        if ($free !== null && $free < $size * 2 + $pendingBytes + self::DISK_RESERVE_BYTES) {
            $this->fail('Серверде орын жеткіліксіз — Жандосқа хабарлаңыз', 'На сервере мало места — сообщите Жандосу', 507);
        }

        $uploadId = bin2hex(random_bytes(16));
        $meta = [
            'user_id'  => $uid,
            'album_id' => (int)$album['id'],
            'name'     => $name,
            'size'     => $size,
            'kind'     => $kind,
            'received' => 0,
            'started'  => time(),
        ];
        file_put_contents($tmp . DIRECTORY_SEPARATOR . $uploadId . '.json', json_encode($meta), LOCK_EX);
        file_put_contents($tmp . DIRECTORY_SEPARATOR . $uploadId . '.part', '', LOCK_EX);

        Response::json(['success' => true, 'upload_id' => $uploadId, 'chunk_size' => self::chunkSize(), 'kind' => $kind]);
    }

    /** @return array{0:array,1:string,2:string} meta, json path, part path */
    private function loadUpload(Request $request, int $uid): array
    {
        $uploadId = self::str($request, 'upload_id');
        if (!preg_match('/^[a-f0-9]{32}$/', $uploadId)) {
            $this->fail('Жүктеу табылмады', 'Загрузка не найдена', 404);
        }
        $tmp = $this->media->tmpDir();
        $jf = $tmp . DIRECTORY_SEPARATOR . $uploadId . '.json';
        $pf = $tmp . DIRECTORY_SEPARATOR . $uploadId . '.part';
        $meta = is_file($jf) ? json_decode((string)file_get_contents($jf), true) : null;
        if (!is_array($meta) || (int)($meta['user_id'] ?? 0) !== $uid || !is_file($pf)) {
            $this->fail('Жүктеу табылмады', 'Загрузка не найдена', 404);
        }
        return [$meta, $jf, $pf];
    }

    /** POST /api/album/upload/chunk {upload_id, offset, chunk(file)} */
    public function apiUploadChunk(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $user = Auth::user();
        [$meta, $jf, $pf] = $this->loadUpload($request, (int)$user['id']);

        $chunk = $_FILES['chunk'] ?? null;
        if (!$chunk || !isset($chunk['error']) || is_array($chunk['error']) || $chunk['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string)$chunk['tmp_name'])) {
            $this->fail('Бөлік жүктелмеді — қайталаңыз', 'Часть файла не дошла — повторите', 400);
        }
        $offset = (int)self::str($request, 'offset');
        $len = (int)filesize((string)$chunk['tmp_name']);

        // блокировка ДО проверки смещения: параллельный повтор той же части не допишет её дважды
        $out = fopen($pf, 'ab');
        if (!$out || !flock($out, LOCK_EX)) {
            $this->fail('Сақтау қатесі', 'Ошибка сохранения', 500);
        }
        $have = (int)fstat($out)['size'];
        if ($offset !== $have) {
            flock($out, LOCK_UN);
            fclose($out);
            Response::json(['success' => false, 'error' => 'offset', 'received' => $have], 409);
        }
        if ($len <= 0 || $len > self::chunkSize() + 1024 || $have + $len > (int)$meta['size']) {
            flock($out, LOCK_UN);
            fclose($out);
            $this->fail('Бөлік өлшемі дұрыс емес', 'Неверный размер части', 400);
        }
        $in = fopen((string)$chunk['tmp_name'], 'rb');
        if (!$in) {
            flock($out, LOCK_UN);
            fclose($out);
            $this->fail('Сақтау қатесі', 'Ошибка сохранения', 500);
        }
        stream_copy_to_stream($in, $out);
        fflush($out);
        $received = (int)fstat($out)['size'];
        $meta['received'] = $received;
        file_put_contents($jf, json_encode($meta), LOCK_EX);
        flock($out, LOCK_UN);
        fclose($out);
        fclose($in);
        Response::json(['success' => true, 'received' => $received]);
    }

    /** POST /api/album/upload/complete {upload_id, caption, taken_date, people, duration, poster(file)} */
    public function apiUploadComplete(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $user = Auth::user();
        $uid = (int)$user['id'];
        [$meta, $jf, $pf] = $this->loadUpload($request, $uid);
        $lang = $this->lang();

        clearstatcache(true, $pf);
        if ((int)filesize($pf) !== (int)$meta['size']) {
            $this->fail('Файл толық жүктелмеді', 'Файл загружен не полностью', 400);
        }
        // атомарно «забираем» загрузку: повторный complete того же upload_id не создаст дубликат
        $lockedJf = $jf . '.done';
        if (!@rename($jf, $lockedJf)) {
            $this->fail('Жүктеу өңделуде', 'Загрузка уже обрабатывается', 409);
        }
        $jf = $lockedJf;
        $album = $this->svc()->getAlbum((int)$meta['album_id']);
        if (!$album || !$this->svc()->canContributeToAlbum($album, $user)) {
            @unlink($pf);
            @unlink($jf);
            $this->fail('Құқық жоқ', 'Нет прав', 403);
        }

        $caption = self::cleanText(self::str($request, 'caption'), 500);
        $date = self::validDate(self::str($request, 'taken_date')) ?? ($album['album_date'] ?? null);
        $people = self::idList($request->post('people', ''));

        try {
            if ($meta['kind'] === 'photo') {
                $res = $this->media->processPhoto($pf, $lang);
                @unlink($pf);
                $row = [
                    'media_type' => 'photo',
                    'file_path'  => $res['file_path'],
                    'thumb_path' => $res['thumb_path'],
                    'ext'        => 'jpg',
                    'file_size'  => $res['file_size'],
                    'width'      => $res['width'],
                    'height'     => $res['height'],
                ];
            } else {
                $res = $this->media->processVideo($pf, (string)$meta['name'], $lang);
                $thumb = null;
                $poster = $_FILES['poster'] ?? null;
                if ($poster && isset($poster['error']) && !is_array($poster['error']) && $poster['error'] === UPLOAD_ERR_OK && is_uploaded_file((string)$poster['tmp_name'])) {
                    try {
                        $thumb = $this->media->processPoster((string)$poster['tmp_name'], $lang);
                    } catch (\Throwable $e) {
                        $thumb = null; // видео сохраняем и без превью
                    }
                }
                $dur = (int)$request->post('duration', 0);
                $row = [
                    'media_type'       => 'video',
                    'file_path'        => $res['file_path'],
                    'thumb_path'       => $thumb,
                    'ext'              => $res['ext'],
                    'file_size'        => $res['file_size'],
                    'duration_seconds' => ($dur > 0 && $dur < 86400) ? $dur : null,
                ];
            }
        } catch (\Throwable $e) {
            @unlink($pf);
            @unlink($jf);
            $msg = $e instanceof RuntimeException ? $e->getMessage() : $this->tr('Файлды өңдеу мүмкін болмады', 'Не удалось обработать файл');
            Response::json(['success' => false, 'error' => $msg], 422);
            return;
        }
        @unlink($jf);

        $row += [
            'album_id'      => (int)$album['id'],
            'original_name' => (string)$meta['name'],
            'caption'       => $caption !== '' ? $caption : null,
            'taken_date'    => $date,
        ];
        try {
            $mediaId = $this->svc()->insertMedia($row, $people, $uid);
        } catch (\Throwable $e) {
            $this->media->deleteFiles($row['file_path'], $row['thumb_path'] ?? null);
            throw $e;
        }

        $item = $this->svc()->getMedia($mediaId);
        Response::json(['success' => true, 'item' => $this->publicItem($item, $user)]);
    }

    /** POST /api/album/upload/cancel {upload_id} */
    public function apiUploadCancel(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $user = Auth::user();
        [, $jf, $pf] = $this->loadUpload($request, (int)$user['id']);
        @unlink($pf);
        @unlink($jf);
        Response::json(['success' => true]);
    }

    // ================================================================== API: фото

    /** POST /api/album/media/update {id, caption, taken_date, people} — автор, редактор или админ */
    public function apiMediaUpdate(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $user = Auth::user();
        $m = $this->svc()->getMedia((int)$request->post('id', 0));
        if (!$m) {
            $this->fail('Табылмады', 'Не найдено', 404);
        }
        $album = $this->svc()->getAlbum((int)$m['album_id']);
        $isAuthorStillMember = AlbumService::canDeleteMedia($m, $user) && $album && $this->svc()->canContributeToAlbum($album, $user);
        if (!$isAuthorStillMember && !Auth::canEdit()) {
            $this->fail('Өзгертуге құқық жоқ', 'Нет прав на изменение', 403);
        }
        $caption = self::cleanText(self::str($request, 'caption'), 500);
        $date = self::validDate(self::str($request, 'taken_date'));
        $this->svc()->updateMediaInfo((int)$m['id'], $caption !== '' ? $caption : null, $date, self::idList($request->post('people', '')), (int)$user['id']);
        Response::json(['success' => true, 'item' => $this->publicItem($this->svc()->getMedia((int)$m['id']), $user)]);
    }

    /** POST /api/album/media/delete {id} — автор или админ */
    public function apiMediaDelete(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $user = Auth::user();
        $m = $this->svc()->getMedia((int)$request->post('id', 0));
        if (!$m) {
            $this->fail('Табылмады', 'Не найдено', 404);
        }
        if (!AlbumService::canDeleteMedia($m, $user)) {
            $this->fail('Жоюға тек автор мен әкімші құқылы', 'Удалять может только автор и администратор', 403);
        }
        $this->svc()->deleteMediaRow($m, (int)$user['id']);
        $this->media->deleteFiles($m['file_path'], $m['thumb_path']);
        Response::json(['success' => true]);
    }

    /** POST /api/album/media/avatar {id, person_id} — фото с отметкой человека → аватар в карточке */
    public function apiSetAvatar(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $user = Auth::user();
        $m = $this->svc()->getMedia((int)$request->post('id', 0));
        $personId = (int)self::str($request, 'person_id');
        if (!$m || $m['media_type'] !== 'photo') {
            $this->fail('Фото табылмады', 'Фото не найдено', 404);
        }
        $tagged = array_map(fn($p) => (int)$p['id'], $m['people']);
        if (!in_array($personId, $tagged, true)) {
            $this->fail('Алдымен адамды фотода белгілеңіз', 'Сначала отметьте человека на фото', 400);
        }
        $isSelf = (int)($user['person_id'] ?? 0) === $personId;
        if (!Auth::canEdit() && !$isSelf) {
            $this->fail('Аватарды тек редактор, әкімші немесе адамның өзі қоя алады', 'Аватар может поставить редактор, администратор или сам человек', 403);
        }

        $db = Database::getConnection();
        $old = $db->prepare('SELECT avatar_path FROM persons WHERE id = ?');
        $old->execute([$personId]);
        $oldPath = $old->fetchColumn();

        // TASK-018: квадрат, выбранный в окне обрезки (доли 0..1); без него — квадрат по центру
        $crop = null;
        $cx = self::str($request, 'crop_x');
        $cy = self::str($request, 'crop_y');
        $cs = self::str($request, 'crop_s');
        if ($cx !== '' && $cy !== '' && $cs !== '') {
            if (!is_numeric($cx) || !is_numeric($cy) || !is_numeric($cs)) {
                $this->fail('Қате кесу аймағы', 'Неверная область обрезки', 400);
            }
            $crop = ['x' => (float)$cx, 'y' => (float)$cy, 's' => (float)$cs];
        }

        $url = $this->media->makeAvatar((string)$m['file_path'], dirname(__DIR__, 2) . '/public/uploads/photos', '/uploads/photos/', $crop);
        $db->prepare('UPDATE persons SET avatar_path = ?, updated_at = NOW() WHERE id = ?')->execute([$url, $personId]);
        try {
            $db->prepare("INSERT INTO audit_logs (user_id, user_name, user_email, action, entity_table, entity_id, summary, old_values, new_values, created_at)
                          VALUES (?, ?, ?, 'UPDATE', 'persons', ?, ?, ?, ?, NOW())")
               ->execute([
                   (int)$user['id'], $user['name'] ?? null, $user['email'] ?? null, $personId,
                   'Аватар альбомнан қойылды (фото ' . (int)$m['id'] . ')',
                   json_encode(['avatar_path' => $oldPath ?: null], JSON_UNESCAPED_UNICODE),
                   json_encode(['avatar_path' => $url], JSON_UNESCAPED_UNICODE),
               ]);
        } catch (\Throwable $e) {
        }
        Response::json(['success' => true, 'avatar_url' => $url]);
    }

    // ================================================================== выдача файлов

    /** GET /album/media?id=&v=thumb|full[&dl=1] */
    public function serve(Request $request): void
    {
        if (!$this->svc()->isReady()) {
            http_response_code(404);
            return;
        }
        $m = $this->svc()->getMedia((int)$request->get('id', 0));
        $variant = (string)$request->get('v', 'thumb');
        if (!$m) {
            http_response_code(404);
            return;
        }
        $rel = $variant === 'full' ? $m['file_path'] : ($m['thumb_path'] ?: null);
        if ($rel === null) {
            http_response_code(404);
            return;
        }
        try {
            $path = $this->media->abs((string)$rel);
        } catch (\Throwable $e) {
            http_response_code(404);
            return;
        }
        if (!is_file($path)) {
            http_response_code(404);
            return;
        }

        $isVideoFile = ($variant === 'full' && $m['media_type'] === 'video');
        $ext = $isVideoFile ? (string)$m['ext'] : 'jpg';
        $mime = AlbumMediaService::mimeForExt($ext);
        if ($isVideoFile && $ext === 'mov' && empty($request->get('dl'))) {
            $mime = 'video/mp4'; // H.264 .mov с iPhone так воспроизводится и в Chrome
        }

        // длинная отдача видео не должна блокировать остальные запросы этой сессии
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        @set_time_limit(0);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $size = (int)filesize($path);
        $download = !empty($request->get('dl'));
        $fileName = 'altybayev-' . (int)$m['id'] . '.' . $ext;

        header_remove('Pragma');
        header_remove('Expires');
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Cross-Origin-Resource-Policy: same-origin');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: private, max-age=86400');
        header('Accept-Ranges: bytes');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $fileName . '"');

        $start = 0;
        $end = $size - 1;
        $range = $_SERVER['HTTP_RANGE'] ?? '';
        if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $mm)) {
            if ($mm[1] === '' && $mm[2] !== '') {
                $start = max(0, $size - (int)$mm[2]);
            } else {
                $start = (int)$mm[1];
                if ($mm[2] !== '') {
                    $end = min((int)$mm[2], $size - 1);
                }
            }
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header("Content-Range: bytes */{$size}");
                return;
            }
            http_response_code(206);
            header("Content-Range: bytes {$start}-{$end}/{$size}");
        }
        $length = $end - $start + 1;
        header('Content-Length: ' . $length);

        $fp = fopen($path, 'rb');
        if (!$fp) {
            return;
        }
        fseek($fp, $start);
        $left = $length;
        while ($left > 0 && !feof($fp) && !connection_aborted()) {
            $buf = fread($fp, (int)min(262144, $left));
            if ($buf === false || $buf === '') {
                break;
            }
            echo $buf;
            flush();
            $left -= strlen($buf);
        }
        fclose($fp);
        exit;
    }

    // ================================================================== помощники

    /** Данные элемента для JS (без путей на диске) */
    public function publicItem(?array $m, ?array $user): ?array
    {
        if (!$m) {
            return null;
        }
        return self::itemForJs($m, $user, Auth::canEdit());
    }

    public static function itemForJs(array $m, ?array $user, bool $canEditAll): array
    {
        $id = (int)$m['id'];
        $canDelete = AlbumService::canDeleteMedia($m, $user);
        return [
            'id'        => $id,
            'type'      => $m['media_type'],
            'thumb'     => !empty($m['thumb_path']) ? "/album/media?id={$id}&v=thumb" : null,
            'full'      => "/album/media?id={$id}&v=full",
            'download'  => "/album/media?id={$id}&v=full&dl=1",
            'ext'       => (string)$m['ext'],
            'w'         => (int)($m['width'] ?? 0),
            'h'         => (int)($m['height'] ?? 0),
            'duration'  => (int)($m['duration_seconds'] ?? 0),
            'caption'   => (string)($m['caption'] ?? ''),
            'date'      => (string)($m['taken_date'] ?? ''),
            'people'    => $m['people'] ?? [],
            'uploader'  => (string)($m['uploader_name'] ?? ''),
            'canDelete' => $canDelete,
            'canEdit'   => $canDelete || $canEditAll,
        ];
    }

    public static function validDate(string $s): ?string
    {
        $s = trim($s);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
            return null;
        }
        if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1]) || (int)$m[1] < 1800 || (int)$m[1] > 2200) {
            return null;
        }
        return $s;
    }

    public static function cleanText(string $s, int $max): string
    {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
        return mb_substr(trim($s), 0, $max);
    }

    /** "1,2,3" или массив → список положительных int */
    public static function idList(mixed $v): array
    {
        if (is_array($v)) {
            $parts = $v;
        } else {
            $parts = explode(',', (string)$v);
        }
        $out = [];
        foreach ($parts as $p) {
            $n = (int)trim((string)$p);
            if ($n > 0) {
                $out[] = $n;
            }
        }
        return array_values(array_unique(array_slice($out, 0, 50)));
    }
}
