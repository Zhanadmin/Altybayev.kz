<?php
declare(strict_types=1);
/**
 * @var array|null $user
 * @var int|null $userPersonId
 * @var array $birthdays
 * @var array $anniversaries
 */
$userName = $user['name'] ?? 'Туысқан / Родственник';
$userEmail = $user['email'] ?? '';
$userRole = $user['role'] ?? 'viewer';
$avatarUrl = $user['avatar_url'] ?? null;

$roleLabels = [
    'admin'  => ['kk' => 'Әкімші', 'ru' => 'Администратор', 'class' => 'role-admin'],
    'editor' => ['kk' => 'Редактор', 'ru' => 'Редактор', 'class' => 'role-editor'],
    'viewer' => ['kk' => 'Оқырман', 'ru' => 'Читатель', 'class' => 'role-viewer'],
];
$roleInfo = $roleLabels[$userRole] ?? ['kk' => $userRole, 'ru' => $userRole, 'class' => 'role-viewer'];
$birthdays = $birthdays ?? [];
$anniversaries = $anniversaries ?? [];
?>
<!DOCTYPE html>
<html lang="kk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Шежіре Алтыбаевтар — Басты бет / Главная</title>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        header {
            background-color: #0d1b2a;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .header-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
        }
        .brand-logo {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1b3b6f, #0d1b2a);
            border: 2px solid #d4af37;
            color: #d4af37;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
        }
        .brand-text h1 {
            font-size: 17px;
            font-weight: 700;
            line-height: 1.2;
            color: #ffffff;
        }
        .brand-text span {
            font-size: 12px;
            color: #94a3b8;
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            text-align: right;
        }
        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #d4af37;
            background-color: #1e293b;
        }
        .user-avatar-placeholder {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background-color: #1e293b;
            color: #d4af37;
            border: 2px solid #d4af37;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 15px;
        }
        .user-details {
            line-height: 1.3;
        }
        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: #f8fafc;
        }
        .role-badge {
            display: inline-block;
            font-size: 10.5px;
            padding: 2px 7px;
            border-radius: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .role-admin {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .role-editor {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .role-viewer {
            background-color: #e2e8f0;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .btn-logout {
            background-color: rgba(255, 255, 255, 0.1);
            color: #f8fafc;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-logout:hover {
            background-color: #ef4444;
            border-color: #ef4444;
            color: #ffffff;
        }
        main {
            flex: 1;
            max-width: 1100px;
            width: 100%;
            margin: 0 auto;
            padding: 30px 20px;
        }
        .hero {
            background: linear-gradient(135deg, #1b263b, #0d1b2a);
            color: #ffffff;
            border-radius: 16px;
            padding: 36px 30px;
            margin-bottom: 28px;
            box-shadow: 0 10px 25px rgba(13, 27, 42, 0.25);
            border-bottom: 3px solid #d4af37;
        }
        .hero h2 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .hero p {
            font-size: 15px;
            color: #cbd5e1;
            max-width: 720px;
            line-height: 1.6;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        .card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #e5e7eb;
        }
        .card h3 {
            font-size: 17px;
            font-weight: 700;
            color: #0d1b2a;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card p {
            font-size: 13.5px;
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: #ecfdf5;
            color: #065f46;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            border: 1px solid #a7f3d0;
            margin-bottom: 14px;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            background-color: #10b981;
            border-radius: 50%;
        }
        .feature-list {
            list-style: none;
        }
        .feature-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
        }
        .feature-item:last-child {
            border-bottom: none;
        }
        .badge-dev {
            font-size: 11px;
            padding: 3px 8px;
            background-color: #f1f5f9;
            color: #64748b;
            border-radius: 6px;
            font-weight: 500;
        }
        .user-kv {
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 13.5px;
        }
        .kv-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .kv-label {
            color: #64748b;
        }
        .kv-value {
            font-weight: 600;
            color: #1e293b;
        }

        /* ПАМЯТНЫЕ ДАТЫ И ДНИ РОЖДЕНИЯ */
        .dates-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 380px;
            overflow-y: auto;
            padding-right: 4px;
        }
        .date-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            gap: 12px;
            transition: all 0.2s ease;
        }
        .date-item:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        .date-item.today {
            background: #fefce8;
            border-color: #fde047;
            box-shadow: 0 2px 8px rgba(234, 179, 8, 0.2);
        }
        .date-item-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .date-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #1e293b;
            color: #d4af37;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
        }
        .date-name {
            font-size: 13.5px;
            font-weight: 600;
            color: #0f172a;
        }
        .date-sub {
            font-size: 11.5px;
            color: #64748b;
        }
        .date-tag {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 12px;
            white-space: nowrap;
        }
        .tag-today {
            background: #ef4444;
            color: #ffffff;
            animation: pulse 2s infinite;
        }
        .tag-soon {
            background: #e0f2fe;
            color: #0369a1;
        }
        .tag-memory {
            background: #fef3c7;
            color: #92400e;
        }

        /* МОДАЛЬНОЕ ОКНО ЖЕТІ АТА */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            backdrop-filter: blur(4px);
            padding: 20px;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-card {
            background: #ffffff;
            border-radius: 14px;
            width: 100%;
            max-width: 760px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            overflow: hidden;
            border: 2px solid #d4af37;
        }
        .modal-header {
            background: #0d1b2a;
            color: #ffffff;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }
        .btn-close {
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 20px;
            cursor: pointer;
        }
        .btn-close:hover {
            color: #ffffff;
        }

        footer {
            background-color: #ffffff;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            padding: 18px 20px;
            font-size: 13px;
            color: #6b7280;
            margin-top: auto;
        }
    </style>
</head>
<body>
    <header>
        <div class="header-inner">
            <a href="/" class="brand">
                <div class="brand-logo" aria-hidden="true">Ш</div>
                <div class="brand-text">
                    <h1>Шежіре Алтыбаевтар</h1>
                    <span>altybayev.kz &bull; Шежире Алтыбаевых</span>
                </div>
            </a>

            <div class="user-menu">
                <div class="user-info">
                    <?php if ($avatarUrl): ?>
                        <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar" class="user-avatar">
                    <?php else: ?>
                        <div class="user-avatar-placeholder">
                            <?= htmlspecialchars(mb_substr($userName, 0, 1, 'UTF-8')) ?>
                        </div>
                    <?php endif; ?>
                    <div class="user-details">
                        <div class="user-name"><?= htmlspecialchars($userName) ?></div>
                        <span class="role-badge <?= $roleInfo['class'] ?>">
                            <?= htmlspecialchars($roleInfo['kk']) ?> / <?= htmlspecialchars($roleInfo['ru']) ?>
                        </span>
                    </div>
                </div>

                <a href="/logout" class="btn-logout" title="Жүйеден шығу / Выход">Шығу / Выход</a>
            </div>
        </div>
    </header>

    <main>
        <div class="hero">
            <h2>Қош келдіңіз, <?= htmlspecialchars($userName) ?>!</h2>
            <p style="font-size:16px; margin-bottom: 6px;">Добро пожаловать в семейный генеалогический портал рода Алтыбаевтар!</p>
            <p>
                Бұл портал Алтыбаевтар әулетінің тарихын, шежіресін және ұрпақтар сабақтастығын сақтауға арналған жабық отбасылық жүйе.
                <br><span style="color:#94a3b8;">Портал предназначен для сохранения истории, генеалогии и преемственности поколений семьи Алтыбаевых.</span>
            </p>
        </div>

        <!-- БЫСТРЫЕ ДЕЙСТВИЯ (КНОПКИ ФУНКЦИОНАЛА) -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <a href="/tree" style="display: flex; align-items: center; gap: 14px; background: linear-gradient(135deg, #0d1b2a, #1b263b); border: 2px solid #d4af37; border-radius: 12px; padding: 16px 20px; color: #fff; text-decoration: none; box-shadow: 0 4px 15px rgba(13,27,42,0.15); transition: transform 0.2s ease;">
                <span style="font-size: 28px;">🌳</span>
                <div>
                    <div style="font-weight: 700; font-size: 15px; color: #fef08a;">Шежіре ағашы</div>
                    <div style="font-size: 12px; color: #94a3b8;">Өңдеу, Орман және Тізім түрлері ➔</div>
                </div>
            </a>

            <button type="button" onclick="openZhetiAtaModal()" style="display: flex; align-items: center; gap: 14px; background: linear-gradient(135deg, #1e3a8a, #1e293b); border: 2px solid #38bdf8; border-radius: 12px; padding: 16px 20px; color: #fff; cursor: pointer; text-align: left; box-shadow: 0 4px 15px rgba(30,58,138,0.15); transition: transform 0.2s ease;">
                <span style="font-size: 28px;">📜</span>
                <div>
                    <div style="font-weight: 700; font-size: 15px; color: #bae6fd;">Жеті ата тексеру</div>
                    <div style="font-size: 12px; color: #94a3b8;">Екі адамның арасында 7 буынды тексеру ➔</div>
                </div>
            </button>

            <a href="/tree/print" style="display: flex; align-items: center; gap: 14px; background: linear-gradient(135deg, #78350f, #1e293b); border: 2px solid #f59e0b; border-radius: 12px; padding: 16px 20px; color: #fff; text-decoration: none; box-shadow: 0 4px 15px rgba(120,53,15,0.15); transition: transform 0.2s ease;">
                <span style="font-size: 28px;">🖨️</span>
                <div>
                    <div style="font-weight: 700; font-size: 15px; color: #fde68a;">Шежіре-плакат басып шығару</div>
                    <div style="font-size: 12px; color: #94a3b8;">Полиграфиялық экспорт және SVG ➔</div>
                </div>
            </a>
        </div>

        <div class="grid">
            <!-- 1. БЛИЖАЙШИЕ ДНИ РОЖДЕНИЯ ЖИВЫХ -->
            <div class="card">
                <h3>
                    <span>🎂</span>
                    <span>Туған күндер / Дни рождения (45 күн)</span>
                </h3>
                <p>Ближайшие дни рождения живых родственников семьи Алтыбаевых:</p>
                <div class="dates-container">
                    <?php if (empty($birthdays)): ?>
                        <div style="text-align:center; padding:20px; color:#94a3b8; font-size:13px;">
                            Алдағы 45 күнде туған күндер жоқ / На ближайшие 45 дней дней рождения не зафиксировано.
                        </div>
                    <?php else: ?>
                        <?php foreach ($birthdays as $b): ?>
                            <div class="date-item <?= $b['is_today'] ? 'today' : '' ?>">
                                <div class="date-item-left">
                                    <div class="date-avatar">
                                        <?= htmlspecialchars(mb_substr($b['first_name'], 0, 1, 'UTF-8')) ?>
                                    </div>
                                    <div>
                                        <div class="date-name">
                                            <?= htmlspecialchars($b['full_name']) ?>
                                            <?php if ($b['kinship_kk']): ?>
                                                <span style="font-size:11px; background:#e0e7ff; color:#3730a3; padding:1px 6px; border-radius:10px; font-weight:600;">
                                                    <?= htmlspecialchars($b['kinship_kk']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="date-sub">
                                            <?= htmlspecialchars($b['day_month_formatted']['kk']) ?> &bull; <?= $b['turning_age'] ?> жасқа толады
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($b['is_today']): ?>
                                        <span class="date-tag tag-today">БҮГІН! 🎉</span>
                                    <?php elseif ($b['is_tomorrow']): ?>
                                        <span class="date-tag tag-soon">Ертең</span>
                                    <?php else: ?>
                                        <span class="date-tag tag-soon"><?= $b['days_until'] ?> күн қалды</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. ГОДОВЩИНЫ УШЕДШИХ (АС, ЖЫЛДЫҚ, ҚҰРАН БАҒЫШТАУ) -->
            <div class="card">
                <h3>
                    <span>🕯️</span>
                    <span>Памятные даты / Ата-бабалар асы (45 күн)</span>
                </h3>
                <p>Годовщины ухода из жизни предков, даты поминальных асов и молитв:</p>
                <div class="dates-container">
                    <?php if (empty($anniversaries)): ?>
                        <div style="text-align:center; padding:20px; color:#94a3b8; font-size:13px;">
                            Алдағы 45 күнде естелік күндері жоқ / В ближайшие 45 дней памятных дат нет.
                        </div>
                    <?php else: ?>
                        <?php foreach ($anniversaries as $a): ?>
                            <div class="date-item <?= $a['is_today'] ? 'today' : '' ?>">
                                <div class="date-item-left">
                                    <div class="date-avatar" style="background:#451a03; color:#fde68a;">
                                        ✨
                                    </div>
                                    <div>
                                        <div class="date-name">
                                            <?= htmlspecialchars($a['full_name']) ?>
                                            <?php if ($a['kinship_kk']): ?>
                                                <span style="font-size:11px; background:#fef3c7; color:#92400e; padding:1px 6px; border-radius:10px; font-weight:600;">
                                                    <?= htmlspecialchars($a['kinship_kk']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="date-sub">
                                            <?= htmlspecialchars($a['day_month_formatted']['kk']) ?> &bull; <?= htmlspecialchars($a['memorial_label_kk']) ?>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($a['is_today']): ?>
                                        <span class="date-tag tag-today">БҮГІН ЕСКЕ АЛУ</span>
                                    <?php else: ?>
                                        <span class="date-tag tag-memory"><?= $a['days_until'] ?> күн қалды</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h3>Сіздің профиліңіз / Профиль</h3>
                <div class="user-kv">
                    <div class="kv-row">
                        <span class="kv-label">Email:</span>
                        <span class="kv-value"><?= htmlspecialchars($userEmail) ?></span>
                    </div>
                    <div class="kv-row">
                        <span class="kv-label">Рөлі / Роль:</span>
                        <span class="kv-value"><?= htmlspecialchars($roleInfo['kk']) ?> (<?= htmlspecialchars($userRole) ?>)</span>
                    </div>
                    <div class="kv-row">
                        <span class="kv-label">ID:</span>
                        <span class="kv-value">#<?= (int)($user['id'] ?? 0) ?></span>
                    </div>
                    <div class="kv-row">
                        <span class="kv-label">Күйі / Статус:</span>
                        <span class="kv-value" style="color:#10b981;">Белсенді / Активен</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>Портал туралы / О портале</h3>
                <p>
                    <strong>Қазақша:</strong> Жүйеде 270 адамнан тұратын Шежіре ағашы, «Жеті ата» қағидасын тексеру, естеліктер мен ақсақалдардың аудиожазбалары және басып шығару плакаты толық іске қосылды.
                </p>
                <p>
                    <strong>Русский:</strong> Доступно полное генеалогическое древо на 270 персон, калькулятор «Жеті ата», аудиозаписи и воспоминания, памятные даты и подарочный плакат шежире.
                </p>
            </div>

            <div class="card" style="grid-column: 1 / -1;">
                <h3>Модульдер тізімі / Список модулей</h3>
                <ul class="feature-list">
                    <li class="feature-item">
                        <span>🌳 <strong>Шежіре ағашы / Генеалогическое древо</strong> («Өңдеу» режимі + «Шежіре-Орман», туыстық атаулар)</span>
                        <a href="/tree" class="badge-dev" style="background:#10b981; color:#fff; text-decoration:none;">Ашу / Открыть ➔</a>
                    </li>
                    <li class="feature-item">
                        <span>📜 <strong>Жеті ата тексеру / Проверка Семи предков</strong> (Екі адамның арасындағы 7 буынды есептеу)</span>
                        <a href="javascript:void(0)" onclick="openZhetiAtaModal()" class="badge-dev" style="background:#2563eb; color:#fff; text-decoration:none;">Тексеру ➔</a>
                    </li>
                    <li class="feature-item">
                        <span>🖨️ <strong>Шежіре-плакат басып шығару / Печать плаката шежире</strong> (Подарочный плакат, выбор ветки и буын, SVG экспорт)</span>
                        <a href="/tree/print" class="badge-dev" style="background:#d97706; color:#fff; text-decoration:none;">Плакатты ашу ➔</a>
                    </li>
                    <?php if ($userRole === 'admin'): ?>
                    <li class="feature-item" style="background:#fefce8; border:1px solid #fef08a;">
                        <span>📥 <strong>GEDCOM импорты</strong> — шежірені алдын ала көру, XSS-тазалау, жабық контактілер және базаға енгізу</span>
                        <a href="/admin/import" class="badge-dev" style="background:#d97706; color:#fff; text-decoration:none;">Басқару / Открыть ➔</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </main>

    <!-- МОДАЛЬНОЕ ОКНО ПРОВЕРКИ ЖЕТІ АТА -->
    <div class="modal-overlay" id="homeZhetiAtaModal">
        <div class="modal-card">
            <div class="modal-header">
                <div style="font-weight:700; font-size:16px; color:#fef08a;">
                    📜 Қазақтың «Жеті ата» салтын тексеру калькуляторы
                </div>
                <button type="button" class="btn-close" onclick="closeZhetiAtaModal()">✕</button>
            </div>
            <div class="modal-body">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px; margin-bottom:16px; font-size:13px; color:#475569;">
                    Тізімнен екі адамды таңдап, олардың арасында әкелер желісі бойынша жеті атаға тола қоймаған туыстық байланыс бар-жоғын анықтаңыз.
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:12.5px; font-weight:700; margin-bottom:4px; color:#0f172a;">1-ші тұлға (А адам):</label>
                        <select id="homePersonA" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13.5px;">
                            <option value="">Жүктелуде...</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:12.5px; font-weight:700; margin-bottom:4px; color:#0f172a;">2-ші тұлға (Б адам):</label>
                        <select id="homePersonB" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13.5px;">
                            <option value="">Жүктелуде...</option>
                        </select>
                    </div>
                </div>

                <div style="text-align:center; margin-bottom:16px;">
                    <button type="button" onclick="runHomeZhetiAtaCheck()" style="background:linear-gradient(135deg, #2563eb, #1d4ed8); color:#fff; border:none; padding:10px 24px; border-radius:24px; font-weight:700; font-size:14px; cursor:pointer; box-shadow:0 4px 12px rgba(37,99,235,0.3);">
                        🔍 Жеті атаны тексеру
                    </button>
                </div>

                <!-- Блок результата -->
                <div id="homeZhetiAtaResult" style="display:none;"></div>
            </div>
        </div>
    </div>

    <footer>
        &copy; 2026 Altybayev.kz &bull; Шежіре Алтыбаевтар &bull; Барлық құқықтар қорғалған
    </footer>

    <script>
        let cachedPersons = [];

        async function openZhetiAtaModal() {
            document.getElementById('homeZhetiAtaModal').classList.add('active');
            if (cachedPersons.length === 0) {
                try {
                    const res = await fetch('/api/tree');
                    const data = await res.json();
                    if (data && data.persons) {
                        cachedPersons = data.persons;
                        populatePersonSelects();
                    }
                } catch(e) {
                    console.error('Failed to load persons', e);
                }
            }
        }

        function closeZhetiAtaModal() {
            document.getElementById('homeZhetiAtaModal').classList.remove('active');
        }

        function populatePersonSelects() {
            const selA = document.getElementById('homePersonA');
            const selB = document.getElementById('homePersonB');
            selA.innerHTML = '';
            selB.innerHTML = '';

            const sorted = [...cachedPersons].sort((a,b) => (a.first_name || '').localeCompare(b.first_name || ''));
            sorted.forEach(p => {
                const optA = document.createElement('option');
                optA.value = p.id;
                optA.text = `${p.first_name} ${p.last_name || ''} (${p.gender === 'M' ? 'Ер' : 'Әйел'}, ${p.birth_year || '—'})`;
                selA.appendChild(optA);

                const optB = document.createElement('option');
                optB.value = p.id;
                optB.text = `${p.first_name} ${p.last_name || ''} (${p.gender === 'M' ? 'Ер' : 'Әйел'}, ${p.birth_year || '—'})`;
                selB.appendChild(optB);
            });

            // По умолчанию выбираем Жандоса (ID 6) и родственника
            selA.value = "6";
            if (sorted.length > 1) {
                selB.value = sorted.find(x => x.id !== 6)?.id || sorted[1].id;
            }
        }

        async function runHomeZhetiAtaCheck() {
            const a = document.getElementById('homePersonA').value;
            const b = document.getElementById('homePersonB').value;
            const resContainer = document.getElementById('homeZhetiAtaResult');
            resContainer.style.display = 'block';
            resContainer.innerHTML = '<div style="text-align:center; padding:20px; color:#64748b;">Есептелуде...</div>';

            try {
                const res = await fetch(`/api/zheti-ata?a=${a}&b=${b}`);
                const data = await res.json();
                if (!data.success) {
                    resContainer.innerHTML = `<div style="color:#ef4444; padding:10px;">${data.error || 'Қате'}</div>`;
                    return;
                }

                const pat = data.paternal;
                const isWithin = pat.is_within_7_generations;
                const statusColor = isWithin ? '#ef4444' : (pat.verdict_code === 'exceeds_7' ? '#10b981' : '#64748b');
                const bgColor = isWithin ? '#fef2f2' : (pat.verdict_code === 'exceeds_7' ? '#ecfdf5' : '#f8fafc');

                let html = `
                    <div style="background:${bgColor}; border:2px solid ${statusColor}; border-radius:10px; padding:16px; margin-bottom:14px;">
                        <div style="font-size:16px; font-weight:700; color:${statusColor}; margin-bottom:6px;">
                            ${pat.title_kk}
                        </div>
                        <div style="font-size:13.5px; line-height:1.6; color:#1e293b;">
                            ${pat.desc_kk}
                        </div>
                    </div>
                `;

                if (pat.has_common_ancestor && pat.path_a && pat.path_b) {
                    html += `
                        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:14px;">
                            <div style="font-weight:700; font-size:13px; color:#0f172a; margin-bottom:10px;">
                                🌿 Ортақ атаға дейінгі туыстық желісі (Әкелер желісі):
                            </div>
                            <div style="font-size:13px; color:#334155; line-height:1.6;">
                                <strong>${data.person_a.first_name} жағынан (${pat.gen_distance_a}-буын):</strong><br>
                                ${pat.path_a.map(x => `${x.title_kk}: <strong>${x.person.first_name} ${x.person.last_name || ''}</strong>`).join(' ➔ ')}
                            </div>
                            <div style="font-size:13px; color:#334155; line-height:1.6; margin-top:8px;">
                                <strong>${data.person_b.first_name} жағынан (${pat.gen_distance_b}-буын):</strong><br>
                                ${pat.path_b.map(x => `${x.title_kk}: <strong>${x.person.first_name} ${x.person.last_name || ''}</strong>`).join(' ➔ ')}
                            </div>
                        </div>
                    `;
                }

                resContainer.innerHTML = html;
            } catch(e) {
                resContainer.innerHTML = `<div style="color:#ef4444; padding:10px;">Қосылу қатесі: ${e.message}</div>`;
            }
        }
    </script>
</body>
</html>
