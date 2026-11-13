<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Сервис вычисления строго геометрической раскладки генеалогического древа
 * 
 * Ключевые требования (TASK-003, Замечания Жандоса 13.11):
 * 1. Семейная пара (супруги) — ОДНА общая плашка (как в MyHeritage):
 *    две карточки вплотную внутри общей рамки (зазор GAP_SPOUSE = 12px, на одном уровне Y).
 * 2. Группы внуков под своими родителями (иерархические блоки семей):
 *    внуки от сына А — строго под парой А, от сына Б — строго под парой Б; группы не перемешиваются.
 * 3. Старшинство братьев и сестер: строго слева направо по возрасту (старший слева).
 *    Фокус на человеке не меняет порядок, а только подсвечивает карточку («★ Мен 🎯»).
 * 4. Каждое поколение строго в своем горизонтальном ряду поколения (одинаковый Y, поколения не пересекаются).
 * 5. При фокусе на замужней женщине (келін, жеңге, ана) показывать ОБЕ стороны семьи:
 *    төркіні (ее родители) сверху, муж рядом в общей плашке, семья мужа (родители мужа, братья мужа и их дети).
 * 6. Правило окрестности: раскрывать вниз ВСЕ поколения потомков до самых последних живых.
 * 7. Линии связей: гарантированная отрисовка во всех режимах (окрестность, любой фокус, всё древо).
 *    Строго положительные координаты (minX >= 1000) без обрезки по SVG (0,0).
 * 8. Семейные ореолы (Шаңырақ ореолдары) вокруг блоков семей.
 */
class TreeLayoutService
{
    public const CARD_WIDTH = 260;
    public const CARD_HEIGHT = 120;
    public const GAP_SPOUSE = 12; // Общая плашка пары: карточки вплотную
    public const GAP_SIBLING = 55; // Зазор между семейными блоками братьев
    public const ROW_HEIGHT = 220; // 120px карточка + 100px канал для связей

