<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Nationalities;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\ClanService;
use App\Services\GenerationService;
use App\Services\PersonContactService;
use App\Services\PersonResidenceService;
use PDO;

/**
 * ALT-098: «Толтырылмаған / Что не заполнено» — у кого каких данных не хватает.
 * Только админ и редактор (проверка и в маршруте, и здесь).
 * ALT-107: пустые поля заполняются прямо в таблице:
 *   POST /api/admin/missing/save  — одно поле одного человека (даты, ру, пол, национальность, девичья фамилия);
 *   GET  /api/admin/missing/state — свежая строка после фото / отца / матери (они сохраняются своими API).
 * Проверки те же, что в обычной форме: даты не противоречат друг другу, браки не пересекаются, ру — только у казахов.
 */
class MissingDataController
{
    /** Виды пробелов (порядок = порядок счётчиков) */
    public const GAPS = ['birth', 'photo', 'clan', 'nationality', 'father', 'mother', 'gender', 'marriage', 'death', 'maiden', 'contacts', 'zheti'];

    /** Поля, которые сохраняются через save() */
    private const SAVEABLE = ['birth', 'death', 'marriage', 'clan', 'gender', 'nationality', 'maiden'];

    public function index(Request $request): void
    {
        if (!Auth::isEditor()) {
            Response::redirect('/?error=forbidden');
        }

        $a = $this->analyze();
        $items = array_values($a['items']);

        // Фильтры (значения проверяются по белому списку)
        [$gapFilter, $genFilter] = $this->filters($request);
        $genKeys = array_keys($a['generations']);
        usort($genKeys, static fn($x, $y) => ($x === 'ancestors' ? 0 : (int)$x) <=> ($y === 'ancestors' ? 0 : (int)$y));

        $filtered = array_values(array_filter($items, fn($it) => $this->matches($it, $gapFilter, $genFilter)));

        $clanNames = [];
        try {
            $svc = new ClanService();
            if ($svc->hasRegistry()) {
                foreach ($svc->all() as $c) {
                    $clanNames[] = (string)$c['name'];
                }
            }
        } catch (\Throwable $e) {
            $clanNames = [];
        }

        Response::view('admin/missing', [
            'items'     => $filtered,
            'counts'    => $a['counts'],
            'totalAll'  => count($a['byId']),
            'totalGaps' => count($items),
            'genKeys'   => $genKeys,
            'gapFilter' => $gapFilter,
            'genFilter' => $genFilter,
            'clanNames' => $clanNames,
            'csrf'      => Session::csrfToken(),
        ]);
    }

    /** @return array{0:string,1:string} */
    private function filters(Request $request, string $source = 'get'): array
    {
        // страница: ?gap=&gen=; API (save/state): gap_filter / gen_filter («gap» в save — это поле)
        $api = $source === 'api';
        $read = static fn(string $k): string => (string)($api ? $request->get($k . '_filter', $request->post($k . '_filter', '')) : $request->get($k, ''));
        $gapFilter = $read('gap');
        if (!in_array($gapFilter, self::GAPS, true)) {
            $gapFilter = '';
        }
        $genFilter = $read('gen');
        if ($genFilter !== 'ancestors' && !ctype_digit($genFilter)) {
            $genFilter = '';
        }
        return [$gapFilter, $genFilter];
    }

    private function matches(array $it, string $gapFilter, string $genFilter): bool
    {
        if ($gapFilter !== '' && !in_array($gapFilter, $it['gaps'], true)) {
            return false;
        }
        return $genFilter === '' || $it['gen'] === $genFilter;
    }

