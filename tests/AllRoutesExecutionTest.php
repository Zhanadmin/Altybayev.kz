<?php

declare(strict_types=1);

$routesToTest = [
    '/'          => 'HomeController@index',
    '/persons'   => 'PersonController@index',
    '/person/6'  => 'PersonController@show',
    '/families'  => 'FamilyController@index',
    '/dates'     => 'MemorialController@index',
    '/history'   => 'HistoryController@index',
    '/admin'     => 'AdminController@index',
    '/tree'      => 'TreeController@index',
];

echo "=======================================================\n";
echo "  Шежіре: Тестирование всех маршрутов и контроллеров\n";
echo "=======================================================\n\n";

$passed = 0;
$total = count($routesToTest);

$phpBinary = 'C:\\PHP\\php.exe';

foreach ($routesToTest as $route => $handler) {
    // Формируем автономный PHP скрипт для изоляции вызова
    $code = '
        require_once "app/bootstrap.php";
        use App\Core\Auth;
        use App\Core\Request;
        Auth::login([
            "id" => 1,
            "email" => "zhandos.alt06@gmail.com",
            "name" => "Жандос Алтыбаев",
            "role" => "admin",
            "person_id" => 6
        ]);
        $route = "' . $route . '";
        $req = new Request();
        try {
            switch ($route) {
                case "/":
                    (new \App\Controllers\HomeController())->index($req);
                    break;
                case "/persons":
                    (new \App\Controllers\PersonController())->index($req);
                    break;
                case "/person/6":
                    (new \App\Controllers\PersonController())->show($req, 6);
                    break;
                case "/families":
                    (new \App\Controllers\FamilyController())->index($req);
                    break;
                case "/dates":
                    (new \App\Controllers\MemorialController())->index($req);
                    break;
                case "/history":
                    (new \App\Controllers\HistoryController())->index($req);
                    break;
                case "/admin":
                    (new \App\Controllers\AdminController())->index($req);
                    break;
                case "/tree":
                    (new \App\Controllers\TreeController())->index($req);
                    break;
            }
        } catch (\Throwable $e) {
            echo "EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
            exit(1);
        }
    ';

    $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'route_test_' . md5($route) . '.php';
    file_put_contents($tempFile, "<?php\n" . $code);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    $proc = proc_open("{$phpBinary} " . escapeshellarg($tempFile), $descriptors, $pipes, dirname(__DIR__));
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($proc);
    @unlink($tempFile);

    $isOk = ($exitCode === 0) && !str_contains($stdout, 'Fatal error') && !str_contains($stdout, 'EXCEPTION') && !str_contains($stderr, 'Fatal error') && strlen($stdout) > 200;

    if ($isOk) {
        $passed++;
        echo "  [OK] Маршрут {$route} ({$handler}) -> 200 OK (размер HTML: " . strlen($stdout) . " байт)\n";
    } else {
        echo "  [FAIL] Маршрут {$route} ({$handler}) упал (exitCode={$exitCode})!\n";
        if (!empty($stderr)) {
            echo "    STDERR: " . trim($stderr) . "\n";
        }
        if (!empty($stdout) && (str_contains($stdout, 'error') || str_contains($stdout, 'EXCEPTION'))) {
            echo "    STDOUT: " . substr(trim($stdout), 0, 300) . "\n";
        }
    }
}

echo "\n-------------------------------------------------------\n";
echo "Результат: {$passed} из {$total} маршрутов успешно отработали!\n";
if ($passed === $total) {
    echo "ВСЕ МАРШРУТЫ РАБОТАЮТ БЕЗ ЕДИНОЙ ОШИБКИ [SUCCESS]\n";
} else {
    echo "ЕСТЬ ОШИБКИ В МАРШРУТАХ [FAILED]\n";
    exit(1);
}
echo "-------------------------------------------------------\n";
