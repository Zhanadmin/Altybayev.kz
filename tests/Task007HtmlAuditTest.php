<?php
declare(strict_types=1);

$routesToTest = [
    '/'          => 'HomeController@index',
    '/persons'   => 'PersonController@index',
    '/person/6'  => 'PersonController@show',
    '/person/1'  => 'PersonController@show (Габит)',
    '/families'  => 'FamilyController@index',
    '/dates'     => 'MemorialController@index',
    '/history'   => 'HistoryController@index',
    '/admin'     => 'AdminController@index',
    '/tree'      => 'TreeController@index',
];

echo "=======================================================\n";
echo "  Шежіре: Аудит HTML всех страниц на служебный код\n";
echo "=======================================================\n\n";

$phpBinary = 'C:\\PHP\\php.exe';

$patterns = [
    'gedcom_id' => '/@I[0-9]+@|@F[0-9]+@/',
    'gedcom_tags' => '/\b(CONC|CONT|_MARNM|_UID)\b/',
    'trans_keys' => '/\b(btn_[a-z0-9_]+|nav_[a-z0-9_]+|th_[a-z0-9_]+|field_[a-z0-9_]+|filter_[a-z0-9_]+)\b/',
    'undefined_or_raw_null' => '/>\s*(null|undefined)\s*</i',
    'error_trace' => '/(Stack trace|Fatal error|Parse error|SQLSTATE|PDOException)/',
];

$allClean = true;

foreach ($routesToTest as $route => $handler) {
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
                case "/person/1":
                    (new \App\Controllers\PersonController())->show($req, 1);
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
            echo "EXCEPTION: " . $e->getMessage();
            exit(1);
        }
    ';

    $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'audit_test_' . md5($route) . '.php';
    file_put_contents($tempFile, "<?php\n" . $code);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    $proc = proc_open("{$phpBinary} " . escapeshellarg($tempFile), $descriptors, $pipes, dirname(__DIR__));
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    @unlink($tempFile);

    echo "Маршрут {$route} ({$handler}):\n";
    $routeClean = true;
    foreach ($patterns as $name => $regex) {
        if (preg_match_all($regex, $stdout, $matches)) {
            $uniqueMatches = array_unique($matches[0]);
            echo "  [FOUND] {$name}: " . implode(', ', array_slice($uniqueMatches, 0, 5)) . "\n";
            $routeClean = false;
            $allClean = false;
        }
    }
    if ($routeClean) {
        echo "  [OK] Чистый HTML (нет утечек служебного кода)\n";
    }
}

if ($allClean) {
    echo "\nРезультат: Все маршруты полностью очищены от служебного кода! [SUCCESS]\n";
} else {
    echo "\nРезультат: Обнаружены служебные коды, требуется доработка шаблонов!\n";
}
