<?php
// ALT-077: блок «Фото» в окне правки человека в шежіре (tree.php): текущая аватарка,
// «Загрузить фото» / «Заменить фото», обрезка овалом (без перекадрирования). Логика — public/assets/js/person-media-edit.js
// (pmeAvatarLoad, pmeAvatarFile, pmeCropApply). Права — как у правки человека (media_edit приходит только тем, кто может править).
declare(strict_types=1);

use App\Core\I18n;

$paE = static fn(string $k): string => htmlspecialchars(I18n::t($k), ENT_QUOTES);
$paJs = static fn(string $k): string => (string)json_encode(I18n::t($k), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<link rel="stylesheet" href="/assets/css/person-media-edit.css?v=7">
<div id="pmeAvBox" class="pme" style="display:none; margin-bottom:14px;">
    <div style="display:flex; align-items:center; gap:14px;">
        <div class="pme-avatar" id="pmeAvatar"></div>
        <div>
            <label class="pme-btn" for="pmeAvatarInput" id="pmeAvatarBtn" role="button" tabindex="0" style="cursor:pointer;" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click();}"><?= $paE('pme_upload_photo') ?></label>
            <input type="file" id="pmeAvatarInput" accept="image/jpeg,image/png,image/webp" hidden onchange="pmeAvatarFile(this)">
        </div>
    </div>
</div>
<div id="pmeOverlay" class="pme-overlay" style="display:none; z-index:100001;" role="dialog" aria-modal="true">
    <div class="pme-dialog">
        <div id="pmeCropPane" style="text-align:center;">
            <div class="pme-sub" style="margin-bottom:8px;"><?= $paE('pme_crop_hint') ?></div>
            <div class="pme-cropwrap"><canvas id="pmeCropCanvas" class="pme-canvas" width="240" height="304"></canvas></div>
            <div class="pme-arrows">
                <button type="button" class="pme-btn" onclick="pmeMove(-1,0)" aria-label="←">←</button>
                <button type="button" class="pme-btn" onclick="pmeMove(0,-1)" aria-label="↑">↑</button>
                <button type="button" class="pme-btn" onclick="pmeMove(0,1)" aria-label="↓">↓</button>
                <button type="button" class="pme-btn" onclick="pmeMove(1,0)" aria-label="→">→</button>
            </div>
            <div class="pme-zoomrow">
                <button type="button" class="pme-btn" onclick="pmeZoomStep(-1)" aria-label="<?= $paE('pme_zoom') ?> −">−</button>
                <input type="range" id="pmeZoom" min="100" max="400" value="100" oninput="pmeZoom()" aria-label="<?= $paE('pme_zoom') ?>">
                <button type="button" class="pme-btn" onclick="pmeZoomStep(1)" aria-label="<?= $paE('pme_zoom') ?> +">+</button>
            </div>
            <div class="pme-prevrow"><canvas id="pmeCropPreview" class="pme-preview" width="120" height="152"></canvas><span class="pme-sub"><?= $paE('pme_preview') ?></span></div>
            <div class="pme-actions" style="justify-content:center; margin-top:12px;">
                <button type="button" class="pme-btn" onclick="pmeClose()"><?= $paE('pme_cancel') ?></button>
                <button type="button" class="pme-btn pme-primary" id="pmeCropApply" onclick="pmeCropApply()"><?= $paE('pme_apply') ?></button>
            </div>
        </div>
    </div>
</div>
<script>
window.PME_T = {
    saved: <?= $paJs('pme_saved') ?>,
    imgError: <?= $paJs('pme_img_error') ?>,
    upload: <?= $paJs('pme_upload_photo') ?>,
    replace: <?= $paJs('pme_replace_photo') ?>
};
</script>
<script src="/assets/js/person-media-edit.js?v=8" defer></script>
