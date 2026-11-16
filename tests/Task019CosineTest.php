<?php

declare(strict_types=1);

/**
 * TASK-019: автотест распознавания лиц и сопоставления с эталонами аватарок.
 * Не требует БД — проверяет математику отпечатков, валидацию и алгоритм сопоставления.
 */

require_once __DIR__ . '/../app/Services/FaceRecognitionService.php';

use App\Services\FaceRecognitionService;

function checkClose(float $a, float $b, string $label): void
{
    if (abs($a - $b) > 1e-5) {
        fwrite(STDERR, "FAIL {$label}: {$a} != {$b}\n");
        exit(1);
    }
}

function checkTrue(bool $val, string $label): void
{
    if (!$val) {
        fwrite(STDERR, "FAIL {$label}: expected true, got false\n");
        exit(1);
    }
}

// 1. Косинусное сходство (обратная совместимость)
$rm = new ReflectionMethod(FaceRecognitionService::class, 'cosine');
$rm->setAccessible(true);

$v = array_fill(0, 128, 0.0);
$v[0] = 1.0;
$w = array_fill(0, 128, 0.0);
$w[1] = 1.0;

checkClose((float)$rm->invoke(null, $v, $v), 1.0, 'cosine identity');
checkClose((float)$rm->invoke(null, $v, $w), 0.0, 'cosine orthogonal');
checkClose((float)$rm->invoke(null, $v, array_map(fn($x) => $x * 2.0, $v)), 1.0, 'cosine scaled');
checkClose((float)$rm->invoke(null, $v, array_map(fn($x) => -$x, $v)), -1.0, 'cosine opposite');

$r1 = array_map(fn() => mt_rand(-1000, 1000) / 1000.0, range(1, 128));
$r2 = $r1;
checkClose((float)$rm->invoke(null, $r1, $r2), 1.0, 'cosine random same');

// 2. Евклидово расстояние (face-api.js euclidean distance)
checkClose(FaceRecognitionService::distance($v, $v), 0.0, 'distance identical');
checkClose(FaceRecognitionService::distance($v, $w), sqrt(2.0), 'distance orthogonal unit');

$d1 = array_fill(0, 128, 0.0);
$d1[0] = 3.0;
$d2 = array_fill(0, 128, 0.0);
$d2[1] = 4.0;
checkClose(FaceRecognitionService::distance($d1, $d2), 5.0, 'distance 3-4-5 triangle');

// 3. cleanDescriptor
checkTrue(FaceRecognitionService::cleanDescriptor([]) === null, 'cleanDescriptor empty');
checkTrue(FaceRecognitionService::cleanDescriptor(array_fill(0, 127, 0.1)) === null, 'cleanDescriptor 127');
checkTrue(FaceRecognitionService::cleanDescriptor(array_fill(0, 129, 0.1)) === null, 'cleanDescriptor 129');
$descGood = array_fill(0, 128, 0.1234567);
$cleaned = FaceRecognitionService::cleanDescriptor($descGood);
checkTrue($cleaned !== null && count($cleaned) === 128, 'cleanDescriptor 128 floats');
checkClose($cleaned[0], 0.123457, 'cleanDescriptor round 6 decimals');

// 4. iou
checkClose(FaceRecognitionService::iou([0, 0, 1, 1], [0, 0, 1, 1]), 1.0, 'iou identical');
checkClose(FaceRecognitionService::iou([0, 0, 0.4, 0.4], [0.5, 0.5, 0.4, 0.4]), 0.0, 'iou disjoint');
// два квадрата 0.5x0.5 с перекрытием 0.25x0.5
// площадь 1 = 0.25, площадь 2 = 0.25, пересечение = 0.25*0.5 = 0.125, объединение = 0.5 - 0.125 = 0.375
checkClose(FaceRecognitionService::iou([0, 0, 0.5, 0.5], [0.25, 0, 0.5, 0.5]), 0.125 / 0.375, 'iou overlap');

// 5. Алгоритм сопоставления лиц с эталонами (assign)
// Эталоны двух человек: Person 10 ($v) и Person 20 ($w)
$refs = [
    10 => $v,
    20 => $w,
];

// Лицо 1: очень близко к Person 10 (расстояние 0.1 < AUTO_DISTANCE 0.47) -> labeled
$f1 = $v;
$f1[2] = 0.1;

// Лицо 2: сомнительное сходство с Person 20 (расстояние 0.52: между AUTO 0.47 и ASK 0.58) -> suggested
$f2 = $w;
$f2[2] = 0.52;

// Лицо 3: совершенно непохоже (расстояние > 0.58) -> unlabeled
$f3 = array_fill(0, 128, 0.0);
$f3[5] = 1.0;

$faces = [
    101 => ['d' => $f1, 'rej' => null],
    102 => ['d' => $f2, 'rej' => null],
    103 => ['d' => $f3, 'rej' => null],
];

$assigned = FaceRecognitionService::assign($faces, $refs);

checkTrue(isset($assigned[101]), 'assigned 101 exists');
checkTrue($assigned[101]['person'] === 10, 'assigned 101 person 10');
checkTrue($assigned[101]['status'] === 'labeled', 'assigned 101 status labeled');

checkTrue(isset($assigned[102]), 'assigned 102 exists');
checkTrue($assigned[102]['person'] === 20, 'assigned 102 person 20');
checkTrue($assigned[102]['status'] === 'suggested', 'assigned 102 status suggested');

checkTrue(isset($assigned[103]), 'assigned 103 exists');
checkTrue($assigned[103]['person'] === null, 'assigned 103 person null');
checkTrue($assigned[103]['status'] === 'unlabeled', 'assigned 103 status unlabeled');

// Проверка rejected: если лицо 101 уже отвергло Person 10
$facesRej = [
    101 => ['d' => $f1, 'rej' => 10],
];
$assignedRej = FaceRecognitionService::assign($facesRej, $refs);
checkTrue($assignedRej[101]['person'] === null, 'assigned rejected person not proposed');

echo "Task019CosineTest OK\n";
