<?php
declare(strict_types=1);

use App\Core\I18n;

// ALT-092: отдельная страница «Жеті ата» (/zheti-ata); табличка и выгрузка — общий /assets/js/zheti-ata.js
$lang = I18n::lang();
$title = 'Жеті ата';
$activeNav = 'zheti';
$dsThemeReady = true;

$persons = $persons ?? [];
$selectedId = (int)($selectedId ?? 0);
$isRu = ($lang === 'ru' || $lang === 'en' || $lang === 'es');

require __DIR__ . '/layout/master_start.php';
?>

<div class="dt za-page">
    <div class="ds-page-head">
        <div>
            <h1>Жеті ата</h1>
            <p class="sub" id="zaSubtitle"></p>
        </div>
    </div>

    <style>
        /* ALT-120: страница и карточка в утверждённом стиле ALT-117 (тёмный камень, золото, кремовые плашки). */
        .za-layout { display:flex; flex-direction:column; gap:18px; }
        .za-side { padding:18px; position:relative; }
        .za-side .form-select { width:100%; min-height:40px; }
        .za-side .za-run { margin-top:10px; min-height:40px; width:100%; }
        .za-info { margin-top:14px; }
        .za-info-name { font-weight:700; font-size:16px; margin-bottom:4px; }
        .za-info-row { font-size:14px; opacity:.9; margin-top:2px; }
        .za-actions { display:flex; flex-direction:column; gap:8px; margin-top:14px; }
        .za-actions .btn { padding:9px 14px; font-size:13px; width:100%; }
        .za-main { min-width:0; }
        /* ALT-100: поиск по вводу */
        .za-combo { position:relative; }
        .za-combo input { width:100%; min-height:40px; box-sizing:border-box; }
        .za-list { position:absolute; left:0; right:0; top:100%; z-index:50; max-height:260px; overflow:auto; margin:2px 0 0; padding:4px; list-style:none;
            border-radius:10px; }
        .za-list li { padding:7px 10px; border-radius:6px; cursor:pointer; font-size:14px; }
        .za-list li.za-empty { cursor:default; opacity:.7; }
        @media (min-width: 900px) {
            .za-layout { flex-direction:row; align-items:flex-start; }
            .za-side { flex:0 0 290px; width:290px; position:sticky; top:16px; }
            .za-main { flex:1 1 auto; display:flex; justify-content:center; }
            .za-main #zaResult { width:100%; }
            /* ALT-120: карточка целиком в окне без прокрутки (экран; экспорт не затронут) */
            .dt.za-page { gap:8px; }
            .dt.za-page .ds-page-head h1 { font-size:24px; }
            .dt.za-page .ds-page-head .sub { margin-top:0; font-size:14px; }
            .za-main #zaResult svg#zhetiAtaPlaque { width:auto !important; max-width:100% !important; height:calc(100vh - 235px); min-height:520px; }
        }
        @media (max-width: 899px) {
            .za-main #zaResult svg#zhetiAtaPlaque { width:100% !important; height:auto !important; min-height:0; }
        }
    </style>

    <div class="za-layout">
        <div class="island za-side">
            <label id="zaPersonLabel" for="zaPerson" style="display:block; margin-bottom:4px;"></label>
            <div class="za-combo">
                <input type="text" id="zaSearch" class="form-input" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="zaList" placeholder="<?= $isRu ? 'Начните вводить имя…' : 'Атын жазыңыз…' ?>">
                <ul class="za-list" id="zaList" role="listbox" hidden></ul>
            </div>
            <select id="zaPerson" class="form-select" style="display:none;" tabindex="-1" aria-hidden="true">
                <?php foreach ($persons as $p):
                    $label = trim((string)$p['first_name'] . ' ' . (string)($p['last_name'] ?? ''))
                        . (!empty($p['maiden_name']) ? ' (' . $p['maiden_name'] . ')' : '')
                        . ' (' . (!empty($p['birth_year']) ? $p['birth_year'] : '—') . ')'; ?>
                    <option value="<?= (int)$p['id'] ?>"<?= (int)$p['id'] === $selectedId ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-primary za-run" id="zaRunBtn"></button>
            <div class="za-info" id="zaInfo"></div>
            <div class="za-actions" id="zaActions"></div>
        </div>
        <div class="za-main">
            <div id="zaResult" style="display:none;"></div>
        </div>
    </div>
</div>

