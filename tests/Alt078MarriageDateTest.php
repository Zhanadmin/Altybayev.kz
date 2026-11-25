<?php
// ALT-078: нормализация даты брака и склонение «N лет со дня свадьбы».
declare(strict_types=1);

spl_autoload_register(function (string $c): void {
    $f = __DIR__ . '/../app/' . str_replace('\\', '/', substr($c, 4)) . '.php';
    if (str_starts_with($c, 'App\\') && is_file($f)) { require_once $f; }
});

use App\Controllers\TreeController;
use App\Services\MemorialService;

$fail = 0;
function chk(bool $ok, string $msg): void { global $fail; echo ($ok ? 'OK   ' : 'FAIL ') . $msg . "\n"; if (!$ok) { $fail++; } }

chk(TreeController::normalizeMarriageDate('') === null, 'пусто → null');
chk(TreeController::normalizeMarriageDate('2010-05-07') === '2010-05-07', 'ISO сохраняется');
chk(TreeController::normalizeMarriageDate('7.5.2010') === '2010-05-07', 'ДД.ММ.ГГГГ → ISO');
chk(TreeController::normalizeMarriageDate('1979') === '1979', 'только год остаётся годом');
chk(TreeController::normalizeMarriageDate('31.02.2010') === false, '31 февраля — ошибка');
chk(TreeController::normalizeMarriageDate('около 1960') === 'около 1960', 'прежний текст не теряется');

$ref = new ReflectionMethod(MemorialService::class, 'pluralRu');
$ref->setAccessible(true);
$svc = (new ReflectionClass(MemorialService::class))->newInstanceWithoutConstructor();
$p = fn(int $n) => $ref->invoke($svc, $n, 'год', 'года', 'лет');
chk($p(1) === 'год' && $p(2) === 'года' && $p(5) === 'лет', '1 год, 2 года, 5 лет');
chk($p(11) === 'лет' && $p(15) === 'лет' && $p(21) === 'год' && $p(24) === 'года', '11, 15 лет; 21 год; 24 года');

echo $fail ? "FAILED: $fail\n" : "ALL OK\n";
exit($fail ? 1 : 0);
