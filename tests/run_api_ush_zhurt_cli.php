<?php
require_once __DIR__ . '/../app/bootstrap.php';
App\Core\Auth::login([
    'id' => 1,
    'email' => 'zhandos.alt06@gmail.com',
    'name' => 'Жандос Алтыбаев',
    'role' => 'admin',
    'person_id' => 6,
]);
$_GET['id'] = 6;
$c = new App\Controllers\TreeController();
$c->apiUshZhurt(new App\Core\Request());
