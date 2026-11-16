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

echo "TASK-023 (ДР вчера/завтра/29 февраля):\n";
tl(I18n::lifeLabel(['birth_date' => '1986-11-14'], 'kk', $today)['short'], '40 жас', 'ДР вчера');
tl(I18n::lifeLabel(['birth_date' => '1986-11-16'], 'kk', $today)['short'], '39 жас', 'ДР завтра — ещё 39');
tl(I18n::lifeLabel(['birth_date' => '2000-02-29'], 'kk', '2026-02-27')['short'], '25 жас', '29.02: 27 февраля — ещё не было');
tl(I18n::lifeLabel(['birth_date' => '2000-02-29'], 'kk', '2026-02-28')['short'], '26 жас', '29.02: в невисокосный год ДР = 28.02');
tl(I18n::lifeLabel(['birth_date' => '2000-02-29'], 'kk', '2028-02-28')['short'], '27 жас', '29.02: в високосный 28.02 ещё не ДР');
tl(I18n::lifeLabel(['birth_date' => '2000-02-29'], 'kk', '2028-02-29')['short'], '28 жас', '29.02: в високосный 29.02 — ДР');

echo "Вместе (TASK-023):\n";
tl(I18n::togetherLabel('1979-03-08', null, 'kk', '2026-03-07'), '46 жыл бірге', 'свадьба ещё не отмечена в этом году (kk)');
tl(I18n::togetherLabel('1979-03-08', null, 'ru', '2026-03-08'), '47 лет вместе', 'годовщина сегодня (ru)');
tl(I18n::togetherLabel('1979', null, 'ru', '2026-11-15'), '≈47 лет вместе', 'только год свадьбы (ru)');

echo "Ушедшие:\n";
tl(I18n::lifeLabel(['is_deceased' => 1, 'birth_year' => 1932, 'death_year' => 2005, 'gender' => 'M'], 'kk', $today)['short'], '1932–2005 · ≈73 жыл ғұмыр', 'только годы — примерно (kk)');
tl(I18n::lifeLabel(['is_deceased' => 1, 'birth_year' => 1932, 'death_year' => 2005, 'gender' => 'M'], 'ru', $today)['short'], '1932–2005 · прожил ≈73 года', 'только годы, мужчина (ru)');
tl(I18n::lifeLabel(['is_deceased' => 1, 'birth_year' => 1940, 'death_year' => 2015, 'gender' => 'F'], 'ru', $today)['short'], '1940–2015 · прожила ≈75 лет', 'только годы, женщина (ru)');
tl(I18n::lifeLabel(['birth_date' => '1940-05-10', 'death_date' => '2015-05-09', 'is_deceased' => 1, 'gender' => 'F'], 'ru', $today)['short'], '1940–2015 · прожила 74 года', 'умерла за день до 75-летия (ru)');
tl(I18n::lifeLabel(['birth_date' => '1940-05-10', 'death_date' => '2015-05-10', 'is_deceased' => 1, 'gender' => 'F'], 'ru', $today)['short'], '1940–2015 · прожила 75 лет', 'умерла в день 75-летия (ru)');
tl(I18n::lifeLabel(['birth_date' => '1932-06-01', 'death_date' => '2005-03-01', 'gender' => 'M'], 'kk', $today)['short'], '1932–2005 · 72 жыл ғұмыр', 'полные даты, без is_deceased (kk)');
tl(I18n::lifeLabel(['is_deceased' => 1, 'birth_year' => 1932], 'kk', $today)['short'], '1932–?', 'неизвестен год смерти');
tl(I18n::lifeLabel(['is_deceased' => 1], 'kk', $today)['short'], '', 'ушедший без дат');

echo "\nИтог: {$passed} из {$total}\n";
exit($passed === $total ? 0 : 1);
