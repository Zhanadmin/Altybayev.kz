<?php
declare(strict_types=1);

/* ALT-046: «оболочка» — страница-обёртка, которая не перезагружается при переходах по сайту.
   Внутри — iframe с обычной страницей сайта (те же адреса, формы, вход, админка);
   здесь живёт фоновая музыка (bgm.js в режиме оболочки), поэтому она не прерывается.
   Выдаётся только для обычных переходов браузера (Sec-Fetch-Dest: document), см. public/index.php. */

use App\Core\I18n;

$lang = I18n::lang();
$startUrl = (string)($_SERVER['REQUEST_URI'] ?? '/');
if ($startUrl === '' || $startUrl[0] !== '/' || str_starts_with($startUrl, '//')) {
    $startUrl = '/';
}
$dark = (($_COOKIE['shezhire_theme'] ?? '') === 'dark');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars(I18n::siteTitle()) ?></title>
    <?php include __DIR__ . '/partials/pwa_head.php'; ?>
    <style>
        html, body { margin: 0; padding: 0; height: 100%; overflow: hidden; background: <?= $dark ? '#14110c' : '#ffffff' ?>; }
        #shellFrame { position: fixed; top: 0; left: 0; width: 100%; height: 100%; border: 0; display: block; background: transparent; }
    </style>
</head>
<body>
    <script>
        /* ALT-046: адрес и заголовок оболочки повторяют страницу внутри iframe (обновление F5 и «поделиться» работают как раньше) */
        window.__bgmShell = true;
        window.__shezhireShellSync = function (win) {
            try {
                var l = win.location;
                if (!l || l.href === 'about:blank') return;
                var p = l.pathname + l.search + l.hash;
                if (p !== location.pathname + location.search + location.hash) history.replaceState(null, '', p);
                var t = win.document.title;
                if (t) document.title = t;
            } catch (e) {}
        };
    </script>
    <!-- без defer: плеер должен быть готов до загрузки страницы во фрейме -->
    <script src="/assets/js/bgm.js?v=5"></script>
    <iframe id="shellFrame" src="<?= htmlspecialchars($startUrl, ENT_QUOTES) ?>" title="<?= htmlspecialchars(I18n::siteTitle()) ?>"
            allow="fullscreen; autoplay; camera; microphone; geolocation; clipboard-read; clipboard-write" allowfullscreen></iframe>
    <script>
        (function () {
            var f = document.getElementById('shellFrame');
            f.addEventListener('load', function () { window.__shezhireShellSync(f.contentWindow); });
            // экранный фокус — во фрейм, чтобы клавиатура и прокрутка работали сразу
            window.addEventListener('focus', function () { try { f.contentWindow.focus(); } catch (e) {} });
        })();
    </script>
</body>
</html>