    /**
     * Считает пробелы у всех живых записей.
     * @return array{byId:array,items:array<int,array>,counts:array,generations:array}
     */
    private function analyze(): array
    {
        $db = Database::getConnection();
        $rows = $db->query('SELECT * FROM persons WHERE is_deleted = 0 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $byId = [];
        foreach ($rows as $r) {
            $byId[(int)$r['id']] = $r;
        }
        $hasNationality = $byId && array_key_exists('nationality', reset($byId));

        // Браки: по ним — дата брака и девичья фамилия
        $spouseLists = [];
        $marriageGaps = [];   // person_id => 'none' | 'year'
        $marriageList = [];   // person_id => [['id','spouse','state'], ...] — браки без полной даты
        try {
            $mRows = $db->query('SELECT * FROM marriages')->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $mRows = [];
        }
        foreach ($mRows as $m) {
            $h = (int)($m['husband_id'] ?? 0);
            $w = (int)($m['wife_id'] ?? 0);
            if (!isset($byId[$h]) || !isset($byId[$w])) {
                continue;
            }
            $spouseLists[$h][] = ['spouse_id' => $w];
            $spouseLists[$w][] = ['spouse_id' => $h];
            $date = self::firstNonEmpty($m['marriage_date'] ?? null, $m['start_date'] ?? null);
            $state = null;
            if ($date === '') {
                $state = 'none';
            } elseif (preg_match('/^\D*\d{4}\D*$/u', $date)) {
                $state = 'year';
            }
            if ($state !== null) {
                foreach ([[$h, $w], [$w, $h]] as [$pid, $other]) {
                    if (($marriageGaps[$pid] ?? '') !== 'none') {
                        $marriageGaps[$pid] = $state;
                    }
                    $marriageList[$pid][] = [
                        'id'     => (int)$m['id'],
                        'spouse' => self::nameOf($byId[$other]),
                        'state'  => $state,
                    ];
                }
            }
        }

        $genMap = [];
        try {
            $genMap = GenerationService::generationMap($byId, $spouseLists, 6);
        } catch (\Throwable $e) {
            $genMap = [];
        }

        $items = [];
        $counts = array_fill_keys(self::GAPS, 0);
        $generations = [];
        foreach ($byId as $id => $p) {
            $gaps = [];
            $detail = [];

            if (trim((string)($p['birth_date'] ?? '')) === '' && trim((string)($p['birth_year'] ?? '')) === '') {
                $gaps[] = 'birth';
                $detail['birth'] = 'none';
            } elseif (trim((string)($p['birth_date'] ?? '')) === '') {
                $gaps[] = 'birth';
                $detail['birth'] = 'year';
            }
            if (trim((string)($p['avatar_path'] ?? '')) === '') {
                $gaps[] = 'photo';
            }
            // Ру — только у казахов (или пока национальность не указана); у детей наследуется от отца
            $nat = $hasNationality ? trim((string)($p['nationality'] ?? '')) : '';
            if ($nat === '' || Nationalities::isKazakh($nat)) {
                if (trim((string)($p['clan_ru'] ?? '')) === '') {
                    $gaps[] = 'clan';
                    $detail['clan'] = empty($p['father_id']) ? 'free' : 'byfather';
                }
            }
            if ($hasNationality && $nat === '') {
                $gaps[] = 'nationality';
            }
            if (empty($p['father_id'])) {
                $gaps[] = 'father';
            }
            if (empty($p['mother_id'])) {
                $gaps[] = 'mother';
            }
            if (!in_array($p['gender'] ?? '', ['M', 'F'], true)) {
                $gaps[] = 'gender';
            }
            if (isset($marriageGaps[$id])) {
                $gaps[] = 'marriage';
                $detail['marriage'] = $marriageGaps[$id];
            }
            if (!empty($p['is_deceased']) && trim((string)($p['death_date'] ?? '')) === '' && trim((string)($p['death_year'] ?? '')) === '') {
                $gaps[] = 'death';
            }
            if (($p['gender'] ?? '') === 'F' && !empty($spouseLists[$id]) && trim((string)($p['maiden_name'] ?? '')) === '') {
                $gaps[] = 'maiden';
            }
            if (empty($p['is_deceased'])) {
                $c = PersonContactService::decodeContacts($p['private_contacts'] ?? null);
                if (!$c['phones'] && !$c['emails'] && !$c['addresses'] && !$c['socials']) {
                    $gaps[] = 'contacts';
                }
            }
            // Жеті ата: цепочка отцов короче семи (сам человек не считается)
            $depth = 0;
            $seen = [$id => true];
            $cur = $p;
            while ($depth < 7 && !empty($cur['father_id']) && isset($byId[(int)$cur['father_id']]) && !isset($seen[(int)$cur['father_id']])) {
                $cur = $byId[(int)$cur['father_id']];
                $seen[(int)$cur['id']] = true;
                $depth++;
            }
            if ($depth < 7) {
                $gaps[] = 'zheti';
                $detail['zheti'] = (string)$depth;
            }

            foreach ($gaps as $g) {
                $counts[$g]++;
            }
            $gen = $genMap[$id] ?? 'ancestors';
            $generations[(string)$gen] = true;
            if ($gaps) {
                $items[$id] = [
                    'id'        => $id,
                    'p'         => $p,
                    'gaps'      => $gaps,
                    'detail'    => $detail,
                    'marriages' => $marriageList[$id] ?? [],
                    'gen'       => (string)$gen,
                ];
            }
        }

        return ['byId' => $byId, 'items' => $items, 'counts' => $counts, 'generations' => $generations];
    }

    /** Первое непустое значение (после trim) или ''. */
    private static function firstNonEmpty(mixed ...$vals): string
    {
        foreach ($vals as $v) {
            $v = trim((string)$v);
            if ($v !== '') {
                return $v;
            }
        }
        return '';
    }

    private static function nameOf(array $p): string
    {
        $n = trim((string)($p['last_name'] ?? '') . ' ' . (string)($p['first_name'] ?? ''));
        return $n !== '' ? $n : ('#' . (int)($p['id'] ?? 0));
    }

    // ------------------------------------------------------------------ ALT-107: сохранение в таблице

    private function fail(string $msg, int $status = 422): never
    {
        Response::json(['success' => false, 'error' => $msg], $status);
    }

    private function guard(Request $request, bool $post): void
    {
        if (!Auth::check()) {
            $this->fail('Кіру қажет / Нужен вход', 401);
        }
        if (!Auth::isEditor()) {
            $this->fail('Рұқсат жоқ / Нет прав', 403);
        }
        if ($post && !Session::validateCsrf((string)$request->post('csrf_token', ''))) {
            $this->fail('Сессия ескірді, бетті жаңартыңыз / Сессия устарела, обновите страницу', 419);
        }
    }

    private function loadPerson(PDO $db, int $id): array
    {
        $st = $db->prepare('SELECT * FROM persons WHERE id = ? AND is_deleted = 0 LIMIT 1');
        $st->execute([$id]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            $this->fail('Тұлға табылмады / Персона не найдена', 404);
        }
        return $p;
    }

    /**
     * Дата ввода → ['date' => 'ГГГГ-ММ-ДД'|null, 'year' => int] или null, если не дата.
     * Принимает ДД.ММ.ГГГГ, ГГГГ-ММ-ДД и (если $allowYear) просто год. Не в будущем.
     */
    private static function parseDate(string $raw, bool $allowYear): ?array
    {
        $raw = trim($raw);
        $y = $mo = $d = 0;
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $m)) {
            [$y, $mo, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        } elseif (preg_match('/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})$/', $raw, $m)) {
            [$y, $mo, $d] = [(int)$m[3], (int)$m[2], (int)$m[1]];
        } elseif ($allowYear && preg_match('/^\d{4}$/', $raw)) {
            $y = (int)$raw;
            if ($y < 1000 || $y > (int)date('Y')) {
                return null;
            }
            return ['date' => null, 'year' => $y];
        } else {
            return null;
        }
        if ($y < 1000 || !checkdate($mo, $d, $y)) {
            return null;
        }
        $date = sprintf('%04d-%02d-%02d', $y, $mo, $d);
        if ($date > date('Y-m-d')) {
            return null;
        }
        return ['date' => $date, 'year' => $y];
    }

