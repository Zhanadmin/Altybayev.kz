<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Services\KinshipService;
use App\Services\MediaService;
use PDO;

class PersonController
{
    private KinshipService $kinshipService;
    private MediaService $mediaService;

    public function __construct()
    {
        $this->kinshipService = new KinshipService();
        $this->mediaService = new MediaService();
    }

    /**
     * Список людей: раздел «Адамдар / Люди» (270 человек)
     */
    public function index(Request $request): void
    {
        $user = Auth::user();
        $userPersonId = !empty($user['person_id']) ? (int)$user['person_id'] : 6;
        $db = Database::getConnection();

        // 1. Получаем все роды для фильтра
        $clans = [];
        $stmtC = $db->query("SHOW TABLES LIKE 'clans'");
        if ($stmtC->rowCount() > 0) {
            $clans = $db->query("SELECT * FROM clans ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        }

        // 2. Получаем параметры фильтрации
        $q = mb_strtolower(trim((string)$request->get('q', '')));
        $clanId = (int)$request->get('clan_id', 0);
        $gender = trim((string)$request->get('gender', ''));
        $status = trim((string)$request->get('status', '')); // 'living' | 'deceased'
        $sort = trim((string)$request->get('sort', 'name_asc'));

        // 3. Запрос персон
        $sql = "SELECT * FROM `persons` WHERE `is_deleted` = 0";
        $params = [];

        if ($clanId > 0) {
            $sql .= " AND `clan_id` = ?";
            $params[] = $clanId;
        }

        if ($gender === 'M' || $gender === 'F') {
            $sql .= " AND `gender` = ?";
            $params[] = $gender;
        }

        if ($status === 'living') {
            $sql .= " AND `is_deceased` = 0";
        } elseif ($status === 'deceased') {
            $sql .= " AND `is_deceased` = 1";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $allPersons = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Получаем всех людей в ассоциативный массив для связей родителей
        $allPersonsById = [];
        $fullStmt = $db->query("SELECT * FROM persons WHERE is_deleted = 0");
        foreach ($fullStmt->fetchAll(PDO::FETCH_ASSOC) as $pRow) {
            $allPersonsById[$pRow['id']] = $pRow;
        }

        // Получаем карту браков
        $marriagesMap = [];
        $mRows = [];
        $stmtM = $db->query("SHOW TABLES LIKE 'marriages'");
        if ($stmtM->rowCount() > 0) {
            $mRows = $db->query("SELECT * FROM marriages ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($mRows as $m) {
                $h = (int)($m['husband_id'] ?? 0);
                $w = (int)($m['wife_id'] ?? 0);
                if ($h && $w) {
                    $marriagesMap[$h][] = [
                        'spouse_id' => $w,
                        'name'      => trim(($allPersonsById[$w]['last_name'] ?? '') . ' ' . ($allPersonsById[$w]['first_name'] ?? '')),
                        'status'    => $m['status'] ?? ($m['is_current'] ? 'married' : 'divorced'),
                        'years'     => trim(($m['start_date'] ?? $m['marriage_date'] ?? '') . ' ' . ($m['end_date'] ?? $m['divorce_date'] ?? '')),
                    ];
                    $marriagesMap[$w][] = [
                        'spouse_id' => $h,
                        'name'      => trim(($allPersonsById[$h]['last_name'] ?? '') . ' ' . ($allPersonsById[$h]['first_name'] ?? '')),
                        'status'    => $m['status'] ?? ($m['is_current'] ? 'married' : 'divorced'),
                        'years'     => trim(($m['start_date'] ?? $m['marriage_date'] ?? '') . ' ' . ($m['end_date'] ?? $m['divorce_date'] ?? '')),
                    ];
                }
            }
        }

        // Задаем предзагруженный датасет для быстрого расчета родства (O(1) на каждую персону)
        $this->kinshipService->setDataset($allPersonsById, $mRows);

        // Фильтрация по поисковой строке
        $filtered = [];
        foreach ($allPersons as $p) {
            $fullName = trim(($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? ''));
            if ($q !== '') {
                $match = mb_stripos($fullName, $q) !== false
                    || mb_stripos($p['clan_ru'] ?? '', $q) !== false
                    || mb_stripos($p['birth_place'] ?? '', $q) !== false
                    || mb_stripos($p['birth_year'] ?? '', $q) !== false
                    || mb_stripos($p['maiden_name'] ?? '', $q) !== false;
                if (!$match) {
                    continue;
                }
            }
            $p['full_name'] = $fullName;
            $p['father'] = !empty($p['father_id']) ? ($allPersonsById[$p['father_id']] ?? null) : null;
            $p['mother'] = !empty($p['mother_id']) ? ($allPersonsById[$p['mother_id']] ?? null) : null;
            $p['spouses'] = $marriagesMap[$p['id']] ?? [];

            // Родство к вошедшему
            $kinDesc = $this->kinshipService->getKinshipDescription($userPersonId, (int)$p['id']);
            $p['kinship'] = $kinDesc;

            $filtered[] = $p;
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

        // Общая статистика
        $totalPersons = count($allPersonsById);
        $totalLiving = 0;
        $totalDeceased = 0;
        foreach ($allPersons as $p) {
            if (!empty($p['is_deceased'])) {
                $totalDeceased++;
            } else {
                $totalLiving++;
            }
        }

        Response::view('persons/index', [
            'user'          => $user,
            'userPersonId'  => $userPersonId,
            'persons'       => $filtered,
            'totalCount'    => count($filtered),
            'totalAll'      => $totalPersons,
            'totalLiving'   => $totalLiving,
            'totalDeceased' => $totalDeceased,
            'clans'         => $clans,
            'filters'       => [
                'q'        => $q,
                'clan_id'  => $clanId,
                'gender'   => $gender,
                'status'   => $status,
                'sort'     => $sort,
            ],
            'canEdit'       => Auth::canEdit(),
            'isAdmin'       => Auth::isAdmin(),
        ]);
    }

    /**
     * Отдельная карточка человека: /person/{id}
     */
    public function show(Request $request, string|int $id): void
    {
        $id = (int)$id;
        if ($id <= 0) {
            Response::redirect('/persons');
        }

        $user = Auth::user();
        $userPersonId = !empty($user['person_id']) ? (int)$user['person_id'] : 6;
        $db = Database::getConnection();

        // 1. Получаем данные персоны
        $stmt = $db->prepare("SELECT * FROM `persons` WHERE `id` = ? AND `is_deleted` = 0");
        $stmt->execute([$id]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$person) {
            Response::redirect('/persons');
        }

        // 2. Родители
        $father = null;
        if (!empty($person['father_id'])) {
            $fStmt = $db->prepare("SELECT * FROM persons WHERE id = ? AND is_deleted = 0");
            $fStmt->execute([$person['father_id']]);
            $father = $fStmt->fetch(PDO::FETCH_ASSOC);
        }

        $mother = null;
        if (!empty($person['mother_id'])) {
            $mStmt = $db->prepare("SELECT * FROM persons WHERE id = ? AND is_deleted = 0");
            $mStmt->execute([$person['mother_id']]);
            $mother = $mStmt->fetch(PDO::FETCH_ASSOC);
        }

        // 3. Супруги
        $spStmt = $db->prepare("
            SELECT m.*, p.id as spouse_id, p.first_name, p.last_name, p.maiden_name, p.birth_year, p.birth_date, p.gender, p.clan_ru, p.avatar_path, p.is_deceased
            FROM marriages m
            JOIN persons p ON (p.id = IF(m.husband_id = ?, m.wife_id, m.husband_id))
            WHERE (m.husband_id = ? OR m.wife_id = ?) AND p.is_deleted = 0
        ");
        $spStmt->execute([$id, $id, $id]);
        $spouses = $spStmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Дети
        $cStmt = $db->prepare("
            SELECT * FROM persons 
            WHERE (father_id = ? OR mother_id = ?) AND is_deleted = 0 
            ORDER BY COALESCE(birth_year, 9999) ASC, id ASC
        ");
        $cStmt->execute([$id, $id]);
        $children = $cStmt->fetchAll(PDO::FETCH_ASSOC);

        // 5. Родные братья и сестры (общие родители)
        $siblings = [];
        if (!empty($person['father_id']) || !empty($person['mother_id'])) {
            $sSql = "SELECT * FROM persons WHERE id != ? AND is_deleted = 0 AND (";
            $sParams = [$id];
            $conds = [];
            if (!empty($person['father_id'])) {
                $conds[] = "father_id = ?";
                $sParams[] = $person['father_id'];
            }
            if (!empty($person['mother_id'])) {
                $conds[] = "mother_id = ?";
                $sParams[] = $person['mother_id'];
            }
            $sSql .= implode(" OR ", $conds) . ") ORDER BY COALESCE(birth_year, 9999) ASC";
            $sStmt = $db->prepare($sSql);
            $sStmt->execute($sParams);
            $siblings = $sStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 6. Родство к текущему пользователю
        $kinshipDesc = $this->kinshipService->getKinshipDescription($userPersonId, $id);

        // 7. Медиа (истории, аудиозаписи, фото)
        $mediaList = $this->mediaService->getPersonMedia($id);

        // 8. Аудит логи
        $auditLogs = [];
        $aStmtTable = $db->query("SHOW TABLES LIKE 'audit_logs'");
        if ($aStmtTable->rowCount() > 0) {
            $aStmt = $db->prepare("SELECT * FROM audit_logs WHERE entity_table = 'persons' AND entity_id = ? ORDER BY id DESC LIMIT 15");
            $aStmt->execute([$id]);
            $auditLogs = $aStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        Response::view('persons/show', [
            'user'         => $user,
            'userPersonId' => $userPersonId,
            'person'       => $person,
            'father'       => $father,
            'mother'       => $mother,
            'spouses'      => $spouses,
            'children'     => $children,
            'siblings'     => $siblings,
            'kinship'      => $kinshipDesc,
            'mediaList'    => $mediaList,
            'auditLogs'    => $auditLogs,
            'canEdit'      => Auth::canEdit(),
            'isAdmin'      => Auth::isAdmin(),
        ]);
    }
}
