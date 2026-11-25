<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/Services/GenerationService.php';
use App\Services\GenerationService as G;

function t(bool $ok, string $m): void { echo ($ok ? "[OK] " : "[FAIL] ") . $m . "\n"; if (!$ok) { exit(1); } }

// 10 бабушка(жива) + 11 дедушка(умер) -> 20 отец, 21 мать(жена) -> 6 якорь (сын)
$p = [
    9  => ['id' => 9,  'father_id' => null, 'mother_id' => null, 'is_deceased' => 1],
    10 => ['id' => 10, 'father_id' => 9, 'mother_id' => null, 'is_deceased' => 0],
    11 => ['id' => 11, 'father_id' => 9, 'mother_id' => null, 'is_deceased' => 1],
    20 => ['id' => 20, 'father_id' => 11, 'mother_id' => 10, 'is_deceased' => 0],
    21 => ['id' => 21, 'father_id' => null, 'mother_id' => null, 'is_deceased' => 0],
    6  => ['id' => 6,  'father_id' => 20, 'mother_id' => 21, 'is_deceased' => 0],
    7  => ['id' => 7,  'father_id' => 6, 'mother_id' => null, 'is_deceased' => 0],
];
$sp = [20 => [['spouse_id' => 21]], 21 => [['spouse_id' => 20]]];
$m = G::generationMap($p, $sp, 6);
t($m[9] === 'ancestors', 'прадед в Предках');
t($m[10] === 1 && $m[11] === 1, 'бабушка (жива) и её брат — 1-е поколение');
t($m[20] === 2 && $m[21] === 2 && $m[6] === 3 && $m[7] === 4, 'нумерация вниз от первого');

// у бабушки и брата — дата смерти: первым становится поколение родителей
$p[10]['death_date'] = '2020-01-01';
$m = G::generationMap($p, $sp, 6);
t($m[10] === 'ancestors' && $m[11] === 'ancestors', 'поколение без живых ушло в Предки');
t($m[20] === 1 && $m[6] === 2 && $m[7] === 3, 'нумерация сдвинулась');
echo "ALL OK\n";
