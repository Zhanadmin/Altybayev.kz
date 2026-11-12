<?php

declare(strict_types=1);

// Автозагрузка классов пространства имен App\
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Загрузка настроек окружения
use App\Core\Config;
use App\Core\Session;

Config::load(__DIR__ . '/../.env');

// Настройка вывода ошибок
if (Config::isDebug()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Запуск сессии
Session::start();
