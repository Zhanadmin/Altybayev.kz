<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Core\I18n;
use App\Services\KinshipService;
use App\Services\PhotoUploadService;

echo "=======================================================\n";
echo "  Шежіре: Тестілеу TASK-007 (Сайт макеті, Тізім, I18n, Фото қауіпсіздігі)\n";
echo "=======================================================\n\n";

$passed = 0;
$total = 0;

function assertCheck(bool $condition, string $message): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [OK] {$message}\n";
    } else {
        echo "  [FAIL] {$message}\n";
    }
}

// -------------------------------------------------------------
// 1. ТЕСТИРОВАНИЕ БЕЗОПАСНОСТИ ЗАГРУЗКИ ФОТО (PhotoUploadService)
// -------------------------------------------------------------
echo "1. Тестирование защищенной загрузки фото (PhotoUploadService):\n";

// Все тестовые файлы создаются исключительно в системной временной папке sys_get_temp_dir()
// и немедленно удаляются, чтобы не сохранять вредоносные сигнатуры на диск и не тревожить антивирус
$testUploadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'shezhire_test_uploads_' . uniqid();
if (!is_dir($testUploadDir)) {
    mkdir($testUploadDir, 0755, true);
}
$uploader = new PhotoUploadService($testUploadDir, '/uploads/test_temp/');

// Тест 1.1: PHP-файл под видом .jpg (фальсификация расширения)
$phpFileAsJpg = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tmp_exploit_' . uniqid() . '.jpg';
file_put_contents($phpFileAsJpg, "<?php phpinfo(); ?>");
$rejectedPhpAsJpg = false;
try {
    $uploader->processUpload([
        'name'     => 'exploit.jpg',
        'type'     => 'image/jpeg',
        'tmp_name' => $phpFileAsJpg,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($phpFileAsJpg),
    ], 6, 1);
} catch (RuntimeException $e) {
    $rejectedPhpAsJpg = true;
    echo "    (Перехвачена угроза 1.1: " . $e->getMessage() . ")\n";
} finally {
    if (file_exists($phpFileAsJpg)) {
        @unlink($phpFileAsJpg);
    }
}
assertCheck($rejectedPhpAsJpg, "PHP-файл под видом .jpg успешно заблокирован");

// Тест 1.2: Валидный JPEG с внедренным PHP кодом в конце (полиглот)
$jpegWithPhp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tmp_polyglot_' . uniqid() . '.jpg';
$img = imagecreatetruecolor(100, 100);
$color = imagecolorallocate($img, 30, 60, 90);
imagefill($img, 0, 0, $color);
imagejpeg($img, $jpegWithPhp, 90);
imagedestroy($img);
// Дописываем тестовый PHP-хвост для сигнатурного детектора
file_put_contents($jpegWithPhp, "\n<?php echo 'injected_exploit'; ?>", FILE_APPEND);

$rejectedPolyglot = false;
try {
    $uploader->processUpload([
        'name'     => 'polyglot.jpg',
        'type'     => 'image/jpeg',
        'tmp_name' => $jpegWithPhp,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($jpegWithPhp),
    ], 6, 1);
} catch (RuntimeException $e) {
    $rejectedPolyglot = true;
    echo "    (Перехвачена угроза 1.2: " . $e->getMessage() . ")\n";
} finally {
    if (file_exists($jpegWithPhp)) {
        @unlink($jpegWithPhp);
    }
}
assertCheck($rejectedPolyglot, "JPEG с внедренным вредоносным PHP-кодом заблокирован сигнатурным сканером");

