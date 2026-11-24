<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Services\KinshipService;
use App\Services\TreeLayoutService;
use PDO;

/**
 * Бас контроллер: 3 түрі (Өңдеу, Шежіре-Орман, Тізім), шынайы өңдеу және туыстық жүйесі
 */
class TreeController
{
    protected KinshipService $kinshipService;
    protected TreeLayoutService $treeLayoutService;

    public function __construct()
    {
        $this->kinshipService = new KinshipService();
        $this->treeLayoutService = new TreeLayoutService();
    }

    /**
     * Отображение главной страницы древа
     */
    public function index(Request $request): void
    {
        $user = Auth::user();

        // Поиск или связка персоны Жандоса
        // TASK-014 (note 16.11): без привязки — фокус на Жандосе, но в users.person_id не пишем.
        $userPersonId = !empty($user['person_id'])
            ? (int)$user['person_id']
            : $this->resolveZhandosPersonId();

        $allTerms = $this->kinshipService->getAllTerms();
        $conceptArtUrl = '/assets/img/shezhire_living_forest_concept.jpg';

        Response::view('tree', [
            'user' => $user,
            'userPersonId' => $userPersonId,
            'canEdit' => Auth::canEdit(),
            'isAdmin' => Auth::isAdmin(),
            // TASK-032: свой круг (я, супруг(а), дети) — кнопка «Өңдеу» только у них; сервер проверяет заново
            'ownCircle' => (!Auth::canEdit() && !empty($user['person_id']))
                ? \App\Services\PersonAccessService::make()->circleOf((int)$user['person_id'])
                : [],
            // ALT-018: кого пользователь добавил сам (кнопка «Удалить»; сервер проверяет заново)
            'myCreatedIds' => ($user && !Auth::isAdmin())
                ? $this->createdPersonIds((int)($user['id'] ?? 0))
                : [],
            'csrfToken' => \App\Core\Session::csrfToken(),
            'kinshipTerms' => $allTerms,
            'conceptArtUrl' => $conceptArtUrl,
        ]);
    }

