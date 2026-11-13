<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\GedcomParserService;
use App\Services\TreeLayoutService;

echo "=======================================================\n";
echo "  Шежіре: Тестирование алгоритма раскладки (TreeLayoutTest) \n";
echo "=======================================================\n\n";

$passed = 0;
$total = 0;

function assertTest(bool $condition, string $label): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [OK] {$label}\n";
    } else {
        echo "  [FAIL] {$label}\n";
    }
}

// 1. Парсинг реального GEDCOM (270 человек, 81 семья)
$gedcomPath = __DIR__ . '/../import/Altubaiev - 2010-05-27.ged';
$parser = new GedcomParserService();
$data = $parser->parseFile($gedcomPath);

$persons = [];
$marriages = [];
$idMap = [];
$autoId = 1;

foreach ($data['persons'] as $gedcomId => $p) {
    $p['id'] = $autoId;
    $idMap[$gedcomId] = $autoId;
    $persons[$autoId] = $p;
    $autoId++;
}

// Заполняем числовые father_id и mother_id
foreach ($persons as $id => &$p) {
    $fGed = $p['father_gedcom_id'] ?? null;
    $mGed = $p['mother_gedcom_id'] ?? null;
    $p['father_id'] = ($fGed && isset($idMap[$fGed])) ? $idMap[$fGed] : null;
    $p['mother_id'] = ($mGed && isset($idMap[$mGed])) ? $idMap[$mGed] : null;
}
unset($p);

// Формируем marriages
$mAutoId = 1;
foreach ($data['families'] as $fam) {
    $hGed = $fam['husband_gedcom_id'] ?? null;
    $wGed = $fam['wife_gedcom_id'] ?? null;
    if ($hGed && $wGed && isset($idMap[$hGed]) && isset($idMap[$wGed])) {
        $marriages[] = [
            'id' => $mAutoId++,
            'gedcom_id' => $fam['gedcom_id'],
            'husband_id' => $idMap[$hGed],
            'wife_id' => $idMap[$wGed],
            'marriage_date' => $fam['marriage_date'] ?? null,
            'marriage_place' => $fam['marriage_place'] ?? null,
        ];
    }
}

echo "Загружено персон: " . count($persons) . ", браков: " . count($marriages) . "\n\n";

$zhandosDbId = $idMap['@I6@'] ?? null;
assertTest($zhandosDbId !== null, "Жандос (@I6@) найден в базе с ID={$zhandosDbId}");

$layoutService = new TreeLayoutService();

// ========================================================
// 1. ТЕСТИРОВАНИЕ РЕЖИМА ОКРЕСТНОСТИ (FOCAL MODE) ВОКРУГ ЖАНДОСА
// ========================================================
echo "\n--- 1. Режим окрестности (Focal Mode) вокруг Жандоса ---\n";
$focalLayout = $layoutService->calculateLayout($persons, $marriages, $zhandosDbId, 'focal');

$focalNodes = $focalLayout['nodes_by_id'];
assertTest(isset($focalNodes[$zhandosDbId]), "Жандос присутствует в окрестности");

// Проверка супруги Жандоса (@I24@ Кубенова Асила)
$asilaDbId = $idMap['@I24@'] ?? null;
assertTest($asilaDbId && isset($focalNodes[$asilaDbId]), "Супруга Жандоса (Кубенова Асила) присутствует рядом в окрестности");

if (isset($focalNodes[$zhandosDbId]) && isset($focalNodes[$asilaDbId])) {
    $zhNode = $focalNodes[$zhandosDbId];
    $asNode = $focalNodes[$asilaDbId];

    assertTest($zhNode['y'] === $asNode['y'], "Жандос и его супруга находятся в одном горизонтальном ряду поколения (y={$zhNode['y']})");
    $gap = abs($asNode['x'] - ($zhNode['x'] + $zhNode['width']));
    assertTest($gap >= TreeLayoutService::GAP_SPOUSE, "Зазор между Жандосом и супругой корректен ({$gap}px >= " . TreeLayoutService::GAP_SPOUSE . "px)");
}

