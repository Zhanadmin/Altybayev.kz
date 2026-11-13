<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use PDO;

class AdminController
{
    /**
     * Главная страница раздела «Әкімші / Админка»
     */
    public function index(Request $request): void
    {
        if (!Auth::isAdmin()) {
            Response::redirect('/?error=admin_only');
        }

        $user = Auth::user();
        $userPersonId = !empty($user['person_id']) ? (int)$user['person_id'] : 6;
        $db = Database::getConnection();

        // 1. Список пользователей
        $users = [];
        $stmtU = $db->query("SHOW TABLES LIKE 'users'");
        if ($stmtU->rowCount() > 0) {
            $uStmt = $db->query("SELECT id, name, email, role, person_id, is_active, last_login_at, created_at FROM users ORDER BY id ASC");
            $users = $uStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 2. Список приглашений
        $invitations = [];
        $stmtI = $db->query("SHOW TABLES LIKE 'invitations'");
        if ($stmtI->rowCount() > 0) {
            $iStmt = $db->query("SELECT * FROM invitations ORDER BY id DESC LIMIT 50");
            $invitations = $iStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 3. Статистика
        $personsCount = (int)$db->query("SELECT COUNT(*) FROM persons WHERE is_deleted = 0")->fetchColumn();
        $marriagesCount = (int)$db->query("SELECT COUNT(*) FROM marriages")->fetchColumn();
        $auditCount = (int)$db->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
        $mediaCount = (int)$db->query("SELECT COUNT(*) FROM person_media")->fetchColumn();

        Response::view('admin/index', [
            'user'           => $user,
            'userPersonId'   => $userPersonId,
            'users'          => $users,
            'invitations'    => $invitations,
            'personsCount'   => $personsCount,
            'marriagesCount' => $marriagesCount,
            'auditCount'     => $auditCount,
            'mediaCount'     => $mediaCount,
            'canEdit'        => true,
            'isAdmin'        => true,
        ]);
    }

    /**
     * API обновления роли пользователя
     */
    public function updateUserRole(Request $request): void
    {
        if (!Auth::isAdmin()) {
            Response::json(['error' => 'Тек әкімшіге рұқсат / Доступно только администратору'], 403);
            return;
        }

        $userId = (int)$request->post('user_id', 0);
        $role = trim((string)$request->post('role', ''));

        if ($userId <= 0 || !in_array($role, ['admin', 'editor', 'viewer'], true)) {
            Response::json(['error' => 'Деректер қате / Некорректные параметры'], 400);
            return;
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$role, $userId]);

            // Логируем в аудит
            $currUser = Auth::user();
            $logStmt = $db->prepare("
                INSERT INTO audit_logs 
                (user_id, user_name, user_email, action, entity_table, entity_id, summary, new_values, created_at)
                VALUES (?, ?, ?, 'UPDATE', 'users', ?, ?, ?, NOW())
            ");
            $summary = "Смена роли пользователя #{$userId} на {$role}";
            $logStmt->execute([
                $currUser['id'] ?? null,
                $currUser['name'] ?? 'Әкімші',
                $currUser['email'] ?? null,
                $userId,
                $summary,
                json_encode(['role' => $role]),
            ]);

            Response::json(['success' => true, 'message' => 'Рөл сәтті жаңартылды / Роль успешно обновлена']);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }
}
