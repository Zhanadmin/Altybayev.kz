<?php

declare(strict_types=1);

/**
 * TASK-017: 500 при раскрытии папки семьи в «Үш жұрт» (views/persons/show.php).
 * Причина: замыкание $renderZhurtPane вызывало $pcLifeMeta, не передав его в use (...) → Error.
 * Тест: все хелперы $pc*, которые используются внутри тела $renderZhurtPane, обязаны быть в его use (...).
 * Плюс: lifeLabel не падает на людях без дат / с '0000-00-00' / ушедших.
 *
 * Запуск: C:\PHP\php.exe tests\Task017ShowClosureScopeTest.php  (без БД)
 */

require_once __DIR__ . '/../app/Core/I18n.php';

use App\Core\I18n;

$fails = 0;
function t17(bool $ok, string $label): void
{
    global $fails;
    if ($ok) { echo "  [OK] {$label}\n"; } else { $fails++; echo "  [FAIL] {$label}\n"; }
}

$src = (string)file_get_contents(__DIR__ . '/../views/persons/show.php');
$start = strpos($src, '$renderZhurtPane = function');
t17($start !== false, 'замыкание $renderZhurtPane найдено');
$end = strpos($src, "\n                    };", (int)$start);
$closure = substr($src, (int)$start, (int)$end - (int)$start);
preg_match('/use \(([^)]*)\)/', $closure, $m);
$uses = $m[1] ?? '';
preg_match_all('/\$(pc[A-Za-z]+)\s*\(/', $closure, $calls);
foreach (array_unique($calls[1]) as $name) {
    t17(strpos($uses, '$' . $name) !== false, "\${$name} используется в теле и передан в use (...)");
}

$cases = [
    'без дат'            => [],
    'нулевая дата'       => ['birth_date' => '0000-00-00', 'death_date' => '0000-00-00'],
    'null-поля'          => ['birth_date' => null, 'birth_year' => null, 'death_year' => null, 'is_deceased' => null],
    'ушедший без дат'    => ['is_deceased' => 1],
    'ушедший с годами'   => ['is_deceased' => 1, 'birth_year' => 1930, 'death_year' => 1999, 'gender' => 'M'],
    'мусор в дате'       => ['birth_date' => 'abc', 'gender' => 'F'],
];
foreach ($cases as $label => $row) {
    foreach (['kk', 'ru'] as $lg) {
        try {
            $r = I18n::lifeLabel($row, $lg, '2026-11-15');
            t17(is_string($r['short']) && is_string($r['title']), "lifeLabel: {$label} ({$lg})");
        } catch (Throwable $e) {
            t17(false, "lifeLabel: {$label} ({$lg}) — " . $e->getMessage());
        }
    }
}

echo $fails === 0 ? "OK\n" : "FAILED: {$fails}\n";
exit($fails === 0 ? 0 : 1);
