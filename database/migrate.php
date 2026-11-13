<?php
/**
 * Скрипт миграции базы данных и инициализации Шежіре (altybayev.kz)
 * Запуск из консоли Plesk / SSH / Планировщика задач:
 * php database/migrate.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

// Автозагрузка и окружение
require_once __DIR__ . '/../app/Core/Config.php';
require_once __DIR__ . '/../app/Core/Database.php';

use App\Core\Config;
use App\Core\Database;

Config::load(__DIR__ . '/../.env');

echo "=== Шежіре: Запуск миграции базы данных ===\n";
echo "Дата/время: " . date('Y-m-d H:i:s') . "\n";

$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/migrations.log';

function logMsg(string $msg, string $logFile): void {
    echo $msg . "\n";
    @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
}

try {
    $db = Database::getConnection();
    logMsg("[OK] Подключение к базе данных успешно.", $logFile);

    // 1. Инициализация таблицы истории миграций (schema_migrations)
    $db->exec("
        CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(191) NOT NULL UNIQUE,
            `batch` INT UNSIGNED NOT NULL DEFAULT 1,
            `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Загружаем список уже выполненных миграций
    $stmt = $db->query("SELECT `migration` FROM `schema_migrations`");
    $appliedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $appliedMap = array_fill_keys($appliedMigrations, true);

    // Определяем номер текущего пакета (batch)
    $stmtBatch = $db->query("SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `schema_migrations`");
    $currentBatch = (int)$stmtBatch->fetchColumn();

    $appliedCount = 0;

    // 2. Базовая схема (schema.sql)
    $schemaFile = __DIR__ . '/schema.sql';
    $schemaKey = '0000_00_00_schema.sql';

    if (!file_exists($schemaFile)) {
        throw new RuntimeException("Файл базовой схемы не найден: " . $schemaFile);
    }

    if (!isset($appliedMap[$schemaKey])) {
        logMsg("--> Применение базовой схемы (schema.sql)...", $logFile);
        $schemaSql = file_get_contents($schemaFile);
        $db->exec($schemaSql);

        $stmtIns = $db->prepare("INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES (?, ?, NOW())");
        $stmtIns->execute([$schemaKey, $currentBatch]);
        $appliedMap[$schemaKey] = true;
        $appliedCount++;
        logMsg("[OK] Базовая схема {$schemaKey} успешно применена.", $logFile);
    } else {
        logMsg("[SKIP] Базовая схема ({$schemaKey}) уже была применена ранее.", $logFile);
    }

    // 3. Каталог дополнительных миграций (database/migrations/*.sql, *.php)
    $migrationsDir = __DIR__ . '/migrations';
    if (is_dir($migrationsDir)) {
        $sqlFiles = glob($migrationsDir . '/*.sql') ?: [];
        $phpFiles = glob($migrationsDir . '/*.php') ?: [];
        $migrationFiles = array_merge($sqlFiles, $phpFiles);
        sort($migrationFiles, SORT_STRING); // Сортировка по имени файла по порядку

        foreach ($migrationFiles as $filePath) {
            $filename = basename($filePath);

            if (isset($appliedMap[$filename])) {
                logMsg("[SKIP] Миграция {$filename} уже применена.", $logFile);
                continue;
            }

            logMsg("--> Выполнение миграции: {$filename}...", $logFile);

            if (str_ends_with($filename, '.php')) {
                // Выполнение PHP миграции
                require $filePath;
            } else {
                // Выполнение SQL миграции
                $sql = file_get_contents($filePath);
                if (trim($sql) === '') {
                    logMsg("[WARN] Файл {$filename} пуст, пропускаем.", $logFile);
                    continue;
                }
                $db->exec($sql);
            }

            $stmtIns = $db->prepare("INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES (?, ?, NOW())");
            $stmtIns->execute([$filename, $currentBatch]);
            $appliedMap[$filename] = true;
            $appliedCount++;

            logMsg("[OK] Миграция {$filename} успешно применена (пакет #{$currentBatch}).", $logFile);
        }
    }

    // 4. Обеспечение доступа для супер-администратора (Жандос)
    $adminEmail = Config::get('SUPER_ADMIN_EMAIL', 'zhandos.alt06@gmail.com');
    $adminEmail = trim(strtolower($adminEmail));

    // Убеждаемся, что таблица invitations поддерживает роль admin
    $db->exec("ALTER TABLE `invitations` MODIFY COLUMN `role` ENUM('admin', 'editor', 'viewer') NOT NULL DEFAULT 'viewer'");

    $stmt = $db->prepare("SELECT id FROM `invitations` WHERE `email` = ?");
    $stmt->execute([$adminEmail]);
    if (!$stmt->fetch()) {
        $stmt = $db->prepare("INSERT INTO `invitations` (`email`, `role`, `notes`, `status`, `created_at`) VALUES (?, 'admin', 'Главный администратор (Жандос)', 'pending', NOW())");
        $stmt->execute([$adminEmail]);
        logMsg("[OK] Приглашение для главного администратора ({$adminEmail}) создано.", $logFile);
    }

    $stmt = $db->prepare("SELECT id, role FROM `users` WHERE `email` = ?");
    $stmt->execute([$adminEmail]);
    $user = $stmt->fetch();
    if ($user && $user['role'] !== 'admin') {
        $stmt = $db->prepare("UPDATE `users` SET `role` = 'admin', `is_active` = 1 WHERE `id` = ?");
        $stmt->execute([$user['id']]);
        logMsg("[OK] Права супер-администратора для {$adminEmail} подтверждены.", $logFile);
    }

    logMsg("=== Миграция успешно завершена! Применено новых миграций: {$appliedCount} ===", $logFile);
    exit(0);

} catch (Throwable $e) {
    $err = "[ERROR] Ошибка миграции: " . $e->getMessage() . "\n" . $e->getTraceAsString();
    logMsg($err, $logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($logDir . '/error.log', "[" . date('Y-m-d H:i:s') . "] MIGRATION ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    exit(1);
}
