<?php

declare(strict_types=1);

// Подключение инициализации приложения
require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Request;
use App\Core\Router;

$router = new Router();

// 1. Главная страница: гостю — название и кнопка входа (TASK-014: Google проверяет домашнюю страницу), члену семьи — шежіре
$router->get('/', 'App\Controllers\HomeController@index');

// TASK-014: публичные страницы для Google OAuth (без входа)
$router->get('/privacy', 'App\Controllers\LegalController@privacy');
$router->get('/terms', 'App\Controllers\LegalController@terms');

// TASK-027: форма обратной связи на приветственной странице (публичная: CSRF, honeypot, лимит) и её управление в админке
$router->post('/api/feedback', 'App\Controllers\FeedbackController@submit');
$router->post('/api/admin/contacts/save', 'App\Controllers\FeedbackController@saveContacts', ['auth', 'admin']);
$router->post('/api/admin/feedback/read', 'App\Controllers\FeedbackController@markRead', ['auth', 'admin']);

// 2. Интерактивное древо (MyHeritage + Шежіре-Орман / Лес)
$router->get('/tree', 'App\Controllers\TreeController@index', ['auth']);

// 3. Новые разделы обычного сайта (TASK-007)
$router->get('/persons', 'App\Controllers\PersonController@index', ['auth']);
$router->get('/person/{id}', 'App\Controllers\PersonController@show', ['auth']);
$router->get('/families', 'App\Controllers\FamilyController@index', ['auth']);
$router->get('/dates', 'App\Controllers\MemorialController@index', ['auth']);
$router->get('/history', 'App\Controllers\HistoryController@index', ['auth', 'admin']); // ALT-004: журнал изменений — только админ
$router->get('/admin', 'App\Controllers\AdminController@index', ['auth', 'admin']);
$router->get('/lang/{code}', 'App\Controllers\AuthController@switchLanguage');
$router->post('/api/photo/upload', 'App\Controllers\PhotoUploadController@upload', ['auth']);
$router->post('/api/admin/user/role', 'App\Controllers\AdminController@updateUserRole', ['auth', 'admin']);
$router->post('/api/admin/user/unlink', 'App\Controllers\AdminController@unlinkUser', ['auth', 'admin']);
$router->post('/api/admin/user/delete', 'App\Controllers\AdminController@deleteUser', ['auth', 'admin']);
$router->post('/api/admin/invitations/create', 'App\Controllers\AdminController@createInvitation', ['auth', 'admin']);
$router->post('/api/admin/invitations/role', 'App\Controllers\AdminController@updateInvitationRole', ['auth', 'admin']);
$router->post('/api/admin/invitations/revoke', 'App\Controllers\AdminController@revokeInvitation', ['auth', 'admin']);
$router->post('/api/history/rollback', 'App\Controllers\HistoryController@rollback', ['auth', 'admin']);
// TASK-030: справочник ру с таңба-знаками (editor и admin)
$router->get('/admin/clans', 'App\Controllers\ClanController@index', ['auth', 'editor']);
$router->get('/api/clans', 'App\Controllers\ClanController@list', ['auth']);
$router->post('/api/clans/save', 'App\Controllers\ClanController@save', ['auth', 'editor']);
$router->post('/api/clans/delete', 'App\Controllers\ClanController@delete', ['auth', 'editor']);
$router->post('/api/clans/tamga/upload', 'App\Controllers\ClanController@tamgaUpload', ['auth', 'editor']);
$router->post('/api/clans/tamga/delete', 'App\Controllers\ClanController@tamgaDelete', ['auth', 'editor']);

// 3. Маршруты авторизации и аутентификации через Google
$router->get('/login', 'App\Controllers\AuthController@showLogin');
$router->get('/logout', 'App\Controllers\AuthController@logout');
$router->get('/__test-login', 'App\Controllers\TestLoginController@login'); // TASK-009: только altybayev.st.int + флаг в .env, иначе 404
$router->get('/auth/google','App\Controllers\AuthController@redirectToGoogle');
$router->get('/auth/google/callback', 'App\Controllers\AuthController@handleGoogleCallback');

