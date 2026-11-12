<?php
declare(strict_types=1);
/**
 * @var array|null $user
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

        <div class="grid">
            <div class="card">
                <div class="status-pill">
                    <span class="status-dot"></span>
                    <span>Жүйе іске қосылды / Система активна</span>
                </div>
                <h3>Портал туралы / О портале</h3>
                <p>
                    <strong>Қазақша:</strong> Негізгі жүйе және Google арқылы авторлану сәтті орнатылды. Жақын арада интерактивті шежіре ағашын көруге болады.
                </p>
                <p>
                    <strong>Русский:</strong> Основное ядро и авторизация через Google успешно запущены. Вскоре будет доступно интерактивное древо шежире.
                </p>
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

            <div class="card" style="grid-column: 1 / -1;">
                <h3>Модульдер жоспары / План модулей</h3>
                <ul class="feature-list">
                    <li class="feature-item">
                        <span>🌳 <strong>Шежіре ағашы / Генеалогическое древо</strong> (Интерактивті D3.js графикасы)</span>
                        <span class="badge-dev">Әзірленуде / В разработке</span>
                    </li>
                    <li class="feature-item">
                        <span>📜 <strong>Жеті ата / Семь предков</strong> (Әкелер желісі бойынша шежірені қарау)</span>
                        <span class="badge-dev">Әзірленуде / В разработке</span>
                    </li>
                    <li class="feature-item">
                        <span>👥 <strong>Туыстар картотекасы / Карточки родственников</strong> (Өмірбаяндар, даталар, фотосуреттер)</span>
                        <span class="badge-dev">Әзірленуде / В разработке</span>
                    </li>
                    <?php if ($userRole === 'admin'): ?>
                    <li class="feature-item">
                        <span>⚙️ <strong>Әкімшілік панель / Панель администратора</strong> (Шақырулар мен рөлдерді басқару)</span>
                        <span class="badge-dev">Әзірленуде / В разработке</span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </main>

    <footer>
        &copy; 2026 Altybayev.kz &bull; Шежіре Алтыбаевтар &bull; Барлық құқықтар қорғалған
    </footer>
</body>
</html>
