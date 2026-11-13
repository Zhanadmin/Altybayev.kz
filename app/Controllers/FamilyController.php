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

            // Сортировка детей внутри семьи по старшинству (по году рождения ascending)
            usort($children, function ($cA, $cB) {
                $yA = (int)($cA['birth_year'] ?? 0);
                $yB = (int)($cB['birth_year'] ?? 0);
                if ($yA && $yB && $yA !== $yB) {
                    return $yA <=> $yB;
                }
                return ((int)$cA['id']) <=> ((int)$cB['id']);
            });

            // Определение старшинства и поколения
            $hYear = (int)($husband['birth_year'] ?? 0);
            $wYear = (int)($wife['birth_year'] ?? 0);
            $seniorityYear = $hYear ?: ($wYear ?: 9999);

            $hDec = !empty($husband['is_deceased']);
            $wDec = !empty($wife['is_deceased']);
            $bothDec = $hDec && $wDec;

            // Определение поколения (0 = Ата-бабалар, 1 = 1-ұрпақ, 2 = 2-ұрпақ, 3 = 3-ұрпақ)
            if ($bothDec || ($seniorityYear > 0 && $seniorityYear < 1945)) {
                $genKey = 'ancestors';
            } elseif ($seniorityYear >= 1945 && $seniorityYear < 1975) {
                $genKey = 1;
            } elseif ($seniorityYear >= 1975 && $seniorityYear < 1996) {
                $genKey = 2;
            } elseif ($seniorityYear >= 1996 && $seniorityYear < 9999) {
                $genKey = 3;
            } else {
                $genKey = 2; // по умолчанию активное поколение
            }

            $families[] = [
                'marriage'       => $m,
                'husband'        => $husband,
                'wife'           => $wife,
                'h_name'         => $hName,
                'w_name'         => $wName,
                'status'         => $status,
                'children'       => $children,
                'seniority_year' => $seniorityYear,
                'generation_key' => $genKey,
            ];
        }

        // Группировка по поколениям
        $generationGroups = [
            'ancestors' => [
                'key'         => 'ancestors',
                'title_kk'    => 'Ата-бабалар',
                'title_ru'    => 'Предки',
                'badge'       => '🏛️',
                'families'    => [],
            ],
            1 => [
                'key'         => 1,
                'title_kk'    => '1-ұрпақ',
                'title_ru'    => '1-е поколение',
                'badge'       => '🌳',
                'families'    => [],
            ],
            2 => [
                'key'         => 2,
                'title_kk'    => '2-ұрпақ',
                'title_ru'    => '2-е поколение',
                'badge'       => '🌿',
                'families'    => [],
            ],
            3 => [
                'key'         => 3,
                'title_kk'    => '3-ұрпақ',
                'title_ru'    => '3-е поколение',
                'badge'       => '🌱',
                'families'    => [],
            ],
        ];

        foreach ($families as $f) {
            $gK = $f['generation_key'];
            if (isset($generationGroups[$gK])) {
                $generationGroups[$gK]['families'][] = $f;
            }
        }

        // Внутри каждого поколения сортируем семьи строго по старшинству (старшие сверху)
        foreach ($generationGroups as $gKey => &$group) {
            usort($group['families'], function ($fA, $fB) {
                $yA = (int)($fA['seniority_year'] ?? 9999);
                $yB = (int)($fB['seniority_year'] ?? 9999);
                if ($yA !== $yB) {
                    return $yA <=> $yB;
                }
                return ((int)$fA['marriage']['id']) <=> ((int)$fB['marriage']['id']);
            });
        }
        unset($group);

        Response::view('families/index', [
            'user'             => $user,
            'userPersonId'     => $userPersonId,
            'families'         => $families,
            'generationGroups' => $generationGroups,
            'totalCount'       => count($families),
            'totalAll'         => count($marriages),
            'totalMarried'     => $totalMarried,
            'totalDivorced'    => $totalDivorced,
            'filters'          => [
                'q'      => $q,
                'status' => $statusFilter,
            ],
            'canEdit'          => Auth::canEdit(),
            'isAdmin'          => Auth::isAdmin(),
        ]);
    }
}
