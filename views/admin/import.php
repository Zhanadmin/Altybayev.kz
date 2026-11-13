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
// TASK-008: страница переведена на общий каркас и дизайн-систему (раньше — отдельная тёмная шапка)
$title = 'GEDCOM импорты';
$activeNav = 'admin';
$dsThemeReady = true;

require __DIR__ . '/../layout/master_start.php';
?>

<div class="ad ad-import">
    <div class="ds-page-head">
        <div>
            <h1>GEDCOM импорты</h1>
            <p class="sub">MyHeritage файлын талдау, алдын ала қарау және дерекқорға енгізу / Анализ, предпросмотр и импорт</p>
        </div>
        <div class="actions">
            <a href="/admin" class="btn btn-ghost">
                <svg class="ic" aria-hidden="true"><use href="#i-settings"/></svg><span>Әкімшілік</span>
            </a>
            <a href="/tree" class="btn">
                <svg class="ic" aria-hidden="true"><use href="#i-tree"/></svg><span>Древо / Дерево</span>
            </a>
        </div>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="alert success" role="status">
            <svg class="ic" aria-hidden="true"><use href="#i-check"/></svg>
            <div><?= htmlspecialchars($successMessage) ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert error" role="alert">
            <svg class="ic" aria-hidden="true"><use href="#i-alert"/></svg>
            <div><?= htmlspecialchars($errorMessage) ?></div>
        </div>
    <?php endif; ?>

    <!-- БЛОК 1: ВЫБОР ИЛИ ЗАГРУЗКА ФАЙЛА GEDCOM -->
    <div class="island">
        <div class="island-head ad-head">
            <svg class="ic" aria-hidden="true"><use href="#i-file"/></svg>
            <div>
                <h3>1. GEDCOM файлын таңдау / Выбор файла GEDCOM</h3>
                <p class="small muted">Файл <code class="ad-mono">import/Altubaiev - 2010-05-27.ged</code> (әкеңіздің MyHeritage бағдарламасынан экспортталған шежіресі).</p>
            </div>
        </div>

        <div class="ad-file-row">
            <?php if (!empty($availableFiles)): ?>
                <form action="/admin/import/preview" method="GET" class="toolbar">
                    <label for="select-file" class="small muted">Файл серверде:</label>
                    <select name="file" id="select-file" class="select ad-file-select">
                        <?php foreach ($availableFiles as $file): ?>
                            <option value="<?= htmlspecialchars($file['name']) ?>" <?= (isset($activeFile) && $activeFile === $file['name']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($file['name']) ?> (<?= htmlspecialchars((string)$file['size_formatted']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">
                        <svg class="ic" aria-hidden="true"><use href="#i-search"/></svg><span>Талдау және предпросмотр / Просмотреть</span>
                    </button>
                </form>
            <?php endif; ?>

            <!-- Загрузка нового файла -->
            <form action="/admin/import/preview" method="POST" enctype="multipart/form-data" class="toolbar ad-upload">
                <input type="file" name="gedcom_file" accept=".ged" required class="ad-file-input" aria-label="GEDCOM (.ged)">
                <button type="submit" class="btn">
                    <svg class="ic" aria-hidden="true"><use href="#i-upload"/></svg><span>Жаңа .ged жүктеу / Загрузить</span>
                </button>
            </form>
        </div>
    </div>

    <?php if (!empty($preview)): ?>
        <!-- БЛОК 2: СТАТИСТИКА И KPI ПРЕДПРОСМОТРА -->
        <div class="grid grid-stats">
            <div class="island stat">
                <div class="label">Персондар / Персоны</div>
                <div class="value"><?= (int)($preview['stats']['total_persons'] ?? 0) ?></div>
                <div class="note">Керей → Алтыбай → Шамсиден шежіресі</div>
            </div>
            <div class="island stat">
                <div class="label">Отбасылар / Семьи (FAM)</div>
                <div class="value"><?= (int)($preview['stats']['total_families'] ?? 0) ?></div>
                <div class="note">Некелер мен ата-аналық байланыстар</div>
            </div>
            <div class="island stat">
                <div class="label">Фото өткізілді / Пропущено фото</div>
                <div class="value"><?= (int)($preview['stats']['photos_skipped'] ?? 0) ?></div>
                <div class="note">Әкеңіздің дербес ПК-індегі жергілікті жолдар</div>
            </div>
            <div class="island stat">
                <div class="label"><svg class="ic" aria-hidden="true"><use href="#i-lock"/></svg>Жабық байланыстар / Контакты</div>
                <div class="value"><?= (int)($preview['stats']['contacts_secured'] ?? 0) ?></div>
                <div class="note">Телефон/email тек әкімшіге көрінеді</div>
            </div>
            <div class="island stat">
                <div class="label"><svg class="ic" aria-hidden="true"><use href="#i-shield"/></svg>XSS қауіптері жойылды / Очищено</div>
                <div class="value ad-v-danger"><?= (int)($preview['stats']['xss_threats_neutralized'] ?? 0) ?></div>
                <div class="note">Шамсиден жазбасындағы script/iframe</div>
            </div>
            <div class="island stat">
                <div class="label"><svg class="ic" aria-hidden="true"><use href="#i-alert"/></svg>Ескертулер / Замечания</div>
                <div class="value ad-v-accent"><?= (int)($preview['stats']['warnings_count'] ?? 0) ?></div>
                <div class="note">Датасы жоқ / Белгісіз жыныс</div>
            </div>
        </div>

        <!-- БЛОК 3: НЕЙТРАЛИЗОВАННЫЕ УГРОЗЫ XSS -->
        <?php if (!empty($preview['xss_threats'])): ?>
            <div class="island ad-threats">
                <div class="island-head ad-head">
                    <svg class="ic" aria-hidden="true"><use href="#i-shield"/></svg>
                    <div>
                        <h3>Қауіпсіздік фильтрі: Вредоносный HTML сәтті жойылды / Вредоносный HTML нейтрализован</h3>
                        <p class="small muted">MyHeritage заметкаларындағы күдікті скрипттер мен фреймдер анықталып, дерекқорға жазылмайтындай толық тазартылды:</p>
                    </div>
                </div>
                <div class="ad-threat-list">
                    <?php foreach ($preview['xss_threats'] as $threat): ?>
                        <div class="island-sub ad-threat">
                            <span class="badge hs-del"><?= htmlspecialchars($threat['type']) ?></span>
                            Персона: <b><?= htmlspecialchars($threat['person_name']) ?></b> (<?= htmlspecialchars((string)$threat['person_id']) ?>):
                            <span class="ad-mono"><?= htmlspecialchars($threat['threat']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- БЛОК 4: ГЛАВНАЯ КНОПКА ЗАПУСКА ИМПОРТА -->
        <div class="island ad-exec">
            <div>
                <h3>2. Шежірені дерекқорға енгізу / Импорт в базу данных</h3>
                <p class="muted">
                    Барлық <strong><?= (int)($preview['stats']['total_persons'] ?? 0) ?></strong> адам мен <strong><?= (int)($preview['stats']['total_families'] ?? 0) ?></strong> отбасы бір транзакциямен дерекқорға жазылады.
                    Қайталап жүктеу кезінде дубликаттар жасалмайды (gedcom_id бойынша тексеріледі). Барлық әрекет журналға тіркеледі және оны кез келген уақытта бір батырмамен толық қайтаруға (откат) болады.
                </p>
            </div>

            <form action="/admin/import/execute" method="POST" onsubmit="return confirm('Шежірені базаға жазуды растайсыз ба? (Барлығы <?= (int)($preview['stats']['total_persons'] ?? 0) ?> адам қосылады/жаңартылады)');">
                <input type="hidden" name="file" value="<?= htmlspecialchars($preview['file_name'] ?? '') ?>">
                <button type="submit" class="btn btn-accent ad-exec-btn">
                    <svg class="ic" aria-hidden="true"><use href="#i-upload"/></svg><span>Дерекқорға импорттау / Импортировать</span>
                </button>
            </form>
        </div>

        <!-- БЛОК 5: ДЕТАЛЬНЫЙ ТАБЛИЧНЫЙ ПРЕДПРОСМОТР ДАННЫХ -->
        <div class="island">
            <div class="pp-bar ad-tabs-bar">
                <div class="seg" role="group" aria-label="Предпросмотр">
                    <button type="button" class="tab-btn active" onclick="switchTab('tab-persons', this)">
                        Персондар (<?= count($preview['persons']) ?>)
                    </button>
                    <button type="button" class="tab-btn" onclick="switchTab('tab-families', this)">
                        Отбасылар / Семьи (<?= count($preview['families']) ?>)
                    </button>
                    <button type="button" class="tab-btn" onclick="switchTab('tab-warnings', this)">
                        Сомнительные записи (<?= count($preview['warnings']) ?>)
                    </button>
                </div>

                <div class="search ad-search">
                    <svg class="ic" aria-hidden="true"><use href="#i-search"/></svg>
                    <input type="text" id="person-search" class="input" placeholder="Аты-жөні немесе ID бойынша іздеу..." onkeyup="filterPersons()" aria-label="Іздеу">
                </div>
            </div>

            <!-- ВКЛАДКА 1: ПЕРСОНЫ -->
            <div id="tab-persons" class="tab-pane active">
                <div class="table-wrap">
                    <table class="table ad-table ad-wrap" id="table-persons">
                        <thead>
                            <tr>
                                <th class="ad-w-gid col-opt-lg">ID</th>
                                <th>Аты-жөні / ФИО</th>
                                <th class="ad-w-gender col-opt-md">Жынысы</th>
                                <th class="ad-w-birth col-opt-md">Туған жылы / Жері</th>
                                <th class="ad-w-parent col-opt-xl">Әкесі</th>
                                <th class="ad-w-parent col-opt-xl">Анасы</th>
                                <th class="ad-w-contacts col-opt-lg">Жабық байланыстар (тек әкімші)</th>
                                <th class="ad-w-notes col-opt-lg">Өмірбаяны / Заметки</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview['persons'] as $id => $p): ?>
                                <tr class="person-row">
                                    <?php
                                    $fatherName = ($p['father_gedcom_id'] && isset($preview['persons'][$p['father_gedcom_id']])) ? (string)$preview['persons'][$p['father_gedcom_id']]['full_name'] : '';
                                    $motherName = ($p['mother_gedcom_id'] && isset($preview['persons'][$p['mother_gedcom_id']])) ? (string)$preview['persons'][$p['mother_gedcom_id']]['full_name'] : '';
                                    ?>
                                    <td class="col-opt-lg"><code class="ad-mono"><?= htmlspecialchars((string)$id) ?></code></td>
                                    <td class="col-name">
                                        <strong><?= htmlspecialchars($p['full_name']) ?></strong>
                                        <?php if (!empty($p['maiden_name'])): ?>
                                            <div class="small muted">(қыз күнінде: <?= htmlspecialchars($p['maiden_name']) ?>)</div>
                                        <?php endif; ?>
                                        <?php if ($fatherName !== '' || $motherName !== ''): ?>
                                            <!-- Скрытые на узком экране колонки «Әкесі / Анасы» -->
                                            <div class="small muted ad-nm-parents">
                                                <?php if ($fatherName !== ''): ?>Әкесі: <?= htmlspecialchars($fatherName) ?><?php endif; ?>
                                                <?php if ($fatherName !== '' && $motherName !== ''): ?> · <?php endif; ?>
                                                <?php if ($motherName !== ''): ?>Анасы: <?= htmlspecialchars($motherName) ?><?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-opt-md">
                                        <?php if ($p['gender'] === 'M'): ?>
                                            <span class="badge">Ер / М</span>
                                        <?php elseif ($p['gender'] === 'F'): ?>
                                            <span class="badge">Әйел / Ж</span>
                                        <?php else: ?>
                                            <span class="small muted">Белгісіз</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-opt-md">
                                        <div><?= htmlspecialchars((string)($p['birth_year'] ?: ($p['birth_date'] ?: '—'))) ?></div>
                                        <?php if (!empty($p['birth_place'])): ?>
                                            <div class="small muted"><?= htmlspecialchars($p['birth_place']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-opt-xl">
                                        <?php if ($p['father_gedcom_id'] && isset($preview['persons'][$p['father_gedcom_id']])): ?>
                                            <?= htmlspecialchars($preview['persons'][$p['father_gedcom_id']]['full_name']) ?>
                                        <?php else: ?>
                                            <span class="muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-opt-xl">
                                        <?php if ($p['mother_gedcom_id'] && isset($preview['persons'][$p['mother_gedcom_id']])): ?>
                                            <?= htmlspecialchars($preview['persons'][$p['mother_gedcom_id']]['full_name']) ?>
                                        <?php else: ?>
                                            <span class="muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-opt-lg">
                                        <?php
                                        $hasPhones = !empty($p['private_contacts']['phones']);
                                        $hasEmails = !empty($p['private_contacts']['emails']);
                                        $hasAddrs = !empty($p['private_contacts']['addresses']);
                                        ?>
                                        <?php if ($hasPhones || $hasEmails || $hasAddrs): ?>
                                            <div class="ad-secure-list">
                                                <?php foreach ($p['private_contacts']['phones'] as $ph): ?>
                                                    <span class="badge ad-secure">Тел.: <?= htmlspecialchars($ph) ?></span>
                                                <?php endforeach; ?>
                                                <?php foreach ($p['private_contacts']['emails'] as $em): ?>
                                                    <span class="badge ad-secure">Email: <?= htmlspecialchars($em) ?></span>
                                                <?php endforeach; ?>
                                                <?php foreach ($p['private_contacts']['addresses'] as $ad): ?>
                                                    <span class="badge ad-secure">Мекенжай: <?= htmlspecialchars($ad) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-opt-lg">
                                        <?php if (!empty($p['biography'])): ?>
                                            <div class="clip small muted" title="<?= htmlspecialchars(strip_tags($p['biography'])) ?>">
                                                <?= htmlspecialchars(mb_substr(strip_tags($p['biography']), 0, 75)) ?>...
                                            </div>
                                        <?php else: ?>
                                            <span class="muted">—</span>
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
                <div class="table-wrap">
                    <table class="table ad-table ad-wrap">
                        <thead>
                            <tr>
                                <th class="ad-w-gid col-opt-md">FAM ID</th>
                                <th>Отағасы / Муж</th>
                                <th>Жұбайы / Жена</th>
                                <th class="col-opt-lg">Балалары / Дети</th>
                                <th class="ad-w-marr col-opt-md">Неке күні / Жері</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview['families'] as $fId => $fam): ?>
                                <tr>
                                    <td class="col-opt-md"><code class="ad-mono"><?= htmlspecialchars((string)$fId) ?></code></td>
                                    <td>
                                        <?php if ($fam['husband_gedcom_id'] && isset($preview['persons'][$fam['husband_gedcom_id']])): ?>
                                            <b><?= htmlspecialchars($preview['persons'][$fam['husband_gedcom_id']]['full_name']) ?></b>
                                        <?php else: ?>
                                            <span class="muted">Белгісіз</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($fam['wife_gedcom_id'] && isset($preview['persons'][$fam['wife_gedcom_id']])): ?>
                                            <b><?= htmlspecialchars($preview['persons'][$fam['wife_gedcom_id']]['full_name']) ?></b>
                                        <?php else: ?>
                                            <span class="muted">Белгісіз</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-opt-lg">
                                        <b class="ad-accent-text"><?= count($fam['children']) ?> бала:</b>
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
                                    <td class="col-opt-md">
                                        <?= htmlspecialchars($fam['marriage_date'] ?: '—') ?>
                                        <?php if ($fam['marriage_place']): ?>
                                            <span class="small muted">(<?= htmlspecialchars($fam['marriage_place']) ?>)</span>
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
                <div class="table-wrap">
                    <table class="table ad-table ad-wrap">
                        <thead>
                            <tr>
                                <th class="ad-w-wtype">Түрі / Тип</th>
                                <th class="ad-w-gid col-opt-md">ID</th>
                                <th class="ad-w-wname">Аты-жөні / ФИО</th>
                                <th>Ескерту сипаттамасы / Описание замечания</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview['warnings'] as $w): ?>
                                <tr>
                                    <td class="clip"><span class="badge badge-accent"><?= htmlspecialchars($w['type']) ?></span></td>
                                    <td class="col-opt-md"><code class="ad-mono"><?= htmlspecialchars((string)$w['person_id']) ?></code></td>
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

    <!-- БЛОК 6: ЖУРНАЛ АУДИТА И КНОПКА ОТКАТА -->
    <div class="island">
        <div class="island-head ad-head">
            <svg class="ic" aria-hidden="true"><use href="#i-history"/></svg>
            <div>
                <h3>3. Импорттар тарихы және откат (Аудит журналы)</h3>
                <p class="small muted">Әрбір импорт пен қайтару әрекеті audit_logs кестесінде сақталады. Қажет болса, кез келген импортты базадан толық тазартуға болады.</p>
            </div>
        </div>

        <?php if (!empty($history)): ?>
            <div class="table-wrap">
                <table class="table ad-table ad-wrap">
                    <thead>
                        <tr>
                            <th class="ad-w-htime col-opt-md">Уақыты / Дата</th>
                            <th class="ad-w-hauthor col-opt-lg">Әкімші / Автор</th>
                            <th class="ad-w-hact">Әрекет / Действие</th>
                            <th>Сипаттамасы / Описание</th>
                            <th class="ad-w-hroll actions">Басқару / Управление</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td class="clip col-opt-md"><code class="ad-mono"><?= htmlspecialchars((string)$h['created_at']) ?></code></td>
                                <td class="col-opt-lg">
                                    <strong><?= htmlspecialchars($h['user_name'] ?? 'Admin') ?></strong>
                                    <div class="small muted clip"><?= htmlspecialchars($h['user_email'] ?? '') ?></div>
                                </td>
                                <td class="clip">
                                    <?php if ($h['action'] === 'IMPORT_GEDCOM'): ?>
                                        <span class="badge badge-success">IMPORT_GEDCOM</span>
                                    <?php else: ?>
                                        <span class="badge hs-del">ROLLBACK</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= htmlspecialchars($h['summary']) ?></td>
                                <td class="actions">
                                    <?php if ($h['action'] === 'IMPORT_GEDCOM' && !empty($h['payload']['batch_id'])): ?>
                                        <form action="/admin/import/rollback" method="POST" onsubmit="return confirm('<?= htmlspecialchars($h['payload']['batch_id']) ?> импортын кері қайтаруды (откат) растайсыз ба? Қосылған персондар базадан жойылады.');">
                                            <input type="hidden" name="batch_id" value="<?= htmlspecialchars($h['payload']['batch_id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" aria-label="Откатить импорт">
                                                <svg class="ic" aria-hidden="true"><use href="#i-history"/></svg><span>Откатить импорт</span>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="small muted">Орындалды</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted"><em>Әзірге импорттар тарихы бос. Жоғарыдағы батырма арқылы бірінші импортты орындауға болады.</em></p>
        <?php endif; ?>
    </div>
</div>

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

<?php
require __DIR__ . '/../layout/master_end.php';
?>
