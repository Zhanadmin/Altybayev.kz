<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Сервис вычисления строго геометрической раскладки генеалогического древа
 * 
 * Ключевые требования (TASK-003, Замечания Жандоса 13.11):
 * 1. Семейная пара (супруги) — неделимый неделимый блок (Family Unit):
 *    супруги ВСЕГДА находятся рядом бок о бок (зазор GAP_SPOUSE = 35px, на одном уровне Y)
 *    при ЛЮБОМ фокусе (стартовом, при клике, при раскрытии веток) и в полном режиме.
 * 2. Дети центрированы строго под родительской парой с ортогональной шиной связей.
 * 3. Поддержка нескольких браков (все супруги в ряд с датами и пометками развода).
 * 4. Полное отсутствие наложений и пересечений (СТРОГО 0 коллизий прямоугольников карточек).
 * 5. Режим окрестности (focal mode) и полный режим (full mode для всех 270 персон).
 */
class TreeLayoutService
{
    public const CARD_WIDTH = 260;
    public const CARD_HEIGHT = 120;
    public const GAP_SPOUSE = 35;
    public const GAP_SIBLING = 55;
    public const ROW_HEIGHT = 220; // 120px карточка + 100px канал для шин связей

    /**
     * Основной метод вычисления координат
     *
     * @param array $persons Список персон из БД
     * @param array $marriages Список браков
     * @param int|null $focalPersonId ID центральной персоны (Жандос)
     * @param string $mode 'focal' (окрестность) или 'full' (все персоны)
     * @param array $expandedIds Список ID развернутых веток
     * @return array Структура с nodes, edges, bounds, stats
     */
    public function calculateLayout(
        array $persons,
        array $marriages,
        ?int $focalPersonId = null,
        string $mode = 'focal',
        array $expandedIds = []
    ): array {
        if (empty($persons)) {
            return [
                'nodes' => [],
                'edges' => ['marriages' => [], 'parent_child' => []],
                'bounds' => ['minX' => 0, 'maxX' => 0, 'minY' => 0, 'maxY' => 0, 'width' => 0, 'height' => 0],
                'focal_id' => null,
                'stats' => ['visible_count' => 0, 'total_count' => 0, 'intersections_count' => 0],
            ];
        }

        // 1. Индексация данных
        $personsById = [];
        foreach ($persons as $p) {
            $pId = (int)$p['id'];
            $personsById[$pId] = $p;
        }

        // Определяем focalPersonId если не передан
        if (!$focalPersonId || !isset($personsById[$focalPersonId])) {
            $focalPersonId = $this->findDefaultFocalId($persons);
        }

        // Индексация связей
        $spousesMap = []; // person_id => [spouse_id, ...]
        $marriageDetails = []; // "p1_p2" => marriage row
        $childrenMap = []; // parent_id => [child_id, ...]
        $parentsMap = []; // child_id => ['father' => id, 'mother' => id]

        foreach ($personsById as $id => $p) {
            $spousesMap[$id] = [];
            $childrenMap[$id] = [];
            $parentsMap[$id] = ['father' => null, 'mother' => null];
        }

        foreach ($personsById as $id => $p) {
            $fId = !empty($p['father_id']) ? (int)$p['father_id'] : null;
            $mId = !empty($p['mother_id']) ? (int)$p['mother_id'] : null;

            if ($fId && isset($personsById[$fId])) {
                $parentsMap[$id]['father'] = $fId;
                if (!in_array($id, $childrenMap[$fId], true)) {
                    $childrenMap[$fId][] = $id;
                }
            }
            if ($mId && isset($personsById[$mId])) {
                $parentsMap[$id]['mother'] = $mId;
                if (!in_array($id, $childrenMap[$mId], true)) {
                    $childrenMap[$mId][] = $id;
                }
            }
            if ($fId && $mId && isset($personsById[$fId]) && isset($personsById[$mId])) {
                if (!in_array($mId, $spousesMap[$fId], true)) {
                    $spousesMap[$fId][] = $mId;
                }
                if (!in_array($fId, $spousesMap[$mId], true)) {
                    $spousesMap[$mId][] = $fId;
                }
            }
        }

        foreach ($marriages as $m) {
            $hId = !empty($m['husband_id']) ? (int)$m['husband_id'] : null;
            $wId = !empty($m['wife_id']) ? (int)$m['wife_id'] : null;
            if ($hId && $wId && isset($personsById[$hId]) && isset($personsById[$wId])) {
                if (!in_array($wId, $spousesMap[$hId], true)) {
                    $spousesMap[$hId][] = $wId;
                }
                if (!in_array($hId, $spousesMap[$wId], true)) {
                    $spousesMap[$wId][] = $hId;
                }
                $key1 = "{$hId}_{$wId}";
                $key2 = "{$wId}_{$hId}";
                $marriageDetails[$key1] = $m;
                $marriageDetails[$key2] = $m;
            }
        }

        // 2. Отбор видимых персон
        $visibleIds = [];
        if ($mode === 'focal') {
            $visibleIds = $this->collectFocalNeighborhood($focalPersonId, $personsById, $spousesMap, $childrenMap, $parentsMap, $expandedIds);
        } else {
            $visibleIds = array_keys($personsById);
        }

        // 3. Вычисление поколения (Generation Level)
        $generations = $this->assignGenerations($visibleIds, $focalPersonId, $parentsMap, $childrenMap, $spousesMap);
        $minGen = !empty($generations) ? min($generations) : 0;
        $maxGen = !empty($generations) ? max($generations) : 0;
        $normalizedGen = [];
        foreach ($visibleIds as $vId) {
            $normalizedGen[$vId] = ($generations[$vId] ?? 0) - $minGen;
        }

        // 4. Построение неделимых семейных блоков (Units) и горизонтальное позиционирование
        $positions = $this->layoutUnitsWithSolidCouples(
            $visibleIds,
            $normalizedGen,
            $spousesMap,
            $childrenMap,
            $parentsMap,
            $focalPersonId,
            $personsById
        );

        // 5. Формирование узлов карточек
        $nodes = [];
        foreach ($visibleIds as $id) {
            $p = $personsById[$id];
            $pos = $positions[$id];

            $hasHiddenParents = false;
            $hiddenParentsCount = 0;
            $fId = $parentsMap[$id]['father'];
            $mId = $parentsMap[$id]['mother'];
            if (($fId && !in_array($fId, $visibleIds, true)) || ($mId && !in_array($mId, $visibleIds, true))) {
                $hasHiddenParents = true;
                if ($fId && !in_array($fId, $visibleIds, true)) $hiddenParentsCount++;
                if ($mId && !in_array($mId, $visibleIds, true)) $hiddenParentsCount++;
            }

            $hasHiddenChildren = false;
            $hiddenChildrenCount = 0;
            foreach ($childrenMap[$id] as $cId) {
                if (!in_array($cId, $visibleIds, true)) {
                    $hasHiddenChildren = true;
                    $hiddenChildrenCount++;
                }
            }

            $isFocal = ($id === $focalPersonId);
            $isSpouseOfFocal = in_array($id, $spousesMap[$focalPersonId] ?? [], true);

            $nodes[$id] = [
                'id' => $id,
                'gedcom_id' => $p['gedcom_id'] ?? null,
                'first_name' => $p['first_name'] ?? '',
                'last_name' => $p['last_name'] ?? '',
                'maiden_name' => $p['maiden_name'] ?? null,
                'middle_name' => $p['middle_name'] ?? null,
                'gender' => $p['gender'] ?? 'unknown',
                'birth_year' => $p['birth_year'] ?? null,
                'death_year' => $p['death_year'] ?? null,
                'is_deceased' => (int)($p['is_deceased'] ?? 0),
                'clan_ru' => $p['clan_ru'] ?? 'белгісіз',
                'clan_id' => $p['clan_id'] ?? null,
                'x' => $pos['x'],
                'y' => $pos['y'],
                'width' => self::CARD_WIDTH,
                'height' => self::CARD_HEIGHT,
                'generation' => $normalizedGen[$id],
                'is_focal' => $isFocal,
                'is_spouse' => $isSpouseOfFocal,
                'has_hidden_parents' => $hasHiddenParents,
                'hidden_parents_count' => $hiddenParentsCount,
                'has_hidden_children' => $hasHiddenChildren,
                'hidden_children_count' => $hiddenChildrenCount,
            ];
        }

        // 6. Построение линий связей (ортогональные шины связей + бейджи брака)
        $edges = $this->buildEdges($nodes, $visibleIds, $spousesMap, $childrenMap, $parentsMap, $marriageDetails);

        // 7. Расчет границ и верификация непересечения
        $bounds = $this->calculateBounds($nodes);
        $intersections = $this->countIntersections($nodes);

        return [
            'mode' => $mode,
            'focal_id' => $focalPersonId,
            'nodes' => array_values($nodes),
            'nodes_by_id' => $nodes,
            'edges' => $edges,
            'bounds' => $bounds,
            'stats' => [
                'visible_count' => count($nodes),
                'total_count' => count($personsById),
                'generations_count' => ($maxGen - $minGen + 1),
                'intersections_count' => $intersections,
            ]
        ];
    }

