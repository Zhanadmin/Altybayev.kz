<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ClanService as C;

$fails = 0;
function chk(bool $ok, string $m): void { global $fails; echo ($ok ? 'OK   ' : 'FAIL ') . $m . "\n"; if (!$ok) $fails++; }

// 1. нормальный SVG сохраняется
$good = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0 0L10 10" stroke="#000" stroke-width="2" fill="none"/></svg>';
$out = C::sanitizeSvg($good);
chk(str_contains($out, '<path') && str_contains($out, 'viewBox'), 'чистый SVG сохранён');

// 2. script, onload, foreignObject, href, style, use, image — вычищены
$bad = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)" viewBox="0 0 10 10">'
     . '<script>alert(1)</script><foreignObject><div>x</div></foreignObject>'
     . '<a href="javascript:alert(1)"><path d="M0 0" onclick="x()" fill="url(http://evil/x)"/></a>'
     . '<use href="http://evil/x.svg#a"/><image href="data:image/png;base64,AAAA"/><style>*{}</style>'
     . '<circle cx="5" cy="5" r="2" style="background:url(x)"/></svg>';
$o = C::sanitizeSvg($bad);
foreach (['script', 'onload', 'onclick', 'foreignObject', 'javascript', 'href', 'evil', '<use', '<image', '<style', 'style=', 'alert'] as $needle) {
    chk(stripos($o, $needle) === false, "нет «{$needle}» после очистки");
}
chk(str_contains($o, '<circle'), 'безопасная <circle> осталась');

// 3. DOCTYPE/ENTITY отклоняются
foreach (['<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>'] as $x) {
    try { C::sanitizeSvg($x); chk(false, 'DOCTYPE отклонён'); } catch (RuntimeException $e) { chk(true, 'DOCTYPE/ENTITY отклонён'); }
}
// 4. не SVG
try { C::sanitizeSvg('<html><body>hi</body></html>'); chk(false, 'не-SVG отклонён'); } catch (RuntimeException $e) { chk(true, 'не-SVG отклонён'); }
try { C::sanitizeSvg('<svg><path'); chk(false, 'битый XML отклонён'); } catch (RuntimeException $e) { chk(true, 'битый XML отклонён'); }

// 5. storeBytes: тип и размер (без БД: экземпляр через рефлексию)
$ref = new ReflectionClass(C::class);
$svc = $ref->newInstanceWithoutConstructor();
$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tamga_test_' . bin2hex(random_bytes(4));
$p = $ref->getProperty('uploadDir'); $p->setAccessible(true); $p->setValue($svc, $dir);

try { $svc->storeBytes(1, "GIF89a....."); chk(false, 'GIF отклонён'); } catch (RuntimeException $e) { chk(true, 'GIF отклонён'); }
try { $svc->storeBytes(1, "<?php echo 1;"); chk(false, 'PHP отклонён'); } catch (RuntimeException $e) { chk(true, 'PHP отклонён'); }
try { $svc->storeBytes(1, str_repeat('a', C::MAX_FILE_SIZE + 1)); chk(false, 'большой файл отклонён'); } catch (RuntimeException $e) { chk(true, 'большой файл отклонён'); }

$name = $svc->storeBytes(7, $bad);
chk((bool)preg_match('/^clan_7_[0-9a-f]{12}\.svg$/', $name), 'SVG записан под безопасным именем: ' . $name);
chk(is_file($dir . DIRECTORY_SEPARATOR . $name) && stripos((string)file_get_contents($dir . DIRECTORY_SEPARATOR . $name), 'script') === false, 'на диске файл без script');

if (function_exists('imagecreatetruecolor')) {
    $im = imagecreatetruecolor(8, 8); imagesavealpha($im, true);
    ob_start(); imagepng($im); $png = (string)ob_get_clean();
    $pn = $svc->storeBytes(7, $png);
    chk(str_ends_with($pn, '.png'), 'PNG принят: ' . $pn);
    @unlink($dir . DIRECTORY_SEPARATOR . $pn);
}
@unlink($dir . DIRECTORY_SEPARATOR . $name);
@rmdir($dir);

// 6. urlFor — только безопасные имена
$u = $ref->newInstanceWithoutConstructor();
chk($u->urlFor('clan_1_ab.svg') === '/uploads/tamga/clan_1_ab.svg', 'urlFor нормальное имя');
chk($u->urlFor('../x.php') === null && $u->urlFor('a.php') === null && $u->urlFor('') === null, 'urlFor отвергает опасные имена');

echo $fails === 0 ? "\nВсе проверки пройдены\n" : "\nПровалено: {$fails}\n";
exit($fails === 0 ? 0 : 1);
