<?php

declare(strict_types=1);

/**
 * ALT-119: сквозная проверка /api/person/delete на временных таблицах тестовой БД.
 * Реальные persons/users/invitations не затрагиваются: таблицы TEMPORARY и исчезают с соединением.
 *
 * Запуск: php tests/Alt119DeleteApiTest.php editor|viewer|linked-editor|invited-editor|linked-admin
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Controllers\TreeController;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;

$scenario = $argv[1] ?? 'editor';
$allowed = ['editor', 'viewer', 'linked-editor', 'invited-editor', 'linked-admin'];
if (!in_array($scenario, $allowed, true)) {
    fwrite(STDERR, "Unknown scenario\n");
    exit(2);
}

$db = Database::getConnection();
foreach (['audit_logs', 'invitations', 'users', 'persons'] as $table) {
    $db->exec("DROP TEMPORARY TABLE IF EXISTS `{$table}`");
}
$db->exec('CREATE TEMPORARY TABLE `persons` (`id` INT PRIMARY KEY, `first_name` VARCHAR(100) NULL, `last_name` VARCHAR(100) NULL, `father_id` INT NULL, `mother_id` INT NULL, `is_deleted` INT DEFAULT 0, `created_by` INT NULL, `updated_at` DATETIME NULL) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE `users` (`id` INT PRIMARY KEY, `role` VARCHAR(20) NOT NULL, `is_active` INT DEFAULT 1, `person_id` INT NULL) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE `invitations` (`id` INT PRIMARY KEY, `email` VARCHAR(191) NULL, `status` VARCHAR(20) NULL, `person_id` INT NULL) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE `audit_logs` (`id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT NULL, `user_name` VARCHAR(191) NULL, `user_email` VARCHAR(191) NULL, `action` VARCHAR(20) NULL, `entity_table` VARCHAR(50) NULL, `entity_id` INT NULL, `summary` VARCHAR(255) NULL, `old_values` TEXT NULL, `new_values` TEXT NULL, `created_at` DATETIME NULL) ENGINE=InnoDB');

// 100 — обычная тестовая карточка с ребёнком 102; 103 — карточка, привязанная к аккаунту user 30
$db->exec("INSERT INTO `persons` (`id`,`first_name`,`last_name`,`father_id`,`mother_id`,`is_deleted`,`created_by`,`updated_at`) VALUES
  (100,'Тест','Редактор',NULL,NULL,0,11,NOW()),
  (102,'Тест','Ребёнок',100,NULL,0,11,NOW()),
  (103,'Тест','Привязанный',NULL,NULL,0,11,NOW())");
$db->exec("INSERT INTO `users` (`id`,`role`,`is_active`,`person_id`) VALUES (1,'admin',1,NULL),(11,'editor',1,NULL),(20,'viewer',1,NULL),(30,'viewer',1,103)");
if ($scenario === 'invited-editor') {
    $db->exec("INSERT INTO `invitations` (`id`,`email`,`status`,`person_id`) VALUES (500,'invite@test.local','pending',100)");
}

$users = [
    'editor' => ['id' => 11, 'email' => 'editor@test.local', 'name' => 'Editor', 'role' => 'editor'],
    'viewer' => ['id' => 20, 'email' => 'viewer@test.local', 'name' => 'Viewer', 'role' => 'viewer'],
    'linked-editor' => ['id' => 11, 'email' => 'editor@test.local', 'name' => 'Editor', 'role' => 'editor'],
    'invited-editor' => ['id' => 11, 'email' => 'editor@test.local', 'name' => 'Editor', 'role' => 'editor'],
    'linked-admin' => ['id' => 1, 'email' => 'admin@test.local', 'name' => 'Admin', 'role' => 'admin'],
];
Auth::login($users[$scenario]);

$targetId = in_array($scenario, ['linked-editor', 'linked-admin'], true) ? 103 : 100;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'id' => (string)$targetId,
    'csrf_token' => Session::csrfToken(),
    'children_confirmed' => '1',
];

register_shutdown_function(static function () use ($db, $scenario): void {
    $status = (int)http_response_code();
    $person100 = $db->query('SELECT is_deleted FROM persons WHERE id = 100')->fetchColumn();
    $person103 = $db->query('SELECT is_deleted FROM persons WHERE id = 103')->fetchColumn();
    $childFather = $db->query('SELECT father_id FROM persons WHERE id = 102')->fetchColumn();
    $user30 = $db->query('SELECT person_id FROM users WHERE id = 30')->fetchColumn();
    $invitation = $db->query('SELECT person_id FROM invitations WHERE id = 500')->fetchColumn();
    $audit = (int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE entity_table = 'persons' AND action = 'DELETE'")->fetchColumn();

    switch ($scenario) {
        case 'editor':
            $ok = $status === 200 && (int)$person100 === 1 && $childFather === null && (int)$person103 === 0 && $audit === 1;
            $name = 'редактор удалил тестовую карточку, ребёнок отвязан, аудит записан';
            break;
        case 'viewer':
            $ok = $status === 403 && (int)$person100 === 0 && $audit === 0;
            $name = 'viewer без прав получил 403, данные не изменены';
            break;
        case 'linked-editor':
            $ok = $status === 403 && (int)$person103 === 0 && (int)$user30 === 103 && $audit === 0;
            $name = 'редактор не удалил карточку, привязанную к аккаунту';
            break;
        case 'invited-editor':
            $ok = $status === 200 && (int)$person100 === 1 && $invitation === null && $audit === 1;
            $name = 'редактор удалил карточку, приглашение отвязано от неё';
            break;
        case 'linked-admin':
            $ok = $status === 200 && (int)$person103 === 1 && $user30 === null && $audit === 1;
            $name = 'админ удалил привязанную карточку, аккаунт отвязан';
            break;
        default:
            $ok = false;
            $name = 'неизвестный сценарий';
    }

    echo "\n" . ($ok ? 'OK: ' : 'FAIL: ') . $name . " (HTTP {$status})\n";
    if (!$ok) {
        echo "DEBUG: person100={$person100} person103={$person103} child=" . var_export($childFather, true)
            . " user30=" . var_export($user30, true) . " invitation=" . var_export($invitation, true) . " audit={$audit}\n";
        exit(1);
    }
});

(new TreeController())->apiPersonDelete(new Request());
