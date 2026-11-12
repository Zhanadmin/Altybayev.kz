<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $connection = null;

    /**
     * Получение единственного экземпляра подключения PDO
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $host = Config::get('DB_HOST', '127.0.0.1');
            $port = Config::get('DB_PORT', '3306');
            $dbName = Config::get('DB_NAME', 'altybayev_shezhire');
            $user = Config::get('DB_USER', 'root');
            $pass = Config::get('DB_PASS', '');
            $charset = Config::get('DB_CHARSET', 'utf8mb4');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci",
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
            ];

            try {
                self::$connection = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                if (Config::isDebug()) {
                    throw new RuntimeException("Database connection error: " . $e->getMessage(), (int)$e->getCode(), $e);
                }
                throw new RuntimeException("Қате: Мәліметтер қорына қосылу сәтсіз аяқталды / Ошибка подключения к базе данных.");
            }
        }

        return self::$connection;
    }

    /**
     * Алиас для getConnection()
     */
    public static function getInstance(): PDO
    {
        return self::getConnection();
    }

    /**
     * Сброс подключения (для тестов и повторной инициализации)
     */
    public static function resetConnection(): void
    {
        self::$connection = null;
    }

    /**
     * Выполнить транзакцию в замыкании
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::getConnection();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
