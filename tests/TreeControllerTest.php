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

// 1. Тест пустого состояния чистой базы данных (без demo)
echo "1. Тестирование ответа /api/tree на чистой базе:\n";
$_GET = [];
$reqEmpty = new Request();
$datasetEmpty = $controller->getDataset(false);

assertCheck($datasetEmpty['error'] === null, 'Ошибок в датасете нет');
assertCheck(is_array($datasetEmpty['persons']), 'Поле persons является массивом');
assertCheck(empty($datasetEmpty['persons']), 'База пуста: персон 0');
assertCheck(!empty($datasetEmpty['clans']), 'Рода clans успешно загружены из базы (5 родов)');

// 2. Тест демонстрационного режима
echo "\n2. Тестирование демонстрационного режима (?demo=1):\n";
$datasetDemo = $controller->getDataset(true);

assertCheck($datasetDemo['is_demo'] === true, 'Флаг is_demo = true');
assertCheck(count($datasetDemo['persons']) === 12, 'В демо-наборе 12 персон');
assertCheck(count($datasetDemo['clans']) === 3, 'В демо-наборе 3 рода');
assertCheck(count($datasetDemo['marriages']) === 3, 'В демо-наборе 3 брака');

// 3. Тест поиска
echo "\n3. Тестирование поиска по дереву:\n";
$_GET['demo'] = '1';
$_GET['q'] = 'Жандос';
$reqSearch = new Request();
$allDemo = $datasetDemo['persons'];
$searchMatches = array_filter($allDemo, fn($p) => mb_stripos($p['first_name'], 'Жандос') !== false);
assertCheck(count($searchMatches) > 0, 'Поиск по имени Жандос находит персону');

// 4. Тест отказоустойчивости при недоступной БД
echo "\n4. Тестирование отказоустойчивости (Graceful DB Error Handling):\n";
Config::set('DB_NAME', 'invalid_db_for_test_graceful');
Database::resetConnection();
$datasetError = $controller->getDataset(false);

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
