<?php

declare(strict_types=1);

/**
 * TASK-017 (замечание): мать — в Өз жұрты (мужчина) / Төркіні (женщина) рядом с отцом,
 * а в Нағашы жұрты — только её родня (родители, братья/сёстры), но не она сама в папке своих родителей.
 *
 * Запуск: C:\PHP\php.exe tests\Task017MotherNotInNagashyTest.php
 */

require_once __DIR__ . '/../app/Config/KinshipDictionary.php';
require_once __DIR__ . '/../app/Services/KinshipService.php';

use App\Services\KinshipService;

$passed = 0;
$total = 0;
function t17(bool $c, string $l): void
{
    global $passed, $total;
    $total++;
    if ($c) { $passed++; echo "  [OK] {$l}\n"; } else { echo "  [FAIL] {$l}\n"; }
}
function t17childIds(array $zhurt): array
{
    $ids = [];
    foreach ($zhurt['families'] as $f) {
        foreach ($f['children'] as $c) $ids[] = (int)$c['person']['id'];
    }
    return $ids;
}
function t17allIds(array $zhurt): array
{
    $ids = t17childIds($zhurt);
    foreach ($zhurt['families'] as $f) {
        if ($f['husband']) $ids[] = (int)$f['husband']['id'];
        if ($f['wife']) $ids[] = (int)$f['wife']['id'];
    }
    foreach ($zhurt['standalone'] as $s) $ids[] = (int)$s['person']['id'];
    return $ids;
}

$P = fn(int $id, string $n, string $g, int $y, int $f = 0, int $m = 0) => [
    'id' => $id, 'first_name' => $n, 'last_name' => '', 'gender' => $g, 'birth_year' => $y,
    'father_id' => $f ?: null, 'mother_id' => $m ?: null, 'clan_ru' => 'Керей',
];
// 40+41 — родители матери (2); 2 + 1 — родители Жандоса (3) и Айгуль (4); 42 — брат матери
$persons = [
    $P(1, 'Отец', 'M', 1950), $P(2, 'Мать', 'F', 1955, 40, 41),
    $P(3, 'Сын', 'M', 1979, 1, 2), $P(4, 'Дочь', 'F', 1982, 1, 2),
    $P(40, 'Дед', 'M', 1925), $P(41, 'Бабушка', 'F', 1928), $P(42, 'ДядяБрат', 'M', 1952, 40, 41),
];
$marriages = [
    ['id' => 1, 'husband_id' => 1, 'wife_id' => 2, 'status' => 'married'],
    ['id' => 2, 'husband_id' => 40, 'wife_id' => 41, 'status' => 'married'],
];

$svc = new KinshipService();
foreach ([3, 4] as $baseId) {
    $label = $baseId === 3 ? 'мужчина' : 'женщина';
    $d = $svc->getUshZhurtFamilies($baseId, $persons, $marriages);
    $ownKey = $baseId === 3 ? 'oz' : 'torkin';
    $nag = t17allIds($d['nagashy_zhurt']);
    $own = t17allIds($d[$ownKey . '_zhurt']);
    t17(!in_array(2, $nag, true), "{$label}: мать не в Нағашы жұрты");
    t17(in_array(2, $own, true), "{$label}: мать в " . ($baseId === 3 ? 'Өз жұрты' : 'Төркіні'));
    t17(in_array(1, $own, true), "{$label}: отец рядом с матерью");
    t17(in_array(40, $nag, true) && in_array(41, $nag, true), "{$label}: родители матери в Нағашы");
    t17(in_array(42, $nag, true), "{$label}: брат матери в Нағашы");
}

echo "\nИтого: {$passed}/{$total}\n";
exit($passed === $total ? 0 : 1);
