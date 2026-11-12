<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

class Logger
{
    private static function getLogPath(string $filename = 'error.log'): string
    {
        $dir = __DIR__ . '/../../storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . '/' . $filename;
    }

    public static function error(string $message, ?Throwable $exception = null): void
    {
        $entry = sprintf("[%s] ERROR: %s", date('Y-m-d H:i:s'), $message);
        if ($exception !== null) {
            $entry .= sprintf(
                "\nException: %s in %s:%d\nStack trace:\n%s",
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            );
        }
        $entry .= "\n----------------------------------------\n";
        @file_put_contents(self::getLogPath('error.log'), $entry, FILE_APPEND);
    }

    public static function info(string $message): void
    {
        $entry = sprintf("[%s] INFO: %s\n", date('Y-m-d H:i:s'), $message);
        @file_put_contents(self::getLogPath('app.log'), $entry, FILE_APPEND);
    }
}
