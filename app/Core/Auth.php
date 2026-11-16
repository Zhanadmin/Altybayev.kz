<?php

declare(strict_types=1);

namespace App\Core;

class Auth
{
    private const SESSION_USER_KEY = 'auth_user';

    /**
     * Возвращает текущего авторизованного пользователя или null
     */
    public static function user(): ?array
    {
        self::syncWithDatabase();
        return Session::get(self::SESSION_USER_KEY);
    }

    private static bool $synced = false;

    /**
     * TASK-014: раз за запрос сверяем сессию с БД — отозванный (is_active = 0) или удалённый
     * пользователь выходит сразу, смена роли действует без перелогина.
     * При ошибке БД сессия не трогается (публичные страницы не должны падать).
     */
    private static function syncWithDatabase(): void
    {
        if (self::$synced) {
            return;
        }
        self::$synced = true;

        $sessionUser = Session::get(self::SESSION_USER_KEY);
        if (!$sessionUser || empty($sessionUser['id'])) {
            return;
        }

        try {
            $stmt = Database::getConnection()->prepare('SELECT role, is_active, person_id FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$sessionUser['id']]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return;
        }

        if (!$row || (int)$row['is_active'] !== 1) {
            Session::remove(self::SESSION_USER_KEY);
            return;
        }

        $sessionUser['role'] = $row['role'] ?: 'viewer';
        // TASK-014 (note 16.11): привязка к карточке — всегда как в БД (снятая привязка тоже снимается)
        $sessionUser['person_id'] = !empty($row['person_id']) ? (int)$row['person_id'] : null;
        Session::set(self::SESSION_USER_KEY, $sessionUser);
    }

    /**
     * Проверка, авторизован ли пользователь
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * ID текущего пользователя
     */
    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int)$user['id'] : null;
    }

    /**
     * Роль текущего пользователя
     */
    public static function role(): ?string
    {
        $user = self::user();
        return $user ? ($user['role'] ?? 'viewer') : null;
    }

    /**
     * Проверка роли Администратора (Жандос)
     */
    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    /**
     * Проверка роли Редактора (или Администратора)
     */
    public static function isEditor(): bool
    {
        $role = self::role();
        return in_array($role, ['admin', 'editor'], true);
    }

    /**
     * Проверка права на редактирование (admin немесе editor)
     */
    public static function canEdit(): bool
    {
        return self::isEditor();
    }

    /**
     * Проверка роли Зрителя (любой авторизованный)
     */
    public static function isViewer(): bool
    {
        return self::check();
    }

    /**
     * Авторизовать пользователя в сессии
     */
    public static function login(array $user): void
    {
        // TASK-014: новый идентификатор сессии после входа (защита от session fixation)
        Session::start();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::$synced = true;
        Session::set(self::SESSION_USER_KEY, [
            'id'         => (int)$user['id'],
            'email'      => $user['email'],
            'name'       => $user['name'],
            'avatar_url' => $user['avatar_url'] ?? null,
            'role'       => $user['role'] ?? 'viewer',
            'person_id'  => !empty($user['person_id']) ? (int)$user['person_id'] : null,
        ]);
    }

    /**
     * Выход из системы
     */
    public static function logout(): void
    {
        Session::remove(self::SESSION_USER_KEY);
        Session::destroy();
    }
}
