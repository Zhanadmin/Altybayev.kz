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
$connectedFirstName = null;   // ALT-062: только имя — для приветствия
$greetingName = null;         // ALT-062: как обращаться (на русском старшим — имя и отчество)
if (!empty($userPersonId)) {
    try {
        $db = Database::getConnection();
        $stmtP = $db->prepare("SELECT id, first_name, last_name, middle_name, gender, birth_year, is_deceased, father_id FROM persons WHERE id = ?");
        $stmtP->execute([(int)$userPersonId]);
        $cp = $stmtP->fetch(PDO::FETCH_ASSOC);
        if ($cp) {
            // ALT-062: имя кириллицей из шежіре — «Жандос Алтыбаев»
            $connectedPersonName = trim(($cp['first_name'] ?? '') . ' ' . ($cp['last_name'] ?? ''));
            $connectedFirstName = trim((string)($cp['first_name'] ?? ''));
            $greetingName = $connectedFirstName;
            // Старшее поколение (предки и 1-е поколение по ALT-032: умершие или родившиеся до 1975) — по-русски имя и отчество
            $by = (int)($cp['birth_year'] ?? 0);
            $isSenior = !empty($cp['is_deceased']) || ($by > 0 && $by < 1975);
            if ($isSenior && ($lang === 'ru')) {
                $fatherRow = null;
                if (!empty($cp['father_id'])) {
                    $stF = $db->prepare("SELECT first_name FROM persons WHERE id = ?");
                    $stF->execute([(int)$cp['father_id']]);
                    $fatherRow = $stF->fetch(PDO::FETCH_ASSOC) ?: null;
                }
                $patr = \App\Services\PatronymicService::resolve($cp, $fatherRow, 'ru');
                if ($patr !== '') {
                    $greetingName = $connectedFirstName . ' ' . $patr;
                }
            }
        }
    } catch (\Throwable $e) {}
}