    /**
     * Поиск фокальной персоны по умолчанию (Жандос)
     */
    protected function findDefaultFocalId(array $persons): int
    {
        foreach ($persons as $p) {
            if (($p['gedcom_id'] ?? '') === '@I6@' || mb_stripos($p['first_name'] ?? '', 'Жандос') !== false) {
                return (int)$p['id'];
            }
        }
        return (int)($persons[0]['id'] ?? 1);
    }

    /**
     * Сбор окрестности фокальной персоны
     * Включает фокальное лицо, супругов, детей, родителей, братьев/сестер со всеми их супругами
     */
    protected function collectFocalNeighborhood(
        int $focalId,
        array $personsById,
        array $spousesMap,
        array $childrenMap,
        array $parentsMap,
        array $expandedIds = []
    ): array {
        $visible = [$focalId => true];

        // 1. Все супруги фокального лица
        foreach ($spousesMap[$focalId] ?? [] as $spId) {
            $visible[$spId] = true;
        }

        // 2. Дети фокального лица и дети супругов
        $allChildrenOfFocal = $childrenMap[$focalId] ?? [];
        foreach ($spousesMap[$focalId] ?? [] as $spId) {
            foreach ($childrenMap[$spId] ?? [] as $cspId) {
                if (!in_array($cspId, $allChildrenOfFocal, true)) {
                    $allChildrenOfFocal[] = $cspId;
                }
            }
        }

        foreach ($allChildrenOfFocal as $cId) {
            $visible[$cId] = true;
            // Супруги детей
            foreach ($spousesMap[$cId] ?? [] as $cspId) {
                $visible[$cspId] = true;
            }
            // Внуки
            foreach ($childrenMap[$cId] ?? [] as $gcId) {
                $visible[$gcId] = true;
                foreach ($spousesMap[$gcId] ?? [] as $gcspId) {
                    $visible[$gcspId] = true;
                }
            }
        }

        // 3. Родители фокального лица (отец, мать)
        $fatherId = $parentsMap[$focalId]['father'] ?? null;
        $motherId = $parentsMap[$focalId]['mother'] ?? null;

        if ($fatherId && isset($personsById[$fatherId])) {
            $visible[$fatherId] = true;
        }
        if ($motherId && isset($personsById[$motherId])) {
            $visible[$motherId] = true;
        }

        // 4. Братья и сестры фокального лица и их семьи
        $parentIds = array_filter([$fatherId, $motherId]);
        foreach ($parentIds as $pId) {
            foreach ($childrenMap[$pId] ?? [] as $sibId) {
                $visible[$sibId] = true;
                // Супруги братьев/сестер (жеңге, жезде)
                foreach ($spousesMap[$sibId] ?? [] as $sspId) {
                    $visible[$sspId] = true;
                }
                // Племянники
                foreach ($childrenMap[$sibId] ?? [] as $nephewId) {
                    $visible[$nephewId] = true;
                }
            }
        }

        // 5. Дедушки и бабушки фокального лица (до 3 поколений предков вверх)
        $ancestorQueue = array_filter([$fatherId, $motherId]);
        $ancestorDepth = 0;
        while (!empty($ancestorQueue) && $ancestorDepth < 2) {
            $nextQueue = [];
            foreach ($ancestorQueue as $aId) {
                $gfId = $parentsMap[$aId]['father'] ?? null;
                $gmId = $parentsMap[$aId]['mother'] ?? null;
                if ($gfId && isset($personsById[$gfId])) {
                    $visible[$gfId] = true;
                    $nextQueue[] = $gfId;
                }
                if ($gmId && isset($personsById[$gmId])) {
                    $visible[$gmId] = true;
                    $nextQueue[] = $gmId;
                }
            }
            $ancestorQueue = $nextQueue;
            $ancestorDepth++;
        }

        // 6. Дополнительно раскрытые ветки (expandedIds)
        foreach ($expandedIds as $expId) {
            $expId = (int)$expId;
            if (!isset($personsById[$expId])) continue;

            $visible[$expId] = true;
            $efId = $parentsMap[$expId]['father'] ?? null;
            $emId = $parentsMap[$expId]['mother'] ?? null;
            if ($efId && isset($personsById[$efId])) $visible[$efId] = true;
            if ($emId && isset($personsById[$emId])) $visible[$emId] = true;

            foreach ($spousesMap[$expId] ?? [] as $sId) $visible[$sId] = true;
            foreach ($childrenMap[$expId] ?? [] as $cId) $visible[$cId] = true;
        }

        return array_keys($visible);
    }