    /** Число для сравнения дат: полная дата — ГГГГММДД, только год — начало (0101) или конец (1231) года. */
    private static function ordKey(?string $date, $year, bool $end): ?int
    {
        $date = trim((string)$date);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $m)) {
            return (int)$m[1] * 10000 + (int)$m[2] * 100 + (int)$m[3];
        }
        $y = trim((string)$year);
        if ($y === '' && preg_match('/(\d{4})/', $date, $m)) {
            $y = $m[1];
        }
        if ($y !== '' && preg_match('/^\d{4}$/', $y)) {
            return (int)$y * 10000 + ($end ? 1231 : 101);
        }
        return null;
    }

    private static function personYear(array $p, string $kind): ?int
    {
        $k = self::ordKey($p[$kind . '_date'] ?? null, $p[$kind . '_year'] ?? null, false);
        return $k === null ? null : intdiv($k, 10000);
    }

    private function audit(PDO $db, int $entityId, string $table, string $summary, ?array $old, ?array $new): void
    {
        $u = Auth::user();
        try {
            $db->prepare(
                "INSERT INTO audit_logs (user_id, user_name, user_email, action, entity_table, entity_id, summary, old_values, new_values, created_at)
                 VALUES (?, ?, ?, 'UPDATE', ?, ?, ?, ?, ?, NOW())"
            )->execute([
                $u['id'] ?? null,
                $u['name'] ?? 'Система',
                $u['email'] ?? null,
                $table,
                $entityId,
                mb_substr($summary, 0, 255),
                $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
                $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            Logger::error('MissingData audit: ' . $e->getMessage(), $e);
        }
    }

    /** POST /api/admin/missing/save: person_id, gap, value, [marriage_id], [gap_filter, gen_filter], csrf_token */
    public function save(Request $request): void
    {
        $this->guard($request, true);
        $db = Database::getConnection();
        $personId = (int)$request->post('person_id', 0);
        $gap = (string)$request->post('gap', '');
        $value = trim((string)$request->post('value', ''));
        if (!in_array($gap, self::SAVEABLE, true)) {
            $this->fail('Өріс қате / Неизвестное поле', 400);
        }
        $p = $this->loadPerson($db, $personId);
        if ($value === '') {
            $this->fail('Мәнді көрсетіңіз / Укажите значение');
        }

        try {
            match ($gap) {
                'birth'       => $this->saveBirth($db, $p, $value),
                'death'       => $this->saveDeath($db, $p, $value),
                'marriage'    => $this->saveMarriage($db, $p, $value, (int)$request->post('marriage_id', 0)),
                'clan'        => $this->saveClan($db, $p, $value),
                'gender'      => $this->saveGender($db, $p, $value),
                'nationality' => $this->saveNationality($db, $p, $value),
                'maiden'      => $this->saveMaiden($db, $p, $value),
            };
        } catch (\PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Logger::error('MissingData save ' . $gap . ': ' . $e->getMessage(), $e);
            $this->fail('Сақтау қатесі / Ошибка сохранения', 500);
        }

        $this->respond($request, $personId);
    }

    /** GET /api/admin/missing/state?person_id=&gap=&gen= — строка после фото / отца / матери */
    public function state(Request $request): void
    {
        $this->guard($request, false);
        $personId = (int)$request->get('person_id', 0);
        $this->loadPerson(Database::getConnection(), $personId);
        $this->respond($request, $personId);
    }

    private function respond(Request $request, int $personId): void
    {
        [$gapFilter, $genFilter] = $this->filters($request, 'api');
        $a = $this->analyze();
        $it = $a['items'][$personId] ?? null;
        $keep = $it !== null && $this->matches($it, $gapFilter, $genFilter);

        $html = '';
        if ($keep) {
            $ctx = require __DIR__ . '/../../views/admin/missing_ctx.php';
            ob_start();
            require __DIR__ . '/../../views/admin/missing_row.php';
            $html = (string)ob_get_clean();
        }
        $ctx = $ctx ?? require __DIR__ . '/../../views/admin/missing_ctx.php';
        $T = $ctx['T'];
        $totalGaps = count($a['items']);
        $totalAll = count($a['byId']);
        Response::json([
            'success'   => true,
            'person_id' => $personId,
            'removed'   => !$keep,
            'html'      => $html,
            'counts'    => $a['counts'],
            'totalGaps' => $totalGaps,
            'sub'       => $T([
                'kk' => 'Толтырылмаған деректері бар адамдар: ' . $totalGaps . ' / ' . $totalAll,
                'ru' => 'Людей с пробелами в данных: ' . $totalGaps . ' из ' . $totalAll,
                'en' => 'People with missing data: ' . $totalGaps . ' of ' . $totalAll,
                'es' => 'Personas con datos faltantes: ' . $totalGaps . ' de ' . $totalAll,
            ]),
        ]);
    }

    // ---- поля

    private function saveBirth(PDO $db, array $p, string $value): void
    {
        $hadYear = trim((string)($p['birth_year'] ?? '')) !== '' && trim((string)($p['birth_date'] ?? '')) === '';
        $d = self::parseDate($value, !$hadYear);
        if (!$d) {
            $this->fail($hadYear
                ? 'Толық күнді жазыңыз: КК.АА.ЖЖЖЖ / Укажите полную дату: ДД.ММ.ГГГГ'
                : 'Күн қате (КК.АА.ЖЖЖЖ) / Неверная дата (ДД.ММ.ГГГГ)');
        }
        $id = (int)$p['id'];
        if ($hadYear && (int)$p['birth_year'] !== $d['year']) {
            $this->fail('Жыл бұрын көрсетілген жылға сәйкес келмейді / Год не совпадает с уже указанным годом рождения (' . (int)$p['birth_year'] . ')');
        }
        $start = self::ordKey($d['date'], $d['year'], false);
        $deathEnd = self::ordKey($p['death_date'] ?? null, $p['death_year'] ?? null, true);
        if ($deathEnd !== null && $start > $deathEnd) {
            $this->fail('Туған күні қайтыс болған күннен кейін болмауы керек / Дата рождения не может быть позже даты смерти');
        }
        // родители старше, дети младше
        foreach (['father_id', 'mother_id'] as $col) {
            if (!empty($p[$col])) {
                $st = $db->prepare('SELECT birth_date, birth_year FROM persons WHERE id = ?');
                $st->execute([(int)$p[$col]]);
                $par = $st->fetch(PDO::FETCH_ASSOC);
                $py = $par ? self::personYear($par, 'birth') : null;
                if ($py !== null && $py >= $d['year']) {
                    $this->fail('Ата-ана баладан кіші болмауы керек / Родитель не может быть моложе ребёнка');
                }
            }
        }
        $st = $db->prepare('SELECT birth_date, birth_year FROM persons WHERE (father_id = ? OR mother_id = ?) AND is_deleted = 0');
        $st->execute([$id, $id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $ch) {
            $cy = self::personYear($ch, 'birth');
            if ($cy !== null && $cy <= $d['year']) {
                $this->fail('Бала ата-анадан үлкен болмауы керек / Ребёнок не может быть старше родителя');
            }
        }
        $db->prepare('UPDATE persons SET birth_date = ?, birth_year = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$d['date'], $d['year'], $id]);
        $this->audit($db, $id, 'persons', 'Туған күні / Дата рождения (таблица «Что не заполнено»)',
            ['birth_date' => $p['birth_date'] ?? null, 'birth_year' => $p['birth_year'] ?? null],
            ['birth_date' => $d['date'], 'birth_year' => $d['year']]);
    }

    private function saveDeath(PDO $db, array $p, string $value): void
    {
        if (empty($p['is_deceased'])) {
            $this->fail('Адам тірі деп белгіленген / Человек отмечен как живой');
        }
        $d = self::parseDate($value, true);
        if (!$d) {
            $this->fail('Күн қате (КК.АА.ЖЖЖЖ) / Неверная дата (ДД.ММ.ГГГГ)');
        }
        $id = (int)$p['id'];
        $end = self::ordKey($d['date'], $d['year'], true);
        $birthStart = self::ordKey($p['birth_date'] ?? null, $p['birth_year'] ?? null, false);
        if ($birthStart !== null && $end < $birthStart) {
            $this->fail('Қайтыс болған күні туған күнінен ерте болмауы керек / Дата смерти не может быть раньше даты рождения');
        }
        $db->prepare('UPDATE persons SET death_date = ?, death_year = ?, is_deceased = 1, updated_at = NOW() WHERE id = ?')
            ->execute([$d['date'], $d['year'], $id]);
        $this->audit($db, $id, 'persons', 'Қайтыс болған күні / Дата смерти (таблица «Что не заполнено»)',
            ['death_date' => $p['death_date'] ?? null, 'death_year' => $p['death_year'] ?? null],
            ['death_date' => $d['date'], 'death_year' => $d['year']]);
    }

    private function saveMarriage(PDO $db, array $p, string $value, int $marriageId): void
    {
        $id = (int)$p['id'];
        $st = $db->prepare('SELECT * FROM marriages WHERE id = ? AND (husband_id = ? OR wife_id = ?) LIMIT 1');
        $st->execute([$marriageId, $id, $id]);
        $m = $st->fetch(PDO::FETCH_ASSOC);
        if (!$m) {
            $this->fail('Неке табылмады / Брак не найден', 404);
        }
        $old = self::firstNonEmpty($m['marriage_date'] ?? null, $m['start_date'] ?? null);
        $d = self::parseDate($value, $old === '');
        if (!$d) {
            $this->fail($old !== ''
                ? 'Толық күнді жазыңыз: КК.АА.ЖЖЖЖ / Укажите полную дату: ДД.ММ.ГГГГ'
                : 'Күн қате (КК.АА.ЖЖЖЖ) / Неверная дата (ДД.ММ.ГГГГ)');
        }
        $stored = $d['date'] ?? (string)$d['year'];
        $start = self::ordKey($d['date'], $d['year'], false);
        $endNew = self::ordKey($m['end_date'] ?? $m['divorce_date'] ?? null, null, true);
        if ($endNew !== null && $start > $endNew) {
            $this->fail('Ажырасу күні неке күнінен ерте болмауы керек / Развод не может быть раньше брака');
        }

        // Супруги: брак — не раньше рождения и не позже смерти
        foreach ([(int)$m['husband_id'], (int)$m['wife_id']] as $sid) {
            $sp = $db->prepare('SELECT * FROM persons WHERE id = ?');
            $sp->execute([$sid]);
            $row = $sp->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                continue;
            }
            $by = self::personYear($row, 'birth');
            if ($by !== null && $d['year'] <= $by) {
                $this->fail('Неке туған жылынан кейін болуы керек / Брак не может быть раньше рождения: ' . self::nameOf($row));
            }
            $dEnd = self::ordKey($row['death_date'] ?? null, $row['death_year'] ?? null, false);
            if ($dEnd !== null && $start !== null && intdiv($start, 10000) > intdiv($dEnd, 10000)) {
                $this->fail('Неке қайтыс болғаннан кейін болмауы керек / Брак не может быть позже смерти: ' . self::nameOf($row));
            }
        }

        // Браки каждого из супругов не пересекаются по времени
        $newEnd = $endNew;
        $other = $db->prepare('SELECT * FROM marriages WHERE (husband_id IN (?, ?) OR wife_id IN (?, ?)) AND id <> ?');
        $other->execute([(int)$m['husband_id'], (int)$m['wife_id'], (int)$m['husband_id'], (int)$m['wife_id'], $marriageId]);
        foreach ($other->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $oStart = self::ordKey($o['marriage_date'] ?? $o['start_date'] ?? null, null, false);
            $oEnd = self::ordKey($o['end_date'] ?? $o['divorce_date'] ?? null, null, true);
            if ($oStart === null || $oEnd === null) {
                continue; // без начала или без конца пересечение не доказать (многожёнство допустимо)
            }
            $overlap = $newEnd !== null
                ? ($start < $oEnd && $oStart < $newEnd)
                : ($oStart < $start && $start < $oEnd);
            if ($overlap) {
                $this->fail('Неке басқа некемен қиылысады / Брак пересекается по датам с другим браком');
            }
        }

        $db->prepare('UPDATE marriages SET start_date = ?, marriage_date = ? WHERE id = ?')->execute([$stored, $stored, $marriageId]);
        $this->audit($db, $marriageId, 'marriages', 'Үйлену күні / Дата брака (таблица «Что не заполнено»)',
            ['marriage_date' => $m['marriage_date'] ?? null, 'start_date' => $m['start_date'] ?? null],
            ['marriage_date' => $stored, 'start_date' => $stored]);
    }

    private function saveClan(PDO $db, array $p, string $value): void
    {
        $id = (int)$p['id'];
        if (!empty($p['father_id'])) {
            $this->fail('Ру әкесінен беріледі — әкесінің русын толтырыңыз / Ру наследуется от отца — заполните ру отца');
        }
        $nat = trim((string)($p['nationality'] ?? ''));
        if ($nat !== '' && !Nationalities::isKazakh($nat)) {
            $this->fail('Ру тек қазақтарға көрсетіледі / Ру указывается только у казахов');
        }
        $svc = new ClanService($db);
        $clanId = null;
        $clanRu = mb_substr($value, 0, 150);
        if ($svc->hasRegistry()) {
            $found = $svc->resolveByName($value);
            if (!$found) {
                $this->fail('Бұл ру «Ру таңбалары» тізімінде жоқ. Алдымен тізімге қосыңыз / Такого ру нет в справочнике «Ру таңбалары» — сначала добавьте его');
            }
            $clanRu = $found['name'];
            $clanId = $found['id'];
        }
        $db->beginTransaction();
        $db->prepare('UPDATE persons SET clan_id = ?, clan_ru = ?, updated_at = NOW() WHERE id = ?')->execute([$clanId, $clanRu, $id]);
        // как в обычной форме: ру переходит потомкам по мужской линии
        $db->prepare("
            UPDATE persons p
            JOIN (
                WITH RECURSIVE patrilineal AS (
                    SELECT id, gender FROM persons WHERE id = ?
                    UNION ALL
                    SELECT child.id, child.gender FROM persons child
                    INNER JOIN patrilineal pt ON child.father_id = pt.id
                    WHERE pt.gender = 'M'
                )
                SELECT id FROM patrilineal WHERE id <> ?
            ) k ON p.id = k.id
            SET p.clan_id = ?, p.clan_ru = ?
        ")->execute([$id, $id, $clanId, $clanRu]);
        $db->commit();
        $this->audit($db, $id, 'persons', 'Ру / Род (таблица «Что не заполнено»): ' . $clanRu,
            ['clan_ru' => $p['clan_ru'] ?? null, 'clan_id' => $p['clan_id'] ?? null],
            ['clan_ru' => $clanRu, 'clan_id' => $clanId]);
    }

    private function saveGender(PDO $db, array $p, string $value): void
    {
        if (!in_array($value, ['M', 'F'], true)) {
            $this->fail('Жынысы қате / Неверный пол');
        }
        $id = (int)$p['id'];
        $st = $db->prepare('SELECT COUNT(*) FROM persons WHERE is_deleted = 0 AND ' . ($value === 'F' ? 'father_id' : 'mother_id') . ' = ?');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            $this->fail($value === 'F'
                ? 'Бұл адам әке ретінде көрсетілген / Этот человек указан отцом'
                : 'Бұл адам ана ретінде көрсетілген / Этот человек указан матерью');
        }
        $db->prepare('UPDATE persons SET gender = ?, maiden_name = ' . ($value === 'F' ? 'maiden_name' : 'NULL') . ', updated_at = NOW() WHERE id = ?')
            ->execute([$value, $id]);
        $this->audit($db, $id, 'persons', 'Жынысы / Пол (таблица «Что не заполнено»): ' . $value,
            ['gender' => $p['gender'] ?? null], ['gender' => $value]);
    }

    private function saveNationality(PDO $db, array $p, string $value): void
    {
        $svc = new PersonResidenceService($db);
        if (!$svc->hasProfileColumns()) {
            $this->fail('Миграция қолданылмаған / Не применена миграция БД', 503);
        }
        $id = (int)$p['id'];
        $val = mb_substr(Nationalities::normalize($value), 0, 150);
        $db->prepare('UPDATE persons SET nationality = ?, updated_at = NOW() WHERE id = ?')->execute([$val, $id]);
        $this->audit($db, $id, 'persons', 'Ұлты / Национальность (таблица «Что не заполнено»): ' . $val,
            ['nationality' => $p['nationality'] ?? null], ['nationality' => $val]);
    }

    private function saveMaiden(PDO $db, array $p, string $value): void
    {
        if (($p['gender'] ?? '') !== 'F') {
            $this->fail('Қыз кездегі тегі тек әйелдерге / Девичья фамилия — только у женщин');
        }
        $id = (int)$p['id'];
        $val = mb_substr($value, 0, 100);
        $db->prepare('UPDATE persons SET maiden_name = ?, updated_at = NOW() WHERE id = ?')->execute([$val, $id]);
        $this->audit($db, $id, 'persons', 'Қыз кезіндегі тегі / Девичья фамилия (таблица «Что не заполнено»)',
            ['maiden_name' => $p['maiden_name'] ?? null], ['maiden_name' => $val]);
    }
}
