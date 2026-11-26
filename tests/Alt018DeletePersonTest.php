<?php

declare(strict_types=1);

/**
 * ALT-018 / ALT-119: права на удаление человека и список детей.
 * SQLite в памяти, а при его отсутствии — временные таблицы тестовой БД.
 */

require_once __DIR__ . '/../app/Services/PersonAccessService.php';
if (!class_exists('App\Core\Auth')) {
    eval('namespace App\Core; class Auth { public static function user(): ?array { return null; } }');
}

use App\Services\PersonAccessService;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    // На этой машине pdo_sqlite нет. Работаем на тестовой БД проекта во ВРЕМЕННЫХ таблицах:
    // они живут только в этом соединении и не затрагивают реальные persons/users.
    require_once __DIR__ . '/../app/bootstrap.php';
    $db = \App\Core\Database::getConnection();
    $db->exec('DROP TEMPORARY TABLE IF EXISTS `users`');
    $db->exec('DROP TEMPORARY TABLE IF EXISTS `persons`');
    $db->exec('CREATE TEMPORARY TABLE `persons` (`id` INT PRIMARY KEY, `first_name` VARCHAR(100) NULL, `last_name` VARCHAR(100) NULL, `father_id` INT NULL, `mother_id` INT NULL, `is_deleted` INT DEFAULT 0, `created_by` INT NULL) ENGINE=InnoDB');
    $db->exec('CREATE TEMPORARY TABLE `users` (`id` INT PRIMARY KEY, `person_id` INT NULL) ENGINE=InnoDB');
} else {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE persons (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT, father_id INT, mother_id INT, is_deleted INT DEFAULT 0, created_by INT)');
    $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, person_id INT)');
}
// 1 — импортирован и привязан к аккаунту user 30; 2 — добавил user 10; 3 — ребёнок 2, добавил user 10; 4 — добавил user 20; 5 — удалённый ребёнок 2
$db->exec("INSERT INTO persons (id, first_name, last_name, father_id, mother_id, created_by, is_deleted) VALUES
  (1,'A','X',NULL,NULL,NULL,0),(2,'B','X',NULL,NULL,10,0),(3,'C','X',2,NULL,10,0),(4,'D','X',NULL,NULL,20,0),(5,'E','X',2,NULL,10,1)");
$db->exec('INSERT INTO users (id, person_id) VALUES (30, 1)');

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

check('админ удаляет любого, включая привязанного', $svc->canDeletePerson(1, $admin) && $svc->canDeletePerson(4, $admin));
check('редактор удаляет любого непривязанного', $svc->canDeletePerson(2, $editor) && $svc->canDeletePerson(4, $editor));
check('редактор не удаляет карточку, привязанную к аккаунту', !$svc->canDeletePerson(1, $editor));
check('автор удаляет своего', $svc->canDeletePerson(2, $u10) && $svc->canDeletePerson(3, $u10));
check('автор не удаляет чужого', !$svc->canDeletePerson(4, $u10) && !$svc->canDeletePerson(1, $u10));
check('другой пользователь удаляет только своё', $svc->canDeletePerson(4, $u20) && !$svc->canDeletePerson(2, $u20));
check('удалённого нельзя', !$svc->canDeletePerson(5, $u10));
check('привязка к аккаунту определяется', $svc->isLinkedToUser(1) && !$svc->isLinkedToUser(2));
check('редактор по-прежнему правит карточку', $svc->canEditPerson(1, $editor) && $svc->canEditPerson(4, $editor));
check('дети: только живые', count($svc->childrenOf(2)) === 1 && (int)$svc->childrenOf(2)[0]['id'] === 3);
check('без детей', count($svc->childrenOf(4)) === 0);

echo $fail === 0 ? "OK: Alt018DeletePersonTest\n" : "FAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
