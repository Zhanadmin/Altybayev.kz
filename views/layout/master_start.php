<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Database;
use App\Core\I18n;

$lang = I18n::lang();
$siteTitle = I18n::siteTitle();
$pageTitle = !empty($title) ? "{$title} — {$siteTitle}" : $siteTitle;

$user = $user ?? Auth::user();
$userPersonId = $userPersonId ?? ($user['person_id'] ?? null);
$userRole = $user['role'] ?? 'viewer';
$isAdmin = ($userRole === 'admin');
$canEdit = ($userRole === 'admin' || $userRole === 'editor');
$activeNav = $activeNav ?? 'home';

// Получаем имя связанной персоны для приветствия «Сіз — ...»
$connectedPersonName = null;
if (!empty($userPersonId)) {
    try {
        $db = Database::getConnection();
        $stmtP = $db->prepare("SELECT id, first_name, last_name, middle_name FROM persons WHERE id = ?");
        $stmtP->execute([(int)$userPersonId]);
        $cp = $stmtP->fetch(PDO::FETCH_ASSOC);
        if ($cp) {
            $connectedPersonName = trim(($cp['last_name'] ?? '') . ' ' . ($cp['first_name'] ?? '') . ' ' . ($cp['middle_name'] ?? ''));
        }
    } catch (\Throwable $e) {}
}

$youAreText = '';
if ($connectedPersonName) {
    $youAreText = ($lang === 'ru')
        ? "Вы — {$connectedPersonName}"
        : I18n::kazakhYouAre($connectedPersonName);
} else {
    $userName = $user['name'] ?? 'Қонақ';
    $youAreText = ($lang === 'ru') ? "Вы — {$userName}" : I18n::kazakhYouAre($userName);
}

$roleBadgeText = match ($userRole) {
    'admin'  => ($lang === 'ru' ? 'Администратор' : 'Әкімші'),
    'editor' => ($lang === 'ru' ? 'Редактор' : 'Редактор'),
    default  => ($lang === 'ru' ? 'Читатель' : 'Оқырман'),
};

// TASK-008: пункты меню (линейные SVG-иконки вместо эмодзи) и хлебные крошки
$navItems = [
    'home'     => ['href' => '/',         'icon' => 'i-home',     'label' => I18n::t('nav_home'),     'count' => null,  'group' => 1],
    'persons'  => ['href' => '/persons',  'icon' => 'i-users',    'label' => I18n::t('nav_persons'),  'count' => '270', 'group' => 1],
    'families' => ['href' => '/families', 'icon' => 'i-family',   'label' => I18n::t('nav_families'), 'count' => '71',  'group' => 1],
    'tree'     => ['href' => '/tree',     'icon' => 'i-tree',     'label' => I18n::t('nav_tree'),     'count' => null,  'group' => 1],
    'dates'    => ['href' => '/dates',    'icon' => 'i-calendar', 'label' => I18n::t('nav_dates'),    'count' => null,  'group' => 2],
    'history'  => ['href' => '/history',  'icon' => 'i-history',  'label' => I18n::t('nav_history'),  'count' => null,  'group' => 2],
];
if ($isAdmin) {
    $navItems['admin'] = ['href' => '/admin', 'icon' => 'i-settings', 'label' => I18n::t('nav_admin'), 'count' => null, 'group' => 3];
}
$navGroupLabels = [
    1 => ($lang === 'ru' ? 'Шежире' : 'Шежіре'),
    2 => ($lang === 'ru' ? 'Записи' : 'Жазбалар'),
    3 => ($lang === 'ru' ? 'Управление' : 'Басқару'),
];

$crumbs = [];
$crumbs[] = ['href' => '/', 'label' => $navItems['home']['label']];
if ($activeNav !== 'home' && isset($navItems[$activeNav])) {
    $crumbs[] = ['href' => $navItems[$activeNav]['href'], 'label' => $navItems[$activeNav]['label']];
}
if (!empty($title) && (string)$title !== end($crumbs)['label']) {
    $crumbs[] = ['href' => null, 'label' => (string)$title];
}

$userInitial = mb_substr((string)($user['name'] ?? 'Ш'), 0, 1);

