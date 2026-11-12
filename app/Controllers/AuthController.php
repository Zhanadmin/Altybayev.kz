<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\GoogleOAuthService;
use PDO;
use Throwable;

class AuthController
{
    /**
     * Отображение страницы входа
     */
    public function showLogin(Request $request): void
    {
        if (Auth::check()) {
            Response::redirect('/');
        }

        Response::view('login', [
            'error' => $request->query('error')
        ]);
    }

    /**
     * Перенаправление в Google на авторизацию
     */
    public function redirectToGoogle(Request $request): void
    {
        $state = bin2hex(random_bytes(16));
        Session::set('oauth_state', $state);

        $oauth = new GoogleOAuthService();
        $url = $oauth->getAuthUrl($state);

        Response::redirect($url);
    }

    /**
     * Обработка ответа от Google (OAuth Callback)
     */
    public function handleGoogleCallback(Request $request): void
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $savedState = Session::get('oauth_state');
        Session::remove('oauth_state');

        if (empty($code) || empty($state) || $state !== $savedState) {
            Response::redirect('/login?error=invalid_state');
        }

        try {
            $oauth = new GoogleOAuthService();
            $googleUser = $oauth->getUserFromCode($code);
            $email = strtolower(trim($googleUser['email']));

            $db = Database::getConnection();

            // 1. Проверяем, является ли пользователь супер-администратором
            $superAdminEmail = strtolower(trim((string)Config::get('SUPER_ADMIN_EMAIL', 'zhandos.alt06@gmail.com')));
            $isSuperAdmin = ($email === $superAdminEmail);

            // 2. Ищем существующего пользователя
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $existingUser = $stmt->fetch();

            if ($existingUser) {
                if (!$existingUser['is_active']) {
                    Response::view('access_denied', [
                        'email'  => $email,
                        'reason' => 'account_blocked'
                    ]);
                }

                // Если это супер-админ, гарантируем роль admin
                $role = $isSuperAdmin ? 'admin' : $existingUser['role'];

                $updateStmt = $db->prepare("
                    UPDATE users 
                    SET google_id = ?, name = ?, avatar_url = ?, role = ?, last_login_at = NOW() 
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $googleUser['google_id'],
                    $googleUser['name'],
                    $googleUser['avatar_url'],
                    $role,
                    $existingUser['id']
                ]);

                $existingUser['role'] = $role;
                Auth::login($existingUser);
                Response::redirect('/');
            }

            // 3. Пользователя нет в users: проверяем приглашения (invitations) или статус супер-админа
            $assignedRole = null;
            $invitationId = null;

            if ($isSuperAdmin) {
                $assignedRole = 'admin';
            } else {
                $invStmt = $db->prepare("SELECT * FROM invitations WHERE email = ? AND status != 'revoked' LIMIT 1");
                $invStmt->execute([$email]);
                $invitation = $invStmt->fetch();

                if ($invitation) {
                    $assignedRole = $invitation['role'] ?? 'viewer';
                    $invitationId = (int)$invitation['id'];
                }
            }

            // Если инвайта нет и не супер-админ — доступ закрыт
            if (!$assignedRole) {
                Response::view('access_denied', [
                    'email'  => $email,
                    'reason' => 'no_invitation'
                ]);
            }

            // 4. Создаем нового пользователя
            $insertStmt = $db->prepare("
                INSERT INTO users (google_id, email, name, avatar_url, role, is_active, last_login_at, created_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
            ");
            $insertStmt->execute([
                $googleUser['google_id'],
                $email,
                $googleUser['name'],
                $googleUser['avatar_url'],
                $assignedRole
            ]);

            $newUserId = (int)$db->lastInsertId();

            // Помечаем инвайт как принятый
            if ($invitationId) {
                $updInv = $db->prepare("UPDATE invitations SET status = 'accepted', accepted_at = NOW() WHERE id = ?");
                $updInv->execute([$invitationId]);
            }

            Auth::login([
                'id'         => $newUserId,
                'email'      => $email,
                'name'       => $googleUser['name'],
                'avatar_url' => $googleUser['avatar_url'],
                'role'       => $assignedRole
            ]);

            Response::redirect('/');

        } catch (Throwable $e) {
            if (Config::isDebug()) {
                throw $e;
            }
            Response::redirect('/login?error=auth_failed');
        }
    }

    /**
     * Выход из системы
     */
    public function logout(Request $request): void
    {
        Auth::logout();
        Response::redirect('/login');
    }

    /**
     * API: получить данные текущего авторизованного пользователя
     */
    public function currentUser(Request $request): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::error('Авторизация қажет / Требуется авторизация', 401);
        }

        Response::success([
            'user' => [
                'id'         => $user['id'],
                'email'      => $user['email'],
                'name'       => $user['name'],
                'avatar_url' => $user['avatar_url'] ?? null,
                'role'       => $user['role'],
                'is_admin'   => Auth::isAdmin(),
                'is_editor'  => Auth::isEditor()
            ]
        ]);
    }
}
