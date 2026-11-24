<?php
declare(strict_types=1);
// ALT-022: показать 10 реальных имён из БД в en и es (только чтение). Запуск: php tests\Alt022DbNamesSample.php
require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Core\I18n;

$db = Database::getConnection();
$rows = $db->query("SELECT first_name, last_name FROM persons WHERE is_deleted = 0 AND first_name <> 'Белгісіз' AND CONCAT(first_name, last_name) REGEXP '[ӘәҒғҚқҢңӨөҰұҮүҺһІі]' GROUP BY first_name, last_name ORDER BY MIN(id) LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
$left = 10 - count($rows);
if ($left > 0) {
    $more = $db->query("SELECT first_name, last_name FROM persons WHERE is_deleted = 0 ORDER BY id DESC LIMIT " . (int)$left)->fetchAll(PDO::FETCH_ASSOC);
    $rows = array_merge($rows, $more);
}
$bad = 0;
foreach ($rows as $r) {
    $n = trim($r['first_name'] . ' ' . $r['last_name']);
    $en = I18n::latinize($n, 'en');
    $es = I18n::latinize($n, 'es');
    if (preg_match('/[\x{0400}-\x{04FF}]/u', $en . $es)) { $bad++; }
    echo "$n | en: $en | es: $es\n";
}
echo $bad === 0 ? "Кириллицы в результате нет\n" : "ЕСТЬ кириллица: $bad\n";
