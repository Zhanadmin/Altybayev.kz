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
        $pStmt = $db->query("SELECT id, first_name, last_name, maiden_name, middle_name, gender, father_id, mother_id, birth_year, birth_date, death_year, death_date, is_deceased, clan_ru, avatar_path FROM persons WHERE is_deleted = 0");
        foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $personsById[$p['id']] = $p;
        }

        // 2. Получаем все браки
        $marriages = [];
        $stmtM = $db->query("SHOW TABLES LIKE 'marriages'");
        if ($stmtM->rowCount() > 0) {
            $mStmt = $db->query("SELECT m.* FROM marriages m JOIN persons ph ON ph.id = m.husband_id AND ph.is_deleted = 0 JOIN persons pw ON pw.id = m.wife_id AND pw.is_deleted = 0 ORDER BY m.id ASC"); // ALT-018
            $marriages = $mStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 3. Получаем всех детей для пар
        $childrenByCouple = [];
        $cStmt = $db->query("SELECT id, first_name, last_name, middle_name, gender, birth_year, birth_date, death_year, death_date, is_deceased, father_id, mother_id FROM persons WHERE is_deleted = 0 AND father_id IS NOT NULL AND mother_id IS NOT NULL");
        foreach ($cStmt->fetchAll(PDO::FETCH_ASSOC) as $child) {
            $key = "{$child['father_id']}_{$child['mother_id']}";
            $childrenByCouple[$key][] = $child;
        }

        // TASK-013: замужняя дочь «переходит» в семью мужа — в семье родителей она только след «ұзатылған»
        $uzatylganByWife = [];
        foreach ($marriages as $m) {
            $wId = (int)($m['wife_id'] ?? 0);
            $hId = (int)($m['husband_id'] ?? 0);
            $mStatus = $m['status'] ?? (!empty($m['is_current']) ? 'married' : 'divorced');
            if ($wId <= 0 || $hId <= 0 || $mStatus !== 'married' || !empty($m['divorce_date']) || !empty($m['end_date']) || !isset($personsById[$hId])) {
                continue;
            }
            $hp = $personsById[$hId];
            $uzatylganByWife[$wId] = [
                'husband_id'  => $hId,
                'marriage_id' => (int)$m['id'],
                'family'      => trim((string)($hp['last_name'] ?? '')) !== ''
                    ? trim((string)$hp['last_name'])
                    : trim((string)($hp['clan_ru'] ?? '')),
            ];
        }

        // ALT-084: поколения от живых (общий расчёт с разделом «Люди»)
        $spouseLists = [];
        foreach ($marriages as $mm) {
            $mh = (int)($mm['husband_id'] ?? 0);
            $mw = (int)($mm['wife_id'] ?? 0);
            if ($mh && $mw) {
                $spouseLists[$mh][] = ['spouse_id' => $mw];
                $spouseLists[$mw][] = ['spouse_id' => $mh];
            }
        }
        $genMap = \App\Services\GenerationService::generationMap($personsById, $spouseLists, 6);

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

            // TASK-028: отчество автоматически по отцу (ручное приоритетнее)
            // ALT-022: en/es — без отчества в строке имени
            $patrLang = in_array(\App\Core\I18n::lang(), ['ru', 'en', 'es'], true) ? \App\Core\I18n::lang() : 'kk';
            $hPatr = $husband ? \App\Services\PatronymicService::resolve($husband, !empty($husband['father_id']) ? ($personsById[$husband['father_id']] ?? null) : null, $patrLang) : '';
            $wPatr = $wife ? \App\Services\PatronymicService::resolve($wife, !empty($wife['father_id']) ? ($personsById[$wife['father_id']] ?? null) : null, $patrLang) : '';
            $hName = $husband ? trim(($husband['last_name'] ?? '') . ' ' . $husband['first_name'] . ' ' . $hPatr) : 'Белгісіз';
            $wName = $wife ? trim(($wife['last_name'] ?? '') . ' ' . $wife['first_name'] . ' ' . $wPatr) : 'Белгісіз';

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
                // Старшие первыми независимо от пола; без даты — в конец
                $yA = (int)($cA['birth_year'] ?? 0) ?: (int)substr((string)($cA['birth_date'] ?? ''), 0, 4);
                $yB = (int)($cB['birth_year'] ?? 0) ?: (int)substr((string)($cB['birth_date'] ?? ''), 0, 4);
                if (!$yA !== !$yB) {
                    return $yA ? -1 : 1;
                }
                if ($yA !== $yB) {
                    return $yA <=> $yB;
                }
                $dA = (string)($cA['birth_date'] ?? '');
                $dB = (string)($cB['birth_date'] ?? '');
                if ($dA !== '' && $dB !== '' && $dA !== $dB) {
                    return strcmp($dA, $dB);
                }
                return ((int)$cA['id']) <=> ((int)$cB['id']);
            });
            foreach ($children as &$child) {
                $child['uzatylgan'] = ($child['gender'] ?? '') === 'F'
                    ? ($uzatylganByWife[(int)$child['id']] ?? null)
                    : null;
            }
            unset($child);

            // TASK-020: браки детей (по старшинству детей) — для кнопки «Семьи детей»
            $childFams = [];
            foreach ($children as $child) {
                $cid = (int)$child['id'];
                foreach ($marriages as $cm) {
                    if ((int)$cm['id'] === (int)$m['id']) {
                        continue;
                    }
                    if ((int)($cm['husband_id'] ?? 0) === $cid || (int)($cm['wife_id'] ?? 0) === $cid) {
                        $childFams[] = (int)$cm['id'];
                    }
                }
            }
            $childFams = array_values(array_unique($childFams));

            // Определение старшинства и поколения
            $hYear = (int)($husband['birth_year'] ?? 0);
            $wYear = (int)($wife['birth_year'] ?? 0);
            $seniorityYear = $hYear ?: ($wYear ?: 9999);

            $hDec = !empty($husband['is_deceased']);
            $wDec = !empty($wife['is_deceased']);
            $bothDec = $hDec && $wDec;

            // Определение поколения (0 = Ата-бабалар, 1 = 1-ұрпақ, 2 = 2-ұрпақ, 3 = 3-ұрпақ)
            if ($genMap && isset($genMap[$hId])) {
                $genKey = $genMap[$hId];
            } elseif ($genMap && isset($genMap[$wId])) {
                $genKey = $genMap[$wId];
            } elseif ($genMap) {
                $genKey = 'ancestors';
            } elseif ($bothDec || ($seniorityYear > 0 && $seniorityYear < 1945)) {
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
                'child_fams'     => $childFams,
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
            if (!isset($generationGroups[$gK])) {
                $generationGroups[$gK] = \App\Services\GenerationService::group($gK) + ['families' => []];
            }
            $generationGroups[$gK]['families'][] = $f;
        }
        uksort($generationGroups, function ($a, $b) {
            return ($a === 'ancestors' ? 0 : (int)$a) <=> ($b === 'ancestors' ? 0 : (int)$b);
        });

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

        // TASK-018: число фото/видео в альбомах семьи (если миграция альбомов уже применена)
        $albumCounts = [];
        try {
            $albumSvc = new \App\Services\AlbumService($db);
            if ($albumSvc->isReady()) {
                $albumCounts = $albumSvc->mediaCountByFamily();
            }
        } catch (\Throwable $e) {
            $albumCounts = [];
        }

        Response::view('families/index', [
            'albumCounts'      => $albumCounts,
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
