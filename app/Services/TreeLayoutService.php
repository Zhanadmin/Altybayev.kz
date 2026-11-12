<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Сервис вычисления геометрической раскладки генеалогического древа
 * 
 * Требования (TASK-003, Замечания Жандоса 13.11):
 * 1. Поколения строго по горизонтальным рядам (предки сверху, потомки снизу).
 * 2. Супруги рядом (husband & wife в одном ряду через зазор gapSpouse).
 * 3. Дети центрированы под родителями с ортогональной шиной связей.
 * 4. Полное отсутствие наложений (0 пересечений прямоугольников карточек).
 * 5. Режим окрестности (focal mode) по умолчанию вокруг выбранного человека (Жандоса).
 * 6. Полный режим (full mode) для всех 270 персон без наложений.
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
     * @param array $persons Список персон из БД или GEDCOM
     * @param array $marriages Список браков
     * @param int|null $focalPersonId ID центральной персоны (Жандос)
     * @param string $mode 'focal' (окрестность) или 'full' (все персоны)
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

        // Индексация связей: браки и дети
        $spousesMap = []; // person_id => [spouse_id, ...]
        $marriageDetails = []; // "p1_p2" => marriage data
        $childrenMap = []; // parent_id => [child_id, ...]
        $parentsMap = []; // child_id => ['father' => id, 'mother' => id]

        foreach ($personsById as $id => $p) {
            $spousesMap[$id] = [];
            $childrenMap[$id] = [];
            $parentsMap[$id] = ['father' => null, 'mother' => null];
        }

        // Родители и дети из полей persons
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

            // Если у ребенка есть оба родителя, они супруги
            if ($fId && $mId && isset($personsById[$fId]) && isset($personsById[$mId])) {
                if (!in_array($mId, $spousesMap[$fId], true)) {
                    $spousesMap[$fId][] = $mId;
                }
                if (!in_array($fId, $spousesMap[$mId], true)) {
                    $spousesMap[$mId][] = $fId;
                }
            }
        }

        // Браки из таблицы marriages
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

        // 2. Отбор видимых персон в зависимости от режима
        $visibleIds = [];
        if ($mode === 'focal') {
            $visibleIds = $this->collectFocalNeighborhood($focalPersonId, $personsById, $spousesMap, $childrenMap, $parentsMap, $expandedIds);
        } else {
            // Full mode: все персоны
            $visibleIds = array_keys($personsById);
        }

        // 3. Вычисление поколения (Generation Level) для каждого видимого узла
        $generations = $this->assignGenerations($visibleIds, $focalPersonId, $parentsMap, $childrenMap, $spousesMap);

        // Нормализация поколений так, чтобы старейшие предки были на уровне 0
        $minGen = !empty($generations) ? min($generations) : 0;
        $maxGen = !empty($generations) ? max($generations) : 0;
        $normalizedGen = [];
        foreach ($visibleIds as $vId) {
            $normalizedGen[$vId] = ($generations[$vId] ?? 0) - $minGen;
        }

        // 4. Построение семейных блоков (Family Units) и горизонтальное позиционирование
        $positions = $this->layoutGenerations($visibleIds, $normalizedGen, $spousesMap, $childrenMap, $parentsMap);

        // 5. Разрешение коллизий и проверка пересечений
        $positions = $this->resolveCollisions($positions, $normalizedGen);

        // 6. Формирование узлов с метаданными и скрытыми ветками
        $nodes = [];
        foreach ($visibleIds as $id) {
            $p = $personsById[$id];
            $pos = $positions[$id];

            // Проверка наличия скрытых предков
            $hasHiddenParents = false;
            $hiddenParentsCount = 0;
            $fId = $parentsMap[$id]['father'];
            $mId = $parentsMap[$id]['mother'];
            if (($fId && !in_array($fId, $visibleIds, true)) || ($mId && !in_array($mId, $visibleIds, true))) {
                $hasHiddenParents = true;
                if ($fId && !in_array($fId, $visibleIds, true)) $hiddenParentsCount++;
                if ($mId && !in_array($mId, $visibleIds, true)) $hiddenParentsCount++;
            }

            // Проверка наличия скрытых детей
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
                'clan_ru' => $p['clan_ru'] ?? 'Керей',
                'clan_id' => $p['clan_id'] ?? 1,
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

        // 7. Построение линий связей (ортогональные шины)
        $edges = $this->buildEdges($nodes, $visibleIds, $spousesMap, $childrenMap, $parentsMap, $marriageDetails);

        // 8. Расчет границ и верификация непересечения
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
     * Поиск фокальной персоны по умолчанию (Жандос или первая персона с семьей)
     */
    protected function findDefaultFocalId(array $persons): int
    {
        // 1. Поиск Жандоса по имени
        foreach ($persons as $p) {
            if (mb_stripos($p['first_name'] ?? '', 'Жандос') !== false || ($p['gedcom_id'] ?? '') === '@I6@') {
                return (int)$p['id'];
            }
        }
        // 2. Персона с именем Алтыбаев
        foreach ($persons as $p) {
            if (mb_stripos($p['last_name'] ?? '', 'Алтыбаев') !== false && !empty($p['father_id'])) {
                return (int)$p['id'];
            }
        }
        return (int)($persons[0]['id'] ?? 1);
    }

    /**
     * Сбор окрестности фокальной персоны
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

        // 1. Супруги фокального лица
        foreach ($spousesMap[$focalId] ?? [] as $spId) {
            $visible[$spId] = true;
        }

        // 2. Дети и внуки фокального лица
        foreach ($childrenMap[$focalId] ?? [] as $cId) {
            $visible[$cId] = true;
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

        // 4. Братья и сестры (дети родителей) и их семьи
        $parentIds = array_filter([$fatherId, $motherId]);
        foreach ($parentIds as $pId) {
            foreach ($childrenMap[$pId] ?? [] as $sibId) {
                $visible[$sibId] = true;
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

        // 6. Дополнительно раскрытые ветки по запросу пользователя (expandedIds)
        foreach ($expandedIds as $expId) {
            $expId = (int)$expId;
            if (!isset($personsById[$expId])) continue;

            $visible[$expId] = true;
            // Раскрываем родителей
            $efId = $parentsMap[$expId]['father'] ?? null;
            $emId = $parentsMap[$expId]['mother'] ?? null;
            if ($efId && isset($personsById[$efId])) $visible[$efId] = true;
            if ($emId && isset($personsById[$emId])) $visible[$emId] = true;

            // Раскрываем супругов и детей
            foreach ($spousesMap[$expId] ?? [] as $sId) $visible[$sId] = true;
            foreach ($childrenMap[$expId] ?? [] as $cId) $visible[$cId] = true;
        }

        return array_keys($visible);
    }

    /**
     * Определение номеров поколений относительно фокальной персоны
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

        // Распространение поколений в ширину (BFS)
        $queue = [$focalId];
        $visited = [$focalId => true];

        while (!empty($queue)) {
            $currId = array_shift($queue);
            $currGen = $generations[$currId];

            // 1. Супруги в том же поколении
            foreach ($spousesMap[$currId] ?? [] as $spId) {
                if (in_array($spId, $visibleIds, true) && !isset($generations[$spId])) {
                    $generations[$spId] = $currGen;
                    $queue[] = $spId;
                }
            }

            // 2. Родители на 1 поколение выше (currGen - 1)
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

            // 3. Дети на 1 поколение ниже (currGen + 1)
            foreach ($childrenMap[$currId] ?? [] as $cId) {
                if (in_array($cId, $visibleIds, true) && !isset($generations[$cId])) {
                    $generations[$cId] = $currGen + 1;
                    $queue[] = $cId;
                }
            }

            // 4. Братья и сестры (дети тех же родителей) в том же поколении
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

        // Для изолированных видимых персон подставляем 0
        foreach ($visibleIds as $id) {
            if (!isset($generations[$id])) {
                $generations[$id] = 0;
            }
        }

        return $generations;
    }

    /**
     * Построение семейных блоков и первичное позиционирование по уровням
     */
    protected function layoutGenerations(
        array $visibleIds,
        array $normalizedGen,
        array $spousesMap,
        array $childrenMap,
        array $parentsMap
    ): array {
        // Группировка по поколениям
        $byGen = [];
        foreach ($visibleIds as $id) {
            $g = $normalizedGen[$id];
            $byGen[$g][] = $id;
        }
        ksort($byGen);

        $positions = [];
        $startX = 1000;
        $startY = 200;

        foreach ($byGen as $g => $members) {
            $y = $startY + $g * self::ROW_HEIGHT;

            // Формируем пары (муж + жена) и одиночек в этом поколении
            $processed = [];
            $units = [];

            foreach ($members as $mId) {
                if (isset($processed[$mId])) continue;

                // Ищем супруга в этом же поколении
                $spouseInGen = null;
                foreach ($spousesMap[$mId] ?? [] as $spId) {
                    if (in_array($spId, $members, true) && !isset($processed[$spId])) {
                        $spouseInGen = $spId;
                        break;
                    }
                }

                if ($spouseInGen !== null) {
                    // Пара: муж слева, жена справа (или по полу)
                    $units[] = [
                        'type' => 'couple',
                        'p1' => $mId,
                        'p2' => $spouseInGen,
                        'width' => self::CARD_WIDTH * 2 + self::GAP_SPOUSE
                    ];
                    $processed[$mId] = true;
                    $processed[$spouseInGen] = true;
                } else {
                    $units[] = [
                        'type' => 'single',
                        'p1' => $mId,
                        'width' => self::CARD_WIDTH
                    ];
                    $processed[$mId] = true;
                }
            }

            // Раскладываем блоки слева направо с зазором GAP_SIBLING
            $currX = $startX;
            foreach ($units as $u) {
                if ($u['type'] === 'couple') {
                    $positions[$u['p1']] = ['x' => $currX, 'y' => $y];
                    $positions[$u['p2']] = ['x' => $currX + self::CARD_WIDTH + self::GAP_SPOUSE, 'y' => $y];
                    $currX += $u['width'] + self::GAP_SIBLING;
                } else {
                    $positions[$u['p1']] = ['x' => $currX, 'y' => $y];
                    $currX += $u['width'] + self::GAP_SIBLING;
                }
            }
        }

        // Центрирование детей под родителями (нисходящая и восходящая гармонизация)
        $positions = $this->alignChildrenUnderParents($positions, $byGen, $childrenMap, $spousesMap);

        return $positions;
    }

    /**
     * Центрирование детей под родительской парой
     */
    protected function alignChildrenUnderParents(
        array $positions,
        array $byGen,
        array $childrenMap,
        array $spousesMap
    ): array {
        foreach ($byGen as $g => $members) {
            foreach ($members as $pId) {
                $children = array_filter($childrenMap[$pId] ?? [], fn($cId) => isset($positions[$cId]));
                if (empty($children)) continue;

                // Находим координаты родителя / пары
                $parentPos = $positions[$pId];
                $spouseId = null;
                foreach ($spousesMap[$pId] ?? [] as $spId) {
                    if (isset($positions[$spId])) {
                        $spouseId = $spId;
                        break;
                    }
                }

                $parentMidX = $parentPos['x'] + self::CARD_WIDTH / 2;
                if ($spouseId !== null) {
                    $spPos = $positions[$spouseId];
                    $parentMidX = ($parentPos['x'] + $spPos['x'] + self::CARD_WIDTH) / 2;
                }

                // Координаты детей
                $minChildX = min(array_map(fn($cId) => $positions[$cId]['x'], $children));
                $maxChildX = max(array_map(fn($cId) => $positions[$cId]['x'] + self::CARD_WIDTH, $children));
                $childrenMidX = ($minChildX + $maxChildX) / 2;

                $delta = $parentMidX - $childrenMidX;
                if (abs($delta) > 10) {
                    // Сдвигаем детей для центрирования
                    foreach ($children as $cId) {
                        $positions[$cId]['x'] += (int)($delta * 0.7);
                    }
                }
            }
        }

        return $positions;
    }

    /**
     * Разрешение коллизий карточек (Строгое устранение любых пересечений)
     */
    protected function resolveCollisions(array $positions, array $normalizedGen): array
    {
        // Группировка по горизонтальным рядам (Y)
        $rows = [];
        foreach ($positions as $id => $pos) {
            $y = $pos['y'];
            $rows[$y][] = $id;
        }

        foreach ($rows as $y => $idsInRow) {
            // Сортировка карточек в ряду слева направо
            usort($idsInRow, fn($a, $b) => $positions[$a]['x'] <=> $positions[$b]['x']);

            for ($i = 1; $i < count($idsInRow); $i++) {
                $prevId = $idsInRow[$i - 1];
                $currId = $idsInRow[$i];

                $prevRight = $positions[$prevId]['x'] + self::CARD_WIDTH;
                $minRequiredX = $prevRight + self::GAP_SPOUSE;

                if ($positions[$currId]['x'] < $minRequiredX) {
                    $shift = $minRequiredX - $positions[$currId]['x'];
                    // Сдвигаем текущую и все последующие в этом ряду
                    for ($k = $i; $k < count($idsInRow); $k++) {
                        $positions[$idsInRow[$k]]['x'] += $shift;
                    }
                }
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

        // 1. Некелік байланыстар (Супруги)
        foreach ($visibleIds as $id) {
            foreach ($spousesMap[$id] ?? [] as $spId) {
                if (!isset($nodes[$spId])) continue;

                $coupleKey = min($id, $spId) . '_' . max($id, $spId);
                if (isset($processedCouples[$coupleKey])) continue;
                $processedCouples[$coupleKey] = true;

                $n1 = $nodes[$id];
                $n2 = $nodes[$spId];

                // Муж и жена
                $leftNode = ($n1['x'] < $n2['x']) ? $n1 : $n2;
                $rightNode = ($n1['x'] < $n2['x']) ? $n2 : $n1;

                $x1 = $leftNode['x'] + $leftNode['width'];
                $y1 = $leftNode['y'] + $leftNode['height'] / 2;
                $x2 = $rightNode['x'];
                $y2 = $rightNode['y'] + $rightNode['height'] / 2;

                $midX = ($x1 + $x2) / 2;
                $midY = ($y1 + $y2) / 2;

                $marriagesEdges[] = [
                    'husband_id' => $leftNode['id'],
                    'wife_id' => $rightNode['id'],
                    'x1' => $x1,
                    'y1' => $y1,
                    'x2' => $x2,
                    'y2' => $y2,
                    'mid_x' => $midX,
                    'mid_y' => $midY,
                ];
            }
        }

        // 2. Родительско-детские ортогональные шины связей
        $processedFamilies = [];
        foreach ($visibleIds as $id) {
            $children = array_filter($childrenMap[$id] ?? [], fn($cId) => isset($nodes[$cId]));
            if (empty($children)) continue;

            // Находим супруга
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

            // Высота горизонтальной шины детей
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
     * Возвращает 0 при идеальной раскладке
     */
    public function countIntersections(array $nodes): int
    {
        $nodeList = array_values($nodes);
        $count = count($nodeList);
        $intersections = 0;

        for ($i = 0; $i < $count; $i++) {
            $a = $nodeList[$i];
            $ax1 = $a['x'];
            $ax2 = $a['x'] + $a['width'];
            $ay1 = $a['y'];
            $ay2 = $a['y'] + $a['height'];

            for ($j = $i + 1; $j < $count; $j++) {
                $b = $nodeList[$j];
                $bx1 = $b['x'];
                $bx2 = $b['x'] + $b['width'];
                $by1 = $b['y'];
                $by2 = $b['y'] + $b['height'];

                // Проверка пересечения прямоугольников
                // Два прямоугольника НЕ пересекаются, если один полностью слева, справа, сверху или снизу
                $noOverlap = ($ax2 <= $bx1 || $bx2 <= $ax1 || $ay2 <= $by1 || $by2 <= $ay1);
                if (!$noOverlap) {
                    $intersections++;
                }
            }
        }

        return $intersections;
    }
}