// TASK-008: страница, уже переведённая на цвета дизайн-системы, ставит $dsThemeReady = true.
// Пока страница со старыми встроенными цветами — только светлая тема (иначе тёмный текст на тёмном фоне).
$dsThemeReady = !empty($dsThemeReady);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>"<?= $dsThemeReady ? '' : ' data-theme="light" data-theme-locked="1"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <?php if ($dsThemeReady): ?>
    <script>
        /* TASK-008: тема — сохранённый выбор, иначе как в системе (до загрузки CSS, без мигания) */
        (function () {
            try {
                var t = localStorage.getItem('shezhire_theme');
                if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap&subset=cyrillic,cyrillic-ext">
    <link rel="stylesheet" href="/assets/css/design-system.css?v=13">
    <style>
        /* Каркас страниц: клан — информационный блок (переключение рода пока не предусмотрено) */
        .ds-clan-switch { cursor: pointer; }
        .ds-clan-switch .ds-tamga .ic { margin: 0; color: var(--c-accent-text); }
        .ds-clan-switch > span:last-child { min-width: 0; }
        .ds-brand-title { font-weight: 600; font-size: var(--fs-md); }
        .ds-you-are { font-size: var(--fs-sm); color: var(--c-text-2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
        .ds-you-are b { color: var(--c-text); font-weight: 600; }
        .ds-topbar .seg button { padding: 4px 11px; font-size: var(--fs-xs); font-weight: 600; }
        .user-chip .badge { margin-left: 2px; }
        .ds-content > .ds-crumbs { margin-bottom: calc(-1 * var(--sp-2)); }
        .ds-page { min-width: 0; }
        /* Сброс отступов, как в прежнем каркасе (страницы со встроенными стилями на него рассчитаны).
           :where() — нулевая специфичность, классы дизайн-системы его перекрывают. */
        :where(.ds-page) *, :where(.ds-page) *::before, :where(.ds-page) *::after { margin: 0; padding: 0; }
    </style>
</head>
<body>

    <!-- Линейные иконки (SVG-спрайт) -->
    <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
        <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/></symbol>
        <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.3-5.5 6.5-5.5s5.9 1.9 6.5 5.5"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.6c2.8.2 4.9 1.9 5.5 5.4"/></symbol>
        <symbol id="i-family" viewBox="0 0 24 24"><circle cx="7" cy="6" r="2.5"/><circle cx="17" cy="6" r="2.5"/><circle cx="12" cy="13" r="2"/><path d="M3 20v-4.5A3.5 3.5 0 0 1 6.5 12H8"/><path d="M21 20v-4.5a3.5 3.5 0 0 0-3.5-3.5H16"/><path d="M9 20v-1.5a3 3 0 0 1 6 0V20"/></symbol>
        <symbol id="i-tree" viewBox="0 0 24 24"><circle cx="12" cy="4.5" r="2"/><circle cx="5" cy="19.5" r="2"/><circle cx="12" cy="19.5" r="2"/><circle cx="19" cy="19.5" r="2"/><path d="M12 6.5v6M5 17.5v-2.5h14v2.5M12 12.5v5"/></symbol>
        <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/></symbol>
        <symbol id="i-history" viewBox="0 0 24 24"><path d="M3.5 12a8.5 8.5 0 1 0 2.5-6"/><path d="M3 4v4h4"/><path d="M12 8v4l3 2"/></symbol>
        <symbol id="i-settings" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></symbol>
        <symbol id="i-chevron-r" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></symbol>
        <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></symbol>
        <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
        <symbol id="i-edit" viewBox="0 0 24 24"><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4z"/><path d="m13.5 6.5 4 4"/></symbol>
        <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></symbol>
        <symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></symbol>
        <symbol id="i-moon" viewBox="0 0 24 24"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></symbol>
        <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
        <symbol id="i-logout" viewBox="0 0 24 24"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 17l-5-5 5-5M5 12h11"/></symbol>
        <symbol id="i-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></symbol>
        <symbol id="i-check" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></symbol>
        <symbol id="i-alert" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.01"/></symbol>
        <symbol id="i-camera" viewBox="0 0 24 24"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></symbol>
        <symbol id="i-upload" viewBox="0 0 24 24"><path d="M12 15V4M7.5 8.5 12 4l4.5 4.5"/><path d="M4 15v3.5A2.5 2.5 0 0 0 6.5 21h11a2.5 2.5 0 0 0 2.5-2.5V15"/></symbol>
        <symbol id="i-file" viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></symbol>
        <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.2 7.5 9.5 4.3-1.3 7.5-4.9 7.5-9.5V6z"/><path d="m9 12 2 2 4-4"/></symbol>
        <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m4 7 8 6 8-6"/></symbol>
        <symbol id="i-media" viewBox="0 0 24 24"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21"/></symbol>
        <symbol id="i-lock" viewBox="0 0 24 24"><rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/></symbol>
        <symbol id="i-save" viewBox="0 0 24 24"><path d="M5 4h11l3 3v12a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1z"/><path d="M8 4v5h7V4M8 20v-6h8v6"/></symbol>
        <!-- Тамга-заглушка: реальную тамгу рода подставит Жандос -->
        <symbol id="i-tamga" viewBox="0 0 24 24"><path d="M12 3v18"/><path d="M6 7c0 3 2.7 5 6 5s6-2 6-5"/><path d="M8 21h8"/></symbol>
    </svg>

    <!-- Оверлей мобильного меню -->
    <div class="ds-sidebar-overlay" id="sidebarOverlay" onclick="toggleMobileSidebar()"></div>

    <div class="ds-shell">
        <!-- Левая панель (остров) -->
        <aside class="ds-sidebar" id="appSidebar">
            <a href="/" class="ds-clan-switch" title="<?= htmlspecialchars($siteTitle) ?>">
                <span class="ds-tamga"><svg class="ic"><use href="#i-tamga"/></svg></span>
                <span>
                    <b class="ds-brand-title"><?= htmlspecialchars($siteTitle) ?></b>
                    <span><?= $lang === 'ru' ? 'Керей · Жагалбайлы' : 'Керей · Жағалбайлы' ?></span>
                </span>
            </a>

            <nav class="ds-nav" aria-label="<?= $lang === 'ru' ? 'Главное меню' : 'Негізгі мәзір' ?>">
                <?php $prevGroup = 0; foreach ($navItems as $key => $item): ?>
                    <?php if ($item['group'] !== $prevGroup): ?>
                        <?php if ($prevGroup !== 0): ?><div class="ds-divider"></div><?php endif; ?>
                        <div class="ds-nav-label"><?= htmlspecialchars($navGroupLabels[$item['group']]) ?></div>
                        <?php $prevGroup = $item['group']; ?>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= $activeNav === $key ? 'active' : '' ?>"<?= $activeNav === $key ? ' aria-current="page"' : '' ?>>
                        <svg class="ic" aria-hidden="true"><use href="#<?= htmlspecialchars($item['icon']) ?>"/></svg>
                        <span><?= htmlspecialchars($item['label']) ?></span>
                        <?php if ($item['count'] !== null): ?><span class="count"><?= htmlspecialchars($item['count']) ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="ds-sidebar-foot">altybayev.kz · 2026 · v2.1</div>
        </aside>

        <!-- Рабочая область (белый остров) -->
        <div class="ds-main">
            <header class="ds-topbar">
                <button type="button" class="btn btn-ghost btn-icon mobile-only" onclick="toggleMobileSidebar()" aria-label="<?= $lang === 'ru' ? 'Меню' : 'Мәзір' ?>">
                    <svg class="ic"><use href="#i-menu"/></svg>
                </button>
                <div class="ds-you-are desktop-only"><?= htmlspecialchars($youAreText) ?></div>
                <div class="spacer"></div>

                <!-- Переключатель языка ҚАЗ / РУС -->
                <div class="seg" role="group" aria-label="<?= $lang === 'ru' ? 'Язык' : 'Тіл' ?>">
                    <button type="button" class="<?= $lang === 'kk' ? 'active' : '' ?>" onclick="switchAppLanguage('kk')">ҚАЗ</button>
                    <button type="button" class="<?= $lang === 'ru' ? 'active' : '' ?>" onclick="switchAppLanguage('ru')">РУС</button>
                </div>

                <!-- Переключатель темы (появляется на страницах, переведённых на дизайн-систему) -->
                <?php if ($dsThemeReady): ?>
                <button type="button" class="btn btn-ghost btn-icon theme-toggle" onclick="toggleAppTheme()"
                        aria-label="<?= $lang === 'ru' ? 'Светлая / тёмная тема' : 'Жарық / қараңғы тақырып' ?>"
                        data-tip="<?= $lang === 'ru' ? 'Светлая / тёмная' : 'Жарық / қараңғы' ?>">
                    <svg class="ic ic-sun"><use href="#i-sun"/></svg><svg class="ic ic-moon"><use href="#i-moon"/></svg>
                </button>
                <?php endif; ?>

                <!-- Пользователь -->
                <div class="user-chip">
                    <span class="avatar">
                        <?php if (!empty($user['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars((string)$user['avatar_url']) ?>" alt="">
                        <?php else: ?>
                            <?= htmlspecialchars($userInitial) ?>
                        <?php endif; ?>
                    </span>
                    <b class="desktop-only"><?= htmlspecialchars((string)($user['name'] ?? 'Туысқан')) ?></b>
                    <span class="badge <?= $userRole === 'admin' ? 'badge-accent' : '' ?> desktop-only"><?= htmlspecialchars($roleBadgeText) ?></span>
                </div>

                <!-- Выход -->
                <a href="/logout" class="btn btn-ghost btn-icon" aria-label="<?= htmlspecialchars(I18n::t('nav_logout')) ?>" data-tip="<?= htmlspecialchars(I18n::t('nav_logout')) ?>">
                    <svg class="ic"><use href="#i-logout"/></svg>
                </a>
            </header>

            <!-- Рабочая область контента -->
            <main class="ds-content">
                <nav class="ds-crumbs" aria-label="<?= $lang === 'ru' ? 'Навигация' : 'Навигация' ?>">
                    <?php foreach ($crumbs as $i => $c): ?>
                        <?php if ($i > 0): ?><svg class="ic" style="width:14px;height:14px" aria-hidden="true"><use href="#i-chevron-r"/></svg><?php endif; ?>
                        <?php if ($i === count($crumbs) - 1 || $c['href'] === null): ?>
                            <span class="cur"><?= htmlspecialchars($c['label']) ?></span>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($c['href']) ?>"><?= htmlspecialchars($c['label']) ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
                <div class="ds-page">
