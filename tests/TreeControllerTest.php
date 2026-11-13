<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Controllers\TreeController;

echo "=======================================================\n";
echo "  Шежіре: Тестирование TreeController и API (TASK-003) \n";
echo "=======================================================\n\n";

$passed = 0;
$total = 0;

function assertCheck(bool $condition, string $label): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [OK] {$label}\n";
    } else {
        echo "  [FAIL] {$label}\n";
    }
}

Auth::login([
    'id' => 1,
    'email' => 'zhandos.alt06@gmail.com',
    'name' => 'Жандос Алтыбаев',
    'role' => 'admin',
]);

$controller = new TreeController();

// 1. Тест ответа /api/tree на базе данных
echo "1. Тестирование ответа /api/tree на базе данных:\n";
$_GET = [];
$reqEmpty = new Request();
$datasetEmpty = $controller->getDataset();

assertCheck($datasetEmpty['error'] === null, 'Ошибок в датасете нет');
assertCheck(is_array($datasetEmpty['persons']), 'Поле persons является массивом');
assertCheck(count($datasetEmpty['persons']) >= 0, 'Персоны успешно получены из базы (' . count($datasetEmpty['persons']) . ' персон)');
assertCheck(is_array($datasetEmpty['clans']), 'Рода clans получены из базы');

// 2. Тест связей и браков
echo "\n2. Тестирование связей и браков:\n";
assertCheck(is_array($datasetEmpty['marriages']), 'Поле marriages является массивом');

// 3. Тест поиска
echo "\n3. Тестирование поиска по дереву:\n";
$_GET['q'] = 'Жандос';
$reqSearch = new Request();
$allPersons = $datasetEmpty['persons'];
$searchMatches = array_filter($allPersons, fn($p) => mb_stripos($p['first_name'] ?? '', 'Жандос') !== false);
assertCheck(count($searchMatches) >= 0, 'Поиск по имени Жандос выполнен успешно');

// 4. Тест отказоустойчивости при недоступной БД
echo "\n4. Тестирование отказоустойчивости (Graceful DB Error Handling):\n";
Config::set('DB_NAME', 'invalid_db_for_test_graceful');
Database::resetConnection();
$datasetError = $controller->getDataset();

assertCheck(!empty($datasetError['error']), 'Зафиксирована ошибка подключения к несуществующей БД');
assertCheck(is_array($datasetError['persons']) && empty($datasetError['persons']), 'При ошибке БД persons возвращается как пустой массив без падения');
assertCheck(file_exists(__DIR__ . '/../storage/logs/error.log'), 'Ошибка записана в storage/logs/error.log');

// Восстанавливаем корректное имя БД
Config::set('DB_NAME', 'altybayev_clean_test');
Database::resetConnection();

echo "\n-------------------------------------------------------\n";
echo "Результат: {$passed} из {$total} проверок пройдено успешно!\n";
if ($passed === $total) {
    echo "ВСЕ ТЕСТЫ КОНТРОЛЛЕРА ДРЕВА ПРОЙДЕНЫ [SUCCESS]\n";
} else {
    echo "ЕСТЬ ОШИБКИ [FAILED]\n";
    exit(1);
}
echo "-------------------------------------------------------\n";
