<?php

declare(strict_types=1);

/**
 * ALT-119: редактор может удалять людей, чужие права не расширяются.
 * Часть 1 — матрица прав (SQLite в памяти или временные таблицы тестовой БД);
 * часть 2 — проверка связки интерфейса и API.
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
    $db->exec('CREATE TEMPORARY TABLE `persons` (`id` INT PRIMARY KEY, `father_id` INT NULL, `mother_id` INT NULL, `is_deleted` INT DEFAULT 0, `created_by` INT NULL) ENGINE=InnoDB');
    $db->exec('CREATE TEMPORARY TABLE `users` (`id` INT PRIMARY KEY, `person_id` INT NULL) ENGINE=InnoDB');
} else {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE persons (id INTEGER PRIMARY KEY, father_id INT, mother_id INT, is_deleted INT DEFAULT 0, created_by INT)');
    $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, person_id INT)');
}

$fail = 0;
$n = 0;
function check(string $name, bool $cond): void
{
    global $fail, $n;
    $n++;
    if (!$cond) {
        $fail++;
        echo "FAIL: $name\n";
    }
}

// --- 1. Матрица прав ---
$db->exec("INSERT INTO persons (id, father_id, mother_id, created_by, is_deleted) VALUES
  (1,NULL,NULL,NULL,0),(2,NULL,NULL,10,0),(3,2,NULL,10,0),(4,NULL,NULL,20,0),(5,NULL,NULL,10,1)");
$db->exec('INSERT INTO users (id, person_id) VALUES (30, 1)');

$svc = new PersonAccessService($db);
$admin = ['id' => 1, 'role' => 'admin'];
$editor = ['id' => 11, 'role' => 'editor'];
$owner = ['id' => 10, 'role' => 'viewer'];
$other = ['id' => 20, 'role' => 'viewer'];
$guest = ['id' => 40, 'role' => 'viewer'];

check('редактор удаляет чужую непривязанную карточку', $svc->canDeletePerson(2, $editor) && $svc->canDeletePerson(4, $editor));
check('редактор не удаляет привязанную к аккаунту', !$svc->canDeletePerson(1, $editor));
check('админ удаляет привязанную', $svc->canDeletePerson(1, $admin));
check('viewer удаляет только добавленную им', $svc->canDeletePerson(2, $owner) && $svc->canDeletePerson(4, $other));
check('viewer не удаляет чужую', !$svc->canDeletePerson(2, $other) && !$svc->canDeletePerson(3, $other));
check('viewer без своих карточек не удаляет', !$svc->canDeletePerson(2, $guest));
check('удалённую карточку повторно не удалить', !$svc->canDeletePerson(5, $owner));
check('редактор по-прежнему может редактировать', $svc->canEditPerson(1, $editor) && $svc->canEditPerson(4, $editor));
check('viewer не получил право править чужое', !$svc->canEditPerson(4, $owner));

// --- 2. Связка сервера и интерфейса ---
$root = dirname(__DIR__);
$read = static function (string $rel) use ($root): string {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    return is_file($path) ? (string)file_get_contents($path) : '';
};

$service = $read('app/Services/PersonAccessService.php');
$treeController = $read('app/Controllers/TreeController.php');
$personController = $read('app/Controllers/PersonController.php');
$treeView = $read('views/tree.php');
$personsView = $read('views/persons/index.php');
$personDeleteJs = $read('public/assets/js/person-delete.js');
$routes = $read('public/index.php');

check('сервер: редактор разрешён в canDeletePerson', str_contains($service, "=== 'editor'") && str_contains($service, 'isLinkedToUser'));
check('сервер: привязанная карточка защищена', str_contains($service, 'users WHERE person_id = ?'));
check('API удаления использует общую проверку прав', str_contains($treeController, '$access->canDeletePerson($id, $user)'));
check('API удаления остался только для авторизованных', str_contains($routes, "'/api/person/delete'") && str_contains($routes, "['auth']"));
check('корзина и восстановление остались только для админа', str_contains($routes, "'/api/admin/persons/trash'") && str_contains($routes, "'auth', 'admin'") && str_contains($routes, "'/api/admin/person/restore'"));
check('список людей: флаг canDeleteAny с сервера', str_contains($personController, "'canDeleteAny'") && str_contains($personController, 'Auth::isEditor()'));
check('список людей: редактор видит действие удаления', str_contains($personsView, '$canDeleteAnyRow') && str_contains($personsView, '$canDelRow = ($canDeleteAnyRow'));
check('древо: редактор видит удаление', str_contains($treeView, 'const canDeletePersonUi = (pid) => canEdit'));
check('древо: явный запрет сервера не перекрывается UI', str_contains($treeView, "typeof data.can_delete === 'undefined'"));
check('подтверждение удаления сохранено', str_contains($personDeleteJs, 'askConfirm') && str_contains($personDeleteJs, 'function askConfirm'));
check('сообщение об успешном удалении добавлено', str_contains($personDeleteJs, 'showSuccessToast') && str_contains($personDeleteJs, 'Человек удалён'));

echo $fail === 0 ? "OK: Alt119EditorDeleteTest ({$n}/{$n})\n" : "FAILED: {$fail} из {$n}\n";
exit($fail === 0 ? 0 : 1);
