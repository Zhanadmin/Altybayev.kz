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
        $userPersonId = null;
        if (!empty($user['person_id'])) {
            $userPersonId = (int)$user['person_id'];
        } else {
            $userPersonId = $this->resolveZhandosPersonId();
            if ($user && $userPersonId) {
                $this->saveUserPersonId((int)$user['id'], $userPersonId);
            }
        }

        $allTerms = $this->kinshipService->getAllTerms();
        $conceptArtUrl = '/assets/img/shezhire_living_forest_concept.jpg';

        Response::view('tree', [
            'user' => $user,
            'userPersonId' => $userPersonId,
            'canEdit' => Auth::canEdit(),
            'isAdmin' => Auth::isAdmin(),
            'kinshipTerms' => $allTerms,
            'conceptArtUrl' => $conceptArtUrl,
        ]);
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
                $stmtMarriages = $db->query("SELECT * FROM `marriages` ORDER BY `id` ASC");
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
        $userPersonId = !empty($user['person_id']) ? (int)$user['person_id'] : $this->resolveZhandosPersonId();
        if ($user && empty($user['person_id']) && $userPersonId) {
            $this->saveUserPersonId((int)$user['id'], $userPersonId);
        }

        // Защита закрытых контактов
        $isAdmin = Auth::isAdmin();
        foreach ($persons as &$p) {
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
            $fullName = trim(($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? ''));

            // Фильтр по поисковому запросу
            if ($q !== '') {
                $match = mb_stripos($fullName, $q) !== false
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
            $deathDisp = !empty($p['death_date']) ? date('d.m.Y', strtotime($p['death_date'])) : ($p['death_year'] ?: '†');

            $yearsDisplay = '';
            if ($isDeceased) {
                $yearsDisplay = "{$birthDisp} — {$deathDisp} ✨";
            } else {
                $yearsDisplay = $birthDisp;
            }

            $filtered[] = [
                'id' => $pId,
                'first_name' => $p['first_name'],
                'last_name' => $p['last_name'],
                'maiden_name' => $p['maiden_name'] ?? null,
                'middle_name' => $p['middle_name'] ?? null,
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
                WHERE (m.husband_id = ? OR m.wife_id = ?)
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

            Response::json([
                'status' => 'success',
                'person' => $person,
                'father' => $father,
                'mother' => $mother,
                'spouses' => $spouses,
                'children' => $children,
                'audit_logs' => $auditLogs,
                'can_edit' => Auth::canEdit(),
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Сохранение изменений персоны (Реальное редактирование в БД с фиксацией в audit_logs)
     */
    public function apiPersonUpdate(Request $request): void
    {
        if (!Auth::canEdit()) {
            Response::json(['error' => 'Өңдеуге рұқсат жоқ / Нет прав на редактирование (только просмотр)'], 403);
            return;
        }

        $id = (int)$request->get('id', 0);
        if (!$id) {
            Response::json(['error' => 'id is required'], 400);
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

            $clanRu = trim((string)$request->get('clan_ru', '')) ?: null;
            $clanId = (int)$request->get('clan_id', 0) ?: null;
            $biography = trim((string)$request->get('biography', '')) ?: null;

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
        if (!Auth::canEdit()) {
            Response::json(['error' => 'Өңдеуге рұқсат жоқ / Нет прав на редактирование'], 403);
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

            if (!$target) {
                Response::json(['error' => 'Мақсатты тұлға табылмады / Целевая персона не найдена'], 404);
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
                        $spStmt = $db->prepare("SELECT wife_id FROM marriages WHERE husband_id = ? AND is_current = 1 LIMIT 1");
                        $spStmt->execute([$targetId]);
                        $motherId = $spStmt->fetchColumn() ?: null;
                    } else {
                        $motherId = $targetId;
                        $spStmt = $db->prepare("SELECT husband_id FROM marriages WHERE wife_id = ? AND is_current = 1 LIMIT 1");
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
     * API: Мягкое удаление персоны (Soft-delete, только Администратор)
     */
    public function apiPersonDelete(Request $request): void
    {
        if (!Auth::isAdmin()) {
            Response::json(['error' => 'Жою тек әкімшіге рұқсат етілген / Удаление разрешено только администратору'], 403);
            return;
        }

        $id = (int)$request->get('id', 0);
        if (!$id) {
            Response::json(['error' => 'id is required'], 400);
            return;
        }

        try {
            $db = Database::getConnection();
            $stmtOld = $db->prepare("SELECT * FROM persons WHERE id = ?");
            $stmtOld->execute([$id]);
            $oldPerson = $stmtOld->fetch(PDO::FETCH_ASSOC);

            if (!$oldPerson) {
                Response::json(['error' => 'Персона не найдена'], 404);
                return;
            }

            $stmt = $db->prepare("UPDATE `persons` SET `is_deleted` = 1, `updated_at` = NOW() WHERE `id` = ?");
            $stmt->execute([$id]);

            $user = Auth::user();
            $this->recordAuditLog(
                'DELETE',
                'persons',
                $id,
                "Тұлға жойылды (soft-delete): {$oldPerson['first_name']} {$oldPerson['last_name']}",
                $oldPerson,
                ['is_deleted' => 1],
                $user
            );

            Response::json([
                'status' => 'success',
                'message' => 'Тұлға сәтті жойылды / Персона успешно удалена'
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Редактирование брака / развода
     */
    public function apiMarriageUpdate(Request $request): void
    {
        if (!Auth::canEdit()) {
            Response::json(['error' => 'Өңдеуге рұқсат жоқ / Нет прав'], 403);
            return;
        }

        $id = (int)$request->get('id', 0);
        if (!$id) {
            Response::json(['error' => 'id is required'], 400);
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

        $personId = (int)$request->get('person_id', 0);
        if (!$personId) {
            Response::json(['error' => 'person_id is required'], 400);
            return;
        }

        try {
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

        foreach ($allData as $p) {
            $fullName = trim(($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? ''));
            if (mb_stripos($fullName, $q) !== false || mb_stripos($p['clan_ru'] ?? '', $q) !== false) {
                // Живые — только год рождения! Ушедшие — годы жизни
                $years = $p['is_deceased'] 
                    ? (($p['birth_year'] ?: '—') . ' — ' . ($p['death_year'] ?: '†'))
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
}