// Проверка детей Жандоса (@I25@, @I170@, @I242@)
$childrenGed = ['@I25@', '@I170@', '@I242@'];
$childrenPresent = 0;
foreach ($childrenGed as $cGed) {
    if (isset($idMap[$cGed]) && isset($focalNodes[$idMap[$cGed]])) {
        $cNode = $focalNodes[$idMap[$cGed]];
        assertTest($cNode['y'] > $focalNodes[$zhandosDbId]['y'], "Ребенок {$cGed} находится строкой ниже родителей ({$cNode['y']} > {$focalNodes[$zhandosDbId]['y']})");
        $childrenPresent++;
    }
}
assertTest($childrenPresent === 3, "Все 3 детей Жандоса присутствуют в окрестности");

// Проверка родителей Жандоса (@I1@ Габит, @I2@ Менслу)
$fatherDbId = $idMap['@I1@'] ?? null;
assertTest($fatherDbId && isset($focalNodes[$fatherDbId]), "Отец Жандоса (Габит) присутствует в окрестности");
if ($fatherDbId && isset($focalNodes[$fatherDbId])) {
    assertTest($focalNodes[$fatherDbId]['y'] < $focalNodes[$zhandosDbId]['y'], "Отец находится строкой выше Жандоса ({$focalNodes[$fatherDbId]['y']} < {$focalNodes[$zhandosDbId]['y']})");
}

// ПРОВЕРКА ПЕРЕСЕЧЕНИЙ ПРЯМОУГОЛЬНИКОВ В РЕЖИМЕ ОКРЕСТНОСТИ
$focalIntersections = $layoutService->countIntersections($focalNodes);
assertTest($focalIntersections === 0, "Количество пересечений карточек в режиме окрестности: {$focalIntersections} (СТРОГО 0)");


// ========================================================
// 2. ТЕСТИРОВАНИЕ ФОКУСА НА ЖЕНЩИНЕ: МЕНСЛУ (@I2@, МАМА)
// ========================================================
echo "\n--- 2. Фокус на женщине: Менслу (@I2@, мама) ---\n";
$mensluDbId = $idMap['@I2@'] ?? null;
$mensluLayout = $layoutService->calculateLayout($persons, $marriages, $mensluDbId, 'focal');

assertTest(count($mensluLayout['nodes']) > 0, "Персоны в окрестности Менслу загружены (" . count($mensluLayout['nodes']) . " персон)");
assertTest(count($mensluLayout['edges']['marriages']) > 0, "Линии браков Менслу отрисованы (" . count($mensluLayout['edges']['marriages']) . " линий)");
assertTest(count($mensluLayout['edges']['parent_child']) > 0, "Линии к детям Менслу отрисованы (" . count($mensluLayout['edges']['parent_child']) . " линий)");

$mensluMinX = min(array_column($mensluLayout['nodes'], 'x'));
assertTest($mensluMinX >= 1000, "Координаты окрестности Менслу строго положительные (minX={$mensluMinX} >= 1000)");

$mensluIntersections = $layoutService->countIntersections($mensluLayout['nodes_by_id']);
assertTest($mensluIntersections === 0, "0 пересечений карточек при фокусе на Менслу");


// ========================================================
// 3. ТЕСТИРОВАНИЕ ФОКУСА НА КЕЛІН (@I22@, ЖЕНА БРАТА)
// ========================================================
echo "\n--- 3. Фокус на келін (@I22@, жена брата): показ обеих семей ---\n";
$kelinDbId = $idMap['@I22@'] ?? null;
$kelinLayout = $layoutService->calculateLayout($persons, $marriages, $kelinDbId, 'focal');
$kelinNodes = $kelinLayout['nodes_by_id'];

assertTest(isset($kelinNodes[$kelinDbId]), "Келін присутствует в окрестности");

