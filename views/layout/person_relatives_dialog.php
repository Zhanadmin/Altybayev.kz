<?php
// ALT-067: общий диалог «выбрать существующего / создать нового» для отца, матери, супруга и ребёнка.
// Подключается один раз на странице. Параметры: $relBtnCls, $relBtnPrimaryCls, $relInputCls, $relOnDoneJs (тело функции onDone(id)).
declare(strict_types=1);

$relBtnCls = $relBtnCls ?? 'btn';
$relBtnPrimaryCls = $relBtnPrimaryCls ?? 'btn btn-primary';
$relInputCls = $relInputCls ?? 'input';
$relOnDoneJs = $relOnDoneJs ?? '';
$relRu = in_array(\App\Core\I18n::lang(), ['ru', 'en', 'es'], true);
$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES);
$inp = $h($relInputCls);
?>
<div id="relDialog" role="dialog" aria-modal="true" aria-labelledby="relTitle"
     style="display:none; position:fixed; inset:0; z-index:400; align-items:center; justify-content:center; background:rgba(0,0,0,.55);">
    <div style="background:var(--c-island, var(--c-bg, #fff)); color:var(--c-text, inherit); border:1px solid var(--c-border, #ccc); border-radius:12px; width:480px; max-width:95vw; max-height:90vh; overflow:auto; padding:18px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h3 id="relTitle" style="margin:0;"></h3>
            <button type="button" class="<?= $h($relBtnCls) ?>" onclick="relClose()" aria-label="Жабу / Закрыть">×</button>
        </div>
        <div style="display:flex; gap:8px; margin-bottom:12px;">
            <button type="button" class="<?= $h($relBtnCls) ?>" id="relTabExisting" onclick="relSetMode('existing')">Бар адам / Существующий</button>
            <button type="button" class="<?= $h($relBtnCls) ?>" id="relTabNew" onclick="relSetMode('new')">Жаңа / Новый</button>
        </div>
        <div id="relMarriageWrap" style="display:none; margin-bottom:12px;">
            <label for="relMarriage" style="display:block; margin-bottom:4px;">Қай некеден / От какого брака</label>
            <select id="relMarriage" class="<?= $inp ?>"></select>
        </div>
        <div id="relPaneExisting">
            <label for="relSearch" style="display:block; margin-bottom:4px;">Іздеу / Поиск</label>
            <input type="text" id="relSearch" class="<?= $inp ?>" autocomplete="off" placeholder="Аты, тегі / Имя, фамилия">
            <div id="relResults" role="listbox" style="max-height:240px; overflow:auto; margin-top:8px;"></div>
        </div>
        <div id="relPaneNew" style="display:none;">
            <div style="margin-bottom:8px;"><label for="relFirst" style="display:block; margin-bottom:4px;">Аты / Имя *</label><input type="text" id="relFirst" class="<?= $inp ?>"></div>
            <div style="margin-bottom:8px;"><label for="relLast" style="display:block; margin-bottom:4px;">Тегі / Фамилия</label><input type="text" id="relLast" class="<?= $inp ?>"></div>
            <div id="relMaidenWrap" style="margin-bottom:8px;"><label for="relMaiden" style="display:block; margin-bottom:4px;">Қыз кезіндегі тегі / Девичья фамилия</label><input type="text" id="relMaiden" class="<?= $inp ?>"></div>
            <div id="relGenderWrap" style="display:none; margin-bottom:8px;">
                <span style="display:block; margin-bottom:4px;">Жынысы / Пол *</span>
                <label style="margin-right:16px;"><input type="radio" name="relGender" value="M"> Ұл / Сын</label>
                <label><input type="radio" name="relGender" value="F"> Қыз / Дочь</label>
            </div>
            <div style="margin-bottom:8px;"><label for="relYear" style="display:block; margin-bottom:4px;">Туған жылы / Год</label><input type="text" id="relYear" class="<?= $inp ?>"></div>
        </div>
        <div id="relError" style="color:var(--c-danger, #c0392b); margin-top:8px;"></div>
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:14px;">
            <button type="button" class="<?= $h($relBtnCls) ?>" onclick="relClose()">Бас тарту / Отмена</button>
            <button type="button" class="<?= $h($relBtnPrimaryCls) ?>" id="relSave" onclick="relSubmit()">Қосу / Добавить</button>
        </div>
    </div>
</div>
<script>
    window.__relCfg = {
        ru: <?= $relRu ? 'true' : 'false' ?>,
        csrf: <?= json_encode(\App\Core\Session::csrfToken()) ?>,
        btnGhost: <?= json_encode($relBtnCls) ?>,
        onDone: async function (id) { <?= $relOnDoneJs ?> }
    };
</script>
<script src="/assets/js/person-relatives.js?v=1"></script>
