<?php

declare(strict_types=1);

/**
 * TASK-018: безопасность загрузки фото и видео в альбомы (без БД).
 * Вредные образцы создаются в памяти → временный файл в sys_get_temp_dir() → сразу удаляются (правило TASK-007).
 *
 * Запуск: C:\PHP\php.exe tests\Task018AlbumMediaTest.php
 */

require_once __DIR__ . '/../app/Core/Config.php';
require_once __DIR__ . '/../app/Core/I18n.php';
require_once __DIR__ . '/../app/Services/AlbumMediaService.php';
require_once __DIR__ . '/../app/Services/AlbumService.php';
require_once __DIR__ . '/../app/Controllers/AlbumController.php';

use App\Controllers\AlbumController;
use App\Services\AlbumMediaService;

$passed = 0;
$total = 0;
function ok(bool $cond, string $label): void
{
    global $passed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo "  [OK] {$label}\n";
    } else {
        echo "  [FAIL] {$label}\n";
    }
}

/** Временный файл с содержимым; удаляется в finally вызывающего кода */
function tmpWith(string $bytes): string
{
    $p = tempnam(sys_get_temp_dir(), 'al18');
    file_put_contents($p, $bytes);
    return $p;
}

function rrmdir(string $d): void
{
    if (!is_dir($d)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($d);
}

function jpegBytes(int $w = 64, int $h = 48): string
{
    $im = imagecreatetruecolor($w, $h);
    imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 200, 150, 60));
    ob_start();
    imagejpeg($im, null, 85);
    imagedestroy($im);
    return (string)ob_get_clean();
}

// метки собираются по частям, чтобы в самом файле теста не было «вредных» строк (антивирус сервера)
$PHPTAG = '<' . '?php';
$SCRIPT = '<' . 'script>';

$base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'al18_store_' . bin2hex(random_bytes(4));
$svc = new AlbumMediaService($base);

