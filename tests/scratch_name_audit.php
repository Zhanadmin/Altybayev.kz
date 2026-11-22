<?php
// READ-ONLY аудит имён (TASK-021). Только SELECT. Не коммитить.
require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

$pdo = Database::getConnection();
$q = static fn(string $sql, array $p = []) => (function () use ($pdo, $sql, $p) {
    $s = $pdo->prepare($sql);
    $s->execute($p);
    return $s->fetchAll();
})();

echo "== 1. persons: Джандос/Жандос/Jandos/Dzhandos ==\n";
foreach ($q("SELECT id, gedcom_id, first_name, last_name, middle_name, is_deleted, updated_at FROM persons WHERE first_name LIKE ? OR first_name LIKE ? OR first_name LIKE ? OR first_name LIKE ? OR middle_name LIKE ? OR middle_name LIKE ? ORDER BY id",
    ['%Джандос%', '%Жандос%', '%jandos%', '%dzhandos%', '%Джандос%', '%Жандос%']) as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE), "\n";
}

echo "\n== 2. Все текстовые колонки БД, содержащие 'Джандос' (кроме persons) ==\n";
$cols = $q("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE IN ('varchar','text','mediumtext','longtext','json')");
foreach ($cols as $c) {
    $t = str_replace('`', '', $c['TABLE_NAME']);
    $col = str_replace('`', '', $c['COLUMN_NAME']);
    $n = $pdo->query("SELECT COUNT(*) FROM `$t` WHERE `$col` LIKE '%Джандос%' OR `$col` LIKE '%Dzhandos%'")->fetchColumn();
    if ((int)$n > 0) echo "$t.$col: $n\n";
}

echo "\n== 3. Дубликаты persons (одинаковые имя+фамилия+отчество, не удалённые) ==\n";
foreach ($q("SELECT first_name, last_name, middle_name, COUNT(*) c, GROUP_CONCAT(id) ids FROM persons WHERE is_deleted=0 GROUP BY first_name, last_name, middle_name HAVING c>1 ORDER BY c DESC LIMIT 100") as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE), "\n";
}

echo "\n== 4. Имена с подозрительным 'Дж' (кандидаты на транслитерацию) ==\n";
foreach ($q("SELECT id, gedcom_id, first_name, last_name FROM persons WHERE first_name LIKE 'Дж%' ORDER BY first_name LIMIT 100") as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE), "\n";
}

echo "\n== 5. Таблицы со столбцами имён (кроме persons) ==\n";
foreach ($q("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME <> 'persons' AND (COLUMN_NAME LIKE '%name%' OR COLUMN_NAME LIKE '%title%')") as $r) {
    echo $r['TABLE_NAME'], '.', $r['COLUMN_NAME'], "\n";
}
