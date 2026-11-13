<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Тестовый вход для проверки в браузере агента (TASK-009).
 * Работает ТОЛЬКО на хосте altybayev.st.int, при TEST_LOGIN_ENABLED=1 и заданном TEST_LOGIN_TOKEN в .env.
 * Во всех остальных случаях отдаёт 404, как будто маршрута нет.
 */
class TestLoginController
{
    private const ALLOWED_HOST = 'altybayev.st.int';
    private const TEST_EMAIL = 'test-agent@altybayev.st.int';

    public function login(Request $request): void
    {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host);
        $enabled = filter_var(Config::get('TEST_LOGIN_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN);
        $token = (string)Config::get('TEST_LOGIN_TOKEN', '');
        $given = (string)$request->query('token', '');

        if ($host !== self::ALLOWED_HOST || !$enabled || strlen($token) < 24 || !hash_equals($token, $given)) {
            $this->notFound();
        }

        $db = Database::getConnection();

        // Персона Жандоса берётся у супер-администратора (у него уже привязан person_id)
        $superEmail = strtolower(trim((string)Config::get('SUPER_ADMIN_EMAIL', 'zhandos.alt06@gmail.com')));
        $st = $db->prepare('SELECT person_id FROM users WHERE email = ? LIMIT 1');
        $st->execute([$superEmail]);
        $personId = $st->fetchColumn();
        $personId = $personId ? (int)$personId : null;

        $st = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $st->execute([self::TEST_EMAIL]);
        $user = $st->fetch();

        if (!$user) {
            $ins = $db->prepare("INSERT INTO users (email, name, role, is_active, person_id, last_login_at, created_at)
                VALUES (?, 'Test Agent', 'viewer', 1, ?, NOW(), NOW())");
            $ins->execute([self::TEST_EMAIL, $personId]);
            $st = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $st->execute([(int)$db->lastInsertId()]);
            $user = $st->fetch();
        } else {
            $upd = $db->prepare('UPDATE users SET person_id = ?, is_active = 1, role = ?, last_login_at = NOW() WHERE id = ?');
            $upd->execute([$personId, 'viewer', $user['id']]);
            $user['person_id'] = $personId;
            $user['role'] = 'viewer';
        }

        Auth::login($user);
        Response::redirect('/tree');
    }

    private function notFound(): never
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not Found';
        exit;
    }
}
