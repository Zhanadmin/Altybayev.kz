<?php
require_once __DIR__ . '/../app/Core/Config.php';
require_once __DIR__ . '/../app/Core/Database.php';
App\Core\Config::load(__DIR__ . '/../.env');
$db = App\Core\Database::getConnection();
echo "left with 'уточнить': ", $db->query("SELECT COUNT(*) FROM clans WHERE summary LIKE '%уточнить%' OR description LIKE '%уточнить%'")->fetchColumn(), "\n";
foreach ($db->query("SELECT name, summary FROM clans WHERE name IN ('Жалайыр','Алшын','Қожа','Адай','Алтын','Құралас','Сәмбет')") as $r) echo $r['name'], ' | ', $r['summary'], "\n";
