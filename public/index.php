<?php

declare(strict_types=1);

// Подключение инициализации приложения
require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Request;
use App\Core\Router;

$router = new Router();

// 1. Главная страница (доступна только авторизованным пользователям, иначе редирект на /login)
$router->get('/', 'App\Controllers\HomeController@index', ['auth']);

// 2. Маршруты авторизации и аутентификации через Google
$router->get('/login', 'App\Controllers\AuthController@showLogin');
$router->get('/logout', 'App\Controllers\AuthController@logout');
$router->get('/auth/google', 'App\Controllers\AuthController@redirectToGoogle');
$router->get('/auth/google/callback', 'App\Controllers\AuthController@handleGoogleCallback');

// 3. API маршруты
$router->get('/api/me', 'App\Controllers\AuthController@currentUser');

// Запуск диспетчера маршрутов
$request = new Request();
$router->dispatch($request);