    /**
     * Основной метод вычисления координат
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
                'couples' => [],
                'auras' => [],
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

        // 3. Вычисление поколений (Generation Level)
        // Каждое поколение на своем горизонтальном уровне от общего корня
        $generations = $this->assignGenerations($visibleIds, $focalPersonId, $parentsMap, $childrenMap, $spousesMap);
        $minGen = !empty($generations) ? min($generations) : 0;
        $maxGen = !empty($generations) ? max($generations) : 0;
        $normalizedGen = [];
        foreach ($visibleIds as $vId) {
            $normalizedGen[$vId] = ($generations[$vId] ?? 0) - $minGen;
        }

        // 4. Иерархическая раскладка семейных блоков (Subtrees)
        // Гарантия: дети под своей парой, группы братьев не пересекаются и не перемешиваются
        $layoutResult = $this->layoutSubtrees(
            $visibleIds,
            $normalizedGen,
            $spousesMap,
            $childrenMap,
            $parentsMap,
            $focalPersonId,
            $personsById,
            $marriageDetails
        );

        $positions = $layoutResult['positions'];
        $couples = $layoutResult['couples'];
        $auras = $layoutResult['auras'];

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
                'birth_date' => $p['birth_date'] ?? null,
                'birth_year' => $p['birth_year'] ?? null,
                'birth_place' => $p['birth_place'] ?? null,
                'death_date' => $p['death_date'] ?? null,
                'death_year' => $p['death_year'] ?? null,
                'death_place' => $p['death_place'] ?? null,
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

        // 6. Построение линий связей
        $edges = $this->buildEdges($nodes, $visibleIds, $spousesMap, $childrenMap, $parentsMap, $marriageDetails);

        // 7. Нормализация координат: гарантируем minX >= 1000 и minY >= 200 (нулевые отрицательные координаты!)
        $this->normalizeCoordinates($nodes, $edges, $couples, $auras);

        // 8. Расчет границ и верификация непересечения
        $bounds = $this->calculateBounds($nodes);
        $intersections = $this->countIntersections($nodes);

        return [
            'mode' => $mode,
            'focal_id' => $focalPersonId,
            'nodes' => array_values($nodes),
            'nodes_by_id' => $nodes,
            'edges' => $edges,
            'couples' => $couples,
            'auras' => $auras,
            'bounds' => $bounds,
            'stats' => [
                'visible_count' => count($nodes),
                'total_count' => count($personsById),
                'generations_count' => ($maxGen - $minGen + 1),
                'intersections_count' => $intersections,
                'marriages_count' => count($edges['marriages']),
                'parent_child_count' => count($edges['parent_child']),
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
     * Включает ОБЕ стороны семьи при фокусе на замужней женщине,
     * а также раскрывает ВСЕ поколения потомков вниз до самого конца.
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
        $spousesOfFocal = $spousesMap[$focalId] ?? [];
        foreach ($spousesOfFocal as $spId) {
            $visible[$spId] = true;
        }

        // 2. ВСЕ поколения потомков вниз (дети, внуки, правнуки до самого конца)
        $descendantsQueue = array_unique(array_merge(
            $childrenMap[$focalId] ?? [],
            ...array_map(fn($sId) => $childrenMap[$sId] ?? [], $spousesOfFocal)
        ));

        while (!empty($descendantsQueue)) {
            $nextDesc = [];
            foreach ($descendantsQueue as $dId) {
                if (!isset($personsById[$dId])) continue;
                $visible[$dId] = true;
                // Супруги потомка
                foreach ($spousesMap[$dId] ?? [] as $dspId) {
                    if (isset($personsById[$dspId])) {
                        $visible[$dspId] = true;
                    }
                }
                // Дети потомка
                foreach ($childrenMap[$dId] ?? [] as $childId) {
                    if (isset($personsById[$childId]) && !isset($visible[$childId])) {
                        $visible[$childId] = true;
                        $nextDesc[] = $childId;
                    }
                }
            }
            $descendantsQueue = $nextDesc;
        }

        // 3. Предки и родня с ОБЕИХ сторон (төркіні + семья мужа/жены)
        // Для каждого из пары (фокальное лицо + супруг(а)) собираем родителей и братьев
        $focusPeople = array_unique(array_merge([$focalId], $spousesOfFocal));

        foreach ($focusPeople as $personId) {
            $fId = $parentsMap[$personId]['father'] ?? null;
            $mId = $parentsMap[$personId]['mother'] ?? null;

            if ($fId && isset($personsById[$fId])) {
                $visible[$fId] = true;
            }
            if ($mId && isset($personsById[$mId])) {
                $visible[$mId] = true;
            }

            // Братья и сестры и их семьи
            $parents = array_filter([$fId, $mId]);
            foreach ($parents as $pId) {
                foreach ($childrenMap[$pId] ?? [] as $sibId) {
                    if (!isset($personsById[$sibId])) continue;
                    $visible[$sibId] = true;
                    // Супруги братьев/сестер (жеңге, жезде)
                    foreach ($spousesMap[$sibId] ?? [] as $sspId) {
                        if (isset($personsById[$sspId])) {
                            $visible[$sspId] = true;
                        }
                    }
                    // Племянники
                    foreach ($childrenMap[$sibId] ?? [] as $nephewId) {
                        if (isset($personsById[$nephewId])) {
                            $visible[$nephewId] = true;
                        }
                    }
                }
            }

            // Дедушки и бабушки (до 2 поколений вверх)
            $ancestors = array_filter([$fId, $mId]);
            foreach ($ancestors as $aId) {
                $gfId = $parentsMap[$aId]['father'] ?? null;
                $gmId = $parentsMap[$aId]['mother'] ?? null;
                if ($gfId && isset($personsById[$gfId])) $visible[$gfId] = true;
                if ($gmId && isset($personsById[$gmId])) $visible[$gmId] = true;
            }
        }

        // 4. Дополнительно раскрытые ветки (expandedIds)
        foreach ($expandedIds as $expId) {
            $expId = (int)$expId;
            if (!isset($personsById[$expId])) continue;

            $visible[$expId] = true;
            $efId = $parentsMap[$expId]['father'] ?? null;
            $emId = $parentsMap[$expId]['mother'] ?? null;
            if ($efId && isset($personsById[$efId])) $visible[$efId] = true;
            if ($emId && isset($personsById[$emId])) $visible[$emId] = true;

            foreach ($spousesMap[$expId] ?? [] as $sId) {
                if (isset($personsById[$sId])) $visible[$sId] = true;
            }
            foreach ($childrenMap[$expId] ?? [] as $cId) {
                if (isset($personsById[$cId])) $visible[$cId] = true;
            }
        }

        return array_keys($visible);
    }

    /**
     * Вычисление поколения (Generation Level)
     * Каждое поколение строго на своем горизонтальном ряду от общего корня.
     */
    protected function assignGenerations(
        array $visibleIds,
        int $focalId,
        array $parentsMap,
        array $childrenMap,
        array $spousesMap
    ): array {
        $generations = [];

        // 1. Находим корневых предков (у кого нет родителей в видимых)
        $roots = [];
        foreach ($visibleIds as $id) {
            $fId = $parentsMap[$id]['father'] ?? null;
            $mId = $parentsMap[$id]['mother'] ?? null;
            $hasFatherInVisible = ($fId && in_array($fId, $visibleIds, true));
            $hasMotherInVisible = ($mId && in_array($mId, $visibleIds, true));

            if (!$hasFatherInVisible && !$hasMotherInVisible) {
                $roots[] = $id;
            }
        }

        // Инициализируем корни поколением 0
        foreach ($roots as $rId) {
            $generations[$rId] = 0;
        }
        if (empty($generations)) {
            $generations[$focalId] = 0;
        }

        // Итеративная релаксация до сходимости
        for ($iter = 0; $iter < 25; $iter++) {
            $changed = false;

            foreach ($visibleIds as $id) {
                $currGen = $generations[$id] ?? null;

                // Если у персоны есть родители с известным поколением
                $fId = $parentsMap[$id]['father'] ?? null;
                $mId = $parentsMap[$id]['mother'] ?? null;
                $parentGen = null;
                if ($fId && isset($generations[$fId])) {
                    $parentGen = ($parentGen === null) ? $generations[$fId] : max($parentGen, $generations[$fId]);
                }
                if ($mId && isset($generations[$mId])) {
                    $parentGen = ($parentGen === null) ? $generations[$mId] : max($parentGen, $generations[$mId]);
                }

                if ($parentGen !== null) {
                    $targetGen = $parentGen + 1;
                    if ($currGen === null || $currGen < $targetGen) {
                        $generations[$id] = $targetGen;
                        $changed = true;
                    }
                }

                // Супруги строго в том же поколении
                $effectiveGen = $generations[$id] ?? null;
                if ($effectiveGen !== null) {
                    foreach ($spousesMap[$id] ?? [] as $spId) {
                        if (in_array($spId, $visibleIds, true)) {
                            $spGen = $generations[$spId] ?? null;
                            if ($spGen === null || $spGen !== $effectiveGen) {
                                $generations[$spId] = $effectiveGen;
                                $changed = true;
                            }
                        }
                    }
                }
            }

            if (!$changed) break;
        }

        foreach ($visibleIds as $id) {
            if (!isset($generations[$id])) {
                $generations[$id] = 0;
            }
        }

        return $generations;
    }

