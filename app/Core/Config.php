<?php

declare(strict_types=1);

namespace App\Core;

class Config
{
    private static array $settings = [];
    private static bool $loaded = false;

    /**
     * Загружает параметры из .env файла
     */
    public static function load(string $filePath): void
    {
        if (self::$loaded) {
            return;
        }

        if (file_exists($filePath)) {
            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                if (str_contains($line, '=')) {
                    [$name, $value] = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value);

                    // Убираем обрамляющие кавычки
                    if (
                        (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                        (str_starts_with($value, "'") && str_ends_with($value, "'"))
                    ) {
                        $value = substr($value, 1, -1);
                    }

                    self::$settings[$name] = $value;
                    putenv("{$name}={$value}");
                    $_ENV[$name] = $value;
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Получить значение конфигурации
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$settings)) {
            return self::$settings[$key];
        }

        $envVal = getenv($key);
        if ($envVal !== false) {
            return $envVal;
        }

        return $default;
    }

    /**
     * Установить значение конфигурации (в рантайме)
     */
    public static function set(string $key, mixed $value): void
    {
        self::$settings[$key] = $value;
        $_ENV[$key] = $value;
    }

    /**
     * Проверка режима отладки
     */
    public static function isDebug(): bool
    {
        return filter_var(self::get('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
    }
}