    /**
     * Назначение поколений
     */
    protected function assignGenerations(
        array $visibleIds,
        int $focalId,
        array $parentsMap,
        array $childrenMap,
        array $spousesMap
    ): array {
        $generations = [];
        $generations[$focalId] = 0;

        $queue = [$focalId];
        $visited = [$focalId => true];

        while (!empty($queue)) {
            $currId = array_shift($queue);
            $currGen = $generations[$currId];

            // 1. Супруги строго в том же поколении
            foreach ($spousesMap[$currId] ?? [] as $spId) {
                if (in_array($spId, $visibleIds, true) && !isset($generations[$spId])) {
                    $generations[$spId] = $currGen;
                    $queue[] = $spId;
                }
            }

            // 2. Родители на 1 уровень выше
            $fId = $parentsMap[$currId]['father'] ?? null;
            $mId = $parentsMap[$currId]['mother'] ?? null;
            if ($fId && in_array($fId, $visibleIds, true) && !isset($generations[$fId])) {
                $generations[$fId] = $currGen - 1;
                $queue[] = $fId;
            }
            if ($mId && in_array($mId, $visibleIds, true) && !isset($generations[$mId])) {
                $generations[$mId] = $currGen - 1;
                $queue[] = $mId;
            }

            // 3. Дети на 1 уровень ниже
            foreach ($childrenMap[$currId] ?? [] as $cId) {
                if (in_array($cId, $visibleIds, true) && !isset($generations[$cId])) {
                    $generations[$cId] = $currGen + 1;
                    $queue[] = $cId;
                }
            }

            // 4. Братья и сестры в том же поколении
            if ($fId || $mId) {
                $parents = array_filter([$fId, $mId]);
                foreach ($parents as $pId) {
                    foreach ($childrenMap[$pId] ?? [] as $sibId) {
                        if (in_array($sibId, $visibleIds, true) && !isset($generations[$sibId])) {
                            $generations[$sibId] = $currGen;
                            $queue[] = $sibId;
                        }
                    }
                }
            }
        }

        foreach ($visibleIds as $id) {
            if (!isset($generations[$id])) {
                $generations[$id] = 0;
            }
        }

        return $generations;
    }

