<?php

declare(strict_types=1);

// Подключение инициализации приложения
require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Request;
use App\Core\Router;

$router = new Router();

// 1. Главная страница (доступна только авторизованным пользователям, иначе редирект на /login)
$router->get('/', 'App\Controllers\HomeController@index', ['auth']);

// 2. Интерактивное древо (MyHeritage + Шежіре-Орман / Лес)
$router->get('/tree', 'App\Controllers\TreeController@index', ['auth']);

// 3. Маршруты авторизации и аутентификации через Google
$router->get('/login', 'App\Controllers\AuthController@showLogin');
$router->get('/logout', 'App\Controllers\AuthController@logout');
$router->get('/auth/google', 'App\Controllers\AuthController@redirectToGoogle');
$router->get('/auth/google/callback', 'App\Controllers\AuthController@handleGoogleCallback');

// 4. API маршруты
$router->get('/api/me', 'App\Controllers\AuthController@currentUser');
$router->get('/api/tree', 'App\Controllers\TreeController@apiTreeData', ['auth']);
$router->get('/api/kinship', 'App\Controllers\TreeController@apiKinship', ['auth']);
$router->get('/api/tree/search', 'App\Controllers\TreeController@apiSearch', ['auth']);

// Запуск диспетчера маршрутов
$request = new Request();
$router->dispatch($request);