    /** ALT-018: id живых людей, добавленных этим пользователем. */
    private function createdPersonIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        try {
            $st = Database::getConnection()->prepare('SELECT id FROM persons WHERE created_by = ? AND is_deleted = 0');
            $st->execute([$userId]);
            return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Получить реальный датасет из базы данных
     */
    public function getDataset(): array
    {
        try {
            $db = Database::getConnection();

            $stmtP = $db->query("SHOW TABLES LIKE 'persons'");
            if ($stmtP->rowCount() === 0) {
                return ['clans' => [], 'persons' => [], 'marriages' => [], 'error' => null];
            }

            $stmt = $db->query("SELECT * FROM `persons` WHERE `is_deleted` = 0 ORDER BY `id` ASC");
            $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $marriages = [];
            $stmtM = $db->query("SHOW TABLES LIKE 'marriages'");
            if ($stmtM->rowCount() > 0) {
                $stmtMarriages = $db->query("SELECT m.* FROM `marriages` m JOIN `persons` ph ON ph.id = m.husband_id AND ph.is_deleted = 0 JOIN `persons` pw ON pw.id = m.wife_id AND pw.is_deleted = 0 ORDER BY m.`id` ASC"); // ALT-018: без браков удалённых
                $marriages = $stmtMarriages->fetchAll(PDO::FETCH_ASSOC);
            }

            $clans = [];
            $stmtC = $db->query("SHOW TABLES LIKE 'clans'");
            if ($stmtC->rowCount() > 0) {
                $stmtClans = $db->query("SELECT * FROM `clans` ORDER BY `id` ASC");
                $clans = $stmtClans->fetchAll(PDO::FETCH_ASSOC);
            }

            return [
                'clans' => $clans,
                'persons' => $persons,
                'marriages' => $marriages,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Logger::error("Ошибка запроса к базе данных в TreeController: " . $e->getMessage(), $e);
            return [
                'clans' => [],
                'persons' => [],
                'marriages' => [],
                'error' => Config::isDebug() ? $e->getMessage() : 'Деректер қорына қосылу сәтсіз аяқталды.',
            ];
        }
    }

    /**
     * API: Получение полного набора данных древа
     */
    public function apiTreeData(Request $request): void
    {
        $dataset = $this->getDataset();

        if (!empty($dataset['error'])) {
            Response::json([
                'status' => 'error',
                'is_empty' => true,
                'error' => 'Мәліметтер қорының қатесі / Ошибка базы данных',
                'message' => $dataset['error'],
                'clans' => [],
                'persons' => [],
                'marriages' => [],
                'user_person_id' => null,
                'initial_kinships' => (object)[],
            ], 200);
            return;
        }

        $persons = $dataset['persons'];
        $marriages = $dataset['marriages'];
        $clans = $dataset['clans'];

        if (empty($persons)) {
            Response::json([
                'status' => 'success',
                'is_empty' => true,
                'message' => 'Деректер жоқ — GEDCOM импорттаңыз / Данных нет — импортируйте GEDCOM',
                'clans' => $clans,
                'persons' => [],
                'marriages' => [],
                'user_person_id' => null,
                'initial_kinships' => (object)[],
            ], 200);
            return;
        }

        $user = Auth::user();
        // TASK-014 (note 16.11): карточка Жандоса — только точка фокуса дерева по умолчанию.
        // В users.person_id её НЕ записываем: иначе каждый новый пользователь «становится» Жандосом
        // (общий person_id → чужие альбомы, «своё» фото профиля и т. п.). Привязку ставит админ.
        $userPersonId = !empty($user['person_id']) ? (int)$user['person_id'] : $this->resolveZhandosPersonId();

        // Защита закрытых контактов
        $isAdmin = Auth::isAdmin();
        $patrById = array_column($persons, null, 'id');
        foreach ($persons as &$p) {
            // TASK-028: авто-отчество по отцу в обоих языках (ручное middle_name не трогаем)
            $patrBoth = \App\Services\PatronymicService::both($p, !empty($p['father_id']) ? ($patrById[$p['father_id']] ?? null) : null);
            $p['middle_name_kk'] = $patrBoth['kk'];
            $p['middle_name_ru'] = $patrBoth['ru'];
            if (isset($p['private_contacts']) && is_string($p['private_contacts'])) {
                $p['private_contacts'] = json_decode($p['private_contacts'], true);
            }
            if (!$isAdmin && isset($p['private_contacts'])) {
                unset($p['private_contacts']);
            }
        }
        unset($p);

        // Режим раскладки: по умолчанию окрестность ('focal') или полное древо ('full')
        $layoutMode = (string)$request->get('mode', 'focal');
        $reqFocalId = (int)$request->get('focal_id', 0);
        $activeFocalId = $reqFocalId ?: ($userPersonId ?: (int)$persons[0]['id']);
        $expandedStr = trim((string)$request->get('expanded', ''));
        $expandedIds = $expandedStr !== '' ? array_map('intval', explode(',', $expandedStr)) : [];

        $layout = $this->treeLayoutService->calculateLayout(
            $persons,
            $marriages,
            $activeFocalId,
            $layoutMode,
            $expandedIds
        );

        // Расчёт степеней родства относительно фокальной персоны
        $initialKinships = [];
        if ($activeFocalId) {
            $initialKinships = $this->kinshipService->calculateAllKinships($activeFocalId, $persons, $marriages);
        }

        Response::json([
            'status' => 'success',
            'is_empty' => false,
            'clans' => $clans,
            'persons' => $persons,
            'marriages' => $marriages,
            'user_person_id' => $userPersonId,
            'focal_id' => $activeFocalId,
            'layout_mode' => $layoutMode,
            'layout' => $layout,
            'initial_kinships' => $initialKinships,
        ]);
    }

    /**
     * API: Динамический пересчет раскладки (смена фокуса, сворачивание/разворачивание)
     */
    public function apiTreeLayout(Request $request): void
    {
        $dataset = $this->getDataset();

        if (!empty($dataset['error']) || empty($dataset['persons'])) {
            Response::json(['status' => 'error', 'nodes' => [], 'edges' => []]);
            return;
        }

        $user = Auth::user();
        $userPersonId = !empty($user['person_id']) ? (int)$user['person_id'] : $this->resolveZhandosPersonId();
        $focalId = (int)$request->get('focal_id', 0) ?: ($userPersonId ?: (int)$dataset['persons'][0]['id']);
        $mode = (string)$request->get('mode', 'focal');
        $expandedStr = trim((string)$request->get('expanded', ''));
        $expandedIds = $expandedStr !== '' ? array_map('intval', explode(',', $expandedStr)) : [];

        $layout = $this->treeLayoutService->calculateLayout(
            $dataset['persons'],
            $dataset['marriages'],
            $focalId,
            $mode,
            $expandedIds
        );

        Response::json([
            'status' => 'success',
            'layout' => $layout,
        ]);
    }

    /**
     * API: Табличный вид «Тізім / Список» со всеми фильтрами и поиском (Вид 3)
     */
    public function apiTreeList(Request $request): void
    {
        $dataset = $this->getDataset();
        $persons = $dataset['persons'];
        $marriages = $dataset['marriages'];
        $clans = $dataset['clans'];

        if (empty($persons)) {
            Response::json(['results' => [], 'total' => 0]);
            return;
        }

        $personsById = array_column($persons, null, 'id');
        $clansById = array_column($clans, null, 'id');

        $q = mb_strtolower(trim((string)$request->get('q', '')));
        $clanFilter = (int)$request->get('clan_id', 0);
        $genderFilter = trim((string)$request->get('gender', ''));
        $statusFilter = trim((string)$request->get('status', '')); // 'living' or 'deceased'
        $sort = trim((string)$request->get('sort', 'name_asc'));

        // Карта супругов для отображения
        $spousesMap = [];
        foreach ($marriages as $m) {
            $h = (int)($m['husband_id'] ?? 0);
            $w = (int)($m['wife_id'] ?? 0);
            if ($h && $w) {
                $spousesMap[$h][] = [
                    'id' => $w,
                    'name' => trim(($personsById[$w]['last_name'] ?? '') . ' ' . ($personsById[$w]['first_name'] ?? '')),
                    'status' => $m['status'] ?? ($m['is_current'] ? 'married' : 'divorced'),
                    'years' => trim(($m['start_date'] ?? $m['marriage_date'] ?? '') . ' ' . ($m['end_date'] ?? $m['divorce_date'] ?? '')),
                ];
                $spousesMap[$w][] = [
                    'id' => $h,
                    'name' => trim(($personsById[$h]['last_name'] ?? '') . ' ' . ($personsById[$h]['first_name'] ?? '')),
                    'status' => $m['status'] ?? ($m['is_current'] ? 'married' : 'divorced'),
                    'years' => trim(($m['start_date'] ?? $m['marriage_date'] ?? '') . ' ' . ($m['end_date'] ?? $m['divorce_date'] ?? '')),
                ];
            }
        }

        $filtered = [];
        foreach ($persons as $p) {
            $pId = (int)$p['id'];
            $fatherForPatr = !empty($p['father_id']) ? ($personsById[$p['father_id']] ?? null) : null;
            $patr = \App\Services\PatronymicService::both($p, $fatherForPatr);
            $fullName = trim(($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . $patr['kk']);

            // Фильтр по поисковому запросу
            if ($q !== '') {
                $match = mb_stripos($fullName, $q) !== false
                    || mb_stripos($patr['ru'], $q) !== false
                    || mb_stripos($p['clan_ru'] ?? '', $q) !== false
                    || mb_stripos($p['birth_place'] ?? '', $q) !== false
                    || mb_stripos($p['maiden_name'] ?? '', $q) !== false;
                if (!$match) continue;
            }

            // Фильтр по ру
            if ($clanFilter > 0 && (int)($p['clan_id'] ?? 0) !== $clanFilter) {
                continue;
            }

            // Фильтр по полу
            if ($genderFilter !== '' && $genderFilter !== 'all' && $p['gender'] !== $genderFilter) {
                continue;
            }

            // Фильтр по статусу (живые / ушедшие)
            $isDeceased = !empty($p['is_deceased']);
            if ($statusFilter === 'living' && $isDeceased) continue;
            if ($statusFilter === 'deceased' && !$isDeceased) continue;

            // Родители
            $father = !empty($p['father_id']) ? ($personsById[$p['father_id']] ?? null) : null;
            $mother = !empty($p['mother_id']) ? ($personsById[$p['mother_id']] ?? null) : null;

            // Годы и полные даты: живые — дата/год рождения (никаких «жив»!), ушедшие — годы жизни с золотой иконкой ✨
            $birthDisp = !empty($p['birth_date']) ? date('d.m.Y', strtotime($p['birth_date'])) : ($p['birth_year'] ?: '—');
            $deathDisp = !empty($p['death_date']) ? date('d.m.Y', strtotime($p['death_date'])) : ($p['death_year'] ?: '—');

            $yearsDisplay = '';
            if ($isDeceased) {
                $yearsDisplay = "{$birthDisp} — {$deathDisp}";  // TASK-008: без эмодзи, ушедших выделяет золотистая обводка аватара
            } else {
                $yearsDisplay = $birthDisp;
            }

            $filtered[] = [
                'id' => $pId,
                'first_name' => $p['first_name'],
                'last_name' => $p['last_name'],
                'maiden_name' => $p['maiden_name'] ?? null,
                'middle_name' => $p['middle_name'] ?? null,
                'middle_name_kk' => $patr['kk'],
                'middle_name_ru' => $patr['ru'],
                'full_name' => $fullName,
                'gender' => $p['gender'],
                'birth_date' => $p['birth_date'] ?? null,
                'birth_year' => $p['birth_year'],
                'birth_disp' => $birthDisp,
                'death_date' => $p['death_date'] ?? null,
                'death_year' => $p['death_year'],
                'death_disp' => $isDeceased ? $deathDisp : '',
                'death_place' => $p['death_place'] ?? null,
                'years_display' => $yearsDisplay,
                'is_deceased' => $isDeceased ? 1 : 0,
                'birth_place' => $p['birth_place'],
                'clan_ru' => $p['clan_ru'] ?: 'белгісіз',
                'clan_id' => $p['clan_id'],
                'father_id' => $p['father_id'],
                'father_name' => $father ? trim(($father['last_name'] ?? '') . ' ' . $father['first_name']) : null,
                'mother_id' => $p['mother_id'],
                'mother_name' => $mother ? trim(($mother['last_name'] ?? '') . ' ' . $mother['first_name']) : null,
                'spouses' => $spousesMap[$pId] ?? [],
                'avatar_path' => $p['avatar_path'] ?? null,
            ];
        }

        // Сортировка
        usort($filtered, function ($a, $b) use ($sort) {
            switch ($sort) {
                case 'name_desc':
                    return strcmp($b['full_name'], $a['full_name']);
                case 'birth_asc':
                case 'birth_year_asc':
                    return ((int)($a['birth_year'] ?? 0)) <=> ((int)($b['birth_year'] ?? 0));
                case 'birth_desc':
                case 'birth_year_desc':
                    return ((int)($b['birth_year'] ?? 0)) <=> ((int)($a['birth_year'] ?? 0));
                case 'id_asc':
                    return ((int)$a['id']) <=> ((int)$b['id']);
                case 'name_asc':
                default:
                    return strcmp($a['full_name'], $b['full_name']);
            }
        });

        Response::json([
            'status' => 'success',
            'total' => count($filtered),
            'results' => $filtered,
        ]);
    }

    /**
     * API: Получить полные данные персоны для модального окна просмотра и редактирования
     */
    public function apiPersonGet(Request $request): void
    {
        $id = (int)$request->get('id', 0);
        if (!$id) {
            Response::json(['error' => 'id is required'], 400);
            return;
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM `persons` WHERE `id` = ? AND `is_deleted` = 0");
            $stmt->execute([$id]);
            $person = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$person) {
                Response::json(['error' => 'Тұлға табылмады / Персона не найдена'], 404);
                return;
            }

            // Получаем родителей
            $father = null;
            if (!empty($person['father_id'])) {
                $fStmt = $db->prepare("SELECT id, first_name, last_name, birth_year FROM persons WHERE id = ?");
                $fStmt->execute([$person['father_id']]);
                $father = $fStmt->fetch(PDO::FETCH_ASSOC);
            }

            $mother = null;
            if (!empty($person['mother_id'])) {
                $mStmt = $db->prepare("SELECT id, first_name, last_name, birth_year FROM persons WHERE id = ?");
                $mStmt->execute([$person['mother_id']]);
                $mother = $mStmt->fetch(PDO::FETCH_ASSOC);
            }

            // Получаем супругов
            $spStmt = $db->prepare("
                SELECT m.*, p.first_name, p.last_name, p.birth_year, p.gender 
                FROM marriages m
                JOIN persons p ON (p.id = IF(m.husband_id = ?, m.wife_id, m.husband_id))
                WHERE (m.husband_id = ? OR m.wife_id = ?) AND p.is_deleted = 0
            ");
            $spStmt->execute([$id, $id, $id]);
            $spouses = $spStmt->fetchAll(PDO::FETCH_ASSOC);

            // Получаем детей
            $cStmt = $db->prepare("SELECT id, first_name, last_name, birth_year, gender FROM persons WHERE (father_id = ? OR mother_id = ?) AND is_deleted = 0 ORDER BY birth_year ASC");
            $cStmt->execute([$id, $id]);
            $children = $cStmt->fetchAll(PDO::FETCH_ASSOC);

            // Аудит-логи правок персоны
            $aStmt = $db->prepare("SELECT * FROM audit_logs WHERE entity_table = 'persons' AND entity_id = ? ORDER BY id DESC LIMIT 10");
            $aStmt->execute([$id]);
            $auditLogs = $aStmt->fetchAll(PDO::FETCH_ASSOC);

            // Флаг редактирования рода (только для родоначальников без отца в БД)
            // TASK-028: подсказка для поля «Әкесінің аты» (автоматическое значение; в форму как значение не подставляется)
            $person['middle_name_kk'] = \App\Services\PatronymicService::resolve(['gender' => $person['gender'] ?? ''], $father, 'kk');
            $person['middle_name_ru'] = \App\Services\PatronymicService::resolve(['gender' => $person['gender'] ?? ''], $father, 'ru');
            $person['can_edit_clan'] = empty($person['father_id']);
            $person['clan_hint'] = !empty($person['father_id']) ? 'әкесінен / по отцу' : '';

            // TASK-022: контакты для окна редактирования (телефоны, email, статус приглашения)
            $contactsOut = null;
            if (\App\Controllers\PersonContactController::canEditContacts((int)$person['id'])) {
                $ctSvc = new \App\Services\PersonContactService($db);
                $ct = \App\Services\PersonContactService::decodeContacts($person['private_contacts'] ?? null);
                $ctState = $ctSvc->inviteStatus((int)$person['id'], $ct['emails']);
                $contactsOut = [
                    'phones'       => array_map(static fn($ph) => \App\Services\PersonContactService::formatPhone($ph), $ct['phones']),
                    'email'        => $ct['emails'][0] ?? '',
                    'socials'      => $ct['socials'], // TASK-035
                    'invite_state' => $ctState['state'],
                    'can_invite'   => Auth::canEdit(),
                    'is_admin'     => Auth::isAdmin(),
                    'csrf_token'   => \App\Core\Session::csrfToken(),
                ];
            }

            // TASK-022: фото и обложка в окне «Өңдеу» — только тем, кто может править карточку
            $mediaOut = null;
            if (\App\Controllers\PersonContactController::canEditContacts((int)$person['id'])) {
                $mediaOut = [
                    'avatar'      => (string)($person['avatar_path'] ?? ''),
                    'cover'       => \App\Controllers\PersonCoverController::coverState($person),
                    'cover_ready' => \App\Controllers\PersonCoverController::hasCoverColumns($db),
                    'csrf_token'  => \App\Core\Session::csrfToken(),
                    'presets'     => \App\Controllers\PersonCoverController::PRESETS,
                ];
            }

            Response::json([
                'status' => 'success',
                'person' => $person,
                'media_edit' => $mediaOut,
                'father' => $father,
                'mother' => $mother,
                'spouses' => $spouses,
                'children' => $children,
                'audit_logs' => $auditLogs,
                'can_edit' => \App\Services\PersonAccessService::make()->canEditPerson((int)$person['id']),
                'can_delete' => \App\Services\PersonAccessService::make()->canDeletePerson((int)$person['id']),
                'contacts' => $contactsOut,
                // TASK-036: история проживания (пусто, пока миграция не применена)
                'residences' => (new \App\Services\PersonResidenceService($db))->list((int)$person['id']),
                'residences_ready' => (new \App\Services\PersonResidenceService($db))->hasTable(),
                'profile_ready' => (new \App\Services\PersonResidenceService($db))->hasProfileColumns(),
                'burial_ready' => (new \App\Services\PersonBurialService($db))->isReady(),
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Собирает людей, которым распространяется ру по мужской линии от человека $id:
     * отцы вверх по цепочке, их дети (братья/сёстры) и потомки по мужской линии.
     * Жёны (келін) не затрагиваются.
     * @return array{targets: array<int,array>, conflicts: array<int,array>}
     */
    private function collectClanTargets(\PDO $db, int $id, string $ru): array
    {
        $byId = [];
        foreach ($db->query("SELECT id, first_name, last_name, gender, father_id, clan_ru FROM persons WHERE is_deleted = 0")->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $byId[(int)$r['id']] = $r;
        }
        if (!isset($byId[$id])) {
            return ['targets' => [], 'conflicts' => []];
        }
        $kids = [];
        foreach ($byId as $r) {
            if (!empty($r['father_id'])) {
                $kids[(int)$r['father_id']][] = (int)$r['id'];
            }
        }
        // Корень — самый верхний отец
        $root = $id;
        $guard = 0;
        while (!empty($byId[$root]['father_id']) && isset($byId[(int)$byId[$root]['father_id']]) && $guard++ < 100) {
            $root = (int)$byId[$root]['father_id'];
        }
        // Спуск от корня: дети любого пола; дальше — только через сыновей
        $set = [$root => true];
        $queue = [$root];
        while ($queue) {
            $cur = array_shift($queue);
            foreach ($kids[$cur] ?? [] as $kid) {
                if (isset($set[$kid])) {
                    continue;
                }
                $set[$kid] = true;
                if (($byId[$kid]['gender'] ?? '') === 'M') {
                    $queue[] = $kid;
                }
            }
        }
        $targets = [];
        $conflicts = [];
        foreach (array_keys($set) as $pid) {
            $cur = trim((string)($byId[$pid]['clan_ru'] ?? ''));
            $row = ['id' => $pid, 'name' => trim(($byId[$pid]['first_name'] ?? '') . ' ' . ($byId[$pid]['last_name'] ?? '')), 'current' => $cur];
            if ($cur === '' || mb_strtolower($cur) === 'белгісіз') {
                $targets[] = $row;
            } elseif (mb_strtolower($cur) !== mb_strtolower($ru)) {
                $conflicts[] = $row;
            }
        }
        return ['targets' => $targets, 'conflicts' => $conflicts];
    }

    /**
     * API: предпросмотр распространения ру по мужской линии (GET id, clan_ru)
     */
    public function apiClanPropagatePreview(Request $request): void
    {
        if (!Auth::canEdit()) {
            Response::json(['error' => 'Нет прав на редактирование'], 403);
            return;
        }
        $id = (int)$request->get('id', 0);
        $ru = trim((string)$request->get('clan_ru', ''));
        if ($id <= 0 || $ru === '' || mb_strtolower($ru) === 'белгісіз') {
            Response::json(['error' => 'id and clan_ru required'], 422);
            return;
        }
        try {
            $res = $this->collectClanTargets(Database::getConnection(), $id, $ru);
            Response::json([
                'status' => 'success',
                'clan_ru' => $ru,
                'count' => count($res['targets']),
                'targets' => $res['targets'],
                'conflict_count' => count($res['conflicts']),
                'conflicts' => $res['conflicts'],
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: применить ру по мужской линии (POST id, clan_ru, overwrite=0|1).
     * Без overwrite обновляются только люди с пустым/«белгісіз» ру; конфликтующие не трогаются.
     */
    public function apiClanPropagateApply(Request $request): void
    {
        if (!Auth::canEdit()) {
            Response::json(['error' => 'Нет прав на редактирование'], 403);
            return;
        }
        $id = (int)$request->get('id', 0);
        $ru = trim((string)$request->get('clan_ru', ''));
        $overwrite = (int)$request->get('overwrite', 0) === 1;
        if ($id <= 0 || $ru === '' || mb_strtolower($ru) === 'белгісіз') {
            Response::json(['error' => 'id and clan_ru required'], 422);
            return;
        }
        try {
            $db = Database::getConnection();
            $res = $this->collectClanTargets($db, $id, $ru);
            $rows = $overwrite ? array_merge($res['targets'], $res['conflicts']) : $res['targets'];
            $db->beginTransaction();
            $upd = $db->prepare("UPDATE `persons` SET `clan_ru` = ?, `updated_at` = NOW() WHERE `id` = ? AND `is_deleted` = 0");
            foreach ($rows as $r) {
                $upd->execute([$ru, (int)$r['id']]);
            }
            $db->commit();
            $this->recordAuditLog(
                'UPDATE',
                'persons',
                $id,
                'Ру «' . $ru . '» распространён по мужской линии на ' . count($rows) . ' чел.',
                [],
                ['clan_ru' => $ru, 'ids' => array_column($rows, 'id')],
                Auth::user()
            );
            Response::json([
                'status' => 'success',
                'updated' => count($rows),
                'skipped_conflicts' => $overwrite ? 0 : count($res['conflicts']),
            ]);
        } catch (\Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Сохранение изменений персоны (Реальное редактирование в БД с фиксацией в audit_logs)
     */
    public function apiPersonUpdate(Request $request): void
    {
        // TASK-032: редактор/админ — любого; обычный родственник — только свой круг (я, супруг(а), дети, добавленные мной)
        $id = (int)$request->get('id', 0);
        if (!$id) {
            Response::json(['error' => 'id is required'], 400);
            return;
        }
        if (!Auth::check() || !\App\Services\PersonAccessService::make()->canEditPerson($id)) {
            Response::json(['error' => 'Өңдеуге рұқсат жоқ / Нет прав на редактирование (только просмотр)'], 403);
            return;
        }

        $firstName = trim((string)$request->get('first_name', ''));
        if ($firstName === '') {
            Response::json(['error' => 'Аты (имя) толтырылуы тиіс / Имя обязательно для заполнения'], 422);
            return;
        }

        try {
            $db = Database::getConnection();

            // Читаем старые данные для истории аудита
            $stmtOld = $db->prepare("SELECT * FROM `persons` WHERE `id` = ?");
            $stmtOld->execute([$id]);
            $oldPerson = $stmtOld->fetch(PDO::FETCH_ASSOC);

            if (!$oldPerson) {
                Response::json(['error' => 'Персона не найдена'], 404);
                return;
            }

            $lastName = trim((string)$request->get('last_name', '')) ?: null;
            $maidenName = trim((string)$request->get('maiden_name', '')) ?: null;
            $middleName = trim((string)$request->get('middle_name', '')) ?: null;
            $gender = trim((string)$request->get('gender', 'M'));

            $rawBirthDate = trim((string)$request->get('birth_date', ''));
            $birthDate = $this->normalizeSqlDate($rawBirthDate);
            $rawBirthYear = trim((string)$request->get('birth_year', '')) ?: null;
            if (!$rawBirthYear && $birthDate) {
                $rawBirthYear = substr($birthDate, 0, 4);
            }
            $birthYear = $rawBirthYear;
            $birthPlace = trim((string)$request->get('birth_place', '')) ?: null;

            // Обработка статуса смерти: у живых дата смерти пустая, у ушедших заполняется дата/год
            $rawDeathDate = trim((string)$request->get('death_date', ''));
            $deathDate = $this->normalizeSqlDate($rawDeathDate);
            $rawDeathYear = trim((string)$request->get('death_year', '')) ?: null;
            if (!$rawDeathYear && $deathDate) {
                $rawDeathYear = substr($deathDate, 0, 4);
            }
            $deathYear = $rawDeathYear;
            $deathPlace = trim((string)$request->get('death_place', '')) ?: null;
            $isDeceasedInput = (int)$request->get('is_deceased', 0);

            $isDeceased = ($deathDate !== null || $deathYear !== null || $isDeceasedInput === 1) ? 1 : 0;
            if (!$isDeceased) {
                $deathDate = null;
                $deathYear = null;
                $deathPlace = null;
            }

            // Наследование рода (TASK-007):
            // Род задается только у родоначальника/отца без отца в БД.
            // У детей род строго наследуется от отца с подсказкой «әкесінен / по отцу».
            $clanChanged = false;
            if (!Auth::canEdit()) {
                // TASK-032: обычный родственник ру не меняет (справочник и наследование — за редактором)
                $clanId = $oldPerson['clan_id'] !== null ? (int)$oldPerson['clan_id'] : null;
                $clanRu = $oldPerson['clan_ru'] ?? null;
            } elseif (!empty($oldPerson['father_id'])) {
                $fStmt = $db->prepare("SELECT clan_id, clan_ru FROM persons WHERE id = ?");
                $fStmt->execute([$oldPerson['father_id']]);
                $fRow = $fStmt->fetch(PDO::FETCH_ASSOC);
                $clanId = $fRow['clan_id'] ?? null;
                $clanRu = $fRow['clan_ru'] ?? 'белгісіз';
            } else {
                $clanRu = trim((string)$request->get('clan_ru', '')) ?: null;
                $clanId = (int)$request->get('clan_id', 0) ?: null;
                // TASK-030: ру — только из справочника; clan_id ставится по названию
                $clanSvc = new \App\Services\ClanService($db);
                if ($clanSvc->hasRegistry() && $clanRu !== null && $clanRu !== ($oldPerson['clan_ru'] ?? null)
                    && mb_strtolower($clanRu) !== 'белгісіз') {
                    $found = $clanSvc->resolveByName($clanRu);
                    if (!$found) {
                        Response::json(['error' => 'Бұл ру «Ру таңбалары» тізімінде жоқ. Алдымен тізімге қосыңыз / Такого ру нет в справочнике «Ру таңбалары» — сначала добавьте его'], 422);
                        return;
                    }
                    $clanRu = $found['name'];
                    $clanId = $found['id'];
                } elseif ($clanRu !== null && !$clanId && $clanSvc->hasRegistry()) {
                    $found = $clanSvc->resolveByName($clanRu);
                    $clanId = $found['id'] ?? null;
                }
                if ($clanRu !== ($oldPerson['clan_ru'] ?? null) || $clanId !== (int)($oldPerson['clan_id'] ?? 0)) {
                    $clanChanged = true;
                }
            }

            $rawBiography = trim((string)$request->get('biography', '')) ?: null;
            $biography = $rawBiography ? $this->cleanBiography($rawBiography) : null;

            $updateStmt = $db->prepare("
                UPDATE `persons` SET
                    `first_name` = ?,
                    `last_name` = ?,
                    `maiden_name` = ?,
                    `middle_name` = ?,
                    `gender` = ?,
                    `birth_date` = ?,
                    `birth_year` = ?,
                    `birth_place` = ?,
                    `is_deceased` = ?,
                    `death_date` = ?,
                    `death_year` = ?,
                    `death_place` = ?,
                    `clan_id` = ?,
                    `clan_ru` = ?,
                    `biography` = ?,
                    `updated_at` = NOW()
                WHERE `id` = ?
            ");

            $updateStmt->execute([
                $firstName,
                $lastName,
                $maidenName,
                $middleName,
                $gender,
                $birthDate,
                $birthYear,
                $birthPlace,
                $isDeceased,
                $deathDate,
                $deathYear,
                $deathPlace,
                $clanId,
                $clanRu,
                $biography,
                $id
            ]);

            // ALT-002: подробное место упокоения (если миграция применена)
            // сохраняем только если форма прислала эти поля (форма в tree.php их не шлёт — данные не затираем)
            if ($request->get('burial_lat', null) !== null) {
            (new \App\Services\PersonBurialService($db))->save($id, [
                'burial_settlement' => $request->get('burial_settlement', ''),
                'burial_cemetery'   => $request->get('burial_cemetery', ''),
                'burial_address'    => $request->get('burial_address', ''),
                'burial_plot'       => $request->get('burial_plot', ''),
                'burial_lat'        => $request->get('burial_lat', ''),
                'burial_lng'        => $request->get('burial_lng', ''),
            ], (bool)$isDeceased);
            }

            // TASK-036: религия, национальность, образование и история проживания (если миграция применена)
            $resSvc = new \App\Services\PersonResidenceService($db);
            if ($request->get('profile_present', '') !== '') {
                $resSvc->saveProfile($id, [
                    'religion'    => $request->get('religion', ''),
                    'nationality' => $request->get('nationality', ''),
                    'education'   => $request->get('education', ''),
                ]);
            }
            $resJson = (string)$request->get('residences', '');
            if ($resJson !== '' && !$resSvc->replace($id, $resJson)) {
                Response::json(['error' => 'Тұрғылықты жер тізімі қате / Некорректный список проживания'], 422);
                return;
            }

            // Каскадное обновление рода у потомков по мужской линии, если изменился род у отца-родоначальника
            if ($clanChanged) {
                $cascStmt = $db->prepare("
                    UPDATE `persons` p
                    JOIN (
                        WITH RECURSIVE patrilineal AS (
                            SELECT `id`, `gender` FROM `persons` WHERE `id` = ?
                            UNION ALL
                            SELECT `child`.`id`, `child`.`gender` 
                            FROM `persons` `child`
                            INNER JOIN `patrilineal` `pt` ON `child`.`father_id` = `pt`.`id`
                            WHERE `pt`.`gender` = 'M'
                        )
                        SELECT `id` FROM `patrilineal` WHERE `id` != ?
                    ) `k` ON `p`.`id` = `k`.`id`
                    SET `p`.`clan_id` = ?, 
                        `p`.`clan_ru` = ?
                ");
                $cascStmt->execute([$id, $id, $clanId, $clanRu]);

                $user = Auth::user();
                $this->recordAuditLog(
                    'UPDATE',
                    'persons',
                    $id,
                    "Род предка {$firstName} {$lastName} изменен на {$clanRu}, каскадно пересчитан у всех потомков по мужской линии",
                    ['clan_ru' => $oldPerson['clan_ru'], 'clan_id' => $oldPerson['clan_id']],
                    ['clan_ru' => $clanRu, 'clan_id' => $clanId],
                    $user
                );
            }

            // Получаем новые значения
            $stmtNew = $db->prepare("SELECT * FROM `persons` WHERE `id` = ?");
            $stmtNew->execute([$id]);
            $newPerson = $stmtNew->fetch(PDO::FETCH_ASSOC);

            // Запись в аудит-лог
            $user = Auth::user();
            $this->recordAuditLog(
                'UPDATE',
                'persons',
                $id,
                "Тұлға деректері өзгертілді / Обновлены данные персоны: {$firstName} {$lastName}",
                $oldPerson,
                $newPerson,
                $user
            );

            Response::json([
                'status' => 'success',
                'message' => 'Өзгерістер сәтті сақталды / Изменения успешно сохранены!',
                'person' => $newPerson,
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Быстрое добавление родственника (+Ұл, +Қыз, +Әке, +Ана, +Жұбай, +Бауыр)
     */
    public function apiPersonCreateRelative(Request $request): void
    {
        // TASK-032: добавлять новых людей может любой вошедший; существующих он не меняет (см. canAddRelativeTo)
        if (!Auth::check()) {
            Response::json(['error' => 'Кіру қажет / Нужен вход'], 401);
            return;
        }

        $targetId = (int)$request->get('target_id', 0);
        $relationType = trim((string)$request->get('relation_type', ''));

        if (!$targetId || !$relationType) {
            Response::json(['error' => 'target_id and relation_type are required'], 400);
            return;
        }

        $firstName = trim((string)$request->get('first_name', ''));
        if ($firstName === '') {
            Response::json(['error' => 'Аты (имя) толтырылуы тиіс / Имя обязательно'], 422);
            return;
        }

        try {
            $db = Database::getConnection();

            $stmtTarget = $db->prepare("SELECT * FROM `persons` WHERE `id` = ?");
            $stmtTarget->execute([$targetId]);
            $target = $stmtTarget->fetch(PDO::FETCH_ASSOC);

            if (!$target || !empty($target['is_deleted'])) {
                Response::json(['error' => 'Мақсатты тұлға табылмады / Целевая персона не найдена'], 404);
                return;
            }
            if (!\App\Services\PersonAccessService::make()->canAddRelativeTo($target, $relationType)) {
                Response::json(['error' => 'Бұл тұлғаның байланысын өзгертуге рұқсат жоқ / Нет прав менять связи этой карточки (только просмотр)'], 403);
                return;
            }

            $lastName = trim((string)$request->get('last_name', '')) ?: $target['last_name'];
            $maidenName = trim((string)$request->get('maiden_name', '')) ?: null;
            $middleName = trim((string)$request->get('middle_name', '')) ?: null;
            $birthYear = trim((string)$request->get('birth_year', '')) ?: null;
            $birthPlace = trim((string)$request->get('birth_place', '')) ?: $target['birth_place'];
            $isDeceased = (int)$request->get('is_deceased', 0);
            $deathYear = trim((string)$request->get('death_year', '')) ?: null;

            $fatherId = null;
            $motherId = null;
            $gender = 'M';
            $clanId = $target['clan_id'];
            $clanRu = $target['clan_ru'];

            switch ($relationType) {
                case 'son':
                case 'daughter':
                    $gender = ($relationType === 'daughter') ? 'F' : 'M';
                    if ($target['gender'] === 'M') {
                        $fatherId = $targetId;
                        // Ищем супругу для связки матери
                        $spStmt = $db->prepare("SELECT m.wife_id FROM marriages m JOIN persons pw ON pw.id = m.wife_id AND pw.is_deleted = 0 WHERE m.husband_id = ? AND m.is_current = 1 LIMIT 1");
                        $spStmt->execute([$targetId]);
                        $motherId = $spStmt->fetchColumn() ?: null;
                    } else {
                        $motherId = $targetId;
                        $spStmt = $db->prepare("SELECT m.husband_id FROM marriages m JOIN persons ph ON ph.id = m.husband_id AND ph.is_deleted = 0 WHERE m.wife_id = ? AND m.is_current = 1 LIMIT 1");
                        $spStmt->execute([$targetId]);
                        $fatherId = $spStmt->fetchColumn() ?: null;
                        if ($fatherId) {
                            $fRow = $db->query("SELECT clan_id, clan_ru FROM persons WHERE id = {$fatherId}")->fetch(PDO::FETCH_ASSOC);
                            if ($fRow) {
                                $clanId = $fRow['clan_id'];
                                $clanRu = $fRow['clan_ru'];
                            }
                        }
                    }
                    break;

                case 'father':
                    $gender = 'M';
                    break;

                case 'mother':
                    $gender = 'F';
                    $clanRu = 'белгісіз';
                    $clanId = null;
                    break;

                case 'spouse':
                    $gender = ($target['gender'] === 'M') ? 'F' : 'M';
                    if ($gender === 'F') {
                        $clanRu = 'белгісіз';
                        $clanId = null;
                    }
                    break;

                case 'sibling':
                    $gender = trim((string)$request->get('gender', 'M'));
                    $fatherId = $target['father_id'];
                    $motherId = $target['mother_id'];
                    break;
            }

            // Вставка новой персоны
            $user = Auth::user();
            $userId = $user['id'] ?? null;

            $insertStmt = $db->prepare("
                INSERT INTO `persons` (
                    `first_name`, `last_name`, `maiden_name`, `middle_name`,
                    `gender`, `father_id`, `mother_id`,
                    `birth_year`, `birth_place`, `is_deceased`, `death_year`,
                    `clan_id`, `clan_ru`, `created_by`, `created_at`
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, NOW()
                )
            ");

            $insertStmt->execute([
                $firstName,
                $lastName,
                $maidenName,
                $middleName,
                $gender,
                $fatherId,
                $motherId,
                $birthYear,
                $birthPlace,
                $isDeceased,
                $deathYear,
                $clanId,
                $clanRu,
                $userId
            ]);

            $newPersonId = (int)$db->lastInsertId();

            // Дополнительные связи
            if ($relationType === 'father') {
                $db->prepare("UPDATE `persons` SET `father_id` = ? WHERE `id` = ?")->execute([$newPersonId, $targetId]);
            } elseif ($relationType === 'mother') {
                $db->prepare("UPDATE `persons` SET `mother_id` = ? WHERE `id` = ?")->execute([$newPersonId, $targetId]);
            } elseif ($relationType === 'spouse') {
                $husbandId = ($target['gender'] === 'M') ? $targetId : $newPersonId;
                $wifeId = ($target['gender'] === 'M') ? $newPersonId : $targetId;
                $marrStmt = $db->prepare("INSERT INTO `marriages` (`husband_id`, `wife_id`, `is_current`, `status`, `created_at`) VALUES (?, ?, 1, 'married', NOW())");
                $marrStmt->execute([$husbandId, $wifeId]);
            }

            // Аудит-лог
            $newRow = $db->query("SELECT * FROM persons WHERE id = {$newPersonId}")->fetch(PDO::FETCH_ASSOC);
            $this->recordAuditLog(
                'CREATE',
                'persons',
                $newPersonId,
                "Жаңа туыс қосылды / Добавлен новый родственник ({$relationType}): {$firstName} {$lastName}",
                null,
                $newRow,
                $user
            );

            Response::json([
                'status' => 'success',
                'message' => 'Туыс сәтті қосылды / Родственник успешно добавлен!',
                'new_person_id' => $newPersonId,
                'person' => $newRow,
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Мягкое удаление персоны (ALT-018).
     * Права: админ или тот, кто добавил человека (persons.created_by). POST + CSRF.
     * Если есть живые дети — без children_confirmed=1 отвечает 409 со списком детей;
     * с подтверждением: дети остаются, связь с удаляемым рвётся (id записываются в журнал для восстановления).
     * Браки не трогаем: загрузчики пропускают браки с удалёнными людьми, при восстановлении всё вернётся.
     */
    public function apiPersonDelete(Request $request): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::json(['error' => 'Авторизация қажет / Требуется авторизация'], 401);
            return;
        }
        if (!\App\Core\Session::validateCsrf((string)$request->post('csrf_token', ''))) {
            Response::json(['error' => 'Сессия ескірді, бетті жаңартыңыз / Сессия устарела, обновите страницу'], 403);
            return;
        }

        $id = (int)$request->post('id', 0);
        if ($id <= 0) {
            Response::json(['error' => 'id is required'], 400);
            return;
        }

        try {
            $db = Database::getConnection();
            $access = \App\Services\PersonAccessService::make();

            $stmtOld = $db->prepare("SELECT * FROM persons WHERE id = ? AND is_deleted = 0");
            $stmtOld->execute([$id]);
            $oldPerson = $stmtOld->fetch(PDO::FETCH_ASSOC);

            if (!$oldPerson) {
                Response::json(['error' => 'Персона не найдена'], 404);
                return;
            }
            if (!$access->canDeletePerson($id, $user)) {
                Response::json(['error' => 'Жоюға рұқсат жоқ: тек қосқан адам немесе әкімші / Удалять может только тот, кто добавил человека, или администратор'], 403);
                return;
            }

            // карточка, привязанная к аккаунту, — только админ
            if (!Auth::isAdmin()) {
                $lk = $db->prepare('SELECT 1 FROM users WHERE person_id = ? LIMIT 1');
                $lk->execute([$id]);
                if ($lk->fetchColumn()) {
                    Response::json(['error' => 'Бұл карточка пайдаланушыға байланған, жоюды әкімші жасайды / Карточка привязана к пользователю — удаляет только администратор'], 403);
                    return;
                }
            }

            $children = $access->childrenOf($id);
            if ($children &&(string)$request->post('children_confirmed', '') !== '1') {
                Response::json([
                    'status'         => 'needs_confirm',
                    'has_children'   => true,
                    'children_count' => count($children),
                    'children'       => array_map(
                        static fn($c) => trim(($c['last_name'] ?? '') . ' ' . ($c['first_name'] ?? '')),
                        array_slice($children, 0, 10)
                    ),
                ], 409);
                return;
            }

            $db->beginTransaction();
            $detached = ['father' => [], 'mother' => []];
            foreach ($children as $c) {
                if ((int)$c['father_id'] === $id) {
                    $detached['father'][] = (int)$c['id'];
                }
                if ((int)$c['mother_id'] === $id) {
                    $detached['mother'][] = (int)$c['id'];
                }
            }
            $db->prepare("UPDATE `persons` SET `father_id` = NULL, `updated_at` = NOW() WHERE `father_id` = ? AND `is_deleted` = 0")->execute([$id]);
            $db->prepare("UPDATE `persons` SET `mother_id` = NULL, `updated_at` = NOW() WHERE `mother_id` = ? AND `is_deleted` = 0")->execute([$id]);
            $db->prepare("UPDATE `persons` SET `is_deleted` = 1, `updated_at` = NOW() WHERE `id` = ?")->execute([$id]);
            // журнал — внутри транзакции: без него восстановить связи с детьми нечем (ошибка откатит удаление)
            $db->prepare(
                "INSERT INTO `audit_logs` (`user_id`, `user_name`, `user_email`, `action`, `entity_table`, `entity_id`, `summary`, `old_values`, `new_values`, `created_at`)
                 VALUES (?, ?, ?, 'DELETE', 'persons', ?, ?, ?, ?, NOW())"
            )->execute([
                $user['id'] ?? null,
                $user['name'] ?? 'Система',
                $user['email'] ?? 'system@altybayev.kz',
                $id,
                "Тұлға жойылды (soft-delete) / Удалён: {$oldPerson['first_name']} {$oldPerson['last_name']}",
                json_encode($oldPerson, JSON_UNESCAPED_UNICODE),
                json_encode(['is_deleted' => 1, 'detached_children' => $detached], JSON_UNESCAPED_UNICODE),
            ]);
            $db->commit();

            Response::json([
                'status' => 'success',
                'message' => 'Тұлға сәтті жойылды / Персона успешно удалена'
            ]);
        } catch (\Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            Logger::error('ALT-018 delete: ' . $e->getMessage(), $e);
            Response::json(['error' => 'Жою қатесі / Ошибка удаления'], 500);
        }
    }

    /**
     * API (админ): корзина — удалённые люди (ALT-018).
     */
    public function apiTrashList(Request $request): void
    {
        try {
            $db = Database::getConnection();
            $rows = $db->query(
                "SELECT id, first_name, last_name, birth_year, updated_at FROM persons WHERE is_deleted = 1 ORDER BY updated_at DESC, id DESC LIMIT 200"
            )->fetchAll(PDO::FETCH_ASSOC);
            Response::json(['status' => 'success', 'items' => $rows]);
        } catch (\Throwable $e) {
            Response::json(['error' => 'Қате / Ошибка'], 500);
        }
    }

    /**
     * API (админ): восстановить удалённого человека. Связи с детьми, которых отвязали при удалении,
     * возвращаются (только если место отца/матери у ребёнка всё ещё пустое).
     */
    public function apiPersonRestore(Request $request): void
    {
        if (!\App\Core\Session::validateCsrf((string)$request->post('csrf_token', ''))) {
            Response::json(['error' => 'Сессия ескірді, бетті жаңартыңыз / Сессия устарела, обновите страницу'], 403);
            return;
        }
        $id = (int)$request->post('id', 0);
        if ($id <= 0) {
            Response::json(['error' => 'id is required'], 400);
            return;
        }
        try {
            $db = Database::getConnection();
            $st = $db->prepare("SELECT * FROM persons WHERE id = ? AND is_deleted = 1");
            $st->execute([$id]);
            $person = $st->fetch(PDO::FETCH_ASSOC);
            if (!$person) {
                Response::json(['error' => 'Персона не найдена в корзине'], 404);
                return;
            }

            $detached = ['father' => [], 'mother' => []];
            $lg = $db->prepare("SELECT new_values FROM audit_logs WHERE entity_table = 'persons' AND entity_id = ? AND action = 'DELETE' ORDER BY id DESC LIMIT 1");
            $lg->execute([$id]);
            $nv = json_decode((string)$lg->fetchColumn(), true);
            if (is_array($nv) && isset($nv['detached_children']) && is_array($nv['detached_children'])) {
                $detached = array_merge($detached, $nv['detached_children']);
            }

            $db->beginTransaction();
            $db->prepare("UPDATE `persons` SET `is_deleted` = 0, `updated_at` = NOW() WHERE `id` = ?")->execute([$id]);
            $reattach = ['father' => 'father_id', 'mother' => 'mother_id'];
            foreach ($reattach as $key => $col) {
                $upd = $db->prepare("UPDATE `persons` SET `{$col}` = ?, `updated_at` = NOW() WHERE `id` = ? AND `{$col}` IS NULL AND `is_deleted` = 0");
                foreach ((array)$detached[$key] as $childId) {
                    $upd->execute([$id, (int)$childId]);
                }
            }
            $db->commit();

            $this->recordAuditLog(
                'RESTORE',
                'persons',
                $id,
                "Тұлға қалпына келтірілді / Восстановлен: {$person['first_name']} {$person['last_name']}",
                ['is_deleted' => 1],
                ['is_deleted' => 0],
                Auth::user()
            );
            Response::json(['status' => 'success', 'message' => 'Қалпына келтірілді / Восстановлено']);
        } catch (\Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            Logger::error('ALT-018 restore: ' . $e->getMessage(), $e);
            Response::json(['error' => 'Қалпына келтіру қатесі / Ошибка восстановления'], 500);
        }
    }

    /**
     * API: Редактирование брака / развода
     */
    public function apiMarriageUpdate(Request $request): void
    {
        $id = (int)$request->get('id', 0);
        if (!$id) {
            Response::json(['error' => 'id is required'], 400);
            return;
        }
        // TASK-032: брак правит редактор/админ или один из супругов
        if (!Auth::check() || !\App\Services\PersonAccessService::make()->canEditMarriage($id)) {
            Response::json(['error' => 'Өңдеуге рұқсат жоқ / Нет прав'], 403);
            return;
        }

        $status = trim((string)$request->get('status', 'married'));
        $startDate = trim((string)$request->get('start_date', '')) ?: null;
        $endDate = trim((string)$request->get('end_date', '')) ?: null;
        $notes = trim((string)$request->get('notes', '')) ?: null;
        $isCurrent = ($status === 'divorced' || $status === 'widowed' || $endDate !== null) ? 0 : 1;

        try {
            $db = Database::getConnection();
            $stmtOld = $db->prepare("SELECT * FROM marriages WHERE id = ?");
            $stmtOld->execute([$id]);
            $oldM = $stmtOld->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("
                UPDATE `marriages` SET
                    `status` = ?,
                    `start_date` = ?,
                    `marriage_date` = ?,
                    `end_date` = ?,
                    `divorce_date` = ?,
                    `is_current` = ?,
                    `notes` = ?
                WHERE `id` = ?
            ");

            $stmt->execute([
                $status,
                $startDate,
                $startDate,
                $endDate,
                $endDate,
                $isCurrent,
                $notes,
                $id
            ]);

            $user = Auth::user();
            $this->recordAuditLog(
                'UPDATE',
                'marriages',
                $id,
                "Брак мәліметтері өзгертілді / Обновлены данные брака (статус: {$status})",
                $oldM,
                ['status' => $status, 'start_date' => $startDate, 'end_date' => $endDate, 'is_current' => $isCurrent],
                $user
            );

            Response::json(['status' => 'success', 'message' => 'Неке деректері сақталды / Данные брака сохранены']);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Связывание аккаунта пользователя с персоной в древе («Бұл мен / Это я»)
     */
    public function apiSetMyPerson(Request $request): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::json(['error' => 'Авторизация қажет / Требуется авторизация'], 401);
            return;
        }

        // TASK-014 (note 16.11): только POST + CSRF (раньше был GET без токена)
        if (!\App\Core\Session::validateCsrf((string)$request->post('csrf_token', ''))) {
            Response::json(['error' => 'Сессия ескірді, бетті жаңартыңыз / Сессия устарела, обновите страницу'], 403);
            return;
        }

        $personId = (int)$request->post('person_id', 0);
        if (!$personId) {
            Response::json(['error' => 'person_id is required'], 400);
            return;
        }

        // TASK-032: привязка к карточке даёт право править «свою семью», поэтому обычный родственник
        // сам себя привязать не может (иначе любой «станет» чужим предком) — привязку делает админ/редактор
        // или приглашение (users.person_id из invitations).
        if (!Auth::canEdit() && empty($user['person_id'])) {
            Response::json(['error' => 'Карточканы әкімші байланыстырады — Жандосқа жазыңыз / Привязку к карточке делает администратор — напишите Жандосу'], 403);
            return;
        }
        if (!Auth::canEdit()) {
            Response::json(['error' => 'Карточка байланған, өзгерту әкімшіге / Карточка уже привязана, менять может администратор'], 403);
            return;
        }

        try {
            $db = Database::getConnection();
            $chk = $db->prepare('SELECT id FROM persons WHERE id = ? AND is_deleted = 0 LIMIT 1');
            $chk->execute([$personId]);
            if (!$chk->fetchColumn()) {
                Response::json(['error' => 'Тұлға табылмады / Персона не найдена'], 404);
                return;
            }
            // Одна карточка — один пользователь: иначе права и «свои» данные смешиваются.
            $own = $db->prepare('SELECT id FROM users WHERE person_id = ? AND id <> ? LIMIT 1');
            $own->execute([$personId, (int)$user['id']]);
            if ($own->fetchColumn()) {
                Response::json(['error' => 'Бұл тұлға басқа пайдаланушыға байланған / Эта персона уже привязана к другому пользователю'], 409);
                return;
            }

            $this->saveUserPersonId((int)$user['id'], $personId);

            Response::json([
                'status' => 'success',
                'person_id' => $personId,
                'message' => 'Сәтті байланыстырылды! / Успешно привязано!'
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Массовый расчет родства
     */
    public function apiKinshipAll(Request $request): void
    {
        $dataset = $this->getDataset();
        $persons = $dataset['persons'];
        $marriages = $dataset['marriages'];

        if (empty($persons)) {
            Response::json(['status' => 'success', 'base_id' => 0, 'kinships' => (object)[]]);
            return;
        }

        $baseId = (int)$request->get('base_id', 0);
        if (!$baseId) {
            $user = Auth::user();
            $baseId = !empty($user['person_id']) ? (int)$user['person_id'] : $this->resolveZhandosPersonId();
        }

        $kinships = $this->kinshipService->calculateAllKinships($baseId, $persons, $marriages);

        Response::json([
            'status' => 'success',
            'base_id' => $baseId,
            'kinships' => $kinships,
        ]);
    }

    /**
     * API: Получить Үш жұрт (Три родственных круга: таблицы и папки) для персоны (TASK-011)
     */
    public function apiUshZhurt(Request $request): void
    {
        $id = (int)$request->get('id', 0);
        if (!$id) {
            $user = Auth::user();
            $id = !empty($user['person_id']) ? (int)$user['person_id'] : $this->resolveZhandosPersonId();
        }

        $dataset = $this->getDataset();
        $persons = $dataset['persons'];
        $marriages = $dataset['marriages'];
        $personsById = array_column($persons, null, 'id');

        $tablesData = $this->kinshipService->getUshZhurtTables($id, $personsById, $marriages);
        $familiesData = $this->kinshipService->getUshZhurtFamilies($id, $personsById, $marriages);

        Response::json([
            'status' => 'success',
            'base_id' => $id,
            'person' => $tablesData['base_person'] ?? null,
            'is_female' => $tablesData['is_female'] ?? false,
            'groups' => $tablesData['groups'] ?? [],
            'tables' => $tablesData['tables'] ?? [],
            'ush_zhurt' => $familiesData,
            'own_family' => $tablesData['own_family'] ?? [],
        ]);
    }

    /**
     * API: Расчет степени родства между двумя персонами
     */
    public function apiKinship(Request $request): void
    {
        $targetId = (int)$request->get('target_id', 0);
        $baseId = (int)$request->get('base_id', 0);

        if (!$targetId || !$baseId) {
            Response::json(['error' => 'target_id and base_id are required'], 400);
            return;
        }

        $dataset = $this->getDataset();
        $personsById = array_column($dataset['persons'], null, 'id');

        $target = $personsById[$targetId] ?? null;
        $base = $personsById[$baseId] ?? null;

        if (!$target || !$base) {
            Response::json(['error' => 'Person not found'], 404);
            return;
        }

        $kinship = $this->kinshipService->determineKinship($target, $base, 'auto', $personsById, $dataset['marriages']);
        $phrase = $this->kinshipService->formatUserKinshipPhrase($target, $base, $personsById, $dataset['marriages']);

        Response::json([
            'status' => 'success',
            'target' => $target,
            'base' => $base,
            'kinship' => $kinship,
            'phrase' => $phrase,
        ]);
    }

    /**
     * API: Быстрый поиск персон с подсказками
     */
    public function apiSearch(Request $request): void
    {
        $q = trim((string)$request->get('q', ''));
        if (mb_strlen($q) < 2) {
            Response::json(['results' => []]);
            return;
        }

        $dataset = $this->getDataset();
        $allData = $dataset['persons'];
        $results = [];

        $allById = array_column($allData, null, 'id');
        $searchLang = \App\Core\I18n::lang();
        foreach ($allData as $p) {
            $fatherForPatr = !empty($p['father_id']) ? ($allById[$p['father_id']] ?? null) : null;
            $patrBoth = \App\Services\PatronymicService::both($p, $fatherForPatr);
            $fullName = trim(($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($searchLang === 'ru' ? $patrBoth['ru'] : $patrBoth['kk']));
            if (mb_stripos($fullName, $q) !== false || mb_stripos($patrBoth['kk'], $q) !== false || mb_stripos($patrBoth['ru'], $q) !== false || mb_stripos($p['clan_ru'] ?? '', $q) !== false) {
                // Живые — только год рождения! Ушедшие — годы жизни
                $years = $p['is_deceased'] 
                    ? (($p['birth_year'] ?: '—') . ' — ' . ($p['death_year'] ?: '—'))
                    : ($p['birth_year'] ?: '—');

                $results[] = [
                    'id' => $p['id'],
                    'name' => $fullName,
                    'years' => $years,
                    'clan' => $p['clan_ru'] ?? 'белгісіз',
                    'gender' => $p['gender'],
                    'avatar' => $p['avatar_path'] ?? null,
                ];
            }
        }

        Response::json(['results' => array_slice($results, 0, 10)]);
    }

    /**
     * Определение ID персоны Жандоса в БД
     */
    protected function resolveZhandosPersonId(): int
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT id FROM persons WHERE gedcom_id = '@I6@' OR (first_name LIKE '%Жандос%' AND last_name LIKE '%Алтыбаев%') LIMIT 1");
            $id = $stmt->fetchColumn();
            return $id ? (int)$id : 6;
        } catch (\Throwable $e) {
            return 6;
        }
    }

    /**
     * Сохранение привязки в таблице users и сессии
     */
    protected function saveUserPersonId(int $userId, int $personId): void
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE `users` SET `person_id` = ? WHERE `id` = ?");
            $stmt->execute([$personId, $userId]);
            $_SESSION['user']['person_id'] = $personId;
        } catch (\Throwable $e) {
            Logger::error("Не удалось связать пользователя: " . $e->getMessage());
        }
    }

    /**
     * Запись действия в журнал аудита (audit_logs)
     */
    protected function recordAuditLog(
        string $action,
        string $entityTable,
        int $entityId,
        string $summary,
        mixed $oldValues,
        mixed $newValues,
        ?array $user
    ): void {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO `audit_logs` (
                    `user_id`, `user_name`, `user_email`, `action`,
                    `entity_table`, `entity_id`, `summary`,
                    `old_values`, `new_values`, `created_at`
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, NOW()
                )
            ");
            $stmt->execute([
                $user['id'] ?? null,
                $user['name'] ?? 'Система',
                $user['email'] ?? 'system@altybayev.kz',
                $action,
                $entityTable,
                $entityId,
                $summary,
                $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            Logger::error("Ошибка записи audit_logs: " . $e->getMessage());
        }
    }

    /**
     * Нормализация строки даты в формат YYYY-MM-DD для SQL DATE
     */
    protected function normalizeSqlDate(?string $raw): ?string
    {
        if ($raw === null) return null;
        $raw = trim($raw);
        if ($raw === '') return null;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw)) return $raw;
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        }
        $ts = strtotime($raw);
        if ($ts !== false) return date('Y-m-d', $ts);
        return null;
    }

    /**
     * Очистка текста биографии от HTML-мусора и служебных кодов GEDCOM
     */
    protected function cleanBiography(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/^[0-9]+\s+CONC\s+/m', '', $text);
        $text = preg_replace('/^[0-9]+\s+CONT\s+/m', "\n", $text);
        $text = preg_replace('/\bCONC\s+/i', '', $text);
        $text = preg_replace('/\bCONT\s+/i', "\n", $text);
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n\n", $text);
        $text = preg_replace('/<\/div>/i', "\n\n", $text);
        $text = preg_replace('/<\/li>/i', "\n", $text);
        $text = preg_replace('/<li[^>]*>/i', "• ", $text);
        $text = strip_tags($text);
        $text = preg_replace('/@[A-Z0-9_]+@/', '', $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/", " ", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }
}
