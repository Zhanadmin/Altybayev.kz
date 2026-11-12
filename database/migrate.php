<?php
/**
 * Скрипт миграции базы данных и инициализации Шежіре
 * Запуск из консоли Plesk / SSH: php database/migrate.php
 * Или через веб-браузер с секретным ключом: https://altybayev.kz/install?key=YOUR_SECRET
 */

declare(strict_types=1);

// Автозагрузка и окружение
require_once __DIR__ . '/../app/Core/Config.php';
require_once __DIR__ . '/../app/Core/Database.php';

use App\Core\Config;
use App\Core\Database;

Config::load(__DIR__ . '/../.env');

echo "=== Шежіре: Запуск миграции базы данных ===\n";

try {
    $db = Database::getConnection();
    echo "[OK] Подключение к базе данных успешно.\n";

    $sqlFile = __DIR__ . '/schema.sql';
    if (!file_exists($sqlFile)) {
        throw new RuntimeException("Файл schema.sql не найден: " . $sqlFile);
    }

    $sql = file_get_contents($sqlFile);
    
    // Выполняем запросы схемы
    $db->exec($sql);
    echo "[OK] Таблицы базы данных успешно созданы или обновлены.\n";

    // Создаем или обновляем супер-администратора
    $adminEmail = Config::get('SUPER_ADMIN_EMAIL', 'zhandos.alt06@gmail.com');
    $adminEmail = trim(strtolower($adminEmail));

    // Проверяем наличие инвайта для супер-админа
    $stmt = $db->prepare("SELECT id FROM invitations WHERE email = ?");
    $stmt->execute([$adminEmail]);
    if (!$stmt->fetch()) {
        $stmt = $db->prepare("INSERT INTO invitations (email, role, notes, status, created_at) VALUES (?, 'admin', 'Главный администратор (Жандос)', 'pending', NOW())");
        $stmt->execute([$adminEmail]);
        echo "[OK] Приглашение для главного администратора ({$adminEmail}) добавлено.\n";
    }

    // Если супер-админ уже входил, гарантируем роль admin
    $stmt = $db->prepare("SELECT id, role FROM users WHERE email = ?");
    $stmt->execute([$adminEmail]);
    $user = $stmt->fetch();
    if ($user && $user['role'] !== 'admin') {
        $stmt = $db->prepare("UPDATE users SET role = 'admin', is_active = 1 WHERE id = ?");
        $stmt->execute([$user['id']]);
        echo "[OK] Права супер-администратора для {$adminEmail} подтверждены.\n";
    }

    echo "=== Миграция успешно завершена! ===\n";

} catch (Throwable $e) {
    echo "[ERROR] Ошибка миграции: " . $e->getMessage() . "\n";
    exit(1);
}
