<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use PDO;

class FamilyController
{
    /**
     * Раздел «Отбасылар / Семьи» (все 71 союз)
     */
    public function index(Request $request): void
    {
        $user = Auth::user();
        $userPersonId = !empty($user['person_id']) ? (int)$user['person_id'] : 6;
        $db = Database::getConnection();

        $q = mb_strtolower(trim((string)$request->get('q', '')));
        $statusFilter = trim((string)$request->get('status', '')); // 'married' | 'divorced'

        // 1. Получаем все персоны в ассоциативный массив
        $personsById = [];
        $pStmt = $db->query("SELECT id, first_name, last_name, maiden_name, middle_name, gender, birth_year, is_deceased, clan_ru, avatar_path FROM persons WHERE is_deleted = 0");
        foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $personsById[$p['id']] = $p;
        }

        // 2. Получаем все браки
        $marriages = [];
        $stmtM = $db->query("SHOW TABLES LIKE 'marriages'");
        if ($stmtM->rowCount() > 0) {
            $mStmt = $db->query("SELECT * FROM marriages ORDER BY id ASC");
            $marriages = $mStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 3. Получаем всех детей для пар
        $childrenByCouple = [];
        $cStmt = $db->query("SELECT id, first_name, last_name, middle_name, gender, birth_year, father_id, mother_id FROM persons WHERE is_deleted = 0 AND father_id IS NOT NULL AND mother_id IS NOT NULL");
        foreach ($cStmt->fetchAll(PDO::FETCH_ASSOC) as $child) {
            $key = "{$child['father_id']}_{$child['mother_id']}";
            $childrenByCouple[$key][] = $child;
        }

        $families = [];
        $totalMarried = 0;
        $totalDivorced = 0;

        foreach ($marriages as $m) {
            $hId = (int)($m['husband_id'] ?? 0);
            $wId = (int)($m['wife_id'] ?? 0);

            $husband = $personsById[$hId] ?? null;
            $wife = $personsById[$wId] ?? null;

            $status = $m['status'] ?? ($m['is_current'] ? 'married' : 'divorced');
            if ($status === 'married') {
                $totalMarried++;
            } else {
                $totalDivorced++;
            }

            if ($statusFilter !== '' && $status !== $statusFilter) {
                continue;
            }

            $hName = $husband ? trim(($husband['last_name'] ?? '') . ' ' . $husband['first_name'] . ' ' . ($husband['middle_name'] ?? '')) : 'Белгісіз';
            $wName = $wife ? trim(($wife['last_name'] ?? '') . ' ' . $wife['first_name'] . ' ' . ($wife['middle_name'] ?? '')) : 'Белгісіз';

            if ($q !== '') {
                $match = mb_stripos($hName, $q) !== false 
                    || mb_stripos($wName, $q) !== false 
                    || mb_stripos($husband['clan_ru'] ?? '', $q) !== false 
                    || mb_stripos($m['marriage_place'] ?? '', $q) !== false;
                if (!$match) {
                    continue;
                }
            }

            $coupleKey = "{$hId}_{$wId}";
            $children = $childrenByCouple[$coupleKey] ?? [];

            $families[] = [
                'marriage' => $m,
                'husband'  => $husband,
                'wife'     => $wife,
                'h_name'   => $hName,
                'w_name'   => $wName,
                'status'   => $status,
                'children' => $children,
            ];
        }

        Response::view('families/index', [
            'user'          => $user,
            'userPersonId'  => $userPersonId,
            'families'      => $families,
            'totalCount'    => count($families),
            'totalAll'      => count($marriages),
            'totalMarried'  => $totalMarried,
            'totalDivorced' => $totalDivorced,
            'filters'       => [
                'q'      => $q,
                'status' => $statusFilter,
            ],
            'canEdit'       => Auth::canEdit(),
            'isAdmin'       => Auth::isAdmin(),
        ]);
    }
}
