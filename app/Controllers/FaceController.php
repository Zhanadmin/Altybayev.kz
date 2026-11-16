<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AlbumService;
use App\Services\FaceRecognitionService;
use RuntimeException;

/**
 * TASK-019: API распознавания лиц (замечание 16.11 20:49 — без отдельной страницы «Лица»).
 *
 * Эталон лица — аватарка человека; всё распознавание идёт в браузере, сюда приходят только рамки и векторы.
 * GET : /api/face/album/{id}  — что проверить в альбоме, какие аватарки посчитать, где вопросы «Это X?»
 *       /api/face/media/{id}  — рамки с именами на фото (+ вопросы, если можно отвечать)
 * POST: /api/face/save, /api/face/references, /api/face/confirm, /api/face/reject,
 *       /api/face/settings (админ), /api/face/opt-out и /api/face/delete-person (сам человек или админ)
 */
class FaceController
{
    private ?FaceRecognitionService $svc = null;
    private ?AlbumService $albums = null;

    private function svc(): FaceRecognitionService
    {
        return $this->svc ??= new FaceRecognitionService();
    }

    private function albums(): AlbumService
    {
        return $this->albums ??= new AlbumService();
    }

    private function tr(string $kk, string $ru): string
    {
        return \App\Core\I18n::lang() === 'ru' ? $ru : $kk;
    }

    private function requireReady(): void
    {
        if (!$this->svc()->isReady()) {
            Response::json(['success' => false, 'error' => $this->tr('Бет тану әлі қосылмаған (миграция керек)', 'Распознавание лиц ещё не включено (нужна миграция)')], 503);
        }
    }

    private function requireEnabled(): void
    {
        if (!$this->svc()->enabled()) {
            $this->fail('Бет тану өшірілген', 'Распознавание лиц выключено', 403);
        }
    }

