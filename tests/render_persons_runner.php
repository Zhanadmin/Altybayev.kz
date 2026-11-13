<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;
use App\Core\I18n;
use App\Core\Request;
use App\Controllers\PersonController;

Auth::login([
    'id' => 1,
    'email' => 'zhandos.alt06@gmail.com',
    'name' => 'Жандос Алтыбаев',
    'role' => 'admin',
    'person_id' => 6
]);

$lang = $argv[1] ?? 'kk';
$q = $argv[2] ?? '';

I18n::setLang($lang);
$get = ['lang' => $lang];
if ($q !== '') {
    $get['q'] = $q;
}
$req = new Request('GET', '/persons', [], $get);
(new PersonController())->index($req);
