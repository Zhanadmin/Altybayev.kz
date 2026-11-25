<?php
// ALT-080: бывшие супруги (связь только через расторгнутый брак) определяются верно. Без БД.
declare(strict_types=1);
spl_autoload_register(function (string $c): void {
    $f = __DIR__ . '/../app/' . str_replace('\\', '/', substr($c, 4)) . '.php';
    if (str_starts_with($c, 'App\\') && is_file($f)) { require_once $f; }
});
use App\Services\MemorialService;

$fail = 0;
function chk(bool $ok, string $msg): void { global $fail; echo ($ok ? 'OK   ' : 'FAIL ') . $msg . "\n"; if (!$ok) { $fail++; } }

$svc = (new ReflectionClass(MemorialService::class))->newInstanceWithoutConstructor();
// 1 Жанна (дочь 10/11), 2 бывший муж (без родителей), 3 Таир (сын 2+1), 4 Санжар (сын 2+1),
// 5 второй бывший муж, но с родителями в древе (10,11), 6 действующий муж бывшего-мужа №2? нет: 6 — муж и бывший одновременно,
// 7 бывший муж с ребёнком вне брака, 8 ребёнок 7 от неизвестной женщины
$persons = [];
foreach ([1,2,3,4,5,6,7,8,10,11,12] as $id) { $persons[$id] = ['id' => $id, 'father_id' => null, 'mother_id' => null]; }
$persons[1]['father_id'] = 10; $persons[1]['mother_id'] = 11;
$persons[3]['father_id'] = 2; $persons[3]['mother_id'] = 1;
$persons[4]['father_id'] = 2; $persons[4]['mother_id'] = 1;
$persons[5]['father_id'] = 10;
$persons[8]['father_id'] = 7; $persons[8]['mother_id'] = 12;
$rows = [
    ['husband_id' => 2, 'wife_id' => 1, 'status' => 'divorced'],
    ['husband_id' => 5, 'wife_id' => 1, 'status' => 'divorced'],
    ['husband_id' => 6, 'wife_id' => 1, 'divorce_date' => '2005-01-01'],
    ['husband_id' => 6, 'wife_id' => 12, 'status' => 'married'],
    ['husband_id' => 7, 'wife_id' => 1, 'status' => 'divorced'],
];
$ids = $svc->computeExSpouseOnlyIds($persons, $rows);
chk(isset($ids[2]), 'бывший муж без других связей скрыт');
chk(!isset($ids[3]) && !isset($ids[4]), 'дети Таир и Санжар показываются');
chk(!isset($ids[1]), 'Жанна показывается');
chk(!isset($ids[5]), 'бывший муж с родителями в древе показывается');
chk(!isset($ids[6]), 'бывший муж с действующим браком показывается');
chk(!isset($ids[7]), 'бывший муж с ребёнком вне расторгнутых браков показывается');

echo $fail ? "FAILED: $fail\n" : "ALL OK\n";
exit($fail ? 1 : 0);
