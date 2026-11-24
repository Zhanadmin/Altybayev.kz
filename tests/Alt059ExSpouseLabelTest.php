<?php
// ALT-059: бывшим супругам вместо «Жезде» — «<Ребёнок>-дың әкесі»; нынешнему — «Жезде».
require_once __DIR__ . '/../app/Config/KinshipDictionary.php';
require_once __DIR__ . '/../app/Config/KinshipGlossary.php';
require_once __DIR__ . '/../app/Services/KinshipService.php';

use App\Services\KinshipService;

$fail = 0;
function chk(bool $ok, string $msg): void { global $fail; echo ($ok ? 'OK   ' : 'FAIL ') . $msg . "\n"; if (!$ok) $fail++; }

// 1 Жандос (база, 1990), 2 Жанна (сестра, старше, 1980), 3 Серик (бывший), 4 Бауржан (бывший, без детей), 5 Виталий (нынешний)
// 6 Таир — сын Жанны и Серика, 7 Али — сын Жанны и Серика
$mk = fn($id, $fn, $g, $y, $f = null, $m = null) => ['id' => $id, 'first_name' => $fn, 'last_name' => '', 'gender' => $g, 'birth_year' => $y, 'father_id' => $f, 'mother_id' => $m];
$persons = [
    $mk(10, 'Отец', 'M', 1950), $mk(11, 'Мать', 'F', 1955),
    $mk(1, 'Жандос', 'M', 1990, 10, 11), $mk(2, 'Жанна', 'F', 1980, 10, 11),
    $mk(3, 'Серик', 'M', 1975), $mk(4, 'Бауржан', 'M', 1976), $mk(5, 'Виталий', 'M', 1978),
    $mk(6, 'Таир', 'M', 2002, 3, 2), $mk(7, 'Әли', 'M', 2005, 3, 2),
];
$marriages = [
    ['husband_id' => 3, 'wife_id' => 2, 'status' => 'divorced', 'divorce_date' => '2008-01-01'],
    ['husband_id' => 4, 'wife_id' => 2, 'status' => 'divorced', 'divorce_date' => '2012-01-01'],
    ['husband_id' => 5, 'wife_id' => 2, 'status' => 'married'],
];
$svc = new KinshipService();
$k = $svc->calculateAllKinships(1, $persons, $marriages);
echo "Серик: {$k[3]['term_kk']} | {$k[3]['term_ru']}\n";
echo "Бауржан: {$k[4]['term_kk']} | {$k[4]['term_ru']}\n";
echo "Виталий: {$k[5]['term_kk']}\n";
chk($k[3]['term_kk'] === 'Таир мен Әлидің әкесі', 'Серик — отец Таира и Әли');
chk($k[3]['term_ru'] === 'Отец Таира и Али' || str_starts_with($k[3]['term_ru'], 'Отец Таира'), 'Серик ru');
chk($k[4]['term_kk'] === 'Бұрынғы жұбайы', 'Бауржан без детей — Бұрынғы жұбайы');
chk(str_contains($k[5]['term_kk'], 'Жезде'), 'Виталий — Жезде');
chk(KinshipService::kkGenitive('Санжар') === 'Санжардың', 'Санжардың');
chk(KinshipService::kkGenitive('Серік') === 'Серіктің', 'Серіктің');
chk(KinshipService::kkGenitive('Әли') === 'Әлидің', 'Әлидің');
chk(KinshipService::ruGenitive('Таир') === 'Таира', 'Таира');
exit($fail ? 1 : 0);
