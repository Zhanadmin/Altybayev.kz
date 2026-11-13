<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;
use App\Core\Request;
use App\Controllers\TreeController;
use App\Services\KinshipService;

echo "=======================================================\n";
echo "  Шежіре: Тестирование Үш жұрт кестелері (TASK-011)   \n";
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
    'person_id' => 6,
]);

$controller = new TreeController();
$dataset = $controller->getDataset();
$ks = new KinshipService();
$personsById = array_column($dataset['persons'], null, 'id');
$marriages = $dataset['marriages'];

// 1. Тестирование getUshZhurtTables для мужчины (Жандос, ID 6)
echo "1. Тестирование getUshZhurtTables для мужчины (Жандос, ID 6):\n";
$zhandosData = $ks->getUshZhurtTables(6, $personsById, $marriages);

assertCheck(!empty($zhandosData), 'Данные Үш жұрт успешно получены для ID 6');
assertCheck(($zhandosData['base_person']['id'] ?? 0) === 6, 'Базовая персона ID = 6 (Жандос)');
assertCheck(mb_stripos($zhandosData['base_person']['full_name'] ?? '', 'Жандос') !== false, 'Имя базовой персоны содержит Жандос');
assertCheck($zhandosData['is_female'] === false, 'is_female === false для мужчины');
assertCheck(count($zhandosData['tables']) === 3, 'Ровно 3 таблицы в массиве tables');

// Проверка порядка таблиц
$t1 = $zhandosData['tables'][0];
$t2 = $zhandosData['tables'][1];
$t3 = $zhandosData['tables'][2];

assertCheck($t1['key'] === 'oz', 'Таблица 1 — Өз жұрты (key: oz)');
assertCheck($t2['key'] === 'kaiyn', 'Таблица 2 — Қайын жұрты (key: kaiyn)');
assertCheck($t3['key'] === 'nagashy', 'Таблица 3 — Нағашы жұрты (key: nagashy)');

assertCheck($t1['title_kk'] === 'Өз жұрты', 'Заголовок таблицы 1 для мужчины = Өз жұрты');
assertCheck($t2['title_kk'] === 'Қайын жұрты', 'Заголовок таблицы 2 = Қайын жұрты');
assertCheck($t3['title_kk'] === 'Нағашы жұрты', 'Заголовок таблицы 3 = Нағашы жұрты');

assertCheck($t1['total_count'] > 0, "Таблица Өз жұрты заполнена ({$t1['total_count']} персон)");
assertCheck($t2['total_count'] > 0, "Таблица Қайын жұрты заполнена ({$t2['total_count']} персон)");
assertCheck($t3['total_count'] > 0, "Таблица Нағашы жұрты заполнена ({$t3['total_count']} персон)");

// Проверка состава Қайын жұрты для Жандоса
$kaiynNames = array_column($t2['items'], 'full_name');
$hasKayinAta = false;
$hasKayinEne = false;
foreach ($t2['items'] as $item) {
    if (mb_stripos($item['term_kk'], 'Қайын ата') !== false) $hasKayinAta = true;
    if (mb_stripos($item['term_kk'], 'Қайын ене') !== false) $hasKayinEne = true;
}
assertCheck($hasKayinAta, 'В Қайын жұрты присутствует Қайын ата (Избасар)');
assertCheck($hasKayinEne, 'В Қайын жұрты присутствует Қайын ене (Канслу)');

// Проверка структуры каждого элемента таблицы
$sampleItem = $t1['items'][0];
assertCheck(isset($sampleItem['id']) && $sampleItem['id'] > 0, 'Элемент таблицы имеет id');
assertCheck(!empty($sampleItem['full_name']), 'Элемент таблицы имеет full_name');
assertCheck(in_array($sampleItem['gender'], ['M', 'F'], true), 'Элемент таблицы имеет пол (M/F)');
assertCheck(isset($sampleItem['years_display']), 'Элемент таблицы имеет years_display');
assertCheck(!empty($sampleItem['term_kk']), 'Элемент таблицы имеет казахский термин родства term_kk');

// 2. Тестирование Үш жұрт для женщины (Дополнение Жандоса от 13.11)
echo "\n2. Тестирование Үш жұрт для женщины (Дополнение Жандоса от 13.11):\n";
$femaleId = 2; // Менслу Айткалиева
$femaleData = $ks->getUshZhurtTables($femaleId, $personsById, $marriages);