// Муж келін (@I4@)
$husbandDbId = $idMap['@I4@'] ?? null;
assertTest($husbandDbId && isset($kelinNodes[$husbandDbId]), "Муж келін (@I4@) присутствует рядом");

// Отец мужа (Габит @I1@)
assertTest($fatherDbId && isset($kelinNodes[$fatherDbId]), "Отец мужа (Габит @I1@) присутствует в окрестности");

// Брат мужа (Жандос @I6@)
assertTest($zhandosDbId && isset($kelinNodes[$zhandosDbId]), "Брат мужа (Жандос @I6@) присутствует в окрестности");

assertTest(count($kelinLayout['edges']['parent_child']) > 0, "Линии связей у келін отрисованы (" . count($kelinLayout['edges']['parent_child']) . " линий)");
assertTest($layoutService->countIntersections($kelinNodes) === 0, "0 пересечений карточек при фокусе на келін");


// ========================================================
// 4. ТЕСТИРОВАНИЕ ФОКУСА НА ДЕДУШКЕ: ВНУКИ НЕ ПЕРЕМЕШИВАЮТСЯ
// ========================================================
echo "\n--- 4. Фокус на дедушке (Габит @I1@): внуки под своими родителями ---\n";
$gabitDbId = $idMap['@I1@'] ?? null;
$gabitLayout = $layoutService->calculateLayout($persons, $marriages, $gabitDbId, 'focal');
$gNodes = $gabitLayout['nodes_by_id'];

// Находим сыновей Габита: @I3@, @I4@, @I5@, @I6@
$sonsGed = ['@I3@', '@I4@', '@I5@', '@I6@'];
$sonsUnits = [];
foreach ($sonsGed as $sGed) {
    $sId = $idMap[$sGed] ?? null;
    if ($sId && isset($gNodes[$sId])) {
        // Находим детей этого сына
        $childrenOfSon = [];
        foreach ($persons as $pId => $p) {
            if (($p['father_id'] === $sId || $p['mother_id'] === $sId) && isset($gNodes[$pId])) {
                $childrenOfSon[] = $gNodes[$pId];
            }
        }
        $sonsUnits[$sId] = [
            'son' => $gNodes[$sId],
            'children' => $childrenOfSon,
        ];
    }
}

// Проверяем, что для каждого сына, у которого есть дети:
// Координаты X его детей не перекрываются с детьми другого сына
$interleavingErrors = 0;
$sonKeys = array_keys($sonsUnits);
for ($i = 0; $i < count($sonKeys); $i++) {
    $sA = $sonsUnits[$sonKeys[$i]];
    if (empty($sA['children'])) continue;

    $minXA = min(array_column($sA['children'], 'x'));
    $maxXA = max(array_map(fn($c) => $c['x'] + $c['width'], $sA['children']));

    for ($j = $i + 1; $j < count($sonKeys); $j++) {
        $sB = $sonsUnits[$sonKeys[$j]];
        if (empty($sB['children'])) continue;

        $minXB = min(array_column($sB['children'], 'x'));
        $maxXB = max(array_map(fn($c) => $c['x'] + $c['width'], $sB['children']));

        // Проверяем, что диапазоны [minXA, maxXA] и [minXB, maxXB] не пересекаются
        $overlap = !($maxXA <= $minXB || $maxXB <= $minXA);
        if ($overlap) {
            $interleavingErrors++;
        }
    }
}
assertTest($interleavingErrors === 0, "Группы внуков от разных сыновей НЕ перемешиваются (ошибок перемешивания: {$interleavingErrors})");


// ========================================================
// 5. ТЕСТИРОВАНИЕ ПОЛНОГО РЕЖИМА (FULL MODE: ВСЕ 270 ПЕРСОН)
// ========================================================
echo "\n--- 5. Полный режим (Full Mode: все 270 персон) ---\n";
$fullLayout = $layoutService->calculateLayout($persons, $marriages, $zhandosDbId, 'full');

