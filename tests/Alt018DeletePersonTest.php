<?php

declare(strict_types=1);

/**
 * ALT-018: права на удаление человека и список детей. SQLite в памяти.
 */

require_once __DIR__ . '/../app/Services/PersonAccessService.php';
if (!class_exists('App\Core\Auth')) {
    eval('namespace App\Core; class Auth { public static function user(): ?array { return null; } }');
}

use App\Services\PersonAccessService;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP: pdo_sqlite недоступен\n";
    exit(0);
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE persons (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT, father_id INT, mother_id INT, is_deleted INT DEFAULT 0, created_by INT)');
// 1 — импортирован; 2 — добавил user 10; 3 — ребёнок 2, добавил user 10; 4 — добавил user 20; 5 — удалённый ребёнок 2
$db->exec("INSERT INTO persons (id, first_name, last_name, father_id, mother_id, created_by, is_deleted) VALUES
  (1,'A','X',NULL,NULL,NULL,0),(2,'B','X',NULL,NULL,10,0),(3,'C','X',2,NULL,10,0),(4,'D','X',NULL,NULL,20,0),(5,'E','X',2,NULL,10,1)");

$svc = new PersonAccessService($db);
$admin = ['id' => 1, 'role' => 'admin'];
$editor = ['id' => 11, 'role' => 'editor'];
$u10 = ['id' => 10, 'role' => 'viewer'];
$u20 = ['id' => 20, 'role' => 'viewer'];

$fail = 0;
function check(string $name, bool $cond): void
{
    global $fail;
    if (!$cond) {
        $fail++;
        echo "FAIL: $name\n";
    }
}

check('админ удаляет любого', $svc->canDeletePerson(1, $admin) && $svc->canDeletePerson(4, $admin));
check('автор удаляет своего', $svc->canDeletePerson(2, $u10) && $svc->canDeletePerson(3, $u10));
check('автор не удаляет чужого', !$svc->canDeletePerson(4, $u10) && !$svc->canDeletePerson(1, $u10));
check('другой пользователь не удаляет', !$svc->canDeletePerson(2, $u20));
check('редактор не удаляет чужого', !$svc->canDeletePerson(1, $editor));
check('удалённого нельзя', !$svc->canDeletePerson(5, $u10));
check('дети: только живые', count($svc->childrenOf(2)) === 1 && (int)$svc->childrenOf(2)[0]['id'] === 3);
check('без детей', count($svc->childrenOf(4)) === 0);

echo $fail === 0 ? "OK: Alt018DeletePersonTest\n" : "FAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
