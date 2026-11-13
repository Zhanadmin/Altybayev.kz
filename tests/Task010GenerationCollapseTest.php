<?php

declare(strict_types=1);

echo "=======================================================\n";
echo "  TASK-010: Тест сворачивания по поколениям на /persons\n";
echo "=======================================================\n\n";

$phpBinary = 'C:\\PHP\\php.exe';
$runnerPath = __DIR__ . '/render_persons_runner.php';

function renderPersonsPage(string $lang = 'kk', string $q = ''): string {
    global $phpBinary, $runnerPath;
    $cmd = "\"{$phpBinary}\" \"{$runnerPath}\" " . escapeshellarg($lang) . " " . escapeshellarg($q);
    $output = shell_exec($cmd);
    return $output ?? '';
}

$passed = 0;
$total = 0;

function assertTest(bool $condition, string $name, string $details = ''): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [OK] {$name}\n";
    } else {
        echo "  [FAIL] {$name}: {$details}\n";
    }
}

// 1. Тест казахской версии /persons
$htmlKk = renderPersonsPage('kk');

assertTest(str_contains($htmlKk, 'id="btnCollapseAll"'), 'Кнопка "Барлығын жию" присутствует (ID btnCollapseAll)');
assertTest(str_contains($htmlKk, 'Барлығын жию'), 'Текст кнопки "Барлығын жию" на казахском');
assertTest(str_contains($htmlKk, 'id="btnExpandAll"'), 'Кнопка "Барлығын ашу" присутствует (ID btnExpandAll)');
assertTest(str_contains($htmlKk, 'Барлығын ашу'), 'Текст кнопки "Барлығын ашу" на казахском');

assertTest(str_contains($htmlKk, 'data-gen-key="ancestors"'), 'Секция поколения ancestors присутствует');
assertTest(str_contains($htmlKk, 'data-gen-key="1"'), 'Секция поколения 1 присутствует');
assertTest(str_contains($htmlKk, 'data-gen-key="2"'), 'Секция поколения 2 присутствует');
assertTest(str_contains($htmlKk, 'data-gen-key="3"'), 'Секция поколения 3 присутствует');

assertTest(str_contains($htmlKk, 'class="pp-group-head"'), 'Заголовок группы содержит класс pp-group-head');
assertTest(str_contains($htmlKk, 'role="button"'), 'Заголовок группы доступен как role="button"');
assertTest(str_contains($htmlKk, 'aria-expanded="true"'), 'Заголовок группы имеет aria-expanded="true"');
assertTest(str_contains($htmlKk, 'onclick="toggleGeneration('), 'Заголовок группы имеет onclick="toggleGeneration(...)"');
assertTest(str_contains($htmlKk, 'pp-group-chevron'), 'Заголовок группы имеет шеврон pp-group-chevron');
assertTest(str_contains($htmlKk, 'pp-group-body'), 'Таблица обернута в класс pp-group-body');

// 2. Проверка JavaScript логики
assertTest(str_contains($htmlKk, 'shezhire_collapsed_generations'), 'JS использует ключ localStorage: shezhire_collapsed_generations');
assertTest(str_contains($htmlKk, 'window.toggleGeneration'), 'Определена глобальная функция toggleGeneration');
assertTest(str_contains($htmlKk, 'window.collapseAllGenerations'), 'Определена глобальная функция collapseAllGenerations');
assertTest(str_contains($htmlKk, 'window.expandAllGenerations'), 'Определена глобальная функция expandAllGenerations');
assertTest(str_contains($htmlKk, 'window.ensureGenerationExpanded'), 'Определена глобальная функция ensureGenerationExpanded');
assertTest(str_contains($htmlKk, 'searchInput.addEventListener(\'input\''), 'Поиск по странице слушает событие input');
assertTest(str_contains($htmlKk, 'setGroupState(group, false)'), 'Поиск автоматически раскрывает группу с найденным человеком');

// 3. Тест русской версии /persons
$htmlRu = renderPersonsPage('ru');

assertTest(str_contains($htmlRu, 'Свернуть все'), 'Текст кнопки "Свернуть все" на русском');
assertTest(str_contains($htmlRu, 'Развернуть все'), 'Текст кнопки "Развернуть все" на русском');
assertTest(str_contains($htmlRu, 'Предки'), 'Заголовок поколения "Предки" на русском');

// 4. Тест серверного поиска ?q=Жандос
$htmlSearch = renderPersonsPage('ru', 'Жандос');

assertTest(str_contains($htmlSearch, 'Жандос'), 'Поиск возвращает карточку Жандоса');
assertTest(str_contains($htmlSearch, 'initialQuery !== \'\''), 'JS проверяет initialQuery для автоматического раскрытия групп при серверном поиске');

// 5. Проверка CSS стилей в design-system.css
$css = file_get_contents(__DIR__ . '/../public/assets/css/design-system.css');
assertTest(str_contains($css, '.pp-group-head'), 'CSS содержит правила .pp-group-head');
assertTest(str_contains($css, '.pp-group.is-collapsed'), 'CSS содержит правила .pp-group.is-collapsed');
assertTest(str_contains($css, '.pp-group-chevron'), 'CSS содержит правила .pp-group-chevron');
assertTest(str_contains($css, 'transform: rotate(-90deg)'), 'CSS поворачивает шеврон при сворачивании');
assertTest(str_contains($css, '.pp-group.is-collapsed .pp-group-body'), 'CSS скрывает .pp-group-body при свёрнутом состоянии');

echo "\n-------------------------------------------------------\n";
echo "Результат: {$passed} из {$total} тестов пройдено успешно!\n";
if ($passed === $total) {
    echo "TASK-010 ПОЛНОСТЬЮ ПРОТЕСТИРОВАН И ГОТОВ [SUCCESS]\n";
} else {
    echo "ИМЕЮТСЯ ОШИБКИ [FAIL]\n";
    exit(1);
}
echo "-------------------------------------------------------\n";
