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

    <div class="island" style="padding:16px; margin-bottom:16px;">
        <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div style="flex:1; min-width:220px;">
                <label id="zaPersonLabel" for="zaPerson" style="display:block; margin-bottom:4px;"></label>
                <select id="zaPerson" class="form-select" style="width:100%; min-height:40px;">
                    <?php foreach ($persons as $p):
                        $label = trim((string)$p['first_name'] . ' ' . (string)($p['last_name'] ?? ''))
                            . (!empty($p['maiden_name']) ? ' (' . $p['maiden_name'] . ')' : '')
                            . ' (' . (!empty($p['birth_year']) ? $p['birth_year'] : '—') . ')'; ?>
                        <option value="<?= (int)$p['id'] ?>"<?= (int)$p['id'] === $selectedId ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="btn btn-primary" id="zaRunBtn" style="min-height:40px;"></button>
        </div>
    </div>

    <div id="zaResult" style="display:none;"></div>
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
        ZhetiAta.render(box, id, lang, show);
    }
    btn.addEventListener('click', () => show(sel.value));
    sel.addEventListener('change', () => show(sel.value));
    if (sel.value) show(sel.value);
})();
</script>

<?php
require __DIR__ . '/layout/master_end.php';
?>