    /**
     * Построение неделимых блоков супругов (Family Units) и их строгая раскладка
     * Супруги ВСЕГДА идут вместе: spouseX = husbandX + CARD_WIDTH + GAP_SPOUSE!
     */
    protected function layoutUnitsWithSolidCouples(
        array $visibleIds,
        array $normalizedGen,
        array $spousesMap,
        array $childrenMap,
        array $parentsMap,
        int $focalId,
        array $personsById
    ): array {
        // 1. Группировка людей по поколениям
        $byGen = [];
        foreach ($visibleIds as $id) {
            $g = $normalizedGen[$id];
            $byGen[$g][] = $id;
        }
        ksort($byGen);

        // 2. Формирование неделимых семейных юнитов (Units) для каждого поколения
        // Unit: [ 'members' => [id1, id2, ...], 'width' => ..., 'x' => ..., 'y' => ..., 'children' => [...] ]
        $unitsByGen = [];
        $personToUnitMap = []; // person_id => unit_id
        $unitCounter = 1;
        $unitsById = [];

        foreach ($byGen as $g => $members) {
            $processed = [];
            $unitsByGen[$g] = [];

            // Вначале гарантируем порядок: фокальное лицо идет первым в своем поколении
            usort($members, function ($a, $b) use ($focalId) {
                if ($a === $focalId) return -1;
                if ($b === $focalId) return 1;
                return $a <=> $b;
            });

            foreach ($members as $mId) {
                if (isset($processed[$mId])) continue;

                // Находим всех видимых супругов в этом же поколении
                $spousesInGen = [];
                foreach ($spousesMap[$mId] ?? [] as $spId) {
                    if (in_array($spId, $members, true) && !isset($processed[$spId])) {
                        $spousesInGen[] = $spId;
                    }
                }

                $p = $personsById[$mId];
                $unitMembers = [];

                if (empty($spousesInGen)) {
                    // Одиночный узел
                    $unitMembers = [$mId];
                    $processed[$mId] = true;
                } else {
                    // Семейный блок: муж слева, жена справа (или носитель рода первым)
                    if ($p['gender'] === 'M') {
                        $unitMembers = array_merge([$mId], $spousesInGen);
                    } else {
                        // Если первая женщина, а муж среди супругов — ставим мужа первым
                        $husbandFirst = null;
                        $otherSpouses = [];
                        foreach ($spousesInGen as $sId) {
                            if (($personsById[$sId]['gender'] ?? 'M') === 'M' && $husbandFirst === null) {
                                $husbandFirst = $sId;
                            } else {
                                $otherSpouses[] = $sId;
                            }
                        }
                        if ($husbandFirst !== null) {
                            $unitMembers = array_merge([$husbandFirst, $mId], $otherSpouses);
                        } else {
                            $unitMembers = array_merge([$mId], $spousesInGen);
                        }
                    }

                    $processed[$mId] = true;
                    foreach ($spousesInGen as $spId) {
                        $processed[$spId] = true;
                    }
                }

                $memberCount = count($unitMembers);
                $unitWidth = $memberCount * self::CARD_WIDTH + ($memberCount - 1) * self::GAP_SPOUSE;

                $uId = $unitCounter++;
                $unitObj = [
                    'id' => $uId,
                    'generation' => $g,
                    'members' => $unitMembers,
                    'width' => $unitWidth,
                    'x' => 0,
                    'y' => 200 + $g * self::ROW_HEIGHT,
                    'children_units' => [],
                ];

                $unitsById[$uId] = $unitObj;
                $unitsByGen[$g][] = $uId;

                foreach ($unitMembers as $memId) {
                    $personToUnitMap[$memId] = $uId;
                }
            }
        }

        // 3. Первичная раскладка юнитов слева направо в каждом поколении
        $startX = 1000;
        foreach ($unitsByGen as $g => $unitIds) {
            $currX = $startX;
            foreach ($unitIds as $uId) {
                $unitsById[$uId]['x'] = $currX;
                $currX += $unitsById[$uId]['width'] + self::GAP_SIBLING;
            }
        }

        // 4. Связывание родительских юнитов с детскими юнитами
        foreach ($unitsById as $uId => &$u) {
            $allChildrenOfUnit = [];
            foreach ($u['members'] as $memId) {
                foreach ($childrenMap[$memId] ?? [] as $cId) {
                    if (isset($personToUnitMap[$cId])) {
                        $childUnitId = $personToUnitMap[$cId];
                        if (!in_array($childUnitId, $allChildrenOfUnit, true) && $childUnitId !== $uId) {
                            $allChildrenOfUnit[] = $childUnitId;
                        }
                    }
                }
            }
            $u['children_units'] = $allChildrenOfUnit;
        }
        unset($u);

        // 5. Центрирование детских юнитов строго под родительскими юнитами
        // Проходим поколения сверху вниз
        foreach ($unitsByGen as $g => $unitIds) {
            foreach ($unitIds as $uId) {
                $parentUnit = $unitsById[$uId];
                $cUnits = $parentUnit['children_units'];
                if (empty($cUnits)) continue;

                $parentMidX = $parentUnit['x'] + $parentUnit['width'] / 2;

                $minChildX = min(array_map(fn($cuId) => $unitsById[$cuId]['x'], $cUnits));
                $maxChildX = max(array_map(fn($cuId) => $unitsById[$cuId]['x'] + $unitsById[$cuId]['width'], $cUnits));
                $childrenMidX = ($minChildX + $maxChildX) / 2;

                $delta = $parentMidX - $childrenMidX;
                if (abs($delta) > 10) {
                    foreach ($cUnits as $cuId) {
                        $unitsById[$cuId]['x'] += $delta;
                    }
                }
            }
        }

        // 6. Устранение коллизий между юнитами (Строгое соблюдение зазоров)
        foreach ($unitsByGen as $g => $unitIds) {
            // Сортировка юнитов в поколении по координате X
            usort($unitIds, fn($a, $b) => $unitsById[$a]['x'] <=> $unitsById[$b]['x']);

            for ($i = 1; $i < count($unitIds); $i++) {
                $prevUnit = &$unitsById[$unitIds[$i - 1]];
                $currUnit = &$unitsById[$unitIds[$i]];

                $prevRight = $prevUnit['x'] + $prevUnit['width'];
                $minRequiredX = $prevRight + self::GAP_SIBLING;

                if ($currUnit['x'] < $minRequiredX) {
                    $shift = $minRequiredX - $currUnit['x'];
                    for ($k = $i; $k < count($unitIds); $k++) {
                        $unitsById[$unitIds[$k]]['x'] += $shift;
                    }
                }
            }
        }

        // 7. Распаковка позиций карточек из монолитных юнитов
        // ГАРАНТИЯ: супруги находятся строго рядом с зазором GAP_SPOUSE (35px)!
        $positions = [];
        foreach ($unitsById as $u) {
            $memberX = $u['x'];
            $memberY = $u['y'];

            foreach ($u['members'] as $idx => $mId) {
                $positions[$mId] = [
                    'x' => $memberX,
                    'y' => $memberY,
                ];
                $memberX += self::CARD_WIDTH + self::GAP_SPOUSE;
            }
        }

        return $positions;
    }

