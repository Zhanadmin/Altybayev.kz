<?php

declare(strict_types=1);

$routes = [
    '/'         => 'Главная страница',
    '/persons'  => 'Список людей (270 человек)',
    '/person/6' => 'Карточка персоны ID #6',
    '/families' => 'Раздел Семьи (71 брак)',
    '/dates'    => 'Памятные даты и дни рождения',
    '/history'  => 'История правок (audit_logs)',
    '/admin'    => 'Админ-панель',
    '/tree'     => 'Интерактивное древо',
];

echo "=======================================================\n";
echo "  Шежіре: HTTP curl тестирование локального сервера\n";
echo "=======================================================\n\n";

$port = 8998;
$host = '127.0.0.1';
$phpBinary = 'C:\\PHP\\php.exe';
$publicDir = realpath(__DIR__ . '/../public');

// 1. Создаем валидную сессию администратора в системной временной папке
session_name('ALTYBAYEV_SESSION');
session_id('testsession123');
session_start();
$_SESSION['auth_user'] = [
    'id'         => 1,
    'email'      => 'zhandos.alt06@gmail.com',
    'name'       => 'Жандос Алтыбаев',
    'role'       => 'admin',
    'person_id'  => 6,
];
session_write_close();

// 2. Запускаем встроенный PHP сервер
$serverCmd = "{$phpBinary} -S {$host}:{$port} -t " . escapeshellarg($publicDir);
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$serverProc = proc_open($serverCmd, $descriptors, $serverPipes, dirname(__DIR__));
// Даем серверу запуститься
usleep(500000); // 0.5 сек

$passed = 0;
$total = count($routes);

try {
    foreach ($routes as $route => $desc) {
        $url = "http://{$host}:{$port}{$route}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_COOKIE, 'ALTYBAYEV_SESSION=testsession123');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $bodyLen = strlen((string)$body);
        if ($httpCode === 200 && $bodyLen > 100) {
            $passed++;
            echo "  [OK] HTTP {$httpCode} на {$route} ({$desc}) — размер: {$bodyLen} байт\n";
        } else {
            echo "  [FAIL] HTTP {$httpCode} на {$route} ({$desc}) — размер: {$bodyLen} байт\n";
            if ($httpCode === 500) {
                echo "    Ответ сервера: " . substr(strip_tags((string)$body), 0, 300) . "\n";
            }
        }
    }
} finally {
    // Останавливаем сервер
    if (is_resource($serverProc)) {
        proc_terminate($serverProc);
        proc_close($serverProc);
    }
}

echo "\n-------------------------------------------------------\n";
echo "Результат: {$passed} из {$total} HTTP запросов вернули 200 OK!\n";
if ($passed === $total) {
    echo "ВСЕ МАРШРУТЫ УСПЕШНО ПРОШЛИ HTTP CURL ПРОВЕРКУ [SUCCESS]\n";
} else {
    echo "ЕСТЬ ОШИБКИ В HTTP ЗАПРОСАХ [FAILED]\n";
    exit(1);
}
echo "-------------------------------------------------------\n";
