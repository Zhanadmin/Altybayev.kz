<?php

declare(strict_types=1);

/**
 * TASK-019: автотест косинусного сходства (ядро кластеризации лиц).
 * Не требует БД — проверяет только математику отпечатков.
 */

require_once __DIR__ . '/../app/Services/FaceRecognitionService.php';

use App\Services\FaceRecognitionService;

function checkClose(float $a, float $b, string $label): void
{
    if (abs($a - $b) > 1e-6) {
        fwrite(STDERR, "FAIL {$label}: {$a} != {$b}\n");
        exit(1);
    }
}

$rm = new ReflectionMethod(FaceRecognitionService::class, 'cosine');
$rm->setAccessible(true);

$v = array_fill(0, 128, 0.0);
$v[0] = 1.0;
$w = array_fill(0, 128, 0.0);
$w[1] = 1.0;

checkClose((float)$rm->invoke(null, $v, $v), 1.0, 'identity');
checkClose((float)$rm->invoke(null, $v, $w), 0.0, 'orthogonal');
checkClose((float)$rm->invoke(null, $v, array_map(fn($x) => $x * 2.0, $v)), 1.0, 'scaled');
checkClose((float)$rm->invoke(null, $v, array_map(fn($x) => -$x, $v)), -1.0, 'opposite');

$r1 = array_map(fn() => mt_rand(-1000, 1000) / 1000.0, range(1, 128));
$r2 = $r1;
checkClose((float)$rm->invoke(null, $r1, $r2), 1.0, 'random same');

echo "Task019CosineTest OK\n";