// ALT-062: имя из Google-аккаунта на сайте не показываем — только имя из шежіре
$userName = $connectedPersonName ?: (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Гость' : 'Қонақ');
$user['name'] = $userName;

// ALT-062: приветствие по времени суток (Asia/Almaty, +05:00)
try {
    $almHour = (int)(new \DateTimeImmutable('now', new \DateTimeZone('Asia/Almaty')))->format('G');
} catch (\Throwable $e) {
    $almHour = (int)date('G');
}
$dayPart = $almHour >= 5 && $almHour < 12 ? 0 : ($almHour >= 12 && $almHour < 18 ? 1 : ($almHour >= 18 && $almHour < 23 ? 2 : 3));
$greetingWord = ($lang === 'ru' || $lang === 'en' || $lang === 'es')
    ? ['Доброе утро', 'Добрый день', 'Добрый вечер', 'Доброй ночи'][$dayPart]
    : ['Қайырлы таң', 'Қайырлы күн', 'Қайырлы кеш', 'Қайырлы түн'][$dayPart];
$greetingText = $greetingWord . ', ' . ($greetingName ?: $userName) . '!';

$youAreText = '';
if ($connectedPersonName) {
    $youAreText = (($lang === 'ru' || $lang === 'en' || $lang === 'es'))
        ? "Вы — {$connectedPersonName}"
        : "Сіз — {$connectedPersonName}";
} else {
    $youAreText = (($lang === 'ru' || $lang === 'en' || $lang === 'es')) ? "Вы — {$userName}" : I18n::kazakhYouAre($userName);
}

$roleBadgeText = match ($userRole) {
    'admin'  => (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Администратор' : 'Әкімші'),
    'editor' => (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Редактор' : 'Редактор'),
    default  => (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Читатель' : 'Оқырман'),
};

// TASK-008: пункты меню (линейные SVG-иконки вместо эмодзи) и хлебные крошки
$navItems = [
    'home'     => ['href' => '/',         'icon' => 'i-home',     'label' => I18n::t('nav_home'),     'count' => null,  'group' => 1],
    'persons'  => ['href' => '/persons',  'icon' => 'i-users',    'label' => I18n::t('nav_persons'),  'count' => (string)\App\Services\PeopleCountService::people(), 'group' => 1], // ALT-026
    'families' => ['href' => '/families', 'icon' => 'i-family',   'label' => I18n::t('nav_families'), 'count' => (string)\App\Services\PeopleCountService::families(), 'group' => 1],
    'tree'     => ['href' => '/tree',     'icon' => 'i-tree',     'label' => I18n::t('nav_tree'),     'count' => null,  'group' => 1],
    'dates'    => ['href' => '/dates',    'icon' => 'i-calendar', 'label' => I18n::t('nav_dates'),    'count' => null,  'group' => 2],
    'events'   => ['href' => '/events',   'icon' => 'i-camera',   'label' => (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Мероприятия' : 'Іс-шаралар'), 'count' => null, 'group' => 2], // TASK-018
];
if ($isAdmin) {
    // ALT-004: журнал изменений виден только администратору
    $navItems['history'] = ['href' => '/history', 'icon' => 'i-history', 'label' => (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Журнал изменений' : 'Өзгерістер журналы'), 'count' => null, 'group' => 3];
}
if ($canEdit) {
    $navItems['clans'] = ['href' => '/admin/clans', 'icon' => 'i-tree', 'label' => 'Ру таңбалары', 'count' => null, 'group' => 3]; // TASK-030
}
if ($isAdmin) {
    $navItems['admin'] = ['href' => '/admin', 'icon' => 'i-settings', 'label' => I18n::t('nav_admin'), 'count' => null, 'group' => 3];
}
$navGroupLabels = [
    1 => (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Шежире' : 'Шежіре'),
    2 => (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Записи' : 'Жазбалар'),
    3 => (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Управление' : 'Басқару'),
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
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
    <!-- ALT-020: шрифты локально — только Lora (заголовки — Bold Italic) -->
    <!-- ALT-025: предзагрузка основных начертаний (текст и заголовки), остальные грузятся по мере надобности (font-display: swap) -->
    <link rel="preload" href="/assets/fonts/lora-400.ttf" as="font" type="font/ttf" crossorigin>
    <link rel="preload" href="/assets/fonts/lora-italic-700.ttf" as="font" type="font/ttf" crossorigin>
    <link rel="stylesheet" href="/assets/css/fonts.css?v=2">
    <link rel="stylesheet" href="/assets/css/design-system.css?v=34">
    <script src="/assets/js/tooltip.js?v=4" defer></script>
    <script src="/assets/js/kin-hint.js?v=1" defer></script>
    <script src="/assets/js/bgm.js?v=6" defer></script>
    <link rel="stylesheet" href="/assets/css/app-dialog.css?v=1">
    <script src="/assets/js/app-dialog.js?v=1"></script>
    <link rel="stylesheet" href="/assets/css/book.css?v=14">
    <?php include __DIR__ . '/../partials/pwa_head.php'; // ALT-016 ?>
    <style>
        /* Каркас страниц: клан — информационный блок (переключение рода пока не предусмотрено) */
        .ds-clan-switch { cursor: pointer; }
        .ds-clan-switch .ds-tamga .ic { margin: 0; color: var(--c-accent-text); }
        .ds-clan-switch > span:last-child { min-width: 0; }
        .ds-brand-title { font-weight: 400; font-size: var(--fs-md); }
        .ds-you-are { font-size: var(--fs-sm); color: var(--c-text-2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
        .ds-you-are b { color: var(--c-text); font-weight: 600; }
        /* ALT-086: блок управления в боковой панели — прижат к низу и всегда виден */
        .ds-sidebar-tools {
            margin-top: auto; position: sticky; bottom: 0; z-index: 2;
            display: flex; flex-direction: column; gap: var(--sp-2);
            padding: var(--sp-3);
            background: var(--c-island); border: 1px solid var(--c-border);
            border-radius: var(--r-md); box-shadow: var(--shadow-island);
        }
        .ds-sidebar-tools + .ds-sidebar-foot { margin-top: 0; }
        .ds-sidebar-tools .seg { display: flex; width: 100%; }
        .ds-sidebar-tools .seg button { flex: 1 1 0; padding: 4px 6px; font-size: var(--fs-xs); font-weight: 600; }
        .ds-sidebar-tools .user-chip { min-width: 0; }
        .ds-sidebar-tools .user-chip b { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ds-sidebar-tools .user-chip .badge { margin-left: auto; flex: 0 0 auto; }
        .ds-tools-row { display: flex; align-items: center; gap: var(--sp-2); }
        .ds-tools-row #bgmToggle { margin: 0 !important; }
        .ds-tools-logout { margin-left: auto; }
        .ds-content > .ds-crumbs { margin-bottom: calc(-1 * var(--sp-2)); }
        .ds-page { min-width: 0; }
        /* Сброс отступов, как в прежнем каркасе (страницы со встроенными стилями на него рассчитаны).
           :where() — нулевая специфичность, классы дизайн-системы его перекрывают. */
        :where(.ds-page) *, :where(.ds-page) *::before, :where(.ds-page) *::after { margin: 0; padding: 0; }
    </style>
    <?php include __DIR__ . '/../partials/clan_tamga.php'; // TASK-030 ?>
</head>
<body<?= in_array($activeNav, ['admin', 'history', 'clans'], true) ? ' class="ds-admin-ui"' : '' ?>>

    <!-- Линейные иконки (SVG-спрайт) -->
    <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
        <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/></symbol>
        <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.3-5.5 6.5-5.5s5.9 1.9 6.5 5.5"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.6c2.8.2 4.9 1.9 5.5 5.4"/></symbol>
        <symbol id="i-face" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8.5 9.5v.01M15.5 9.5v.01M8.5 14.2c.9 1.4 2.2 2 3.5 2s2.6-.6 3.5-2"/></symbol>
        <symbol id="i-family" viewBox="0 0 24 24"><circle cx="7" cy="6" r="2.5"/><circle cx="17" cy="6" r="2.5"/><circle cx="12" cy="13" r="2"/><path d="M3 20v-4.5A3.5 3.5 0 0 1 6.5 12H8"/><path d="M21 20v-4.5a3.5 3.5 0 0 0-3.5-3.5H16"/><path d="M9 20v-1.5a3 3 0 0 1 6 0V20"/></symbol>
        <symbol id="i-tree" viewBox="0 0 24 24"><circle cx="12" cy="4.5" r="2"/><circle cx="5" cy="19.5" r="2"/><circle cx="12" cy="19.5" r="2"/><circle cx="19" cy="19.5" r="2"/><path d="M12 6.5v6M5 17.5v-2.5h14v2.5M12 12.5v5"/></symbol>
        <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/></symbol>
        <symbol id="i-history" viewBox="0 0 24 24"><path d="M3.5 12a8.5 8.5 0 1 0 2.5-6"/><path d="M3 4v4h4"/><path d="M12 8v4l3 2"/></symbol>
        <symbol id="i-settings" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></symbol>
        <symbol id="i-chevron-r" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></symbol>
        <symbol id="i-chevron-down" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
        <symbol id="i-chevron-up" viewBox="0 0 24 24"><path d="m18 15-6-6-6 6"/></symbol>
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
        <symbol id="i-candle" viewBox="0 0 24 24"><path d="M9 21V11h6v10"/><path d="M12 11V8"/><path d="M12 3c1.5 1.6 1.5 3.4 0 4.6-1.5-1.2-1.5-3 0-4.6z"/><path d="M6 21h12"/></symbol>
    </svg>

    <!-- TASK-012: мотивы Шежіре-Орман (цветные SVG, без эмодзи и фильтров): лист, тюльпан, ветвь, мүйіз, уголок, кольца -->
    <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="gLeaf" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#9cc163"/><stop offset="1" stop-color="#4f7d2b"/></linearGradient>
            <linearGradient id="gLeaf2" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#7aa548"/><stop offset="1" stop-color="#3c6421"/></linearGradient>
            <linearGradient id="gLeafG" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f1d27a"/><stop offset="1" stop-color="#b9861f"/></linearGradient>
            <linearGradient id="gGold" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f7e2a0"/><stop offset=".5" stop-color="#c99a35"/><stop offset="1" stop-color="#8f6a1f"/></linearGradient>
        </defs>
        <symbol id="m-leaf" viewBox="-2 -14 32 20"><path d="M0,0 C5,-9 17,-12 28,-4 C19,5 7,6 0,0 Z" fill="url(#gLeaf)"/><path d="M1,0 C9,-4 17,-5 26,-4" fill="none" stroke="#3d621f" stroke-width=".9"/></symbol>
        <symbol id="m-leafG" viewBox="-2 -14 32 20"><path d="M0,0 C5,-9 17,-12 28,-4 C19,5 7,6 0,0 Z" fill="url(#gLeafG)"/><path d="M1,0 C9,-4 17,-5 26,-4" fill="none" stroke="#8a5f12" stroke-width=".9"/></symbol>
        <!-- значок пола: ұл — лист, қыз — тюльпан; марқұм — золото -->
        <symbol id="g-m" viewBox="-14 -14 28 28"><g transform="translate(-10,6) rotate(-40) scale(.8)"><path d="M0,0 C5,-9 17,-12 28,-4 C19,5 7,6 0,0 Z" fill="url(#gLeaf)"/><path d="M1,0 C9,-4 17,-5 26,-4" fill="none" stroke="#3d621f" stroke-width="1.1"/></g></symbol>
        <symbol id="g-mG" viewBox="-14 -14 28 28"><g transform="translate(-10,6) rotate(-40) scale(.8)"><path d="M0,0 C5,-9 17,-12 28,-4 C19,5 7,6 0,0 Z" fill="url(#gLeafG)"/><path d="M1,0 C9,-4 17,-5 26,-4" fill="none" stroke="#8a5f12" stroke-width="1.1"/></g></symbol>
        <symbol id="g-f" viewBox="-9 -14 18 24"><path d="M0,9 L0,0" stroke="#5f8a34" stroke-width="1.8"/><path d="M-5.5,-1 C-6.5,-7 -3.5,-11 0,-12 C3.5,-11 6.5,-7 5.5,-1 C3,1.2 -3,1.2 -5.5,-1 Z" fill="#e88b98"/><path d="M0,0 C-3,-3.5 -3,-8 0,-12 C3,-8 3,-3.5 0,0 Z" fill="#cf5b6d"/></symbol>
        <symbol id="g-fG" viewBox="-9 -14 18 24"><path d="M0,9 L0,0" stroke="#8a6a2a" stroke-width="1.8"/><path d="M-5.5,-1 C-6.5,-7 -3.5,-11 0,-12 C3.5,-11 6.5,-7 5.5,-1 C3,1.2 -3,1.2 -5.5,-1 Z" fill="#e7c062"/><path d="M0,0 C-3,-3.5 -3,-8 0,-12 C3,-8 3,-3.5 0,0 Z" fill="#b8862a"/></symbol>
        <!-- ветвь с листьями (разделитель заголовка) -->
        <symbol id="o-branch" viewBox="0 0 150 26">
            <path d="M2,16 C40,18 80,14 148,8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <use href="#m-leaf" x="28" y="4" width="18" height="12" transform="rotate(-25 37 10)"/>
            <use href="#m-leaf" x="44" y="12" width="16" height="10" transform="rotate(28 52 17)"/>
            <use href="#m-leaf" x="74" y="2" width="18" height="12" transform="rotate(-30 83 8)"/>
            <use href="#m-leaf" x="92" y="10" width="16" height="10" transform="rotate(25 100 15)"/>
            <use href="#m-leaf" x="122" y="-1" width="18" height="12" transform="rotate(-15 131 5)"/>
            <circle cx="64" cy="15" r="2.4" fill="#f3b3bd"/><circle cx="112" cy="11" r="2.2" fill="#e3b343"/>
        </symbol>
        <!-- TASK-013: ою-өрнек по согласованному листу-образцу (task-013-oyu-obrazec.html), тонкая линия -->
        <!-- қошқар мүйіз: парные рога-спирали наружу, короткий стебель -->
        <symbol id="o-horn" viewBox="10 22 100 60"><g fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M60,64 C60,40 42,26 27,33 C13,40 15,61 31,61 C42,61 44,47 35,45"/>
            <path d="M60,64 C60,40 78,26 93,33 C107,40 105,61 89,61 C78,61 76,47 85,45"/>
            <path d="M60,64 L60,80"/></g>
            <circle cx="60" cy="64" r="2.6" fill="currentColor"/></symbol>
        <!-- угол листа: рамка с закруглением и завитки қошқар мүйіз -->
        <symbol id="o-corner" viewBox="0 0 60 60"><g fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
            <path d="M6,54 L6,22 C6,10 14,6 24,6 L54,6"/>
            <path d="M14,46 C14,30 22,22 30,22 C38,22 40,32 33,33 C29,34 28,29 31,28"/>
            <path d="M14,46 C10,46 9,42 12,41"/>
            <path d="M46,14 C30,14 22,22 22,30"/>
            <path d="M46,14 C46,10 42,9 41,12"/></g></symbol>
        <!-- розетка: четыре қошқар мүйіз вокруг центра (симметрия текемет) -->
        <symbol id="o-rosette" viewBox="0 0 120 120"><g fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
            <path id="o-rosette-arm" d="M60,60 L60,40 M60,40 C60,28 50,22 42,26 C35,30 37,40 45,40 C50,40 51,34 47,33 M60,40 C60,28 70,22 78,26 C85,30 83,40 75,40 C70,40 69,34 73,33"/>
            <use href="#o-rosette-arm" transform="rotate(90 60 60)"/>
            <use href="#o-rosette-arm" transform="rotate(180 60 60)"/>
            <use href="#o-rosette-arm" transform="rotate(270 60 60)"/>
            <circle cx="60" cy="60" r="5"/></g>
            <circle cx="60" cy="60" r="1.8" fill="currentColor"/></symbol>
        <!-- кольца у пар -->
        <symbol id="o-rings" viewBox="-22 -15 44 30"><g fill="none" stroke-linecap="round">
            <circle cx="-7" cy="1.5" r="10" stroke="#5b4214" stroke-width="4" opacity=".2"/><circle cx="8" cy="1.5" r="10" stroke="#5b4214" stroke-width="4" opacity=".2"/>
            <circle cx="-7" cy="0" r="10" stroke="url(#gGold)" stroke-width="3.4"/><circle cx="7" cy="0" r="10" stroke="url(#gGold)" stroke-width="3.4"/>
            <path d="M7,-10 A10,10 0 0 1 15,-6" stroke="#fff4cf" stroke-width="1.1"/></g>
            <path d="M7,-15 L10,-11.5 L7,-8.5 L4,-11.5 Z" fill="#fffaf0" stroke="#a88232" stroke-width=".9"/></symbol>
        <!-- росток (пустой экран) -->
        <symbol id="o-sprout" viewBox="0 0 140 90">
            <path d="M20,70 C50,64 90,64 120,70" fill="none" stroke="#b5bd8c" stroke-width="2" stroke-linecap="round"/>
            <path d="M70,70 C70,56 69,46 70,34" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            <use href="#m-leaf" x="70" y="26" width="30" height="20" transform="rotate(-30 85 36)"/>
            <use href="#m-leaf" x="40" y="30" width="30" height="20" transform="rotate(-150 55 40)"/>
            <circle cx="70" cy="30" r="3" fill="#f3b3bd"/>
        </symbol>
        <!-- тюльпан (большой) -->
        <symbol id="o-tulip" viewBox="-20 -78 40 80">
            <path d="M0,0 C-2,-18 1,-34 0,-46" fill="none" stroke="#5f8a34" stroke-width="2.6" stroke-linecap="round"/>
            <path d="M0,-4 C-14,-12 -19,-27 -15,-40 C-9,-28 -4,-18 0,-10 Z" fill="url(#gLeaf)"/>
            <path d="M0,-8 C12,-15 15,-26 12,-34 C7,-25 3,-18 0,-13 Z" fill="url(#gLeaf2)"/>
            <path d="M-12,-50 C-14,-62 -8,-71 0,-75 C8,-71 14,-62 12,-50 C8,-44 -8,-44 -12,-50 Z" fill="#e88b98"/>
            <path d="M0,-45 C-10,-47 -16,-57 -13,-70 C-7,-64 -3,-58 0,-52 Z" fill="#cf5b6d"/>
            <path d="M0,-45 C10,-47 16,-57 13,-70 C7,-64 3,-58 0,-52 Z" fill="#f2a6b0"/>
        </symbol>
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
                    <span><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Керей · Жагалбайлы' : 'Керей · Жағалбайлы' ?></span>
                </span>
            </a>

            <nav class="ds-nav" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Главное меню' : 'Негізгі мәзір' ?>">
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

            <?php /* ALT-086: компактный блок управления — всегда виден (панель не прокручивается вместе со страницей; на телефоне — внутри выезжающего меню) */ ?>
            <div class="ds-sidebar-tools" id="sidebarTools">
                <!-- Пользователь -->
                <div class="user-chip">
                    <span class="avatar">
                        <?php if (!empty($user['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars((string)$user['avatar_url']) ?>" alt="">
                        <?php else: ?>
                            <?= htmlspecialchars($userInitial) ?>
                        <?php endif; ?>
                    </span>
                    <b><?= htmlspecialchars((string)($user['name'] ?? 'Туысқан')) ?></b>
                    <span class="badge <?= $userRole === 'admin' ? 'badge-accent' : '' ?>"><?= htmlspecialchars($roleBadgeText) ?></span>
                </div>

                <!-- ALT-007: переключатель языка ҚАЗ / РУС / ENG / ESP -->
                <div class="seg" role="group" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Язык' : 'Тіл' ?>">
                    <button type="button" class="<?= $lang === 'kk' ? 'active' : '' ?>" onclick="switchAppLanguage('kk')">ҚАЗ</button>
                    <button type="button" class="<?= $lang === 'ru' ? 'active' : '' ?>" onclick="switchAppLanguage('ru')">РУС</button>
                    <button type="button" class="<?= $lang === 'en' ? 'active' : '' ?>" onclick="switchAppLanguage('en')">ENG</button>
                    <button type="button" class="<?= $lang === 'es' ? 'active' : '' ?>" onclick="switchAppLanguage('es')">ESP</button>
                </div>

                <div class="ds-tools-row">
                    <?php /* кнопку музыки (#bgmToggle) сюда вставляет bgm.js */ ?>
                    <span id="bgmSlot" style="display:contents"></span>
                    <?php if ($dsThemeReady): ?>
                    <button type="button" class="btn btn-ghost btn-icon theme-toggle" onclick="toggleAppTheme()"
                            aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Светлая / тёмная тема' : 'Жарық / қараңғы тақырып' ?>"
                            data-tip="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Светлая / тёмная' : 'Жарық / қараңғы' ?>">
                        <svg class="ic ic-sun"><use href="#i-sun"/></svg><svg class="ic ic-moon"><use href="#i-moon"/></svg>
                    </button>
                    <?php endif; ?>
                    <a href="/logout" class="btn btn-ghost btn-icon ds-tools-logout" aria-label="<?= htmlspecialchars(I18n::t('nav_logout')) ?>" data-tip="<?= htmlspecialchars(I18n::t('nav_logout')) ?>">
                        <svg class="ic"><use href="#i-logout"/></svg>
                    </a>
                </div>
            </div>

            <div class="ds-sidebar-foot">
                <svg class="orn orn-branch" aria-hidden="true" focusable="false"><use href="#o-branch"/></svg>
                <span>altybayev.kz · 2026</span>
                <?php $__ver = \App\Core\Version::info(); if ($__ver): ?>
                <?php /* ALT-044: версия из git; время, затем дата */ ?>
                <span class="ds-version" tabindex="0" title="<?= htmlspecialchars($__ver['full'] . "\n" . $__ver['subject'], ENT_QUOTES) ?>">
                    <?= $__ver['count'] > 0 ? 'v.' . (int)$__ver['count'] . ' · ' : '' ?><?= htmlspecialchars($__ver['short']) ?> · <?= date('H:i d.m.Y', (int)$__ver['updated']) ?>
                </span>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Рабочая область (белый остров) -->
        <div class="ds-main">
            <header class="ds-topbar">
                <button type="button" class="btn btn-ghost btn-icon mobile-only" onclick="toggleMobileSidebar()" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Меню' : 'Мәзір' ?>">
                    <svg class="ic"><use href="#i-menu"/></svg>
                </button>
                <div class="ds-you-are desktop-only"><?= htmlspecialchars($youAreText) ?></div>
                <div class="spacer"></div>
                <?php /* ALT-086: язык, музыка, тема, пользователь и выход перенесены в боковую панель (#sidebarTools) */ ?>
            </header>

            <!-- Рабочая область контента -->
            <main class="ds-content">
                <nav class="ds-crumbs" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Навигация' : 'Навигация' ?>">
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