assertCheck($femaleData['is_female'] === true, 'is_female === true для женщины');
assertCheck($femaleData['tables'][0]['title_kk'] === 'Төркін жұрты', 'Для женщины таблица 1 называется Төркін жұрты');
assertCheck($femaleData['tables'][1]['title_kk'] === 'Қайын жұрты', 'Для женщины таблица 2 называется Қайын жұрты');
assertCheck(mb_stripos($femaleData['tables'][1]['title_ru'], 'мужа') !== false, 'Для женщины пояснение таблицы 2 содержит род мужа');
assertCheck($femaleData['tables'][2]['title_kk'] === 'Нағашы жұрты', 'Для женщины таблица 3 называется Нағашы жұрты');
assertCheck($femaleData['tables'][0]['total_count'] > 0, 'Төркін жұрты содержит родственников');

// 3. Тестирование ответа контроллера /api/person/ush-zhurt через отдельный изолированный вызов
echo "\n3. Тестирование ответа контроллера apiUshZhurt:\n";
$runnerFile = __DIR__ . '/run_api_ush_zhurt_cli.php';
$phpBinary = 'C:\\PHP\\php.exe';
$cmd = $phpBinary . ' ' . escapeshellarg($runnerFile);
$apiOut = (string)shell_exec($cmd);
$apiJson = json_decode($apiOut, true);

assertCheck(is_array($apiJson), 'API возвращает валидный JSON');
assertCheck(($apiJson['status'] ?? '') === 'success', 'API возвращает status: success');
assertCheck(($apiJson['base_id'] ?? 0) === 6, 'API возвращает base_id: 6');
assertCheck(isset($apiJson['person']['full_name']), 'API возвращает объект person');
assertCheck(isset($apiJson['tables']) && count($apiJson['tables']) === 3, 'API возвращает массив tables из 3 таблиц');
assertCheck(isset($apiJson['groups']['oz']) && isset($apiJson['groups']['kaiyn']) && isset($apiJson['groups']['nagashy']), 'API возвращает объект groups с ключами oz, kaiyn, nagashy');
assertCheck(isset($apiJson['ush_zhurt']['oz_zhurt']), 'API сохраняет обратную совместимость с ush_zhurt (семейные папки)');

// 4. Тестирование разметки и скриптов в views/tree.php
echo "\n4. Тестирование разметки и скриптов в views/tree.php:\n";
$treeHtml = file_get_contents(__DIR__ . '/../views/tree.php');

assertCheck(strpos($treeHtml, 'list-person-row') !== false, 'Класс list-person-row добавлен строкам таблицы');
assertCheck(strpos($treeHtml, 'togglePersonUshZhurt') !== false, 'Функция togglePersonUshZhurt объявлена');
assertCheck(strpos($treeHtml, 'navigateToListPerson') !== false, 'Функция navigateToListPerson (переход к человеку) объявлена');
assertCheck(strpos($treeHtml, 'renderSingleUshZhurtCard') !== false, 'Функция renderSingleUshZhurtCard объявлена');
assertCheck(strpos($treeHtml, 'filterUshCardRows') !== false, 'Функция фильтрации строк внутри карточки filterUshCardRows объявлена');
assertCheck(strpos($treeHtml, 'toggleUshLayout') !== false, 'Функция переключения вида toggleUshLayout (рядом / под ним) объявлена');
assertCheck(strpos($treeHtml, 'lt-ush-tables-grid') !== false, 'Сетка трех таблиц lt-ush-tables-grid присутствует в CSS');
assertCheck(strpos($treeHtml, 'uz-avatar-mini') !== false, 'Цветовая индикация пола uz-avatar-mini присутствует в CSS');
assertCheck(strpos($treeHtml, 'kin-term-badge') !== false, 'Стили бейджей терминов родства kin-term-badge присутствуют в CSS');

echo "\n-------------------------------------------------------\n";
echo "Результат: {$passed} из {$total} проверок пройдено успешно!\n";
if ($passed === $total) {
    echo "ВСЕ ТЕСТЫ ҮШ ЖҰРТ (TASK-011) УСПЕШНО ПРОЙДЕНЫ [SUCCESS]\n";
} else {
    echo "ЕСТЬ ОШИБКИ В ТЕСТАХ!\n";
}
echo "-------------------------------------------------------\n";
