<?php
declare(strict_types=1);

use App\Core\I18n;

/**
 * @var string|null $email
 * @var string|null $reason
 */
$lang = I18n::lang();
$siteTitle = I18n::siteTitle();
$pageTitle = ($lang === 'ru') ? "{$siteTitle} — Доступ ограничен" : "{$siteTitle} — Қолжетімділік шектелген";
$isBlocked = (($reason ?? '') === 'account_blocked');
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
        <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m4 7 8 6 8-6"/></symbol>
        <symbol id="i-block" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m5.7 5.7 12.6 12.6"/></symbol>
    </svg>

    <!-- Язык и тема -->
    <div class="au-top">
        <div class="seg" role="group" aria-label="<?= $lang === 'ru' ? 'Язык' : 'Тіл' ?>">
            <a href="/lang/kk?ref=/access-denied" class="<?= $lang === 'kk' ? 'active' : '' ?>">ҚАЗ</a>
            <a href="/lang/ru?ref=/access-denied" class="<?= $lang === 'ru' ? 'active' : '' ?>">РУС</a>
        </div>
        <button type="button" class="btn btn-ghost btn-icon theme-toggle" onclick="toggleAuthTheme()"
                aria-label="<?= $lang === 'ru' ? 'Светлая / тёмная тема' : 'Жарық / қараңғы тақырып' ?>">
            <svg class="ic ic-sun"><use href="#i-sun"/></svg><svg class="ic ic-moon"><use href="#i-moon"/></svg>
        </button>
    </div>

    <main class="island au-card">
        <div class="au-mark<?= $isBlocked ? ' au-mark-danger' : '' ?>" aria-hidden="true">
            <svg class="ic"><use href="#<?= $isBlocked ? 'i-block' : 'i-mail' ?>"/></svg>
        </div>

        <?php if ($isBlocked): ?>
            <h1><?= $lang === 'ru' ? 'Аккаунт заблокирован' : 'Аккаунт бұғатталған' ?></h1>
        <?php else: ?>
            <h1><?= $lang === 'ru' ? 'Доступ только по приглашению' : 'Тек шақыру бойынша қолжетімді' ?></h1>
        <?php endif; ?>

        <?php if (!empty($email)): ?>
            <div class="au-email"><span class="badge"><?= htmlspecialchars((string)$email) ?></span></div>
        <?php endif; ?>

        <div class="alert au-message">
            <?php if ($isBlocked): ?>
                <p>
                    <?= $lang === 'ru'
                        ? 'Ваша учетная запись временно приостановлена. Для восстановления доступа обратитесь к администратору портала.'
                        : 'Сіздің аккаунтыңыз жүйеде уақытша тоқтатылған. Шежірені көру немесе өңдеу құқығын қалпына келтіру үшін сайт әкімшісіне хабарласыңыз.' ?>
                </p>
            <?php else: ?>
                <p>
                    <?= htmlspecialchars($lang === 'ru'
                        ? "Для данного email еще нет активного приглашения. Портал «{$siteTitle}» является закрытым семейным ресурсом. Если вы являетесь членом семьи, свяжитесь с администратором для получения приглашения."
                        : "Бұл электрондық поштаға шақыру жіберілмеген. «{$siteTitle}» порталы жабық отбасылық дерекқор болып табылады. Егер сіз осы әулеттің мүшесі болсаңыз, әкімшіге хабарласып, шақыру алыңыз.") ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="au-actions">
            <a href="/auth/google" class="btn btn-primary">
                <?= $lang === 'ru' ? 'Сменить аккаунт Google' : 'Басқа Google аккаунтымен кіру' ?>
            </a>
            <a href="/logout" class="btn">
                <?= $lang === 'ru' ? 'На главную входа' : 'Басты кіру бетіне оралу' ?>
            </a>
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
