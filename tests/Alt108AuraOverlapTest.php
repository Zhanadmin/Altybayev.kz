<?php
// ALT-108: рамки семей не пересекаются; дети — под своим отцом; рамка Виталия — только пара и общие дети
require __DIR__ . '/../app/Services/PatronymicService.php';
require __DIR__ . '/../app/Services/TreeLayoutService.php';

use App\Services\TreeLayoutService;

// 1,2 — родители; 3 Рустам (+4 жена, дети 5,6); 7 Виталий (+8 Жанна); Жанна: бывшие мужья 9 (сын Таир 12), 10 (сын Санжар 13);
// общие дети Виталия и Жанны 14, 15; 16 Дастан (+17, дети 18)
$P = [
    [1, 'F', 'M', null, null], [2, 'M', 'F', null, null],
    [3, 'Rustam', 'M', 1, 2], [4, 'RW', 'F', null, null], [5, 'R1', 'M', 3, 4], [6, 'R2', 'F', 3, 4],
    [7, 'Vitaly', 'M', 1, 2], [8, 'Zhanna', 'F', null, null],
    [9, 'Ex1', 'M', null, null], [10, 'Ex2', 'M', null, null],
    [12, 'Tair', 'M', 9, 8], [13, 'Sanzhar', 'M', 10, 8],
    [14, 'V1', 'M', 7, 8], [15, 'V2', 'F', 7, 8],
    [16, 'Dastan', 'M', 1, 2], [17, 'DW', 'F', null, null], [18, 'D1', 'M', 16, 17],
];
$persons = [];
foreach ($P as $r) {
    $persons[] = ['id' => $r[0], 'first_name' => $r[1], 'last_name' => '', 'gender' => $r[2],
        'father_id' => $r[3], 'mother_id' => $r[4], 'birth_year' => (string)(1900 + $r[0] * 3)];
}
$marr = [
    ['husband_id' => 1, 'wife_id' => 2], ['husband_id' => 3, 'wife_id' => 4],
    ['husband_id' => 16, 'wife_id' => 17],
    ['husband_id' => 9, 'wife_id' => 8, 'start_date' => '1990-01-01', 'status' => 'divorced', 'divorce_date' => '1995-01-01'],
    ['husband_id' => 10, 'wife_id' => 8, 'start_date' => '1996-01-01', 'status' => 'divorced', 'divorce_date' => '2000-01-01'],
    ['husband_id' => 7, 'wife_id' => 8, 'start_date' => '2001-01-01'],
];
$svc = new TreeLayoutService();
$fail = 0;
$ov = function ($a, $b) {
    return $a['x'] < $b['x'] + $b['width'] && $b['x'] < $a['x'] + $a['width']
        && $a['y'] < $b['y'] + $b['height'] && $b['y'] < $a['y'] + $a['height'];
};
$inside = function ($a, $b) { // a целиком внутри b
    return $a['x'] >= $b['x'] - 0.01 && $a['y'] >= $b['y'] - 0.01
        && $a['x'] + $a['width'] <= $b['x'] + $b['width'] + 0.01 && $a['y'] + $a['height'] <= $b['y'] + $b['height'] + 0.01;
};
foreach ([8, 7, 9, 12, 14, 3, 16, 1, 5] as $f) {
    foreach (['focal', 'all'] as $mode) {
        $r = $svc->calculateLayout($persons, $marr, $f, $mode);
        $n = $r['nodes_by_id'];
        $errs = [];
        $au = $r['auras'];
        for ($i = 0; $i < count($au); $i++) {
            for ($j = $i + 1; $j < count($au); $j++) {
                // сравниваем рамки одного ряда (верх на одной высоте); вложенность предков — прежнее поведение
                if (abs($au[$i]['y'] - $au[$j]['y']) > 0.5) continue;
                if ($ov($au[$i], $au[$j])) $errs[] = "aura {$au[$i]['id']} x {$au[$j]['id']}";
            }
        }
        foreach ([12 => 9, 13 => 10, 14 => 7] as $c => $fa) {
            if (isset($n[$c], $n[$fa])) {
                $dx = abs(($n[$c]['x']) - ($n[$fa]['x']));
                if ($c === 14) { // общий ребёнок — под парой: в пределах пары Виталий+Жанна
                    if (isset($n[8]) && !($n[14]['x'] >= $n[7]['x'] - 300 && $n[14]['x'] <= $n[8]['x'] + 300)) $errs[] = "child $c not under pair";
                } elseif ($dx > 150) $errs[] = "child $c not under father $fa (dx=$dx)";
            }
        }
        // рамка семьи Виталия не содержит бывших мужей
        foreach ($au as $a) {
            if (in_array(7, $a['member_ids'], true) && (in_array(9, $a['member_ids'], true) || in_array(10, $a['member_ids'], true))) $errs[] = 'vitaly aura has ex';
            if (in_array(7, $a['member_ids'], true) && isset($n[9]) && $ov($a, ['x' => $n[9]['x'], 'y' => $n[9]['y'], 'width' => 280, 'height' => 190])) $errs[] = 'vitaly aura covers ex1 card';
            if (in_array(7, $a['member_ids'], true) && isset($n[12]) && $ov($a, ['x' => $n[12]['x'], 'y' => $n[12]['y'], 'width' => 280, 'height' => 190])) $errs[] = 'vitaly aura covers Tair';
        }
        echo "focus=$f $mode: " . ($errs ? 'FAIL ' . implode('; ', $errs) : 'OK') . "\n";
        if ($errs) $fail++;
        if ($errs && ($argv[1] ?? '') === 'debug') {
            foreach ($au as $a) echo "   {$a['id']} x={$a['x']} w={$a['width']} y={$a['y']} m=" . implode(',', $a['member_ids']) . "\n";
            foreach ($n as $id => $nd) echo "   node $id x={$nd['x']} y={$nd['y']}\n";
        }
    }
}
exit($fail ? 1 : 0);