try {
    echo "Фото:\n";
    // 1. Обычное фото → принимается, пересохраняется, превью создаётся
    $p = tmpWith(jpegBytes(1200, 800));
    try {
        $r = $svc->processPhoto($p, 'ru');
        ok(is_file($svc->abs($r['file_path'])) && is_file($svc->abs($r['thumb_path'])), 'обычное JPEG принято, есть полный файл и превью');
        $gi = getimagesize($svc->abs($r['thumb_path']));
        ok($gi !== false && max($gi[0], $gi[1]) <= AlbumMediaService::THUMB_MAX_EDGE, 'превью не больше 480 px');
        ok(str_ends_with($r['file_path'], '.jpg') && preg_match('#^\d{4}/\d{2}/p_[0-9a-f-]{36}\.jpg$#', $r['file_path']) === 1, 'имя файла случайное (uuid), расширение задаёт сервер');
    } catch (Throwable $e) {
        ok(false, 'обычное JPEG принято: ' . $e->getMessage());
    } finally {
        @unlink($p);
    }

    // 2. JPEG с PHP-кодом в конце → отклонён
    $p = tmpWith(jpegBytes() . $PHPTAG . ' echo 1;');
    try {
        $svc->processPhoto($p, 'ru');
        ok(false, 'JPEG с PHP в конце отклонён');
    } catch (RuntimeException $e) {
        ok(str_contains($e->getMessage(), 'скрипт'), 'JPEG с PHP в конце отклонён: ' . $e->getMessage());
    } finally {
        @unlink($p);
    }

    // 3. SVG со скриптом → отклонён
    $p = tmpWith('<' . 'svg xmlns="http://www.w3.org/2000/svg">' . $SCRIPT . 'alert(1)</' . 'svg>');
    try {
        $svc->processPhoto($p, 'ru');
        ok(false, 'SVG отклонён');
    } catch (RuntimeException $e) {
        ok(true, 'SVG отклонён: ' . $e->getMessage());
    } finally {
        @unlink($p);
    }

    // 4. PHP-файл под видом .jpg → отклонён
    $p = tmpWith('GIF89a' . $PHPTAG . ' echo 2;');
    try {
        $svc->processPhoto($p, 'kk');
        ok(false, 'PHP под видом фото отклонён');
    } catch (RuntimeException $e) {
        ok(true, 'PHP под видом фото отклонён');
    } finally {
        @unlink($p);
    }

    echo "Видео:\n";
    $mp4 = "\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isomiso2" . str_repeat("\x00", 2048);
    $p = tmpWith($mp4);
    ok($svc->detectVideoExt($p, 'a.mp4') === 'mp4', 'MP4 (ftyp isom) распознан');
    ok($svc->detectVideoExt($p, 'IMG_0001.MOV') === 'mov', 'iPhone .MOV с брендом isom сохраняется как mov');
    @unlink($p);

    $p = tmpWith("\x00\x00\x00\x14ftypqt  \x00\x00\x02\x00qt  " . str_repeat("\x00", 2048));
    ok($svc->detectVideoExt($p, 'b.mov') === 'mov', 'MOV (ftyp qt) распознан');
    @unlink($p);

    $p = tmpWith("\x1A\x45\xDF\xA3\x9F\x42\x86\x81\x01\x42\xF7\x81\x01\x42\x82\x84webm" . str_repeat("\x00", 2048));
    ok($svc->detectVideoExt($p, 'c.webm') === 'webm', 'WebM (EBML + webm) распознан');
    @unlink($p);

    $p = tmpWith("\x1A\x45\xDF\xA3\x9F\x42\x86\x81\x01\x42\x82\x88matroska" . str_repeat("\x00", 2048));
    ok($svc->detectVideoExt($p, 'd.webm') === null, 'Matroska (mkv) под видом webm отклонена');
    @unlink($p);

    $p = tmpWith("\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic" . str_repeat("\x00", 2048));
    ok($svc->detectVideoExt($p, 'e.mp4') === null, 'HEIC-картинка под видом mp4 отклонена');
    @unlink($p);

    $p = tmpWith($PHPTAG . ' echo 3;' . str_repeat('A', 100));
    ok($svc->detectVideoExt($p, 'evil.mp4') === null, 'PHP под видом mp4 отклонён');
    @unlink($p);

    // видео с HTML/скриптом внутри — отклоняется сигнатурной проверкой
    $p = tmpWith($mp4 . $SCRIPT . 'alert(1)');
    try {
        $svc->processVideo($p, 'x.mp4', 'ru');
        ok(false, 'MP4 со скриптом внутри отклонён');
    } catch (RuntimeException $e) {
        ok(true, 'MP4 со скриптом внутри отклонён');
    } finally {
        @unlink($p);
    }

    // нормальное видео переносится в хранилище со случайным именем
    $p = tmpWith($mp4);
    try {
        $r = $svc->processVideo($p, 'family.mp4', 'ru');
        ok(is_file($svc->abs($r['file_path'])) && preg_match('#/m_[0-9a-f-]{36}\.mp4$#', $r['file_path']) === 1, 'MP4 сохранён в хранилище со случайным именем');
        ok(!is_file($p), 'временный файл убран');
    } catch (Throwable $e) {
        ok(false, 'MP4 сохранён: ' . $e->getMessage());
    } finally {
        @unlink($p);
    }

    echo "Пути и ввод:\n";
    foreach (['../.env', '/etc/passwd', '2026/11/../../x', 'a\\..\\b', "2026/11/p_x.jpg\0.php"] as $bad) {
        try {
            $svc->abs($bad);
            ok(false, "путь «{$bad}» отклонён");
        } catch (RuntimeException $e) {
            ok(true, 'путь отклонён: ' . addcslashes($bad, "\0"));
        }
    }
    ok(AlbumMediaService::kindByName('a.JPG') === 'photo' && AlbumMediaService::kindByName('b.MOV') === 'video', 'тип по расширению: JPG → фото, MOV → видео');
    ok(AlbumMediaService::kindByName('x.svg') === null && AlbumMediaService::kindByName('x.php') === null && AlbumMediaService::kindByName('x.jpg.php') === null, 'svg, php, jpg.php не принимаются');
    ok(AlbumMediaService::videoMaxBytes() === 500 * 1024 * 1024, 'лимит видео по умолчанию 500 МБ');
    ok(AlbumController::validDate('2026-11-15') === '2026-11-15' && AlbumController::validDate('2026-02-30') === null && AlbumController::validDate("2026-11-15' OR 1") === null, 'дата проверяется строго');
    ok(AlbumController::idList('3, 5,abc,-1,5') === [3, 5], 'список id очищается');
    ok(AlbumController::cleanText("  Той\x00\x07 ", 10) === 'Той', 'управляющие символы убираются');
    $cs = AlbumController::chunkSize();
    ok($cs >= 512 * 1024 && $cs <= 8 * 1024 * 1024, 'размер части загрузки 0,5–8 МБ (' . round($cs / 1048576, 2) . ' МБ)');

    echo "Маршруты:\n";
    $routes = (string)file_get_contents(__DIR__ . '/../public/index.php');
    $a = strpos($routes, "'/album/media'");
    $b = strpos($routes, "'/album/{id}'");
    ok($a !== false && $b !== false && $a < $b, '/album/media объявлен раньше /album/{id}');
    ok(preg_match_all("#'/api/(album|event)/[^']+', '[^']+', \['auth'\]#", $routes) >= 11, 'все API альбомов требуют входа');
    ok(is_file(__DIR__ . '/../storage/.htaccess') && is_file(__DIR__ . '/../storage/web.config'), 'storage закрыт от прямого доступа (.htaccess и web.config)');
} finally {
    rrmdir($base);
}

echo "\nИтого: {$passed} из {$total}\n";
exit($passed === $total ? 0 : 1);