    /**
     * Построение шин связей (Edges) для SVG
     */
    protected function buildEdges(
        array $nodes,
        array $visibleIds,
        array $spousesMap,
        array $childrenMap,
        array $parentsMap,
        array $marriageDetails
    ): array {
        $marriagesEdges = [];
        $parentChildEdges = [];
        $processedCouples = [];

        // 1. Некелік байланыстар (Супружеские связи)
        foreach ($visibleIds as $id) {
            foreach ($spousesMap[$id] ?? [] as $spId) {
                if (!isset($nodes[$spId])) continue;

                $coupleKey = min($id, $spId) . '_' . max($id, $spId);
                if (isset($processedCouples[$coupleKey])) continue;
                $processedCouples[$coupleKey] = true;

                $n1 = $nodes[$id];
                $n2 = $nodes[$spId];

                $leftNode = ($n1['x'] < $n2['x']) ? $n1 : $n2;
                $rightNode = ($n1['x'] < $n2['x']) ? $n2 : $n1;

                $x1 = $leftNode['x'] + $leftNode['width'];
                $y1 = $leftNode['y'] + $leftNode['height'] / 2;
                $x2 = $rightNode['x'];
                $y2 = $rightNode['y'] + $rightNode['height'] / 2;

                $midX = ($x1 + $x2) / 2;
                $midY = ($y1 + $y2) / 2;

                // Проверка статуса брака и развода
                $mDetail = $marriageDetails["{$leftNode['id']}_{$rightNode['id']}"] ?? null;
                $isDivorced = false;
                $marrYears = '';
                if ($mDetail) {
                    $status = $mDetail['status'] ?? 'married';
                    $divorceDate = $mDetail['divorce_date'] ?? $mDetail['end_date'] ?? null;
                    if ($status === 'divorced' || (!empty($divorceDate))) {
                        $isDivorced = true;
                    }
                    $sDate = $mDetail['start_date'] ?? $mDetail['marriage_date'] ?? '';
                    $eDate = $mDetail['end_date'] ?? $mDetail['divorce_date'] ?? '';
                    if ($sDate || $eDate) {
                        $marrYears = trim("{$sDate} — {$eDate}", ' —');
                    }
                }

                $marriagesEdges[] = [
                    'husband_id' => $leftNode['id'],
                    'wife_id' => $rightNode['id'],
                    'x1' => $x1,
                    'y1' => $y1,
                    'x2' => $x2,
                    'y2' => $y2,
                    'mid_x' => $midX,
                    'mid_y' => $midY,
                    'is_divorced' => $isDivorced,
                    'years' => $marrYears,
                ];
            }
        }

        // 2. Родительско-детские ортогональные шины связей
        $processedFamilies = [];
        foreach ($visibleIds as $id) {
            $children = array_filter($childrenMap[$id] ?? [], fn($cId) => isset($nodes[$cId]));
            if (empty($children)) continue;

            $spouseId = null;
            foreach ($spousesMap[$id] ?? [] as $spId) {
                if (isset($nodes[$spId])) {
                    $spouseId = $spId;
                    break;
                }
            }

            $famKey = $spouseId ? min($id, $spouseId) . '_' . max($id, $spouseId) : (string)$id;
            if (isset($processedFamilies[$famKey])) continue;
            $processedFamilies[$famKey] = true;

            $pNode = $nodes[$id];
            $parentMidX = $pNode['x'] + $pNode['width'] / 2;
            $parentBottomY = $pNode['y'] + $pNode['height'];

            if ($spouseId !== null) {
                $spNode = $nodes[$spouseId];
                $leftNode = ($pNode['x'] < $spNode['x']) ? $pNode : $spNode;
                $rightNode = ($pNode['x'] < $spNode['x']) ? $spNode : $pNode;
                $parentMidX = ($leftNode['x'] + $leftNode['width'] + $rightNode['x']) / 2;
                $parentBottomY = $leftNode['y'] + $leftNode['height'];
            }

            $busY = $parentBottomY + 45;

            $childDropPoints = [];
            foreach ($children as $cId) {
                $cNode = $nodes[$cId];
                $cMidX = $cNode['x'] + $cNode['width'] / 2;
                $cTopY = $cNode['y'];

                $childDropPoints[] = [
                    'child_id' => $cId,
                    'x' => $cMidX,
                    'bus_y' => $busY,
                    'top_y' => $cTopY,
                ];
            }

            $minBusX = min(array_merge([$parentMidX], array_column($childDropPoints, 'x')));
            $maxBusX = max(array_merge([$parentMidX], array_column($childDropPoints, 'x')));

            $parentChildEdges[] = [
                'parent_id' => $id,
                'spouse_id' => $spouseId,
                'parent_mid_x' => $parentMidX,
                'parent_bottom_y' => $parentBottomY,
                'bus_y' => $busY,
                'bus_min_x' => $minBusX,
                'bus_max_x' => $maxBusX,
                'children' => $childDropPoints,
            ];
        }

        return [
            'marriages' => $marriagesEdges,
            'parent_child' => $parentChildEdges,
        ];
    }