    private function requireCsrf(Request $request): void
    {
        $v = $request->input('csrf_token');
        $token = is_string($v) && $v !== '' ? $v : (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'error' => $this->tr('Бет ескірді — жаңартыңыз', 'Страница устарела — обновите её')], 419);
        }
    }

    private function fail(string $kk, string $ru, int $code = 400): void
    {
        Response::json(['success' => false, 'error' => $this->tr($kk, $ru)], $code);
    }

    private function intInput(Request $request, string $key): int
    {
        $v = $request->input($key);
        return is_int($v) || (is_string($v) && ctype_digit($v)) ? (int)$v : 0;
    }

    /** Альбом фото, если текущий пользователь может в нём отмечать людей (editor/admin или участник) */
    private function contributableAlbumOfMedia(int $mediaId): ?array
    {
        $media = $this->albums()->getMedia($mediaId);
        if (!$media) {
            return null;
        }
        $album = $this->albums()->getAlbum((int)$media['album_id']);
        return ($album && $this->albums()->canContributeToAlbum($album, Auth::user())) ? $album : null;
    }

    /** Эталон с аватарки может посчитать редактор/админ — для любого, остальные — только для себя */
    private static function mayWriteReference(int $personId): bool
    {
        if (Auth::canEdit()) {
            return true;
        }
        $user = Auth::user();
        return $personId > 0 && (int)($user['person_id'] ?? 0) === $personId;
    }

    /** Сам человек или админ */
    private static function isSelfOrAdmin(int $personId): bool
    {
        if (Auth::isAdmin()) {
            return true;
        }
        $user = Auth::user();
        return $personId > 0 && (int)($user['person_id'] ?? 0) === $personId;
    }

    private function mediaPayload(int $mediaId, bool $withQuestions): array
    {
        return [
            'media_id' => $mediaId,
            'faces'    => $this->svc()->facesForMedia($mediaId, $withQuestions),
            'people'   => $this->svc()->mediaPeople($mediaId),
        ];
    }

    // ======================================================= GET

    /** GET /api/face/album/{id} */
    public function apiAlbum(Request $request, string $id): void
    {
        $this->requireReady();
        $album = $this->albums()->getAlbum((int)$id);
        if (!$album) {
            $this->fail('Альбом табылмады', 'Альбом не найден', 404);
        }
        $enabled = $this->svc()->enabled();
        $canContribute = $this->albums()->canContributeToAlbum($album, Auth::user());
        if (!$enabled || !$canContribute) {
            Response::success(['enabled' => $enabled, 'canContribute' => $canContribute, 'unscanned' => [], 'questions' => [], 'refs' => []]);
        }
        $state = $this->svc()->albumState((int)$album['id']);
        if (Auth::canEdit()) {
            $refs = $this->svc()->referencesNeeded();
        } else {
            $own = (int)(Auth::user()['person_id'] ?? 0);
            $refs = $own > 0 ? $this->svc()->referencesNeeded(1, [$own]) : [];
        }
        Response::success(['enabled' => true, 'canContribute' => true] + $state + ['refs' => $refs]);
    }

    /** GET /api/face/media/{id} — рамки с именами */
    public function apiMediaFaces(Request $request, string $id): void
    {
        $this->requireReady();
        $mediaId = (int)$id;
        if (!$this->svc()->enabled() || !$this->albums()->getMedia($mediaId)) {
            Response::success(['media_id' => $mediaId, 'faces' => [], 'people' => []]);
        }
        Response::success($this->mediaPayload($mediaId, $this->contributableAlbumOfMedia($mediaId) !== null));
    }

    // ======================================================= POST

    /** POST /api/face/save {media_id, faces:[{x,y,w,h,descriptor}]} — лица одного фото */
    public function apiSave(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $this->requireEnabled();
        $mediaId = $this->intInput($request, 'media_id');
        if ($mediaId <= 0 || $this->contributableAlbumOfMedia($mediaId) === null) {
            $this->fail('Құқық жоқ', 'Нет прав', 403);
        }
        $faces = $request->input('faces');
        if (!is_array($faces)) {
            $this->fail('Беттер дерегі дұрыс емес', 'Некорректные данные лиц');
        }
        try {
            $this->svc()->saveFaces($mediaId, $faces, Auth::id());
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'media_not_found') {
                $this->fail('Фото табылмады', 'Фото не найдено', 404);
            }
            $this->fail('Беттерді сақтау мүмкін болмады', 'Не удалось сохранить лица');
        }
        Response::success($this->mediaPayload($mediaId, true));
    }

    /** POST /api/face/references {refs:[{person_id, avatar, descriptor|null}]} — отпечатки с аватарок */
    public function apiReferences(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $this->requireEnabled();
        $refs = $request->input('refs');
        if (!is_array($refs)) {
            $this->fail('Деректер дұрыс емес', 'Некорректные данные');
        }
        try {
            $res = $this->svc()->saveReferences($refs, Auth::id(), fn(int $pid): bool => self::mayWriteReference($pid));
        } catch (RuntimeException $e) {
            $this->fail('Сақтау мүмкін болмады', 'Не удалось сохранить');
        }
        Response::success($res);
    }

    /** POST /api/face/confirm {face_id} — «Да, это он» */
    public function apiConfirm(Request $request): void
    {
        $this->answer($request, true);
    }

    /** POST /api/face/reject {face_id} — «Нет» / «Не он» */
    public function apiReject(Request $request): void
    {
        $this->answer($request, false);
    }

    private function answer(Request $request, bool $yes): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $this->requireEnabled();
        $face = $this->svc()->getFace($this->intInput($request, 'face_id'));
        if (!$face) {
            $this->fail('Бет табылмады', 'Лицо не найдено', 404);
        }
        if ($this->contributableAlbumOfMedia((int)$face['media_id']) === null) {
            $this->fail('Құқық жоқ', 'Нет прав', 403);
        }
        try {
            $mediaId = $yes ? $this->svc()->confirmFace((int)$face['id']) : $this->svc()->rejectFace((int)$face['id']);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'person_opt_out') {
                $this->fail('Бұл адамды тануға тыйым салынған', 'Распознавание этого человека запрещено', 403);
            }
            $this->fail('Бет табылмады', 'Лицо не найдено', 404);
        }
        Response::success($this->mediaPayload($mediaId, true));
    }

    /** POST /api/face/settings {enabled} — общий переключатель (только админ) */
    public function apiSettings(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        if (!Auth::isAdmin()) {
            $this->fail('Тек әкімшіге', 'Только администратор', 403);
        }
        $on = filter_var($request->input('enabled', '1'), FILTER_VALIDATE_BOOLEAN);
        $this->svc()->setEnabled($on);
        Response::success(['enabled' => $this->svc()->enabled()]);
    }

    /** POST /api/face/opt-out {person_id, opt_out} — запретить/разрешить распознавание (сам или админ) */
    public function apiOptOut(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $personId = $this->intInput($request, 'person_id');
        if ($personId <= 0 || !$this->albums()->getPerson($personId)) {
            $this->fail('Адам табылмады', 'Человек не найден', 404);
        }
        if (!self::isSelfOrAdmin($personId)) {
            $this->fail('Құқық жоқ', 'Нет прав', 403);
        }
        $out = filter_var($request->input('opt_out', '1'), FILTER_VALIDATE_BOOLEAN);
        $deleted = $this->svc()->setPersonOptOut($personId, $out);
        Response::success(['opt_out' => $out, 'deleted' => $deleted]);
    }

    /** POST /api/face/delete-person {person_id} — удалить все отпечатки человека (сам или админ) */
    public function apiDeletePerson(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $personId = $this->intInput($request, 'person_id');
        if ($personId <= 0 || !$this->albums()->getPerson($personId)) {
            $this->fail('Адам табылмады', 'Человек не найден', 404);
        }
        if (!self::isSelfOrAdmin($personId)) {
            $this->fail('Құқық жоқ', 'Нет прав', 403);
        }
        Response::success(['deleted' => $this->svc()->deletePersonData($personId)]);
    }
}
