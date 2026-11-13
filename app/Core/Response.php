<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    /**
     * Отправить JSON-ответ
     */
    public static function json(mixed $data, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');

            foreach ($headers as $key => $value) {
                header("{$key}: {$value}");
            }
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Отправить успешный JSON-ответ
     */
    public static function success(mixed $data = [], string $message = 'OK'): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], 200);
    }

    /**
     * Отправить JSON-ответ с ошибкой
     */
    public static function error(string $message, int $status = 400, mixed $errors = null): void
    {
        self::json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    /**
     * Перенаправление (Redirect)
     */
    public static function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        if (!headers_sent()) {
            header("Location: {$url}");
        }
        exit;
    }

    /**
     * Рендеринг HTML шаблона
     */
    public static function view(string $viewPath, array $data = []): void
    {
        $file = __DIR__ . '/../../views/' . ltrim($viewPath, '/') . '.php';
        if (!file_exists($file)) {
            http_response_code(500);
            echo "Шаблон не найден: " . htmlspecialchars($viewPath);
            exit;
        }

        extract($data, EXTR_SKIP);
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        require $file;
        exit;
    }
}
