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

<div class="dt">
    <div class="ds-page-head">
        <div>
            <h1>Жеті ата</h1>
            <p class="sub" id="zaSubtitle"></p>
        </div>
    </div>

    <style>
        .za-layout { display:flex; flex-direction:column; gap:16px; }
        .za-side { padding:16px; }
        .za-side .form-select { width:100%; min-height:40px; }
        .za-side .za-run { margin-top:10px; min-height:40px; width:100%; }
        .za-info { margin-top:14px; }
        .za-info-name { font-weight:700; font-size:16px; margin-bottom:4px; }
        .za-info-row { font-size:14px; opacity:.85; margin-top:2px; }
        .za-actions { display:flex; flex-direction:column; gap:8px; margin-top:14px; }
        .za-actions .btn { padding:8px 14px; font-size:13px; width:100%; }
        .za-main { min-width:0; }
        @media (min-width: 900px) {
            .za-layout { flex-direction:row; align-items:flex-start; }
            .za-side { flex:0 0 280px; width:280px; position:sticky; top:16px; }
            .za-main { flex:1 1 auto; display:flex; justify-content:center; }
            .za-main #zaResult { width:100%; }
        }
    </style>

    <div class="za-layout">
        <div class="island za-side">
            <label id="zaPersonLabel" for="zaPerson" style="display:block; margin-bottom:4px;"></label>
            <select id="zaPerson" class="form-select">
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
        history.replaceState(null, '', '/zheti-ata?id=' + encodeURIComponent(id));
        ZhetiAta.render(box, id, lang, show, {
            actionsBox: document.getElementById('zaActions'),
            infoBox: document.getElementById('zaInfo')
        });
    }
    btn.addEventListener('click', () => show(sel.value));
    sel.addEventListener('change', () => show(sel.value));
    if (sel.value) show(sel.value);
})();
</script>

<?php
require __DIR__ . '/layout/master_end.php';
?>
