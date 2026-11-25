<?php
// ALT-070: при любом фокусе братья жены в одном ряду с ней и мужем
require __DIR__ . '/../app/Services/PatronymicService.php';
require __DIR__ . '/../app/Services/TreeLayoutService.php';

use App\Services\TreeLayoutService;

$P = [
    [1, 'GF', 'M', null, null], [2, 'GM', 'F', null, null],
    [3, 'Father', 'M', 1, 2], [4, 'Mother', 'F', null, null],
    [5, 'Zhandos', 'M', 3, 4],
    [6, 'AF', 'M', null, null], [7, 'AM', 'F', null, null],
    [8, 'Asila', 'F', 6, 7], [9, 'Br1', 'M', 6, 7], [10, 'Br2', 'M', 6, 7], [11, 'Zhanna', 'F', 6, 7],
];
$persons = [];
foreach ($P as $r) {
    $persons[] = ['id' => $r[0], 'first_name' => $r[1], 'last_name' => '', 'gender' => $r[2],
        'father_id' => $r[3], 'mother_id' => $r[4], 'birth_year' => '19' . sprintf('%02d', $r[0])];
}
$marr = [
    ['husband_id' => 5, 'wife_id' => 8], ['husband_id' => 3, 'wife_id' => 4],
    ['husband_id' => 1, 'wife_id' => 2], ['husband_id' => 6, 'wife_id' => 7],
];
$svc = new TreeLayoutService();
$fail = 0;
foreach ([5, 8, 9, 11, 3, 6] as $f) {
    foreach (['focal', 'all'] as $mode) {
        $r = $svc->calculateLayout($persons, $marr, $f, $mode);
        $n = $r['nodes_by_id'];
        $ok = true;
        if (isset($n[5], $n[8])) $ok = $ok && $n[5]['y'] === $n[8]['y'];
        foreach ([9, 10, 11] as $b) {
            if (isset($n[8], $n[$b])) $ok = $ok && $n[$b]['y'] === $n[8]['y'];
        }
        if (isset($n[3], $n[5])) $ok = $ok && $n[3]['y'] < $n[5]['y'];
        if (isset($n[6], $n[8])) $ok = $ok && $n[6]['y'] < $n[8]['y'];
        echo "focus=$f $mode: " . ($ok ? 'OK' : 'FAIL') . ' ys=' . implode(',', array_map(fn($x) => $x['y'], $n)) . "\n";
        if (!$ok) $fail++;
    }
}
exit($fail ? 1 : 0);
