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

// Жена с родителями и братом: сама жена не должна быть ни в одном жұрте, её родня — в Қайын
$persons2 = [
    $P(1, 'Отец', 'M', 1950), $P(2, 'Мать', 'F', 1955), $P(3, 'Жандос', 'M', 1980, 1, 2),
    $P(5, 'Тесть', 'M', 1952), $P(6, 'Теща', 'F', 1956), $P(7, 'Жена', 'F', 1982, 5, 6),
    $P(8, 'Шурин', 'M', 1985, 5, 6),
];
$marriages2 = [
    ['id' => 1, 'husband_id' => 1, 'wife_id' => 2, 'status' => 'married'],
    ['id' => 2, 'husband_id' => 5, 'wife_id' => 6, 'status' => 'married'],
    ['id' => 3, 'husband_id' => 3, 'wife_id' => 7, 'status' => 'married'],
];
$res2 = $svc->getUshZhurtFamilies(3, array_column($persons2, null, 'id'), $marriages2);
$inZhurt = [];
foreach ($res2['order'] as $key) {
    $z = $res2[$key . '_zhurt'] ?? [];
    foreach ($z['families'] ?? [] as $f) {
        foreach (['husband', 'wife'] as $role) {
            if ($f[$role]) { $inZhurt[(int)$f[$role]['id']][] = $key; }
        }
        foreach ($f['children'] as $c) { $inZhurt[(int)$c['person']['id']][] = $key; }
    }
    foreach ($z['standalone'] ?? [] as $s) { $inZhurt[(int)$s['person']['id']][] = $key; }
}
t34(!isset($inZhurt[7]), 'жена (id 7) не входит ни в один жұрт');
t34(isset($inZhurt[5]) && in_array('kaiyn', $inZhurt[5], true), 'тесть — в Қайын жұрт');
t34(isset($inZhurt[8]) && in_array('kaiyn', $inZhurt[8], true), 'шурин — в Қайын жұрт');
t34(!isset($inZhurt[3]) || !array_intersect($inZhurt[3], ['kaiyn', 'nagashy']), 'сам человек не в Қайын/Нағашы');
t34(($res2['own_family']['spouses'][0]['full_name'] ?? '') !== '', 'own_family.spouses имеет full_name');

echo "\n" . $passed . '/' . $total . "\n";
exit($passed === $total ? 0 : 1);
