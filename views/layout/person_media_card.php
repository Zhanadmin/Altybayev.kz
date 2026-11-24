<?php
// TASK-037: смена аватарки и обложки прямо на карточке человека (как в Facebook).
// Кнопки (#pmeAvatarBtn, #pmeCoverBtn) стоят в views/persons/show.php; здесь — скрытые поля файла,
// меню обложки, окно выбора/обрезки и подключение JS. Показывается только тем, кто может менять человека.
// Ожидает: $person, $coverUrl/$coverPos/$coverDim (из show.php). Логика — public/assets/js/person-media-edit.js.
declare(strict_types=1);

use App\Core\I18n;
use App\Core\Session;

$pmeE = static fn(string $k): string => htmlspecialchars(I18n::t($k), ENT_QUOTES);
$pmeJs = static fn(string $k): string => (string)json_encode(I18n::t($k), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$pmeAccept = 'image/jpeg,image/png,image/webp';
?>
<link rel="stylesheet" href="/assets/css/person-media-edit.css?v=6">
<input type="file" id="pmeAvatarInput" accept="<?= $pmeAccept ?>" hidden onchange="pmeAvatarFile(this)">
<input type="file" id="pmeCoverInput" accept="<?= $pmeAccept ?>" hidden onchange="pmeCoverFile(this)">

<div id="pmeCoverMenu" class="pme-menu" hidden>
    <label class="pme-menu-item" for="pmeCoverInput"><?= $pmeE('pme_upload') ?></label>
    <button type="button" class="pme-menu-item" onclick="pmeOpenPicker('cover')"><?= $pmeE('pme_from_album') ?></button>
    <div class="pme-menu-sub"><?= $pmeE('pme_presets') ?></div>
    <div class="pme-presets">
        <?php foreach (\App\Controllers\PersonCoverController::PRESETS as $pmeKey): ?>
            <button type="button" class="pme-preset" onclick="pmeCoverPreset('<?= $pmeKey ?>')" title="<?= $pmeE('pme_preset_' . $pmeKey) ?>">
                <img src="/assets/img/covers/<?= $pmeKey ?>.svg" alt="<?= $pmeE('pme_preset_' . $pmeKey) ?>" width="72" height="24">
                <span><?= $pmeE('pme_preset_' . $pmeKey) ?></span>
            </button>
        <?php endforeach; ?>
    </div>
    <div id="pmeCoverTuning">
        <label class="pme-slider"><span><?= $pmeE('pme_pos') ?></span>
            <input type="range" id="pmePos" min="0" max="100" value="50" oninput="pmeTune()" onchange="pmeTuneSave()"></label>
        <label class="pme-slider"><span><?= $pmeE('pme_dim') ?></span>
            <input type="range" id="pmeDim" min="0" max="70" value="30" oninput="pmeTune()" onchange="pmeTuneSave()"></label>
    </div>
    <button type="button" class="pme-menu-item pme-menu-danger" id="pmeCoverRemove" onclick="pmeCoverRemove()"><?= $pmeE('pme_remove') ?></button>
</div>

<div id="pmeOverlay" class="pme-overlay" style="display:none;" role="dialog" aria-modal="true">
    <div class="pme-dialog">
        <div class="pme-dialog-head">
            <strong id="pmeGridTitle"></strong>
            <button type="button" class="pme-btn" onclick="pmeClose()" aria-label="<?= $pmeE('pme_cancel') ?>">×</button>
        </div>
        <div id="pmeGridPane">
            <div id="pmeGridEmpty" class="pme-note" style="display:none;"><?= $pmeE('pme_empty_albums') ?></div>
            <div id="pmeGrid" class="pme-grid"></div>
            <div style="text-align:center; margin-top:10px;"><button type="button" class="pme-btn" id="pmeMore" style="display:none;" onclick="pmePickerMore()"><?= $pmeE('pme_more') ?></button></div>
        </div>
        <div id="pmeCropPane" style="display:none; text-align:center;">
            <div class="pme-sub" style="margin-bottom:8px;"><?= $pmeE('pme_crop_hint') ?></div>
            <div class="pme-cropwrap"><canvas id="pmeCropCanvas" class="pme-canvas" width="240" height="304"></canvas></div>
            <div class="pme-arrows">
                <button type="button" class="pme-btn" onclick="pmeMove(-1,0)" aria-label="←">←</button>
                <button type="button" class="pme-btn" onclick="pmeMove(0,-1)" aria-label="↑">↑</button>
                <button type="button" class="pme-btn" onclick="pmeMove(0,1)" aria-label="↓">↓</button>
                <button type="button" class="pme-btn" onclick="pmeMove(1,0)" aria-label="→">→</button>
            </div>
            <div class="pme-zoomrow">
                <button type="button" class="pme-btn" onclick="pmeZoomStep(-1)" aria-label="<?= $pmeE('pme_zoom') ?> −">−</button>
                <input type="range" id="pmeZoom" min="100" max="400" value="100" oninput="pmeZoom()" aria-label="<?= $pmeE('pme_zoom') ?>">
                <button type="button" class="pme-btn" onclick="pmeZoomStep(1)" aria-label="<?= $pmeE('pme_zoom') ?> +">+</button>
            </div>
            <div class="pme-prevrow"><canvas id="pmeCropPreview" class="pme-preview" width="120" height="152"></canvas><span class="pme-sub"><?= $pmeE('pme_preview') ?></span></div>
            <div class="pme-actions" style="justify-content:center; margin-top:12px;">
                <button type="button" class="pme-btn" onclick="pmeClose()"><?= $pmeE('pme_cancel') ?></button>
                <button type="button" class="pme-btn pme-primary" id="pmeCropApply" onclick="pmeCropApply()"><?= $pmeE('pme_apply') ?></button>
            </div>
        </div>
    </div>
</div>
<script>
window.PME_T = {
    saved: <?= $pmeJs('pme_saved') ?>,
    pickAvatar: <?= $pmeJs('pme_pick_avatar') ?>,
    pickCover: <?= $pmeJs('pme_pick_cover') ?>,
    imgError: <?= $pmeJs('pme_img_error') ?>
};
window.addEventListener('DOMContentLoaded', function () {
    if (typeof pmeCardInit === 'function') {
        pmeCardInit({
            personId: <?= (int)$person['id'] ?>,
            csrf: <?= json_encode(Session::csrfToken()) ?>,
            cover: { url: <?= json_encode($coverUrl !== '' ? $coverUrl : null) ?>, pos_y: <?= (int)$coverPos ?>, dim: <?= (int)$coverDim ?> }
        });
    }
});
</script>
<script src="/assets/js/person-media-edit.js?v=5" defer></script>
