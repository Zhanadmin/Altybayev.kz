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
// 1. ТЕСТИРОВАНИЕ РЕЖИМА ОКРЕСТНОСТИ (FOCAL MODE)
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
// 2. ТЕСТИРОВАНИЕ ПОЛНОГО РЕЖИМА (FULL MODE: 270 ПЕРСОН)
// ========================================================
echo "\n--- 2. Полный режим (Full Mode: все 270 персон) ---\n";
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

// ПРОВЕРКА ПЕРЕСЕЧЕНИЙ ПРЯМОУГОЛЬНИКОВ НА ВСЕХ 270 КАРТОЧКАХ (36 315 ПАР!)
$fullIntersections = $layoutService->countIntersections($fullNodes);
assertTest($fullIntersections === 0, "Количество пересечений карточек на полном объеме (270 человек): {$fullIntersections} (СТРОГО 0)");

// Проверка границ холста
$bounds = $fullLayout['bounds'];
assertTest($bounds['width'] > 0 && $bounds['height'] > 0, "Границы холста рассчитаны корректно: {$bounds['width']} x {$bounds['height']} px");

echo "\n-------------------------------------------------------\n";
echo "Результат: {$passed} из {$total} проверок пройдено успешно!\n";
if ($passed === $total) {
    echo "ВСЕ ТЕСТЫ АЛГОРИТМА РАСКЛАДКИ ДРЕВА ПРОЙДЕНЫ [SUCCESS]\n";
} else {
    echo "ЕСТЬ ОШИБКИ [FAILED]\n";
    exit(1);
}
echo "-------------------------------------------------------\n";
