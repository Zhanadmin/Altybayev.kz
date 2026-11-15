<?php

declare(strict_types=1);

/**
 * TASK-013 (шаг 10): возраст вместо года рождения.
 * Живой — «38 жас» / «38 лет» (склонение год/года/лет, «≈» если известен только год),
 * ушедший — «1932–2005 · 73 жыл ғұмыр» / «1932–2005 · прожил 73 года».
 *
 * Запуск: C:\PHP\php.exe tests\Task013LifeLabelTest.php  (без БД)
 */

require_once __DIR__ . '/../app/Core/I18n.php';

use App\Core\I18n;

$passed = 0;
$total = 0;

function tl(string $got, string $want, string $label): void
{
    global $passed, $total;
    $total++;
    if ($got === $want) {
        $passed++;
        echo "  [OK] {$label}: {$got}\n";
    } else {
        echo "  [FAIL] {$label}: получено «{$got}», ожидалось «{$want}»\n";
    }
}

$today = '2026-11-15';

echo "Склонение (ru):\n";
tl(I18n::ruYears(1), '1 год', '1');
tl(I18n::ruYears(3), '3 года', '3');
tl(I18n::ruYears(5), '5 лет', '5');
tl(I18n::ruYears(11), '11 лет', '11');
tl(I18n::ruYears(14), '14 лет', '14');
tl(I18n::ruYears(21), '21 год', '21');
tl(I18n::ruYears(38), '38 лет', '38');
tl(I18n::ruYears(73), '73 года', '73');
tl(I18n::ruYears(111), '111 лет', '111');

echo "Живые:\n";
tl(I18n::lifeLabel(['birth_date' => '1988-01-10'], 'kk', $today)['short'], '38 жас', 'полная дата, ДР прошёл (kk)');
tl(I18n::lifeLabel(['birth_date' => '1988-12-20'], 'kk', $today)['short'], '37 жас', 'полная дата, ДР ещё не был (kk)');
tl(I18n::lifeLabel(['birth_date' => '1988-11-15'], 'ru', $today)['short'], '38 лет', 'ДР сегодня (ru)');
tl(I18n::lifeLabel(['birth_year' => 1988], 'ru', $today)['short'], '≈38 лет', 'только год — примерно (ru)');
tl(I18n::lifeLabel(['birth_date' => '15.11.2005'], 'ru', $today)['short'], '21 год', 'формат дд.мм.гггг (ru)');
tl(I18n::lifeLabel([], 'kk', $today)['short'], '', 'нет данных');
tl(I18n::lifeLabel(['birth_date' => '1988-01-10'], 'ru', $today)['title'], 'Дата рождения: 10 января 1988', 'подсказка с полной датой');

echo "Ушедшие:\n";
tl(I18n::lifeLabel(['is_deceased' => 1, 'birth_year' => 1932, 'death_year' => 2005, 'gender' => 'M'], 'kk', $today)['short'], '1932–2005 · 73 жыл ғұмыр', 'годы (kk)');
tl(I18n::lifeLabel(['is_deceased' => 1, 'birth_year' => 1932, 'death_year' => 2005, 'gender' => 'M'], 'ru', $today)['short'], '1932–2005 · прожил 73 года', 'годы, мужчина (ru)');
tl(I18n::lifeLabel(['is_deceased' => 1, 'birth_year' => 1940, 'death_year' => 2015, 'gender' => 'F'], 'ru', $today)['short'], '1940–2015 · прожила 75 лет', 'годы, женщина (ru)');
tl(I18n::lifeLabel(['birth_date' => '1932-06-01', 'death_date' => '2005-03-01', 'gender' => 'M'], 'kk', $today)['short'], '1932–2005 · 72 жыл ғұмыр', 'полные даты, без is_deceased (kk)');
tl(I18n::lifeLabel(['is_deceased' => 1, 'birth_year' => 1932], 'kk', $today)['short'], '1932–?', 'неизвестен год смерти');
tl(I18n::lifeLabel(['is_deceased' => 1], 'kk', $today)['short'], '', 'ушедший без дат');

echo "\nИтог: {$passed} из {$total}\n";
exit($passed === $total ? 0 : 1);
