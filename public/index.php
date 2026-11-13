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
$router->get('/api/tree/layout', 'App\Controllers\TreeController@apiTreeLayout', ['auth']);
$router->get('/api/tree/list', 'App\Controllers\TreeController@apiTreeList', ['auth']);
$router->get('/api/person', 'App\Controllers\TreeController@apiPersonGet', ['auth']);
$router->post('/api/person/update', 'App\Controllers\TreeController@apiPersonUpdate', ['auth']);
$router->post('/api/person/create-relative', 'App\Controllers\TreeController@apiPersonCreateRelative', ['auth']);
$router->post('/api/person/delete', 'App\Controllers\TreeController@apiPersonDelete', ['auth']);
$router->post('/api/marriage/update', 'App\Controllers\TreeController@apiMarriageUpdate', ['auth']);
$router->post('/api/user/set-my-person', 'App\Controllers\TreeController@apiSetMyPerson', ['auth']);
$router->get('/api/user/set-my-person', 'App\Controllers\TreeController@apiSetMyPerson', ['auth']);
$router->get('/api/kinship', 'App\Controllers\TreeController@apiKinship', ['auth']);
$router->get('/api/kinship-all', 'App\Controllers\TreeController@apiKinshipAll', ['auth']);
$router->get('/api/tree/search', 'App\Controllers\TreeController@apiSearch', ['auth']);

// 5. Жеті ата, естеліктер мен аудио, памятные даты, печать шежіре (TASK-004)
$router->get('/api/zheti-ata', 'App\Controllers\ZhetiAtaController@check', ['auth']);
$router->post('/api/zheti-ata', 'App\Controllers\ZhetiAtaController@check', ['auth']);
$router->get('/api/zheti-ata/seven', 'App\Controllers\ZhetiAtaController@sevenAncestors', ['auth']);
$router->get('/api/memorial-dates', 'App\Controllers\MemorialController@apiSummary', ['auth']);
$router->get('/api/memorial-dates/birthdays', 'App\Controllers\MemorialController@apiBirthdays', ['auth']);
$router->get('/api/memorial-dates/anniversaries', 'App\Controllers\MemorialController@apiAnniversaries', ['auth']);
$router->get('/api/person/media', 'App\Controllers\MediaController@apiList', ['auth']);
$router->post('/api/person/media/upload', 'App\Controllers\MediaController@upload', ['auth']);
$router->post('/api/person/story/save', 'App\Controllers\MediaController@saveStory', ['auth']);
$router->post('/api/person/media/delete', 'App\Controllers\MediaController@delete', ['auth']);
$router->get('/media/stream', 'App\Controllers\MediaController@stream', ['auth']);
$router->get('/tree/print', 'App\Controllers\PrintPosterController@index', ['auth']);
$router->get('/api/tree/print-data', 'App\Controllers\PrintPosterController@apiData', ['auth']);
$router->get('/tree/print/svg', 'App\Controllers\PrintPosterController@downloadSvg', ['auth']);

// 6. Админ-панель: Импорт GEDCOM из MyHeritage и управление откатами (TASK-005)
$router->get('/admin/import', 'App\Controllers\AdminImportController@index', ['auth', 'admin']);
$router->get('/admin/import/preview', 'App\Controllers\AdminImportController@preview', ['auth', 'admin']);
$router->post('/admin/import/preview', 'App\Controllers\AdminImportController@preview', ['auth', 'admin']);
$router->post('/admin/import/execute', 'App\Controllers\AdminImportController@execute', ['auth', 'admin']);
$router->post('/admin/import/rollback', 'App\Controllers\AdminImportController@rollback', ['auth', 'admin']);
$router->get('/admin/import/clear', 'App\Controllers\AdminImportController@clearPreview', ['auth', 'admin']);
$router->get('/api/admin/import/preview', 'App\Controllers\AdminImportController@preview', ['auth', 'admin']);

// Запуск диспетчера маршрутов
$request = new Request();
$router->dispatch($request);