// 4. API маршруты
$router->get('/api/me', 'App\Controllers\AuthController@currentUser');
$router->get('/api/tree', 'App\Controllers\TreeController@apiTreeData', ['auth']);
$router->get('/api/tree/layout', 'App\Controllers\TreeController@apiTreeLayout', ['auth']);
$router->get('/api/tree/list', 'App\Controllers\TreeController@apiTreeList', ['auth']);
$router->get('/api/person', 'App\Controllers\TreeController@apiPersonGet', ['auth']);
$router->post('/api/person/update', 'App\Controllers\TreeController@apiPersonUpdate', ['auth']);
$router->get('/api/person/clan-propagate-preview', 'App\Controllers\TreeController@apiClanPropagatePreview', ['auth']);
$router->post('/api/person/clan-propagate', 'App\Controllers\TreeController@apiClanPropagateApply', ['auth']);
$router->post('/api/person/create-relative', 'App\Controllers\TreeController@apiPersonCreateRelative', ['auth']);
$router->post('/api/person/delete', 'App\Controllers\TreeController@apiPersonDelete', ['auth']);
$router->get('/api/admin/persons/trash', 'App\Controllers\TreeController@apiTrashList', ['auth', 'admin']); // ALT-018
$router->post('/api/admin/person/restore', 'App\Controllers\TreeController@apiPersonRestore', ['auth', 'admin']); // ALT-018
$router->post('/api/person/contacts/save', 'App\Controllers\PersonContactController@save', ['auth']); // TASK-022
$router->post('/api/person/invite', 'App\Controllers\PersonContactController@invite', ['auth']); // TASK-022
$router->get('/api/album/photos', 'App\Controllers\PersonCoverController@photos', ['auth']); // TASK-022: выбор фото из альбомов
$router->post('/api/person/avatar/upload', 'App\Controllers\PersonCoverController@avatarUpload', ['auth']); // TASK-022
$router->post('/api/person/cover/save', 'App\Controllers\PersonCoverController@save', ['auth']); // TASK-022
$router->post('/api/marriage/update', 'App\Controllers\TreeController@apiMarriageUpdate', ['auth']);
$router->post('/api/user/set-my-person', 'App\Controllers\TreeController@apiSetMyPerson', ['auth']);
$router->get('/api/kinship', 'App\Controllers\TreeController@apiKinship', ['auth']);
$router->get('/api/kinship-all', 'App\Controllers\TreeController@apiKinshipAll', ['auth']);
$router->get('/api/person/ush-zhurt', 'App\Controllers\TreeController@apiUshZhurt', ['auth']);
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

// TASK-018: фотоальбомы семей и мероприятий (фото и видео; файлы вне public, выдача после входа)
$router->get('/family/{id}/albums', 'App\Controllers\AlbumController@familyAlbums', ['auth']);
$router->get('/album/media', 'App\Controllers\AlbumController@serve', ['auth']); // до /album/{id}
$router->get('/album/{id}', 'App\Controllers\AlbumController@show', ['auth']);
$router->get('/events', 'App\Controllers\AlbumController@events', ['auth']);
$router->get('/event/{id}', 'App\Controllers\AlbumController@event', ['auth']);
$router->post('/api/album/save', 'App\Controllers\AlbumController@apiSaveAlbum', ['auth']);
$router->post('/api/album/delete', 'App\Controllers\AlbumController@apiDeleteAlbum', ['auth']);
$router->post('/api/event/save', 'App\Controllers\AlbumController@apiSaveEvent', ['auth']);
$router->post('/api/event/delete', 'App\Controllers\AlbumController@apiDeleteEvent', ['auth']);
$router->post('/api/album/upload/init', 'App\Controllers\AlbumController@apiUploadInit', ['auth']);
$router->post('/api/album/upload/chunk', 'App\Controllers\AlbumController@apiUploadChunk', ['auth']);
$router->post('/api/album/upload/complete', 'App\Controllers\AlbumController@apiUploadComplete', ['auth']);
$router->post('/api/album/upload/cancel', 'App\Controllers\AlbumController@apiUploadCancel', ['auth']);
$router->post('/api/album/media/update', 'App\Controllers\AlbumController@apiMediaUpdate', ['auth']);
$router->post('/api/album/media/delete', 'App\Controllers\AlbumController@apiMediaDelete', ['auth']);
$router->post('/api/album/media/avatar', 'App\Controllers\AlbumController@apiSetAvatar', ['auth']);

// TASK-019: распознавание лиц в фотоальбомах (в браузере; эталон — аватарка; отдельной страницы «Лица» нет — замечание 16.11)
$router->get('/api/face/album/{id}', 'App\Controllers\FaceController@apiAlbum', ['auth']);
$router->get('/api/face/media/{id}', 'App\Controllers\FaceController@apiMediaFaces', ['auth']);
$router->post('/api/face/save', 'App\Controllers\FaceController@apiSave', ['auth']);
$router->post('/api/face/references', 'App\Controllers\FaceController@apiReferences', ['auth']);
$router->post('/api/face/confirm', 'App\Controllers\FaceController@apiConfirm', ['auth']);
$router->post('/api/face/reject', 'App\Controllers\FaceController@apiReject', ['auth']);
$router->post('/api/face/settings', 'App\Controllers\FaceController@apiSettings', ['auth']);
$router->post('/api/face/delete-person', 'App\Controllers\FaceController@apiDeletePerson', ['auth']);
$router->post('/api/face/opt-out', 'App\Controllers\FaceController@apiOptOut', ['auth']);

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
