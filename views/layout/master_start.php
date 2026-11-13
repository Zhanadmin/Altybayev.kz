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
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        :root {
            --bg-body: #f1f5f9;
            --bg-surface: #ffffff;
            --sidebar-bg: #0d1b2a;
            --sidebar-hover: #1b2e4b;
            --sidebar-active: #1e3a8a;
            --header-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --accent-gold: #d4af37;
            --accent-blue: #2563eb;
            --accent-green: #10b981;
            --accent-red: #ef4444;
            --sidebar-width: 260px;
            --header-height: 64px;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
        }
        /* Боковая панель (Sidebar) */
        .app-sidebar {
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            color: #f8fafc;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            transition: transform 0.25s ease;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.2);
        }
        .sidebar-brand {
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #ffffff;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .sidebar-logo {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #1b3b6f, #0d1b2a);
            border: 2px solid var(--accent-gold);
            color: var(--accent-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
            flex-shrink: 0;
        }
        .sidebar-brand-text h1 {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.3px;
            line-height: 1.1;
        }
        .sidebar-brand-text span {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .nav-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 11px 14px;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.15s ease;
        }
        .nav-link-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .nav-link-icon {
            font-size: 17px;
            width: 22px;
            text-align: center;
        }
        .nav-link:hover {
            background-color: var(--sidebar-hover);
            color: #ffffff;
        }
        .nav-link.active {
            background: linear-gradient(90deg, #1e3a8a, #1b3b6f);
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
            border-left: 3px solid var(--accent-gold);
        }
        .nav-badge {
            font-size: 11px;
            padding: 2px 7px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            color: #f1f5f9;
            font-weight: 600;
        }
        .sidebar-footer {
            padding: 16px 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 11px;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Основная область контента */
        .app-main-wrapper {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 100vh;
        }

        /* Шапка страницы (Header) */
        .app-header {
            height: var(--header-height);
            background-color: var(--header-bg);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 90;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .mobile-menu-btn {
            display: none;
            background: none;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 8px;
            cursor: pointer;
            color: var(--text-main);
            font-size: 16px;
        }
        .header-status-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .header-status-pill .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #2563eb;
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        /* Переключатель языка ҚАЗ | РУС */
        .lang-switch-group {
            display: flex;
            align-items: center;
            background-color: #f1f5f9;
            border-radius: 8px;
            padding: 3px;
            border: 1px solid #e2e8f0;
        }
        .lang-btn {
            padding: 5px 11px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            color: #64748b;
            border-radius: 6px;
            transition: all 0.15s ease;
            cursor: pointer;
            border: none;
            background: none;
        }
        .lang-btn:hover {
            color: #0f172a;
        }
        .lang-btn.active {
            background-color: #ffffff;
            color: #1e3a8a;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        /* Пользователь и меню */
        .header-user-block {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #1b3b6f;
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1.5px solid var(--accent-gold);
            overflow: hidden;
        }
        .header-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .user-text-info {
            line-height: 1.2;
            text-align: right;
        }
        .user-name-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
        }
        .user-role-badge {
            font-size: 10.5px;
            font-weight: 600;
            padding: 1px 7px;
            border-radius: 6px;
            display: inline-block;
            margin-top: 2px;
        }
        .role-admin { background-color: #fef3c7; color: #92400e; }
        .role-editor { background-color: #dbeafe; color: #1e40af; }
        .role-viewer { background-color: #f1f5f9; color: #475569; }

        .btn-header-logout {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 7px 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background-color: #ffffff;
            color: #64748b;
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 600;
            transition: all 0.15s ease;
        }
        .btn-header-logout:hover {
            background-color: #fee2e2;
            border-color: #fca5a5;
            color: #dc2626;
        }

        /* Контейнер страницы */
        .app-content {
            padding: 24px;
            flex: 1;
        }
        .content-container {
            max-width: 1280px;
            margin: 0 auto;
        }

        /* Оверлей для мобильного меню */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 95;
        }

        @media (max-width: 900px) {
            .app-sidebar {
                transform: translateX(-100%);
            }
            .app-sidebar.open {
                transform: translateX(0);
            }
            .sidebar-overlay.open {
                display: block;
            }
            .app-main-wrapper {
                margin-left: 0;
            }
            .mobile-menu-btn {
                display: block;
            }
            .header-status-pill {
                display: none;
            }
        }
    </style>
</head>
<body>

    <!-- Оверлей мобильного меню -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleMobileSidebar()"></div>

    <!-- Левая панель разделов (Sidebar) -->
    <aside class="app-sidebar" id="appSidebar">
        <a href="/" class="sidebar-brand">
            <div class="sidebar-logo">Ш</div>
            <div class="sidebar-brand-text">
                <h1><?= htmlspecialchars($siteTitle) ?></h1>
                <span><?= $lang === 'ru' ? 'Семейный портал' : 'Отбасылық шежіре' ?></span>
            </div>
        </a>

        <nav class="sidebar-nav">
            <!-- 1. Басты / Главная -->
            <a href="/" class="nav-link <?= $activeNav === 'home' ? 'active' : '' ?>">
                <div class="nav-link-content">
                    <span class="nav-link-icon">🏠</span>
                    <span><?= htmlspecialchars(I18n::t('nav_home')) ?></span>
                </div>
            </a>

            <!-- 2. Адамдар / Люди (270 человек) -->
            <a href="/persons" class="nav-link <?= $activeNav === 'persons' ? 'active' : '' ?>">
                <div class="nav-link-content">
                    <span class="nav-link-icon">👥</span>
                    <span><?= htmlspecialchars(I18n::t('nav_persons')) ?></span>
                </div>
                <span class="nav-badge">270</span>
            </a>

            <!-- 3. Отбасылар / Семьи (71 брак) -->
            <a href="/families" class="nav-link <?= $activeNav === 'families' ? 'active' : '' ?>">
                <div class="nav-link-content">
                    <span class="nav-link-icon">👨‍👩‍👧‍👦</span>
                    <span><?= htmlspecialchars(I18n::t('nav_families')) ?></span>
                </div>
                <span class="nav-badge">71</span>
            </a>

            <!-- 4. Шежіре / Древо -->
            <a href="/tree" class="nav-link <?= $activeNav === 'tree' ? 'active' : '' ?>">
                <div class="nav-link-content">
                    <span class="nav-link-icon">🌳</span>
                    <span><?= htmlspecialchars(I18n::t('nav_tree')) ?></span>
                </div>
            </a>

            <!-- 5. Есте қаларлық күндер / Памятные даты -->
            <a href="/dates" class="nav-link <?= $activeNav === 'dates' ? 'active' : '' ?>">
                <div class="nav-link-content">
                    <span class="nav-link-icon">📅</span>
                    <span><?= htmlspecialchars(I18n::t('nav_dates')) ?></span>
                </div>
            </a>

            <!-- 6. Тарих / История правок -->
            <a href="/history" class="nav-link <?= $activeNav === 'history' ? 'active' : '' ?>">
                <div class="nav-link-content">
                    <span class="nav-link-icon">📜</span>
                    <span><?= htmlspecialchars(I18n::t('nav_history')) ?></span>
                </div>
            </a>

            <!-- 7. Әкімші / Админка (доступна admin) -->
            <?php if ($isAdmin): ?>
                <a href="/admin" class="nav-link <?= $activeNav === 'admin' ? 'active' : '' ?>">
                    <div class="nav-link-content">
                        <span class="nav-link-icon">⚙️</span>
                        <span><?= htmlspecialchars(I18n::t('nav_admin')) ?></span>
                    </div>
                </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <span>altybayev.kz &bull; 2026</span>
            <span>v2.1</span>
        </div>
    </aside>

    <!-- Основная область с шапкой и контентом -->
    <div class="app-main-wrapper">
        <!-- Верхняя шапка (Header) -->
        <header class="app-header">
            <div class="header-left">
                <button type="button" class="mobile-menu-btn" onclick="toggleMobileSidebar()" aria-label="Меню">
                    ☰
                </button>
                <div class="header-status-pill">
                    <span class="dot"></span>
                    <span><?= htmlspecialchars($youAreText) ?></span>
                </div>
            </div>

            <div class="header-right">
                <!-- Переключатель языка ҚАЗ / РУС -->
                <div class="lang-switch-group">
                    <button type="button" class="lang-btn <?= $lang === 'kk' ? 'active' : '' ?>" onclick="switchAppLanguage('kk')">ҚАЗ</button>
                    <button type="button" class="lang-btn <?= $lang === 'ru' ? 'active' : '' ?>" onclick="switchAppLanguage('ru')">РУС</button>
                </div>

                <!-- Блок пользователя -->
                <div class="header-user-block">
                    <div class="user-text-info">
                        <div class="user-name-title"><?= htmlspecialchars((string)($user['name'] ?? 'Туысқан')) ?></div>
                        <span class="user-role-badge role-<?= htmlspecialchars($userRole) ?>"><?= htmlspecialchars($roleBadgeText) ?></span>
                    </div>
                    <div class="header-avatar">
                        <?php if (!empty($user['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars((string)$user['avatar_url']) ?>" alt="Avatar">
                        <?php else: ?>
                            <?= mb_substr((string)($user['name'] ?? 'Ш'), 0, 1) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Кнопка выхода -->
                <a href="/logout" class="btn-header-logout" title="<?= htmlspecialchars(I18n::t('nav_logout')) ?>">
                    <span>🚪</span>
                    <span><?= htmlspecialchars(I18n::t('nav_logout')) ?></span>
                </a>
            </div>
        </header>

        <!-- Рабочая область контента -->
        <main class="app-content">
            <div class="content-container">
