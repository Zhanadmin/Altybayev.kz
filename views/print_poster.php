<?php
declare(strict_types=1);
/**
 * @var array|null $user
 * @var array $patriarchs
 * @var int $rootId
 * @var int $gens
 * @var string $lang
 * @var array $posterData
 */

$isKk = ($lang === 'kk');
$root = $posterData['root'] ?? [];
$levels = $posterData['levels'] ?? [];
$totalPersons = $posterData['total_persons'] ?? 0;
$clan = $posterData['clan'] ?? 'Керей';
$rootName = $posterData['root_name'] ?? 'Алтыбай';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($isKk ? "Шежіре-плакат: «{$rootName}» ұрпақтары" : "Плакат шежире: потомки «{$rootName}»") ?> — Altybayev.kz</title>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, serif;
            background-color: #0b131f;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ПАНЕЛЬ УПРАВЛЕНИЯ ПЛАКАТОМ (СКРЫВАЕТСЯ ПРИ ПЕЧАТИ) */
        .poster-toolbar {
            background-color: #0d1b2a;
            border-bottom: 2px solid #d4af37;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
        }
        .toolbar-left, .toolbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .toolbar-title {
            font-size: 16px;
            font-weight: 700;
            color: #fef08a;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-tb {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }
        .btn-tb-primary {
            background: linear-gradient(135deg, #d4af37, #b45309);
            color: #ffffff;
            box-shadow: 0 2px 10px rgba(212, 175, 55, 0.35);
        }
        .btn-tb-primary:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }
        .btn-tb-secondary {
            background-color: rgba(255, 255, 255, 0.08);
            color: #e2e8f0;
            border-color: rgba(255, 255, 255, 0.2);
        }
        .btn-tb-secondary:hover {
            background-color: rgba(255, 255, 255, 0.15);
            color: #ffffff;
        }
        .tb-select {
            background-color: #1e293b;
            color: #f8fafc;
            border: 1px solid #d4af37;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 13.5px;
            cursor: pointer;
        }

        /* ОБЛАСТЬ ПЛАКАТА */
        .poster-viewport {
            flex: 1;
            padding: 30px 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            overflow: auto;
        }
        .poster-sheet {
            background: radial-gradient(circle at center top, #132238 0%, #0a1320 100%);
            width: 100%;
            max-width: 1400px;
            min-height: 900px;
            border: 4px solid #d4af37;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.7);
            position: relative;
        }
        /* Внутренняя тонкая орнаментальная рамка */
        .poster-inner-border {
            border: 1.5px dashed rgba(212, 175, 55, 0.4);
            border-radius: 8px;
            padding: 30px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        /* Заголовок плаката */
        .poster-header {
            text-align: center;
            margin-bottom: 36px;
            position: relative;
        }
        .poster-ornament {
            color: #d4af37;
            font-size: 24px;
            margin-bottom: 8px;
            letter-spacing: 6px;
        }
        .poster-main-title {
            font-family: Georgia, "Times New Roman", serif;
            font-size: 32px;
            font-weight: 700;
            color: #fef08a;
            letter-spacing: 2px;
            text-shadow: 0 2px 10px rgba(212, 175, 55, 0.4);
            margin-bottom: 8px;
        }
        .poster-subtitle {
            font-size: 16px;
            color: #cbd5e1;
            margin-bottom: 12px;
        }
        .poster-meta {
            display: inline-flex;
            gap: 20px;
            background-color: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(212, 175, 55, 0.3);
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 13px;
            color: #94a3b8;
        }

        /* Сетка поколений плаката */
        .poster-generations {
            display: flex;
            flex-direction: column;
            gap: 28px;
            flex: 1;
        }
        .gen-row {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .gen-label-col {
            width: 110px;
            flex-shrink: 0;
            font-size: 13px;
            font-weight: 700;
            color: #d4af37;
            text-align: right;
            border-right: 2px solid rgba(212, 175, 55, 0.5);
            padding-right: 14px;
        }
        .gen-cards-col {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            flex: 1;
            align-items: center;
        }

        /* Карточка персоны на плакате */
        .p-card {
            background: linear-gradient(135deg, rgba(30, 48, 74, 0.85), rgba(15, 27, 44, 0.95));
            border: 1.5px solid #38bdf8;
            border-radius: 8px;
            padding: 10px 14px;
            min-width: 170px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            position: relative;
        }
        .p-card.female {
            border-color: #f472b6;
        }
        .p-card.deceased {
            border-color: #d4af37;
        }
        .p-card-name {
            font-size: 13.5px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 2px;
        }
        .p-card-years {
            font-size: 11px;
            color: #94a3b8;
            margin-bottom: 2px;
        }
        .p-card-spouse {
            font-size: 10.5px;
            color: #fbcfe8;
            font-style: italic;
            border-top: 1px dashed rgba(255,255,255,0.15);
            margin-top: 4px;
            padding-top: 3px;
        }

        /* Подвал плаката */
        .poster-footer {
            margin-top: 36px;
            border-top: 1px solid rgba(212, 175, 55, 0.3);
            padding-top: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #64748b;
        }

        /* РИСОВАННОЕ ДРЕВО «ШЕЖІРЕ-АҒАШ» (Rodoslov) — вид по умолчанию */
        .rodoslov-sheet {
            width: 100%;
            max-width: 1600px;
            background: #fbf7ee;
            border-radius: 6px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6);
            overflow: hidden;
        }
        .rodoslov-sheet svg {
            display: block;
            width: 100%;
            height: auto;
        }
        .rodoslov-msg {
            padding: 60px 20px;
            text-align: center;
            color: #5a4128;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 18px;
        }
        body.view-rodoslov .poster-sheet { display: none; }
        body.view-list .rodoslov-sheet { display: none; }

        /* СТИЛИ ПЕЧАТИ (PRINT ONLY) */
        @media print {
            .rodoslov-sheet {
                max-width: 100% !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            @page {
                size: A3 landscape;
                margin: 6mm;
            }
            body {
                background: #ffffff !important;
                color: #000000 !important;
            }
            .poster-toolbar {
                display: none !important;
            }
            .poster-viewport {
                padding: 0 !important;
            }
            .poster-sheet {
                background: #ffffff !important;
                border: 2px solid #b45309 !important;
                box-shadow: none !important;
                max-width: 100% !important;
                color: #000000 !important;
                padding: 15px !important;
            }
            .poster-inner-border {
                border-color: #b45309 !important;
                padding: 10px !important;
            }
            .poster-main-title {
                color: #78350f !important;
                text-shadow: none !important;
                font-size: 26px !important;
            }
            .poster-subtitle {
                color: #1e293b !important;
            }
            .poster-meta {
                background: #f8fafc !important;
                border-color: #cbd5e1 !important;
                color: #334155 !important;
            }
            .gen-label-col {
                color: #78350f !important;
                border-color: #78350f !important;
            }
            .p-card {
                background: #ffffff !important;
                border: 1.5px solid #0284c7 !important;
                box-shadow: none !important;
                color: #000000 !important;
            }
            .p-card.female {
                border-color: #db2777 !important;
            }
            .p-card.deceased {
                border-color: #b45309 !important;
            }
            .p-card-name {
                color: #0f172a !important;
            }
            .p-card-years {
                color: #475569 !important;
            }
            .p-card-spouse {
                color: #9d174d !important;
                border-color: #e2e8f0 !important;
            }
            .poster-footer {
                color: #64748b !important;
                border-color: #cbd5e1 !important;
            }
        }
    </style>
    <noscript><style>body.view-rodoslov .poster-sheet { display: block; } .rodoslov-sheet { display: none; }</style></noscript>
</head>
<body class="view-rodoslov">

    <!-- ПАНЕЛЬ УПРАВЛЕНИЯ -->
    <header class="poster-toolbar">
        <div class="toolbar-left">
            <a href="/tree" class="btn-tb btn-tb-secondary">
                ← <?= $isKk ? 'Древоға қайту' : 'Назад к древу' ?>
            </a>
            <div class="toolbar-title">
                <span><?= $isKk ? 'Шежірені басып шығару (Плакат)' : 'Печать шежире (Подарочный плакат)' ?></span>
            </div>
        </div>

        <div class="toolbar-right">
            <!-- Выбор родоначальника -->
            <label for="patriarchSelect" style="font-size:13px; color:#cbd5e1;"><?= $isKk ? 'Түп атасы:' : 'Родоначальник:' ?></label>
            <select id="patriarchSelect" class="tb-select" onchange="applyPosterFilters()">
                <?php foreach ($patriarchs as $pat): ?>
                    <option value="<?= $pat['id'] ?>" <?= ($pat['id'] === $rootId) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($pat['name']) ?> (<?= $pat['descendants_count'] ?> <?= $isKk ? 'ұрпақ' : 'потомков' ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Число колен -->
            <label for="gensSelect" style="font-size:13px; color:#cbd5e1;"><?= $isKk ? 'Буын:' : 'Колен:' ?></label>
            <select id="gensSelect" class="tb-select" onchange="applyPosterFilters()">
                <option value="3" <?= ($gens === 3) ? 'selected' : '' ?>>3 буын (Ата, бала, немере)</option>
                <option value="5" <?= ($gens === 5) ? 'selected' : '' ?>>5 буын</option>
                <option value="7" <?= ($gens === 7) ? 'selected' : '' ?>>7 буын (Жеті ата)</option>
                <option value="9" <?= ($gens === 9) ? 'selected' : '' ?>>9 буын</option>
                <option value="15" <?= ($gens === 15) ? 'selected' : '' ?>>Барлық ұрпақтар / Все</option>
            </select>

            <!-- Вид плаката: рисованное древо или список карточек -->
            <button type="button" id="btnPosterView" class="btn-tb btn-tb-secondary" onclick="togglePosterView()"
                    data-label-tree="<?= $isKk ? 'Шежіре-ағаш' : 'Древо' ?>"
                    data-label-list="<?= $isKk ? 'Тізім' : 'Список' ?>">
                <?= $isKk ? 'Тізім' : 'Список' ?>
            </button>

            <!-- Тіл ауыстырғыш -->
            <button type="button" class="btn-tb btn-tb-secondary" onclick="togglePosterLanguage()">
                <?= $isKk ? 'РУС' : 'ҚАЗ' ?>
            </button>

            <!-- Кнопка скачивания SVG: в виде «древо» — рисунок со страницы, в виде «список» — старый серверный SVG -->
            <a id="btnPosterSvg" href="/tree/print/svg?root=<?= $rootId ?>&amp;gens=<?= $gens ?>&amp;lang=<?= $lang ?>" class="btn-tb btn-tb-secondary" onclick="return downloadPosterSvg(event)" title="<?= $isKk ? 'Векторлық SVG форматында жүктеп алу (кез келген өлшемге созылады)' : 'Скачать в векторном формате SVG (масштабируется без потерь)' ?>">
                <?= $isKk ? 'SVG жүктеу (Вектор)' : 'Скачать SVG (Вектор)' ?>
            </a>

            <!-- Кнопка печати -->
            <button type="button" class="btn-tb btn-tb-primary" onclick="window.print()">
                <?= $isKk ? 'Басып шығару / PDF' : 'Печать / В PDF' ?>
            </button>
        </div>
    </header>

    <!-- ПЛАКАТ -->
    <main class="poster-viewport">
        <div class="rodoslov-sheet" id="rodoslovSheet" aria-live="polite">
            <div class="rodoslov-msg"><?= $isKk ? 'Шежіре-ағаш салынуда…' : 'Строится родословное древо…' ?></div>
        </div>
        <div class="poster-sheet" id="posterSheet">
            <div class="poster-inner-border">
                
                <div class="poster-header">
                    <div class="poster-ornament">◆ ◆ ◆</div>
                    <h1 class="poster-main-title"><?= $isKk ? 'АЛТЫБАЕВТАР ӘУЛЕТІНІҢ ШЕЖІРЕСІ' : 'ШЕЖИРЕ РОДА АЛТЫБАЕВТАР' ?></h1>
                    <div class="poster-subtitle">
                        <?= htmlspecialchars($posterData['title_kk']) ?> &bull; <?= $isKk ? 'Руы:' : 'Род:' ?> <strong><?= htmlspecialchars($clan) ?></strong>
                    </div>
                    <div class="poster-meta">
                        <span><?= $isKk ? 'Буын саны:' : 'Колен:' ?> <strong><?= count($levels) ?></strong></span>
                        <span><?= $isKk ? 'Тұлғалар:' : 'Персон:' ?> <strong><?= $totalPersons ?></strong></span>
                        <span>altybayev.kz</span>
                    </div>
                </div>

                <div class="poster-generations">
                    <?php foreach ($levels as $genNum => $personsInGen): ?>
                        <div class="gen-row">
                            <div class="gen-label-col">
                                <?= $isKk ? "{$genNum}-буын" : "{$genNum}-е колено" ?>
                            </div>
                            <div class="gen-cards-col">
                                <?php foreach ($personsInGen as $p): 
                                    $isMale = ($p['gender'] === 'M');
                                    $cls = 'p-card ' . ($isMale ? 'male' : 'female') . ($p['is_deceased'] ? ' deceased' : '');
                                ?>
                                    <div class="<?= $cls ?>">
                                        <div class="p-card-name">
                                            <?= htmlspecialchars((string)$p['first_name'] . ' ' . (string)($p['last_name'] ?? '')) ?>
                                        </div>
                                        <div class="p-card-years">
                                            <?php 
                                            $yStr = (string)($p['birth_year'] ?: '');
                                            if ($p['is_deceased'] && $p['death_year']) {
                                                $yStr .= ' – ' . $p['death_year'];
                                            }
                                            echo htmlspecialchars($yStr ?: '—');
                                            ?>
                                        </div>
                                        <?php if (!empty($p['spouses'])): ?>
                                            <div class="p-card-spouse">
                                                <?= $isKk ? 'жұбайы:' : 'супруг(а):' ?> <?= htmlspecialchars($p['spouses'][0]['name']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="poster-footer">
                    <div>Қасиетті аталар сөзі мен асыл ұрпақтар сабақтастығы &bull; Шежіре Алтыбаевтар</div>
                    <div>altybayev.kz &bull; <?= date('Y') ?> жыл</div>
                </div>

            </div>
        </div>
    </main>

    <script src="/assets/js/rodoslov-generator.js"></script>
    <script>
        const POSTER_LANG = '<?= $isKk ? 'kk' : 'ru' ?>';
        const POSTER_DATA = <?= json_encode($rodoslovData ?? ['persons' => [], 'marriages' => []], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{"persons":[],"marriages":[]}' ?>;
        const POSTER_OPTS = <?= json_encode([
            'title'    => $isKk ? 'АЛТЫБАЕВТАР ӘУЛЕТІНІҢ ШЕЖІРЕСІ' : 'ШЕЖИРЕ РОДА АЛТЫБАЕВТАР',
            'subtitle' => ($isKk ? "«{$rootName}» ұрпақтары · Руы: " : "Потомки «{$rootName}» · Род: ") . $clan,
            'mainClan' => mb_strtoupper((string)$clan, 'UTF-8'),
            'footnote' => 'altybayev.kz · ' . date('d.m.Y'),
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;
        let posterSvg = '';
        let posterOrientation = 'landscape';
        const pageStyle = document.createElement('style');
        document.head.appendChild(pageStyle);

        // @page нельзя ограничить классом — переписываем под текущий вид
        function updatePageStyle() {
            const o = document.body.classList.contains('view-list') ? 'landscape' : posterOrientation;
            pageStyle.textContent = '@media print { @page { size: A3 ' + o + '; margin: 6mm; } }';
        }

        function renderRodoslovPoster() {
            const sheet = document.getElementById('rodoslovSheet');
            const isRu = POSTER_LANG === 'ru';
            if (!window.RodoslovGenerator) {
                showPosterView('list');
                return;
            }
            const opts = Object.assign({}, POSTER_OPTS, {
                focalId: POSTER_DATA.focal_id || 0,
                emptyText: isRu ? 'Данных нет — импортируйте GEDCOM' : 'Деректер жоқ — GEDCOM импорттаңыз',
                labels: isRu ? { father: 'РОД', mother: 'ПО МАТЕРИ', inlaw: 'РОД ЖЕНЫ', other: 'СВАТЫ' }
                             : { father: 'АТА ЖҰРТ' }
            });
            if (isRu) {
                opts.maidenPrefix = 'урожд.';
                opts.marriagePrefix = 'брак ·';
                opts.tamgaPlaceholder = 'тамга';
                opts.unknownClan = 'род не указан';
                opts.legendText = {
                    son: 'сын',
                    daughter: 'дочь / сноха',
                    deceased: 'ушедшие (золотая лента, золотые листья)',
                    marriage: 'брак',
                    dates: '*  дата рождения     †  дата смерти'
                };
            }
            try {
                const res = RodoslovGenerator.build(POSTER_DATA, opts);
                sheet.innerHTML = res.svg;
                posterSvg = res.svg;
                // Ориентация листа под пропорции рисунка
                posterOrientation = res.width >= res.height ? 'landscape' : 'portrait';
                updatePageStyle();
            } catch (err) {
                console.error('RodoslovGenerator:', err);
                posterSvg = '';
                sheet.innerHTML = '';
                const msg = document.createElement('div');
                msg.className = 'rodoslov-msg';
                msg.textContent = isRu ? 'Не удалось построить древо. Нажмите «Список» вверху.' : 'Шежіре-ағашты салу мүмкін болмады. Жоғарыдағы «Тізім» батырмасын басыңыз.';
                sheet.appendChild(msg);
            }
        }

        function showPosterView(view) {
            document.body.classList.toggle('view-rodoslov', view !== 'list');
            document.body.classList.toggle('view-list', view === 'list');
            const btn = document.getElementById('btnPosterView');
            btn.textContent = view === 'list' ? btn.dataset.labelTree : btn.dataset.labelList;
            updatePageStyle();
        }

        function togglePosterView() {
            showPosterView(document.body.classList.contains('view-list') ? 'tree' : 'list');
        }

        function downloadPosterSvg(e) {
            // В виде «список» или без рисунка — обычная ссылка на серверный SVG
            if (document.body.classList.contains('view-list') || !posterSvg) return true;
            e.preventDefault();
            const xmlHead = '<' + '?xml version="1.0" encoding="UTF-8"?' + '>\n';
            const blob = new Blob([xmlHead + posterSvg], { type: 'image/svg+xml;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'shezhire_altybayev_<?= date('Ymd') ?>.svg';
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
            return false;
        }

        renderRodoslovPoster();

        function applyPosterFilters() {
            const root = document.getElementById('patriarchSelect').value;
            const gens = document.getElementById('gensSelect').value;
            const lang = '<?= $lang ?>';
            window.location.href = `/tree/print?root=${root}&gens=${gens}&lang=${lang}`;
        }

        function togglePosterLanguage() {
            const root = document.getElementById('patriarchSelect').value;
            const gens = document.getElementById('gensSelect').value;
            const nextLang = ('<?= $lang ?>' === 'kk') ? 'ru' : 'kk';
            window.location.href = `/tree/print?root=${root}&gens=${gens}&lang=${nextLang}`;
        }
    </script>
</body>
</html>