    /**
     * Расчет внешних границ холста (Bounding Box)
     */
    protected function calculateBounds(array $nodes): array
    {
        if (empty($nodes)) {
            return ['minX' => 0, 'maxX' => 0, 'minY' => 0, 'maxY' => 0, 'width' => 0, 'height' => 0];
        }

        $minX = PHP_INT_MAX;
        $maxX = PHP_INT_MIN;
        $minY = PHP_INT_MAX;
        $maxY = PHP_INT_MIN;

        foreach ($nodes as $n) {
            if ($n['x'] < $minX) $minX = $n['x'];
            if ($n['x'] + $n['width'] > $maxX) $maxX = $n['x'] + $n['width'];
            if ($n['y'] < $minY) $minY = $n['y'];
            if ($n['y'] + $n['height'] > $maxY) $maxY = $n['y'] + $n['height'];
        }

        return [
            'minX' => $minX,
            'maxX' => $maxX,
            'minY' => $minY,
            'maxY' => $maxY,
            'width' => ($maxX - $minX),
            'height' => ($maxY - $minY),
        ];
    }

    /**
     * Подсчет количества пересечений между карточками
     */
    public function countIntersections(array $nodes): int
    {
        $nodeList = array_values($nodes);
        $count = count($nodeList);
        $intersections = 0;

        for ($i = 0; $i < $count; $i++) {
            $a = $nodeList[$i];
            for ($j = $i + 1; $j < $count; $j++) {
                $b = $nodeList[$j];

                // Проверка перекрытия ограничивающих прямоугольников (AABB)
                $overlapX = ($a['x'] < $b['x'] + $b['width']) && ($a['x'] + $a['width'] > $b['x']);
                $overlapY = ($a['y'] < $b['y'] + $b['height']) && ($a['y'] + $a['height'] > $b['y']);

                if ($overlapX && $overlapY) {
                    $intersections++;
                }
            }
        }

        return $intersections;
    }
}