    /**
     * Сравнение персон по возрасту (старший слева)
     */
    public function comparePersonsByAge(array $pA, array $pB): int
    {
        $yearA = $this->extractBirthYear($pA);
        $yearB = $this->extractBirthYear($pB);

        if ($yearA !== null && $yearB !== null && $yearA !== $yearB) {
            return $yearA <=> $yearB; // Меньший год = раньше родился = старший слева
        }
        if ($yearA !== null && $yearB === null) return -1;
        if ($yearA === null && $yearB !== null) return 1;

        return ((int)($pA['id'] ?? 0)) <=> ((int)($pB['id'] ?? 0));
    }

    protected function extractBirthYear(array $p): ?int
    {
        if (!empty($p['birth_date']) && preg_match('/^(\d{4})/', (string)$p['birth_date'], $m)) {
            return (int)$m[1];
        }
        if (!empty($p['birth_year']) && preg_match('/\b(1[6-9]\d{2}|20\d{2})\b/', (string)$p['birth_year'], $m)) {
            return (int)$m[1];
        }
        return null;
    }

    /**
     * Иерархическая раскладка семейных поддеревьев (Subtrees)
     * Гарантирует:
     * - Дети стоят строго под своей родительской парой
     * - Группы детей соседних братьев не перемешиваются
     * - Старшинство братьев слева направо
     * - Общая плашка пары
     */
    protected function layoutSubtrees(
        array $visibleIds,
        array $normalizedGen,
        array $spousesMap,
        array $childrenMap,
        array $parentsMap,
        int $focalId,
        array $personsById,
        array $marriageDetails
    ): array {
        // 1. Формирование неделимых юнитов (Units)
        // Юнит объединяет мужа и жену (или неженатого человека)
        $units = [];
        $personToUnitMap = [];
        $unitCounter = 1;
        $processed = [];

        // Сортируем всех персон по поколениям, затем по возрасту
        $sortedPersons = $visibleIds;
        usort($sortedPersons, function ($a, $b) use ($normalizedGen, $personsById) {
            if ($normalizedGen[$a] !== $normalizedGen[$b]) {
                return $normalizedGen[$a] <=> $normalizedGen[$b];
            }
            return $this->comparePersonsByAge($personsById[$a], $personsById[$b]);
        });

        foreach ($sortedPersons as $mId) {
            if (isset($processed[$mId])) continue;

            $p = $personsById[$mId];
            $gen = $normalizedGen[$mId];

            // Находим супругов в том же поколении
            $spousesInGen = [];
            foreach ($spousesMap[$mId] ?? [] as $spId) {
                if (in_array($spId, $visibleIds, true) && !isset($processed[$spId])) {
                    $spousesInGen[] = $spId;
                }
            }

            $unitMembers = [];
            if (empty($spousesInGen)) {
                $unitMembers = [$mId];
                $processed[$mId] = true;
            } else {
                // В паре: муж слева, жена справа
                if ($p['gender'] === 'M') {
                    $unitMembers = array_merge([$mId], $spousesInGen);
                } else {
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

            $mCount = count($unitMembers);
            $unitWidth = $mCount * self::CARD_WIDTH + ($mCount - 1) * self::GAP_SPOUSE;

            $uId = $unitCounter++;
            $units[$uId] = [
                'id' => $uId,
                'generation' => $gen,
                'members' => $unitMembers,
                'width' => $unitWidth,
                'x' => 0,
                'y' => 200 + $gen * self::ROW_HEIGHT,
                'children_units' => [],
                'parent_units' => [],
                'subtree_width' => $unitWidth,
            ];

            foreach ($unitMembers as $memId) {
                $personToUnitMap[$memId] = $uId;
            }
        }

        // 2. Связывание родительских и дочерних юнитов
        foreach ($units as $uId => &$u) {
            $childrenUnitList = [];
            foreach ($u['members'] as $memId) {
                foreach ($childrenMap[$memId] ?? [] as $cId) {
                    if (isset($personToUnitMap[$cId])) {
                        $cuId = $personToUnitMap[$cId];
                        if (!in_array($cuId, $childrenUnitList, true) && $cuId !== $uId) {
                            $childrenUnitList[] = $cuId;
                        }
                    }
                }
            }

            // Сортировка дочерних юнитов строго по возрасту старшего члена юнита
            usort($childrenUnitList, function ($cuA, $cuB) use ($units, $personsById) {
                $pA = $personsById[$units[$cuA]['members'][0]];
                $pB = $personsById[$units[$cuB]['members'][0]];
                return $this->comparePersonsByAge($pA, $pB);
            });

            $u['children_units'] = $childrenUnitList;

            foreach ($childrenUnitList as $cuId) {
                if (!in_array($uId, $units[$cuId]['parent_units'], true)) {
                    $units[$cuId]['parent_units'][] = $uId;
                }
            }
        }
        unset($u);

        // 3. Вычисление ширины поддеревьев снизу вверх (Post-Order)
        // Ширина семейного блока = max(ширина родительской пары, сумма ширин поддеревьев детей)
        $visitedSubtree = [];
        foreach ($units as $uId => &$u) {
            $this->calculateUnitSubtreeWidth($uId, $units, $visitedSubtree);
        }
        unset($u);

        // 4. Находим корневые юниты (у кого нет родителей в видимом наборе)
        $rootUnits = [];
        foreach ($units as $uId => $u) {
            if (empty($u['parent_units'])) {
                $rootUnits[] = $uId;
            }
        }

        // Сортировка корневых юнитов по поколению, затем по возрасту
        usort($rootUnits, function ($a, $b) use ($units, $personsById) {
            if ($units[$a]['generation'] !== $units[$b]['generation']) {
                return $units[$a]['generation'] <=> $units[$b]['generation'];
            }
            $pA = $personsById[$units[$a]['members'][0]];
            $pB = $personsById[$units[$b]['members'][0]];
            return $this->comparePersonsByAge($pA, $pB);
        });

        // 5. Иерархическое позиционирование поддеревьев
        $currX = 1000;
        $placedUnits = [];
        foreach ($rootUnits as $rId) {
            $this->positionUnitSubtree($rId, $currX, $units, $placedUnits);
            $currX += $units[$rId]['subtree_width'] + self::GAP_SIBLING;
        }

        // Размещаем любые оставшиеся изолированные юниты
        foreach ($units as $uId => $u) {
            if (!isset($placedUnits[$uId])) {
                $units[$uId]['x'] = $currX;
                $currX += $u['width'] + self::GAP_SIBLING;
                $placedUnits[$uId] = true;
            }
        }

        // 6. Устранение горизонтальных пересечений по каждому поколению
        $unitsByGen = [];
        foreach ($units as $uId => $u) {
            $unitsByGen[$u['generation']][] = $uId;
        }
        ksort($unitsByGen);

        foreach ($unitsByGen as $g => $unitList) {
            usort($unitList, fn($a, $b) => $units[$a]['x'] <=> $units[$b]['x']);
            for ($i = 1; $i < count($unitList); $i++) {
                $prevU = &$units[$unitList[$i - 1]];
                $currU = &$units[$unitList[$i]];
                $minReqX = $prevU['x'] + $prevU['width'] + self::GAP_SIBLING;
                if ($currU['x'] < $minReqX) {
                    $shift = $minReqX - $currU['x'];
                    for ($k = $i; $k < count($unitList); $k++) {
                        $units[$unitList[$k]]['x'] += $shift;
                    }
                }
            }
        }

        // 7. Распаковка позиций карточек, формирование общих плашек пар (Couples) и ореолов (Auras)
        $positions = [];
        $couples = [];
        $auras = [];

        foreach ($units as $uId => $u) {
            $memX = $u['x'];
            $memY = $u['y'];

            foreach ($u['members'] as $mId) {
                $positions[$mId] = [
                    'x' => $memX,
                    'y' => $memY,
                ];
                $memX += self::CARD_WIDTH + self::GAP_SPOUSE;
            }

            // Формирование плашки пары
            if (count($u['members']) >= 2) {
                $hId = $u['members'][0];
                $wId = $u['members'][1];
                $mDetail = $marriageDetails["{$hId}_{$wId}"] ?? null;
                $isDiv = ($mDetail && (($mDetail['status'] ?? '') === 'divorced' || !empty($mDetail['divorce_date'])));

                $couples[] = [
                    'id' => "couple_{$hId}_{$wId}",
                    'husband_id' => $hId,
                    'wife_id' => $wId,
                    'x' => $u['x'] - 8,
                    'y' => $u['y'] - 8,
                    'width' => $u['width'] + 16,
                    'height' => self::CARD_HEIGHT + 16,
                    'is_divorced' => $isDiv,
                ];
            }

            // Ореол семьи (Aura) вокруг пары и ее детей
            if (!empty($u['children_units'])) {
                $childXs = [];
                $childMaxXs = [];
                $childMaxYs = [];

                foreach ($u['children_units'] as $cuId) {
                    $cu = $units[$cuId];
                    $childXs[] = $cu['x'];
                    $childMaxXs[] = $cu['x'] + $cu['width'];
                    $childMaxYs[] = $cu['y'] + self::CARD_HEIGHT;
                }

                $minAuraX = min(array_merge([$u['x']], $childXs)) - 25;
                $maxAuraX = max(array_merge([$u['x'] + $u['width']], $childMaxXs)) + 25;
                $minAuraY = $u['y'] - 20;
                $maxAuraY = max($childMaxYs) + 20;

                $fatherPerson = $personsById[$u['members'][0]] ?? null;
                $auraName = $fatherPerson ? ($fatherPerson['first_name'] . ' шаңырағы') : 'Отбасы';

                $auras[] = [
                    'id' => "aura_{$uId}",
                    'name' => $auraName,
                    'x' => $minAuraX,
                    'y' => $minAuraY,
                    'width' => $maxAuraX - $minAuraX,
                    'height' => $maxAuraY - $minAuraY,
                    'is_focal' => in_array($focalId, $u['members'], true),
                ];
            }
        }

        return [
            'positions' => $positions,
            'couples' => $couples,
            'auras' => $auras,
        ];
    }

    /**
     * Рекурсивное вычисление ширины поддерева (Subtree Width)
     */
    protected function calculateUnitSubtreeWidth(int $unitId, array &$units, array &$visited): float
    {
        if (isset($visited[$unitId])) {
            return $units[$unitId]['subtree_width'];
        }
        $visited[$unitId] = true;

        $cUnits = $units[$unitId]['children_units'] ?? [];
        if (empty($cUnits)) {
            $units[$unitId]['subtree_width'] = (float)$units[$unitId]['width'];
            return $units[$unitId]['subtree_width'];
        }

        $totalChildrenWidth = 0.0;
        foreach ($cUnits as $idx => $cuId) {
            $cw = $this->calculateUnitSubtreeWidth($cuId, $units, $visited);
            $totalChildrenWidth += $cw;
            if ($idx > 0) {
                $totalChildrenWidth += self::GAP_SIBLING;
            }
        }

        $units[$unitId]['subtree_width'] = max((float)$units[$unitId]['width'], $totalChildrenWidth);
        return $units[$unitId]['subtree_width'];
    }

    /**
     * Рекурсивное центрирование и позиционирование поддерева
     */
    protected function positionUnitSubtree(int $unitId, float $startX, array &$units, array &$placed): void
    {
        $placed[$unitId] = true;
        $subWidth = $units[$unitId]['subtree_width'];
        $unitWidth = $units[$unitId]['width'];

        // Центрируем родительскую пару над ее поддеревом
        if ($subWidth > $unitWidth) {
            $units[$unitId]['x'] = $startX + ($subWidth - $unitWidth) / 2.0;
        } else {
            $units[$unitId]['x'] = $startX;
        }

        $cUnits = $units[$unitId]['children_units'] ?? [];
        if (empty($cUnits)) return;

        // Вычисляем суммарную ширину детей
        $totalCWidth = 0.0;
        foreach ($cUnits as $idx => $cuId) {
            $totalCWidth += $units[$cuId]['subtree_width'];
            if ($idx > 0) {
                $totalCWidth += self::GAP_SIBLING;
            }
        }

        // Центрируем группу детей под родительской парой
        $childCursorX = $startX;
        if ($unitWidth > $totalCWidth) {
            $childCursorX = $units[$unitId]['x'] + ($unitWidth - $totalCWidth) / 2.0;
        }

        foreach ($cUnits as $cuId) {
            $this->positionUnitSubtree($cuId, $childCursorX, $units, $placed);
            $childCursorX += $units[$cuId]['subtree_width'] + self::GAP_SIBLING;
        }
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
                // Центр родительской плашки
                $parentMidX = ($leftNode['x'] + $rightNode['x'] + $rightNode['width']) / 2;
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
     * Нормализация всех координат: minX >= 1000, minY >= 200
     */
    protected function normalizeCoordinates(array &$nodes, array &$edges, array &$couples, array &$auras): void
    {
        if (empty($nodes)) return;

        $minX = PHP_INT_MAX;
        $minY = PHP_INT_MAX;

        foreach ($nodes as $n) {
            if ($n['x'] < $minX) $minX = $n['x'];
            if ($n['y'] < $minY) $minY = $n['y'];
        }

        $targetMinX = 1000;
        $targetMinY = 200;

        $dx = $targetMinX - $minX;
        $dy = $targetMinY - $minY;

        if ($dx === 0 && $dy === 0) return;

        // Сдвигаем карточки
        foreach ($nodes as &$n) {
            $n['x'] += $dx;
            $n['y'] += $dy;
        }
        unset($n);

        // Сдвигаем плашки пар
        foreach ($couples as &$c) {
            $c['x'] += $dx;
            $c['y'] += $dy;
        }
        unset($c);

        // Сдвигаем ореолы
        foreach ($auras as &$a) {
            $a['x'] += $dx;
            $a['y'] += $dy;
        }
        unset($a);

        // Сдвигаем линии браков
        foreach ($edges['marriages'] as &$m) {
            $m['x1'] += $dx;
            $m['y1'] += $dy;
            $m['x2'] += $dx;
            $m['y2'] += $dy;
            $m['mid_x'] += $dx;
            $m['mid_y'] += $dy;
        }
        unset($m);

        // Сдвигаем линии связей с детьми
        foreach ($edges['parent_child'] as &$pc) {
            $pc['parent_mid_x'] += $dx;
            $pc['parent_bottom_y'] += $dy;
            $pc['bus_y'] += $dy;
            $pc['bus_min_x'] += $dx;
            $pc['bus_max_x'] += $dx;
            foreach ($pc['children'] as &$c) {
                $c['x'] += $dx;
                $c['bus_y'] += $dy;
                $c['top_y'] += $dy;
            }
            unset($c);
        }
        unset($pc);
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

                // Проверка пересечения двух AABB (Axis-Aligned Bounding Boxes)
                $noOverlap = (
                    ($a['x'] + $a['width']) <= $b['x'] ||
                    ($b['x'] + $b['width']) <= $a['x'] ||
                    ($a['y'] + $a['height']) <= $b['y'] ||
                    ($b['y'] + $b['height']) <= $a['y']
                );

                if (!$noOverlap) {
                    $intersections++;
                }
            }
        }

        return $intersections;
    }
}
