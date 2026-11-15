<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Controllers\TreeController;

echo "=======================================================\n";
echo "  Шежіре: Тестирование 3D Шежіре-Орман (TASK-006)       \n";
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

// 1. Проверка файлов Three.js и модулей
echo "1. Проверка библиотек Three.js и вендорных модулей:\n";
$threeMinPath = __DIR__ . '/../public/assets/js/three/three.min.js';
$threeModulePath = __DIR__ . '/../public/assets/js/three/three.module.js';
$plcPath = __DIR__ . '/../public/assets/js/three/PointerLockControls.js';
$ocPath = __DIR__ . '/../public/assets/js/three/OrbitControls.js';
$bloomPath = __DIR__ . '/../public/assets/js/three/UnrealBloomPass.js';

assertCheck(file_exists($threeMinPath) && filesize($threeMinPath) > 500000, 'three.min.js существует (> 500 KB)');
assertCheck(file_exists($threeModulePath) && filesize($threeModulePath) > 1000000, 'three.module.js существует (> 1 MB)');
assertCheck(file_exists($plcPath) && filesize($plcPath) > 2000, 'PointerLockControls.js присутствует');
assertCheck(file_exists($ocPath) && filesize($ocPath) > 15000, 'OrbitControls.js присутствует');
assertCheck(file_exists($bloomPath) && filesize($bloomPath) > 5000, 'UnrealBloomPass.js присутствует');

// 2. Проверка скрипта движка 3D леса (shezhire-forest-3d.js)
echo "\n2. Проверка движка 3D Шежіре-Орман:\n";
$forest3dJs = __DIR__ . '/../public/assets/js/shezhire-forest-3d.js';
assertCheck(file_exists($forest3dJs) && filesize($forest3dJs) > 20000, 'shezhire-forest-3d.js скомпилирован (> 20 KB)');

// Синтаксис JS
$jsCheck = shell_exec("node --check \"{$forest3dJs}\" 2>&1");
assertCheck(empty(trim((string)$jsCheck)), 'Синтаксис shezhire-forest-3d.js валиден (node --check OK)');

$forestContent = file_get_contents($forest3dJs);
// TASK-016: лес переделан — дерево = род по отцу, линии = только браки (старые аудио/атмосфера убраны)
assertCheck(strpos($forestContent, 'function buildForestModel') !== false, 'buildForestModel (модель леса из данных) реализован');
assertCheck(strpos($forestContent, 'class ForestCamera') !== false, 'ForestCamera (мышь, клавиши, тач) реализован');
assertCheck(strpos($forestContent, 'showIntro') !== false, 'Подсказка при входе реализована');
assertCheck(strpos($forestContent, 'focusMine') !== false, 'Кнопка «Показать мой род» реализована');
assertCheck(strpos($forestContent, '/api/person/ush-zhurt') !== false, 'Карточка человека с үш жұрт реализована');
assertCheck(strpos($forestContent, 'Math.random') === false, 'Нет случайных линий и случайной раскладки');
assertCheck(strpos($forestContent, 'ShezhireForest3D') !== false, 'ShezhireForest3D экспорт доступен');
$modelTest = shell_exec('node "' . __DIR__ . '/Task016Forest3DModelTest.js" 2>&1');
assertCheck(strpos((string)$modelTest, '[SUCCESS]') !== false, 'Модель леса: tests/Task016Forest3DModelTest.js пройден');

// 3. Проверка интеграции в views/tree.php
echo "\n3. Проверка интеграции в views/tree.php:\n";
$treePhp = __DIR__ . '/../views/tree.php';
$phpCheck = shell_exec("C:\\PHP\\php.exe -l \"{$treePhp}\" 2>&1");
assertCheck(strpos((string)$phpCheck, 'No syntax errors') !== false, 'views/tree.php синтаксически корректен (php -l OK)');

$treePhpContent = file_get_contents($treePhp);
assertCheck(strpos($treePhpContent, 'shezhire-forest-3d.js') !== false, 'Подключение shezhire-forest-3d.js присутствует в views/tree.php');
assertCheck(strpos($treePhpContent, 'forest3dMount') !== false, 'Монтажная область #forest3dMount создана в views/tree.php');
assertCheck(strpos($treePhpContent, 'btnModeForest') !== false, 'Кнопка переключения #btnModeForest настроена');
assertCheck(strpos($treePhpContent, 'btn_view_in_tree') !== false, 'Кнопка «Древода көрсету» присутствует в профиле');

// 4. Проверка данных для 3D леса
echo "\n4. Проверка данных для построения 3D леса:\n";
Auth::login([
    'id' => 1,
    'email' => 'zhandos.alt06@gmail.com',
    'name' => 'Жандос Алтыбаев',
    'role' => 'admin',
]);
$controller = new TreeController();
$dataset = $controller->getDataset();

assertCheck(count($dataset['persons']) >= 200, 'В базе доступно ' . count($dataset['persons']) . ' персон для генерации 3D байтерека');
assertCheck(count($dataset['clans']) >= 3, 'В базе доступно ' . count($dataset['clans']) . ' родов для генерации священных рощ');

echo "\n-------------------------------------------------------\n";
echo "Результат: {$passed} из {$total} проверок пройдено успешно!\n";
if ($passed === $total) {
    echo "ВСЕ ТЕСТЫ 3D ШЕЖІРЕ-ОРМАН ПРОЙДЕНЫ УСПЕШНО [SUCCESS]\n";
} else {
    echo "ЕСТЬ ОШИБКИ [FAILED]\n";
    exit(1);
}
echo "-------------------------------------------------------\n";