$fullNodes = $fullLayout['nodes_by_id'];
assertTest(count($fullNodes) === 270, "Все 270 персон размещены на холсте");

// Проверка супругов: все супруги в одном ряду поколения
$coupleRowAlignErrors = 0;
foreach ($marriages as $m) {
    $hId = $m['husband_id'];
    $wId = $m['wife_id'];
    if (isset($fullNodes[$hId]) && isset($fullNodes[$wId])) {
        if ($fullNodes[$hId]['y'] !== $fullNodes[$wId]['y']) {
            $coupleRowAlignErrors++;
        }
    }
}
assertTest($coupleRowAlignErrors === 0, "Все семейные пары супругов находятся строго на одном горизонтальном уровне (ошибок: {$coupleRowAlignErrors})");

// Проверка родитель-ребенок: все дети строго ниже родителей
$parentChildRowErrors = 0;
foreach ($persons as $id => $p) {
    if (!empty($p['father_id']) && isset($fullNodes[$p['father_id']]) && isset($fullNodes[$id])) {
        if ($fullNodes[$id]['y'] <= $fullNodes[$p['father_id']]['y']) {
            $parentChildRowErrors++;
        }
    }
}
assertTest($parentChildRowErrors === 0, "Все дети находятся строго строками ниже своих родителей (ошибок: {$parentChildRowErrors})");

// Проверка одинакового Y для людей одного поколения
$genYMap = [];
$genMismatchErrors = 0;
foreach ($fullNodes as $n) {
    $gen = $n['generation'];
    if (!isset($genYMap[$gen])) {
        $genYMap[$gen] = $n['y'];
    } elseif ($genYMap[$gen] !== $n['y']) {
        $genMismatchErrors++;
    }
}
assertTest($genMismatchErrors === 0, "Все люди одного поколения имеют одинаковый Y (ошибок смещения ряда: {$genMismatchErrors})");

// ПРОВЕРКА ПЕРЕСЕЧЕНИЙ ПРЯМОУГОЛЬНИКОВ НА ВСЕХ 270 КАРТОЧКАХ (36 315 ПАР!)
$fullIntersections = $layoutService->countIntersections($fullNodes);
assertTest($fullIntersections === 0, "Количество пересечений карточек на полном объеме (270 человек): {$fullIntersections} (СТРОГО 0)");

// Проверка наличия линий связей в полном режиме
assertTest(count($fullLayout['edges']['marriages']) > 0, "Линии браков в полном режиме присутствуют (" . count($fullLayout['edges']['marriages']) . " линий)");
assertTest(count($fullLayout['edges']['parent_child']) > 0, "Шины связей к детям в полном режиме присутствуют (" . count($fullLayout['edges']['parent_child']) . " линий)");

// Проверка плашек пар и ореолов
assertTest(count($fullLayout['couples']) > 0, "Общие плашки семейных пар сформированы (" . count($fullLayout['couples']) . " плашек)");
assertTest(count($fullLayout['auras']) > 0, "Семейные ореолы сформированы (" . count($fullLayout['auras']) . " ореолов)");

// Проверка границ холста
$bounds = $fullLayout['bounds'];
assertTest($bounds['width'] > 0 && $bounds['height'] > 0, "Границы холста рассчитаны корректно: {$bounds['width']} x {$bounds['height']} px");
assertTest($bounds['minX'] >= 1000, "Минимальный X строго >= 1000 ({$bounds['minX']})");

echo "\n-------------------------------------------------------\n";
echo "Результат: {$passed} из {$total} проверок пройдено успешно!\n";
if ($passed === $total) {
    echo "ВСЕ ТЕСТЫ АЛГОРИТМА РАСКЛАДКИ ДРЕВА ПРОЙДЕНЫ [SUCCESS]\n";
} else {
    echo "ЕСТЬ ОШИБКИ [FAILED]\n";
    exit(1);
}
echo "-------------------------------------------------------\n";