<script src="/assets/js/clan-emblem.js?v=<?= (int)@filemtime(__DIR__ . '/../public/assets/js/clan-emblem.js') ?>"></script>
<script src="/assets/js/zheti-ata.js?v=<?= (int)@filemtime(__DIR__ . '/../public/assets/js/zheti-ata.js') ?>"></script>
<script>
(function () {
    const lang = <?= json_encode($lang) ?>;
    const L = ZhetiAta.labels(lang);
    const sel = document.getElementById('zaPerson');
    const box = document.getElementById('zaResult');
    document.getElementById('zaSubtitle').textContent = L.subtitle;
    document.getElementById('zaPersonLabel').textContent = L.person;
    const btn = document.getElementById('zaRunBtn');
    btn.textContent = '🔍 ' + L.run;

    // клик по имени в цепочке — таблица этого человека
    function show(id) {
        if (!id) return;
        sel.value = String(id);
        syncInput();
        history.replaceState(null, '', '/zheti-ata?id=' + encodeURIComponent(id));
        ZhetiAta.render(box, id, lang, show, {
            actionsBox: document.getElementById('zaActions'),
            infoBox: document.getElementById('zaInfo')
        });
    }
    // ALT-100: поиск по вводу (без учёта регистра, казахские буквы)
    const inp = document.getElementById('zaSearch');
    const list = document.getElementById('zaList');
    inp.setAttribute('aria-labelledby', 'zaPersonLabel');
    document.getElementById('zaPersonLabel').setAttribute('for', 'zaSearch');
    const items = Array.from(sel.options).map(o => ({ id: o.value, label: o.textContent, key: fold(o.textContent) }));
    function fold(s) { return String(s).toLocaleLowerCase('kk').replace(/ё/g, 'е'); }
    let cur = [], act = -1;
    function openList(v) {
        const match = (it, s) => {
            const q = fold(s).trim();
            const words = q ? q.split(/\s+/) : [];
            return words.every(w => it.key.includes(w));
        };
        // ALT-114: если по введённому пусто — пробуем исправить раскладку («;fyljc» → «Жандос»)
        const found = window.LayoutFix ? window.LayoutFix.pickCandidate(v, items, match) : { results: items.filter(it => match(it, v)), hint: '' };
        cur = found.results.slice(0, 50);
        act = cur.length ? 0 : -1;
        list.innerHTML = (found.hint ? '<li class="za-empty lf-hint"></li>' : '') + (cur.length
            ? cur.map((it, i) => '<li role="option" data-i="' + i + '"' + (i === 0 ? ' class="active"' : '') + '></li>').join('')
            : '<li class="za-empty">—</li>');
        const off = found.hint ? 1 : 0;
        if (found.hint) {
            const hl = list.children[0];
            const parts = window.LayoutFix.hintText('\u0000', document.documentElement.lang).split('\u0000');
            hl.appendChild(document.createTextNode(parts[0]));
            const a = document.createElement('a');
            a.href = '#'; a.textContent = found.hint;
            a.addEventListener('mousedown', (e) => { e.preventDefault(); e.stopPropagation(); inp.value = found.hint; openList(found.hint); });
            hl.appendChild(a);
            hl.appendChild(document.createTextNode(parts[1] || ''));
        }
        cur.forEach((it, i) => { list.children[i + off].textContent = it.label; });
        list.hidden = false; inp.setAttribute('aria-expanded', 'true');
    }
    function closeList() { list.hidden = true; inp.setAttribute('aria-expanded', 'false'); }
    function pick(i) {
        const it = cur[i]; if (!it) return;
        inp.value = it.label; closeList(); show(it.id);
    }
    function mark(n) {
        if (!cur.length) return;
        act = (n + cur.length) % cur.length;
        const opts = Array.from(list.querySelectorAll('li[data-i]'));
        opts.forEach((li, i) => li.classList.toggle('active', i === act));
        if (opts[act]) opts[act].scrollIntoView({ block: 'nearest' });
    }
    inp.addEventListener('input', () => openList(inp.value));
    inp.addEventListener('focus', () => { inp.select(); openList(''); });
    inp.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); if (list.hidden) openList(inp.value); else mark(act + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); mark(act - 1); }
        else if (e.key === 'Enter') { e.preventDefault(); if (!list.hidden && act >= 0) pick(act); else show(sel.value); }
        else if (e.key === 'Escape') closeList();
    });
    list.addEventListener('mousedown', (e) => {
        const li = e.target.closest('li[data-i]');
        if (li) { e.preventDefault(); pick(parseInt(li.getAttribute('data-i'), 10)); }
    });
    document.addEventListener('click', (e) => { if (!e.target.closest('.za-combo')) closeList(); });
    function syncInput() { const o = sel.options[sel.selectedIndex]; if (o) inp.value = o.textContent; }
    sel.addEventListener('change', syncInput);
    syncInput();

    btn.addEventListener('click', () => show(sel.value));
    sel.addEventListener('change', () => show(sel.value));
    if (sel.value) show(sel.value);
})();
</script>

<?php
require __DIR__ . '/layout/master_end.php';
?>
