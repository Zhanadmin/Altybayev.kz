<?php

declare(strict_types=1);

/**
 * TASK-034: в ответе Үш жұрт у мужа, жены, детей и «отдельных» есть full_name (иначе окно показывало «undefined»).
 * Запуск: C:\PHP\php.exe tests\Task034UshZhurtNamesTest.php
 */

require_once __DIR__ . '/../app/Config/KinshipDictionary.php';
require_once __DIR__ . '/../app/Services/KinshipService.php';

use App\Services\KinshipService;

$passed = 0;
$total = 0;
function t34(bool $c, string $l): void
{
    global $passed, $total;
    $total++;
    if ($c) { $passed++; echo "  [OK] " . $l . "\n"; } else { echo "  [FAIL] " . $l . "\n"; }
}

$P = fn(int $id, string $n, string $g, int $y, int $f = 0, int $m = 0) => [
    'id' => $id, 'first_name' => $n, 'last_name' => 'Алтыбаев', 'gender' => $g, 'birth_year' => $y,
    'father_id' => $f ?: null, 'mother_id' => $m ?: null, 'clan_ru' => 'Керей',
];
$persons = [
    $P(1, 'Отец', 'M', 1950), $P(2, 'Мать', 'F', 1955), $P(3, 'Жандос', 'M', 1980, 1, 2),
    $P(4, 'Сестра', 'F', 1983, 1, 2),
];
$marriages = [['id' => 1, 'husband_id' => 1, 'wife_id' => 2, 'status' => 'married']];

$svc = new KinshipService();
$res = $svc->getUshZhurtFamilies(3, array_column($persons, null, 'id'), $marriages);

$checked = 0;
foreach ($res['order'] as $key) {
    $z = $res[$key . '_zhurt'] ?? [];
    foreach ($z['families'] ?? [] as $f) {
        foreach (['husband', 'wife'] as $role) {
            if ($f[$role]) {
                $checked++;
                t34(($f[$role]['full_name'] ?? '') !== '', $key . ': ' . $role . ' имеет full_name');
            }
        }
        foreach ($f['children'] as $c) {
            $checked++;
            t34(($c['person']['full_name'] ?? '') !== '', $key . ': ребёнок имеет full_name');
        }
    }
    foreach ($z['standalone'] ?? [] as $s) {
        $checked++;
        t34(($s['person']['full_name'] ?? '') !== '', $key . ': отдельный имеет full_name');
    }
}
t34($checked > 0, 'Проверена хотя бы одна запись');
t34(($res['base_person']['full_name'] ?? '') === 'Жандос Алтыбаев', 'base_person.full_name');

echo "\n" . $passed . '/' . $total . "\n";
exit($passed === $total ? 0 : 1);
