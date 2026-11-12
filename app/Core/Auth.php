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
        return Session::get(self::SESSION_USER_KEY);
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
