<?php
declare(strict_types=1);

use App\Core\I18n;

/**
 * @var string|null $error
 */
$lang = I18n::lang();
$siteTitle = I18n::siteTitle();
$pageTitle = ($lang === 'ru') ? "{$siteTitle} — Вход в систему" : "{$siteTitle} — Жүйеге кіру";
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <script>
        /* TASK-008: тема — сохранённый выбор, иначе как в системе */
        (function () {
            try {
                var t = localStorage.getItem('shezhire_theme');
                if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap&subset=cyrillic,cyrillic-ext">
    <link rel="stylesheet" href="/assets/css/design-system.css?v=13">
</head>
<body class="au-body">
    <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
        <symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></symbol>
        <symbol id="i-moon" viewBox="0 0 24 24"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></symbol>
        <symbol id="i-tamga" viewBox="0 0 24 24"><path d="M12 3v18"/><path d="M6 7c0 3 2.7 5 6 5s6-2 6-5"/><path d="M8 21h8"/></symbol>
        <symbol id="i-alert" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.01"/></symbol>
        <symbol id="i-lock" viewBox="0 0 24 24"><rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/></symbol>
    </svg>

    <!-- Язык и тема -->
    <div class="au-top">
        <div class="seg" role="group" aria-label="<?= $lang === 'ru' ? 'Язык' : 'Тіл' ?>">
            <a href="/lang/kk?ref=/login" class="<?= $lang === 'kk' ? 'active' : '' ?>">ҚАЗ</a>
            <a href="/lang/ru?ref=/login" class="<?= $lang === 'ru' ? 'active' : '' ?>">РУС</a>
        </div>
        <button type="button" class="btn btn-ghost btn-icon theme-toggle" onclick="toggleAuthTheme()"
                aria-label="<?= $lang === 'ru' ? 'Светлая / тёмная тема' : 'Жарық / қараңғы тақырып' ?>">
            <svg class="ic ic-sun"><use href="#i-sun"/></svg><svg class="ic ic-moon"><use href="#i-moon"/></svg>
        </button>
    </div>

    <main class="island au-card">
        <div class="au-mark" aria-hidden="true">
            <svg class="ic"><use href="#i-tamga"/></svg>
        </div>

        <h1><?= htmlspecialchars($siteTitle) ?></h1>
        <p class="au-sub">
            <?= $lang === 'ru' ? 'Семейный генеалогический портал' : 'Отбасылық шежіре порталы' ?>
        </p>

        <?php if (!empty($error)): ?>
            <div class="alert error" role="alert">
                <svg class="ic" aria-hidden="true"><use href="#i-alert"/></svg>
                <div>
                    <?php if ($error === 'auth_failed'): ?>
                        <strong><?= $lang === 'ru' ? 'Ошибка входа:' : 'Кіру сәтсіз аяқталды:' ?></strong><br>
                        <?= $lang === 'ru' ? 'При авторизации через Google произошла ошибка. Попробуйте снова.' : 'Google арқылы кіру кезінде қате орын алды. Қайталап көріңіз.' ?>
                    <?php elseif ($error === 'invalid_state'): ?>
                        <strong><?= $lang === 'ru' ? 'Ошибка сессии:' : 'Қауіпсіздік қатесі:' ?></strong><br>
                        <?= $lang === 'ru' ? 'Сессия устарела. Обновите страницу и попробуйте снова.' : 'Сессия мерзімі өтіп кетті. Бетті жаңартып, қайта кіріңіз.' ?>
                    <?php elseif ($error === 'forbidden'): ?>
                        <strong><?= $lang === 'ru' ? 'Доступ запрещен:' : 'Рұқсат жоқ:' ?></strong><br>
                        <?= $lang === 'ru' ? 'Недостаточно прав для доступа к этому разделу.' : 'Бұл бөлімге кіруге құқығыңыз жеткіліксіз.' ?>
                    <?php elseif ($error === 'admin_only'): ?>
                        <strong><?= $lang === 'ru' ? 'Только для администратора:' : 'Тек әкімшіге:' ?></strong><br>
                        <?= $lang === 'ru' ? 'Этот раздел доступен только администратору.' : 'Бұл бет тек әкімшіге арналған.' ?>
                    <?php else: ?>
                        <strong><?= $lang === 'ru' ? 'Ошибка:' : 'Қате:' ?></strong> <?= htmlspecialchars($error) ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="au-actions">
            <a href="/auth/google" class="btn btn-google">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
                <span><?= $lang === 'ru' ? 'Войти через Google' : 'Google арқылы кіру' ?></span>
            </a>
        </div>

        <div class="alert au-note">
            <svg class="ic" aria-hidden="true"><use href="#i-lock"/></svg>
            <div>
                <strong><?= $lang === 'ru' ? 'Закрытая родословная:' : 'Жабық отбасылық шежіре:' ?></strong><br>
                <?= $lang === 'ru' ? 'Доступ открыт только для приглашенных членов семьи.' : 'Қолжетімділік тек шақырылған отбасы мүшелеріне ашық.' ?>
            </div>
        </div>

        <div class="au-foot">altybayev.kz &bull; 2026</div>
    </main>

    <script>
        function toggleAuthTheme() {
            const root = document.documentElement;
            const current = root.getAttribute('data-theme')
                || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            const next = current === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('shezhire_theme', next); } catch (e) {}
            document.cookie = `shezhire_theme=${next}; path=/; max-age=31536000; SameSite=Lax`;
        }
        (function () {
            const root = document.documentElement;
            if (!root.getAttribute('data-theme') && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                root.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</body>
</html>