// Тест 1.3: SVG со скриптом XSS (даже с расширением .jpg)
$svgScript = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tmp_xss_' . uniqid() . '.jpg';
file_put_contents($svgScript, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>');
$rejectedSvg = false;
try {
    $uploader->processUpload([
        'name'     => 'xss.jpg',
        'type'     => 'image/jpeg',
        'tmp_name' => $svgScript,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($svgScript),
    ], 6, 1);
} catch (RuntimeException $e) {
    $rejectedSvg = true;
    echo "    (Перехвачена угроза 1.3: " . $e->getMessage() . ")\n";
} finally {
    if (file_exists($svgScript)) {
        @unlink($svgScript);
    }
}
assertCheck($rejectedSvg, "SVG со скриптом заблокирован (SVG строго запрещен)");

// Тест 1.4: Легитимное чистое фото (JPEG)
$cleanJpg = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tmp_clean_' . uniqid() . '.jpg';
$img = imagecreatetruecolor(200, 200);
$bg = imagecolorallocate($img, 212, 175, 55); // золотой цвет
imagefill($img, 0, 0, $bg);
imagejpeg($img, $cleanJpg, 95);
imagedestroy($img);

$cleanUploadResult = null;
try {
    $cleanUploadResult = $uploader->processUpload([
        'name'     => 'clean_photo.jpg',
        'type'     => 'image/jpeg',
        'tmp_name' => $cleanJpg,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($cleanJpg),
    ], 6, 1);
} catch (RuntimeException $e) {
    echo "    (Ошибка: " . $e->getMessage() . ")\n";
} finally {
    if (file_exists($cleanJpg)) {
        @unlink($cleanJpg);
    }
}
assertCheck($cleanUploadResult !== null && $cleanUploadResult['success'] === true, "Легитимное изображение успешно принято");
assertCheck(file_exists($cleanUploadResult['file_path']), "Пересохраненный файл существует на диске");
assertCheck(str_starts_with($cleanUploadResult['file_name'], 'photo_') && str_ends_with($cleanUploadResult['file_name'], '.jpg'), "Имя файла случайное UUID с безопасным расширением .jpg");
assertCheck($cleanUploadResult['width'] === 200 && $cleanUploadResult['height'] === 200, "Разрешение корректно (200x200)");

// Проверяем, что в пересохраненном файле нет PHP
$savedContent = file_get_contents($cleanUploadResult['file_path']);
assertCheck(!str_contains($savedContent, '<?php'), "В пересохраненном файле гарантированно отсутствует код");

// Тест 1.5: Легитимный PNG с альфа-каналом
$cleanPng = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tmp_clean_' . uniqid() . '.png';
$imgPng = imagecreatetruecolor(150, 150);
imagealphablending($imgPng, false);
imagesavealpha($imgPng, true);
$trans = imagecolorallocatealpha($imgPng, 0, 0, 0, 127);
imagefilledrectangle($imgPng, 0, 0, 150, 150, $trans);
imagepng($imgPng, $cleanPng, 8);
imagedestroy($imgPng);

$pngUploadResult = null;
try {
    $pngUploadResult = $uploader->processUpload([
        'name'     => 'clean_logo.png',
        'type'     => 'image/png',
        'tmp_name' => $cleanPng,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($cleanPng),
    ], 6, 1);
} finally {
    if (file_exists($cleanPng)) {
        @unlink($cleanPng);
    }
}
assertCheck($pngUploadResult !== null && $pngUploadResult['success'] === true, "Легитимный PNG успешно пересохранен");
assertCheck(str_ends_with($pngUploadResult['file_name'], '.png'), "PNG сохраняется с расширением .png");

// -------------------------------------------------------------
// 2. ЗАЩИТА ПАПКИ ЗАГРУЗОК (UPLOADS SECURITY: .htaccess и web.config)
// -------------------------------------------------------------
echo "\n2. Тестирование защитной конфигурации папки uploads:\n";

$htaccessPath = __DIR__ . '/../public/uploads/.htaccess';
assertCheck(file_exists($htaccessPath), "Файл public/uploads/.htaccess существует");
$htaccessContent = file_get_contents($htaccessPath);
assertCheck(str_contains($htaccessContent, 'php_flag engine off') && str_contains($htaccessContent, 'nosniff'), ".htaccess отключает движок PHP и включает X-Content-Type-Options: nosniff");

$webConfigPath = __DIR__ . '/../public/uploads/web.config';
assertCheck(file_exists($webConfigPath), "Файл public/uploads/web.config для Plesk IIS существует");
$webConfigContent = file_get_contents($webConfigPath);
assertCheck(str_contains($webConfigContent, 'fileExtension=".php" allowed="false"'), "web.config запрещает выполнение .php скриптов");

// -------------------------------------------------------------
// 3. ТЕСТИРОВАНИЕ СИСТЕМЫ ЛОКАЛИЗАЦИИ И СИНГАРМОНИЗМА (I18n)
// -------------------------------------------------------------
echo "\n3. Тестирование единой системы локализации (I18n):\n";

I18n::setLang('kk');
assertCheck(I18n::lang() === 'kk', "Установка казахского языка успешна");
assertCheck(I18n::siteTitle() === 'Шежіре', "Название сайта на казахском строго «Шежіре» (без фамилии)");

I18n::setLang('ru');
assertCheck(I18n::lang() === 'ru', "Установка русского языка успешна");
assertCheck(I18n::siteTitle() === 'Родословная', "Название сайта на русском строго «Родословная» (без фамилии)");

// Проверка казахского сингармонизма для предиката 2-го лица «Сіз — ...»
$youZhandos = I18n::kazakhYouAre('Жандос');
assertCheck($youZhandos === 'Сіз — Жандоссыreader' || str_contains($youZhandos, 'Жандоссыз'), "Сингармонизм для Жандос: «Сіз — Жандоссыз» (жуан дауысты)");

$youKenen = I18n::kazakhYouAre('Кенен');
assertCheck(str_contains($youKenen, 'Кененсіз'), "Сингармонизм для Кенен: «Сіз — Кененсіз» (жіңішке дауысты)");

// Проверка форматирования степеней родства
$kinKk = I18n::formatKinship('Әкесі', 'Отец');
I18n::setLang('kk');
assertCheck(I18n::formatKinship('Әкесі', 'Отец') === 'Әкесі', "В казахской локали термин родства чистый: «Әкесі»");

I18n::setLang('ru');
assertCheck(I18n::formatKinship('Әкесі', 'Отец') === 'Әкесі (Отец)', "В русской локали термин родства с переводом: «Әкесі (Отец)»");

// -------------------------------------------------------------
// 4. ТЕСТИРОВАНИЕ ТІЗІМ (СПИСОК 270 ЧЕЛОВЕК) И КОНТРОЛЛЕРА
// -------------------------------------------------------------
echo "\n4. Тестирование раздела «Адамдар / Люди» и списка 270 персон:\n";

$db = Database::getConnection();
$stmtP = $db->query("SELECT COUNT(*) FROM persons WHERE is_deleted = 0");
$countPersons = (int)$stmtP->fetchColumn();
assertCheck($countPersons === 270, "В базе данных ровно 270 неделетированных персон");

// Тестируем логику apiTreeList
$treeController = new \App\Controllers\TreeController();
$ds = $treeController->getDataset();
assertCheck(count($ds['persons']) === 270, "Датасет содержит ровно 270 персон");
assertCheck(count($ds['clans']) > 0, "Датасет содержит роды");
assertCheck(count($ds['marriages']) === 71, "Датасет содержит 71 брак");

// -------------------------------------------------------------
// 5. ТЕСТИРОВАНИЕ ОТБАСЫЛАР (СЕМЬИ)
// -------------------------------------------------------------
echo "\n5. Тестирование раздела «Отбасылар / Семьи»:\n";

$stmtM = $db->query("SELECT COUNT(*) FROM marriages");
$countMarriages = (int)$stmtM->fetchColumn();
assertCheck($countMarriages === 71, "В базе данных ровно 71 брак/семья");

// -------------------------------------------------------------
// 6. ТЕСТИРОВАНИЕ АУДИТА И ОТКАТА (HistoryController)
// -------------------------------------------------------------
echo "\n6. Тестирование журнала аудита и отката:\n";

$stmtAudit = $db->query("SELECT COUNT(*) FROM audit_logs");
$countAudit = (int)$stmtAudit->fetchColumn();
assertCheck($countAudit > 0, "В таблице audit_logs присутствуют записи аудита (найдено: {$countAudit})");

// Очистка тестовой папки
array_map('unlink', glob("{$testUploadDir}/*"));
rmdir($testUploadDir);

echo "\n-------------------------------------------------------\n";
echo "Результат: {$passed} из {$total} проверок успешно пройдено!\n";
if ($passed === $total) {
    echo "ВСЕ ТЕСТЫ TASK-007 ПРОЙДЕНЫ [SUCCESS]\n";
} else {
    echo "ЧАСТЬ ТЕСТОВ НЕ ПРОШЛА [FAIL]\n";
}
echo "-------------------------------------------------------\n";
