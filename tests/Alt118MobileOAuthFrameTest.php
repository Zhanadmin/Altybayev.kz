<?php
declare(strict_types=1);

/**
 * ALT-118 (2026-11-26): мобильный вход и HTTP 403.
 *
 * Причина: ALT-046 оборачивал главную "/" в iframe-оболочку. Гость видел страницу
 * входа внутри iframe; ссылка "/auth/google" открывала Google OAuth в этом же
 * iframe. Google запрещает работу во фрейме (X-Frame-Options: DENY) и показывает
 * страницу «403. Произошла ошибка».
 *
 * Регрессия: гостевые страницы не оборачиваются в оболочку; ссылки OAuth уходят
 * на верхний уровень; страница входа сама выходит из фрейма.
 */

$root = dirname(__DIR__);
$fails = 0;
function chk(bool $ok, string $m): void {
    global $fails;
    echo ($ok ? 'OK   ' : 'FAIL ') . $m . "\n";
    if (!$ok) $fails++;
}

$indexPath = $root . '/public/index.php';
$loginPath = $root . '/views/login.php';
$deniedPath = $root . '/views/access_denied.php';

$index = is_file($indexPath) ? (string)file_get_contents($indexPath) : '';
$login = is_file($loginPath) ? (string)file_get_contents($loginPath) : '';
$denied = is_file($deniedPath) ? (string)file_get_contents($deniedPath) : '';

chk($index !== '', 'public/index.php читается');
chk($login !== '', 'views/login.php читается');
chk($denied !== '', 'views/access_denied.php читается');

// 1. Гостевая страница не должна попадать в iframe-оболочку.
$shellStart = strpos($index, '(static function');
$shellEnd = $shellStart === false ? false : strpos($index, '})();', $shellStart);
$shell = ($shellStart !== false && $shellEnd !== false) ? substr($index, $shellStart, $shellEnd - $shellStart) : '';
chk($shell !== '', 'Найден блок оболочки ALT-046');

$authPos = $shell === '' ? false : strpos($shell, 'Auth::check()');
$returnPos = ($authPos === false) ? false : strpos($shell, 'return;', $authPos);
$requirePos = $shell === '' ? false : strpos($shell, 'views/shell.php');
chk($authPos !== false, 'В оболочке есть проверка Auth::check()');
chk(
    $authPos !== false && $returnPos !== false && $requirePos !== false
        && $authPos < $returnPos && $returnPos < $requirePos,
    'Гость выходит из оболочки до подключения views/shell.php'
);

// 2. OAuth и служебные пути по-прежнему исключены из оболочки.
chk(
    (bool)preg_match('#\^/\(api\|auth\|#', $index),
    'Пути /api и /auth исключены из оболочки'
);

// 3. Все ссылки входа через Google должны уходить на верхний уровень.
if (preg_match_all('/<a\b[^>]*\bhref="\/auth\/google"[^>]*>/i', $login, $m)) {
    $links = $m[0];
    chk(count($links) >= 2, 'На странице входа есть обе ссылки /auth/google');
    $allTop = true;
    foreach ($links as $link) {
        if (!preg_match('/\btarget="_top"/i', $link)) {
            $allTop = false;
            echo "     ссылка без target=\"_top\": {$link}\n";
        }
    }
    chk($allTop, 'Все ссылки /auth/google на странице входа открываются в top');
} else {
    chk(false, 'На странице входа найдены ссылки /auth/google');
}

// 4. Страница входа сама выходит из фрейма (на случай истёкшей сессии внутри оболочки).
chk(
    (bool)preg_match('/window\.top\s*!==\s*window\.self/', $login),
    'Страница входа содержит frame-bust'
);
chk(
    (bool)preg_match('/window\.top\.location\.replace\(\s*[\'"]\/login[\'"]\s*\)/', $login),
    'Frame-bust ведёт на /login (не остаётся во фрейме)'
);

// 5. Смена аккаунта на странице ожидания тоже не должна открывать Google во фрейме.
chk(
    (bool)preg_match('/<a\b[^>]*\bhref="\/auth\/google"[^>]*\btarget="_top"[^>]*>/i', $denied),
    'Ссылка /auth/google на access_denied.php открывается в top'
);

echo $fails === 0 ? "\nALT-118: все проверки пройдены!\n" : "\nALT-118: провалено $fails\n";
exit($fails === 0 ? 0 : 1);
