<?php
declare(strict_types=1);
/**
 * @var array|null $user
 * @var array $availableFiles
 * @var array $history
 * @var array|null $preview
 * @var string|null $successMessage
 * @var string|null $errorMessage
 * @var string|null $activeFile
 */
$userName = $user['name'] ?? 'Жандос Алтыбаев';
$userEmail = $user['email'] ?? 'zhandos.alt06@gmail.com';
?>
<!DOCTYPE html>
<html lang="kk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Шежіре Алтыбаевтар — GEDCOM импорты және предпросмотр (Әкімші)</title>
    <style>
        :root {
            --bg: #f8fafc;
            --surface: #ffffff;
            --header-bg: #0d1b2a;
            --primary: #1e3a8a;
            --primary-hover: #1d4ed8;
            --gold: #d4af37;
            --gold-dark: #b45309;
            --border: #e2e8f0;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --male: #2563eb;
            --female: #ec4899;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --purple: #8b5cf6;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--bg);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            background-color: var(--header-bg);
            color: #ffffff;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border-bottom: 2px solid var(--gold);
        }

        .header-inner {
            max-width: 1300px;
            margin: 0 auto;
            padding: 12px 20px;
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
            color: #fff;
        }

        .brand-logo {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 20px;
            color: #fff;
        }

        .brand-text h1 {
            font-size: 17px;
            font-weight: 700;
        }

        .brand-text span {
            font-size: 11px;
            color: #94a3b8;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-btn {
            background: rgba(255, 255, 255, 0.1);
            color: #f8fafc;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .nav-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .badge-admin {
            background: #fef3c7;
            color: #92400e;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        main {
            flex: 1;
            max-width: 1300px;
            width: 100%;
            margin: 0 auto;
            padding: 24px 20px;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-success {
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-warning {
            background-color: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .card {
            background-color: var(--surface);
            border-radius: 12px;
            padding: 22px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            margin-bottom: 24px;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--header-bg);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .kpi-card {
            background-color: var(--surface);
            border-radius: 10px;
            padding: 18px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 4px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            gap: 6px;
            position: relative;
            overflow: hidden;
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
        }

        .kpi-persons::before { background-color: var(--primary); }
        .kpi-families::before { background-color: var(--male); }
        .kpi-photos::before { background-color: var(--text-muted); }
        .kpi-contacts::before { background-color: var(--purple); }
        .kpi-xss::before { background-color: var(--danger); }
        .kpi-warnings::before { background-color: var(--warning); }

        .kpi-num {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1;
        }

        .kpi-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .kpi-sub {
            font-size: 11.5px;
            color: #94a3b8;
            margin-top: 2px;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: #fff;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.35);
            transition: all 0.2s;
        }

        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(212, 175, 55, 0.45);
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f1f5f9;
            color: #334155;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            border: 1px solid #cbd5e1;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-danger {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-danger:hover {
            background: #ef4444;
            color: #fff;
        }

        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        table.data-table th {
            background-color: #f8fafc;
            color: #475569;
            padding: 10px 14px;
            font-weight: 700;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        table.data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
        }

        table.data-table tr:hover td {
            background-color: #f8fafc;
        }

        .badge-gender-m {
            background-color: #dbeafe;
            color: #1e40af;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 11px;
        }

        .badge-gender-f {
            background-color: #fce7f3;
            color: #9d174d;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 11px;
        }

        .badge-secure {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background-color: #f3e8ff;
            color: #6b21a8;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .threat-item {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-left: 4px solid var(--danger);
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 10px;
            font-size: 13px;
        }

        .tabs {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid var(--border);
            margin-bottom: 18px;
        }

        .tab-btn {
            padding: 9px 18px;
            font-size: 13.5px;
            font-weight: 600;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            font-weight: 700;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        .note-preview {
            max-width: 320px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #64748b;
            font-size: 12px;
        }

        .search-box {
            padding: 8px 14px;
            border-radius: 6px;
            border: 1px solid var(--border);
            font-size: 13px;
            width: 260px;
        }

        footer {
            background: #fff;
            border-top: 1px solid var(--border);
            text-align: center;
            padding: 16px;
            font-size: 12.5px;
            color: var(--text-muted);
            margin-top: auto;
        }
    </style>
</head>
<body>
    <header>
        <div class="header-inner">
            <a href="/" class="brand">
                <div class="brand-logo">Ш</div>
                <div class="brand-text">
                    <h1>Шежіре Алтыбаевтар &bull; GEDCOM Басқару</h1>
                    <span>altybayev.kz &bull; Әкімшілік панель</span>
                </div>
            </a>

            <div class="nav-links">
                <a href="/" class="nav-btn">← Басты бет / Главная</a>
                <a href="/tree" class="nav-btn" style="border-color:var(--gold); color:var(--gold);">🌳 Древо / Дерево</a>
                <span class="badge-admin">Әкімші / Admin: <?= htmlspecialchars($userName) ?></span>
                <a href="/logout" class="nav-btn" style="background:rgba(239,68,68,0.2); border-color:#ef4444;">Шығу</a>
            </div>
        </div>
    </header>

    <main>
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success">
                <span>✅</span>
                <div><?= htmlspecialchars($successMessage) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error">
                <span>❌</span>
                <div><?= htmlspecialchars($errorMessage) ?></div>
            </div>
        <?php endif; ?>

        <!-- БЛОК 1: ВЫБОР ИЛИ ЗАГРУЗКА ФАЙЛА GEDCOM -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">📁 1. GEDCOM файлын таңдау / Выбор файла GEDCOM</h2>
                    <p style="font-size:13px; color:var(--text-muted); margin-top:4px;">
                        Файл <code>import/Altubaiev - 2010-05-27.ged</code> (әкеңіздің MyHeritage бағдарламасынан экспортталған шежіресі).
                    </p>
                </div>
            </div>

            <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:center;">
                <?php if (!empty($availableFiles)): ?>
                    <form action="/admin/import/preview" method="GET" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <label for="select-file" style="font-size:13px; font-weight:600;">Файл серверде:</label>
                        <select name="file" id="select-file" style="padding:8px 12px; border-radius:6px; border:1px solid var(--border); font-size:13px; min-width:280px;">
                            <?php foreach ($availableFiles as $file): ?>
                                <option value="<?= htmlspecialchars($file['name']) ?>" <?= (isset($activeFile) && $activeFile === $file['name']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($file['name']) ?> (<?= $file['size_formatted'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-secondary" style="background:#1e3a8a; color:#fff; border-color:#1e3a8a;">
                            🔍 Талдау және предпросмотр / Просмотреть
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Загрузка нового файла -->
                <form action="/admin/import/preview" method="POST" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:center; margin-left:auto;">
                    <input type="file" name="gedcom_file" accept=".ged" required style="font-size:12px;">
                    <button type="submit" class="btn-secondary">
                        ⬆️ Жаңа .ged жүктеу / Загрузить
                    </button>
                </form>
            </div>
        </div>

        <?php if (!empty($preview)): ?>
            <!-- БЛОК 2: СТАТИСТИКА И KPI ПРЕДПРОСМОТРА -->
            <div class="kpi-grid">
                <div class="kpi-card kpi-persons">
                    <div class="kpi-num"><?= $preview['stats']['total_persons'] ?? 0 ?></div>
                    <div class="kpi-label">Персондар / Персоны</div>
                    <div class="kpi-sub">Керей → Алтыбай → Шамсиден шежіресі</div>
                </div>

                <div class="kpi-card kpi-families">
                    <div class="kpi-num"><?= $preview['stats']['total_families'] ?? 0 ?></div>
                    <div class="kpi-label">Отбасылар / Семьи (FAM)</div>
                    <div class="kpi-sub">Некелер мен ата-аналық байланыстар</div>
                </div>

                <div class="kpi-card kpi-photos">
                    <div class="kpi-num"><?= $preview['stats']['photos_skipped'] ?? 0 ?></div>
                    <div class="kpi-label">Фото өткізілді / Пропущено фото</div>
                    <div class="kpi-sub">Әкеңіздің дербес ПК-індегі жергілікті жолдар</div>
                </div>

                <div class="kpi-card kpi-contacts">
                    <div class="kpi-num"><?= $preview['stats']['contacts_secured'] ?? 0 ?></div>
                    <div class="kpi-label">Жабық байланыстар / Контакты</div>
                    <div class="kpi-sub">Телефон/email тек әкімшіге көрінеді</div>
                </div>

                <div class="kpi-card kpi-xss">
                    <div class="kpi-num"><?= $preview['stats']['xss_threats_neutralized'] ?? 0 ?></div>
                    <div class="kpi-label">XSS қауіптері жойылды / Очищено</div>
                    <div class="kpi-sub">Шамсиден жазбасындағы script/iframe</div>
                </div>

                <div class="kpi-card kpi-warnings">
                    <div class="kpi-num"><?= $preview['stats']['warnings_count'] ?? 0 ?></div>
                    <div class="kpi-label">Ескертулер / Замечания</div>
                    <div class="kpi-sub">Датасы жоқ / Белгісіз жыныс</div>
                </div>
            </div>

            <!-- БЛОК 3: НЕЙТРАЛИЗОВАННЫЕ УГРОЗЫ XSS (ТРЕБОВАНИЕ 2) -->
            <?php if (!empty($preview['xss_threats'])): ?>
                <div class="card" style="border-left: 4px solid var(--danger); background:#fff5f5;">
                    <h3 style="color:#b91c1c; font-size:15px; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                        🛡️ Қауіпсіздік фильтрі: Вредоносный HTML сәтті жойылды / Вредоносный HTML нейтрализован
                    </h3>
                    <p style="font-size:13px; color:#7f1d1d; margin-bottom:12px;">
                        MyHeritage заметкаларындағы күдікті скрипттер мен фреймдер анықталып, дерекқорға жазылмайтындай толық тазартылды:
                    </p>
                    <?php foreach ($preview['xss_threats'] as $threat): ?>
                        <div class="threat-item">
                            <strong>[<?= htmlspecialchars($threat['type']) ?>]</strong>
                            Персона: <b><?= htmlspecialchars($threat['person_name']) ?></b> (<?= htmlspecialchars($threat['person_id']) ?>):
                            <span><?= htmlspecialchars($threat['threat']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- БЛОК 4: ГЛАВНАЯ КНОПКА ЗАПУСКА ИМПОРТА ЖАНДОСОМ -->
            <div class="card" style="background: linear-gradient(135deg, #0d1b2a, #1b263b); color:#fff; border: 2px solid var(--gold);">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                    <div>
                        <h3 style="color:var(--gold); font-size:19px; margin-bottom:6px;">
                            📥 2. Шежірені дерекқорға енгізу / Импорт в базу данных
                        </h3>
                        <p style="font-size:13.5px; color:#cbd5e1; max-width:750px; line-height:1.5;">
                            Барлық <strong><?= $preview['stats']['total_persons'] ?></strong> адам мен <strong><?= $preview['stats']['total_families'] ?></strong> отбасы бір транзакциямен дерекқорға жазылады.
                            Қайталап жүктеу кезінде дубликаттар жасалмайды (gedcom_id бойынша тексеріледі). Барлық әрекет журналға тіркеледі және оны кез келген уақытта бір батырмамен толық қайтаруға (откат) болады.
                        </p>
                    </div>

                    <form action="/admin/import/execute" method="POST" onsubmit="return confirm('Шежірені базаға жазуды растайсыз ба? (Барлығы <?= $preview['stats']['total_persons'] ?> адам қосылады/жаңартылады)');">
                        <input type="hidden" name="file" value="<?= htmlspecialchars($preview['file_name'] ?? '') ?>">
                        <button type="submit" class="btn-action">
                            🚀 Дерекқорға импорттау / Импортировать
                        </button>
                    </form>
                </div>
            </div>

            <!-- БЛОК 5: ДЕТАЛЬНЫЙ ТАБЛИЧНЫЙ ПРЕДПРОСМОТР ДАННЫХ -->
            <div class="card">
                <div class="card-header">
                    <div class="tabs">
                        <button class="tab-btn active" onclick="switchTab('tab-persons', this)">
                            👥 Персондар (<?= count($preview['persons']) ?>)
                        </button>
                        <button class="tab-btn" onclick="switchTab('tab-families', this)">
                            👨‍👩‍👧‍👦 Отбасылар / Семьи (<?= count($preview['families']) ?>)
                        </button>
                        <button class="tab-btn" onclick="switchTab('tab-warnings', this)">
                            ⚠️ Сомнительные записи (<?= count($preview['warnings']) ?>)
                        </button>
                    </div>

                    <input type="text" id="person-search" class="search-box" placeholder="Аты-жөні немесе ID бойынша іздеу..." onkeyup="filterPersons()">
                </div>

                <!-- ВКЛАДКА 1: ПЕРСОНЫ -->
                <div id="tab-persons" class="tab-pane active">
                    <div class="table-responsive">
                        <table class="data-table" id="table-persons">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Аты-жөні / ФИО</th>
                                    <th>Жынысы</th>
                                    <th>Туған жылы / Жері</th>
                                    <th>Әкесі</th>
                                    <th>Анасы</th>
                                    <th>Жабық байланыстар (🔒 Тек әкімші)</th>
                                    <th>Өмірбаяны / Заметки</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($preview['persons'] as $id => $p): ?>
                                    <tr class="person-row">
                                        <td><code><?= htmlspecialchars($id) ?></code></td>
                                        <td class="col-name">
                                            <strong><?= htmlspecialchars($p['full_name']) ?></strong>
                                            <?php if (!empty($p['maiden_name'])): ?>
                                                <div style="font-size:11px; color:#ec4899;">(қыз күнінде: <?= htmlspecialchars($p['maiden_name']) ?>)</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($p['gender'] === 'M'): ?>
                                                <span class="badge-gender-m">Ер / М</span>
                                            <?php elseif ($p['gender'] === 'F'): ?>
                                                <span class="badge-gender-f">Әйел / Ж</span>
                                            <?php else: ?>
                                                <span style="color:#94a3b8; font-size:11px;">Белгісіз</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($p['birth_year'] ?: ($p['birth_date'] ?: '—')) ?></div>
                                            <?php if (!empty($p['birth_place'])): ?>
                                                <div style="font-size:11px; color:#64748b;"><?= htmlspecialchars($p['birth_place']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($p['father_gedcom_id'] && isset($preview['persons'][$p['father_gedcom_id']])): ?>
                                                <?= htmlspecialchars($preview['persons'][$p['father_gedcom_id']]['full_name']) ?>
                                            <?php else: ?>
                                                <span style="color:#cbd5e1;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($p['mother_gedcom_id'] && isset($preview['persons'][$p['mother_gedcom_id']])): ?>
                                                <?= htmlspecialchars($preview['persons'][$p['mother_gedcom_id']]['full_name']) ?>
                                            <?php else: ?>
                                                <span style="color:#cbd5e1;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $hasPhones = !empty($p['private_contacts']['phones']);
                                            $hasEmails = !empty($p['private_contacts']['emails']);
                                            $hasAddrs = !empty($p['private_contacts']['addresses']);
                                            ?>
                                            <?php if ($hasPhones || $hasEmails || $hasAddrs): ?>
                                                <div style="display:flex; flex-direction:column; gap:2px;">
                                                    <?php foreach ($p['private_contacts']['phones'] as $ph): ?>
                                                        <span class="badge-secure">📞 <?= htmlspecialchars($ph) ?></span>
                                                    <?php endforeach; ?>
                                                    <?php foreach ($p['private_contacts']['emails'] as $em): ?>
                                                        <span class="badge-secure">✉️ <?= htmlspecialchars($em) ?></span>
                                                    <?php endforeach; ?>
                                                    <?php foreach ($p['private_contacts']['addresses'] as $ad): ?>
                                                        <span class="badge-secure">🏠 <?= htmlspecialchars($ad) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color:#cbd5e1;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($p['biography'])): ?>
                                                <div class="note-preview" title="<?= htmlspecialchars(strip_tags($p['biography'])) ?>">
                                                    <?= htmlspecialchars(mb_substr(strip_tags($p['biography']), 0, 75)) ?>...
                                                </div>
                                            <?php else: ?>
                                                <span style="color:#cbd5e1;">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ВКЛАДКА 2: СЕМЬИ -->
                <div id="tab-families" class="tab-pane">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>FAM ID</th>
                                    <th>Отағасы / Муж</th>
                                    <th>Жұбайы / Жена</th>
                                    <th>Балалары / Дети</th>
                                    <th>Неке күні / Жері</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($preview['families'] as $fId => $fam): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($fId) ?></code></td>
                                        <td>
                                            <?php if ($fam['husband_gedcom_id'] && isset($preview['persons'][$fam['husband_gedcom_id']])): ?>
                                                <b><?= htmlspecialchars($preview['persons'][$fam['husband_gedcom_id']]['full_name']) ?></b>
                                            <?php else: ?>
                                                <span style="color:#cbd5e1;">Белгісіз</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($fam['wife_gedcom_id'] && isset($preview['persons'][$fam['wife_gedcom_id']])): ?>
                                                <b><?= htmlspecialchars($preview['persons'][$fam['wife_gedcom_id']]['full_name']) ?></b>
                                            <?php else: ?>
                                                <span style="color:#cbd5e1;">Белгісіз</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="font-weight:700; color:var(--primary);"><?= count($fam['children']) ?> бала:</span>
                                            <?php
                                            $childNames = [];
                                            foreach ($fam['children'] as $cId) {
                                                if (isset($preview['persons'][$cId])) {
                                                    $childNames[] = $preview['persons'][$cId]['first_name'] ?: $cId;
                                                }
                                            }
                                            echo htmlspecialchars(implode(', ', $childNames));
                                            ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($fam['marriage_date'] ?: '—') ?>
                                            <?php if ($fam['marriage_place']): ?>
                                                <span style="font-size:11px; color:#64748b;">(<?= htmlspecialchars($fam['marriage_place']) ?>)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ВКЛАДКА 3: СОМНИТЕЛЬНЫЕ ЗАПИСИ И ПРЕДУПРЕЖДЕНИЯ -->
                <div id="tab-warnings" class="tab-pane">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Түрі / Тип</th>
                                    <th>ID</th>
                                    <th>Аты-жөні / ФИО</th>
                                    <th>Ескерту сипаттамасы / Описание замечания</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($preview['warnings'] as $w): ?>
                                    <tr>
                                        <td>
                                            <span style="font-size:11px; padding:2px 6px; border-radius:4px; background:#fef3c7; color:#92400e; font-weight:700;">
                                                <?= htmlspecialchars($w['type']) ?>
                                            </span>
                                        </td>
                                        <td><code><?= htmlspecialchars($w['person_id']) ?></code></td>
                                        <td><b><?= htmlspecialchars($w['name']) ?></b></td>
                                        <td><?= htmlspecialchars($w['message']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- БЛОК 6: ЖУРНАЛ АУДИТА И КНОПКА ОТКАТА (ТРЕБОВАНИЕ 6) -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">📜 3. Импорттар тарихы және откат (Аудит журналы)</h2>
                    <p style="font-size:13px; color:var(--text-muted); margin-top:4px;">
                        Әрбір импорт пен қайтару әрекеті audit_logs кестесінде сақталады. Қажет болса, кез келген импортты базадан толық тазартуға болады.
                    </p>
                </div>
            </div>

            <?php if (!empty($history)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Уақыты / Дата</th>
                                <th>Әкімші / Автор</th>
                                <th>Әрекет / Действие</th>
                                <th>Сипаттамасы / Описание</th>
                                <th>Басқару / Управление</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td style="white-space:nowrap;"><code><?= htmlspecialchars($h['created_at']) ?></code></td>
                                    <td>
                                        <strong><?= htmlspecialchars($h['user_name'] ?? 'Admin') ?></strong>
                                        <div style="font-size:11px; color:#64748b;"><?= htmlspecialchars($h['user_email'] ?? '') ?></div>
                                    </td>
                                    <td>
                                        <?php if ($h['action'] === 'IMPORT_GEDCOM'): ?>
                                            <span style="background:#ecfdf5; color:#065f46; font-weight:700; padding:3px 8px; border-radius:4px; font-size:11px;">
                                                IMPORT_GEDCOM
                                            </span>
                                        <?php else: ?>
                                            <span style="background:#fee2e2; color:#991b1b; font-weight:700; padding:3px 8px; border-radius:4px; font-size:11px;">
                                                ROLLBACK
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:12.5px;"><?= htmlspecialchars($h['summary']) ?></td>
                                    <td>
                                        <?php if ($h['action'] === 'IMPORT_GEDCOM' && !empty($h['payload']['batch_id'])): ?>
                                            <form action="/admin/import/rollback" method="POST" onsubmit="return confirm('<?= htmlspecialchars($h['payload']['batch_id']) ?> импортын кері қайтаруды (откат) растайсыз ба? Қосылған персондар базадан жойылады.');">
                                                <input type="hidden" name="batch_id" value="<?= htmlspecialchars($h['payload']['batch_id']) ?>">
                                                <button type="submit" class="btn-danger">
                                                    ↩️ Откатить импорт
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color:#cbd5e1; font-size:11px;">Орындалды</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color:var(--text-muted); font-size:13.5px; font-style:italic;">
                    Әзірге импорттар тарихы бос. Жоғарыдағы батырма арқылы бірінші импортты орындауға болады.
                </p>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        &copy; 2026 Altybayev.kz &bull; Шежіре Алтыбаевтар &bull; GEDCOM импорттау және тексеру модулі
    </footer>

    <script>
        function switchTab(tabId, btn) {
            document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            const target = document.getElementById(tabId);
            if (target) {
                target.classList.add('active');
            }
            if (btn) {
                btn.classList.add('active');
            }
        }

        function filterPersons() {
            const input = document.getElementById('person-search');
            const filter = input.value.toLowerCase();
            const rows = document.querySelectorAll('#table-persons tbody tr.person-row');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.indexOf(filter) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
