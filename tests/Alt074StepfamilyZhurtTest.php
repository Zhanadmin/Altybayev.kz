<?php
// ALT-074: бывшие супруги жены и пасынки — не жұрт; кровная родня жены — Қайын жұрты (фокус — Виталий).
require_once __DIR__ . '/../app/Config/KinshipDictionary.php';
require_once __DIR__ . '/../app/Config/KinshipGlossary.php';
require_once __DIR__ . '/../app/Services/KinshipService.php';

use App\Services\KinshipService;

$fail = 0;
function chk(bool $ok, string $msg): void { global $fail; echo ($ok ? 'OK   ' : 'FAIL ') . $msg . "\n"; if (!$ok) $fail++; }

$mk = fn($id, $fn, $g, $y, $f = null, $m = null) => ['id' => $id, 'first_name' => $fn, 'last_name' => '', 'gender' => $g, 'birth_year' => $y, 'father_id' => $f, 'mother_id' => $m];
// 5 Виталий (фокус), 2 Жанна (жена), её родители 10/11, брат 1; бывшие 3, 4; сыновья 6, 7
$persons = [
    $mk(10, 'Отец', 'M', 1950), $mk(11, 'Мать', 'F', 1955),
    $mk(1, 'Жандос', 'M', 1990, 10, 11), $mk(2, 'Жанна', 'F', 1980, 10, 11),
    $mk(3, 'Серик', 'M', 1975), $mk(4, 'Бауржан', 'M', 1976), $mk(5, 'Виталий', 'M', 1978),
    $mk(6, 'Таир', 'M', 2002, 3, 2), $mk(7, 'Санжар', 'M', 2005, 4, 2),
];
$marriages = [
    ['husband_id' => 3, 'wife_id' => 2, 'status' => 'divorced', 'divorce_date' => '2008-01-01'],
    ['husband_id' => 4, 'wife_id' => 2, 'status' => 'divorced', 'divorce_date' => '2012-01-01'],
    ['husband_id' => 5, 'wife_id' => 2, 'status' => 'married'],
];
$k = (new KinshipService())->calculateAllKinships(5, $persons, $marriages);
foreach ([3, 4, 6, 7, 10, 11, 1] as $id) {
    echo $id . ': ' . ($k[$id]['term_kk'] ?? '?') . ' | жұрт=' . ($k[$id]['zhurt'] ?? '?') . "\n";
}
chk(($k[3]['zhurt'] ?? 'x') === '', 'Серик — без жұрта');
chk(($k[4]['zhurt'] ?? 'x') === '', 'Бауржан — без жұрта');
chk(($k[6]['zhurt'] ?? 'x') === '', 'Таир — без жұрта');
chk(($k[7]['zhurt'] ?? 'x') === '', 'Санжар — без жұрта');
chk(($k[10]['zhurt'] ?? '') === 'Қайын жұрты', 'Отец Жанны — Қайын жұрты');
chk(($k[11]['zhurt'] ?? '') === 'Қайын жұрты', 'Мать Жанны — Қайын жұрты');
chk(($k[1]['zhurt'] ?? '') === 'Қайын жұрты', 'Брат Жанны — Қайын жұрты');
exit($fail ? 1 : 0);
