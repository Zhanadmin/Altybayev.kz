<?php
// TASK-022 (замечание 16.11 19:26): фото (портрет) и обложка человека в окне «Өңдеу».
// TASK-033: обложка — настоящий фон вверху окна, поверх аватарка; мелкие кнопки «Изменить» в углу.
// Подключается в начале формы редактирования (master_end.php и tree.php). Логика — public/assets/js/person-media-edit.js.
declare(strict_types=1);

use App\Core\I18n;

$pmeE = static fn(string $k): string => htmlspecialchars(I18n::t($k), ENT_QUOTES);
$pmeJs = static fn(string $k): string => (string)json_encode(I18n::t($k), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$pmeAccept = 'image/jpeg,image/png,image/webp';
?>
<link rel="stylesheet" href="/assets/css/person-media-edit.css?v=4">
<div id="pmeBox" class="pme" style="display:none;">
    <div id="pmeNoMigration" class="pme-note" style="display:none;"><?= $pmeE('pme_no_migration') ?></div>

    <div class="pme-hero" id="pmeCoverBlock">
        <div class="pme-cover" id="pmeCover">
            <div class="pme-cover-dim" id="pmeCoverDim"></div>
            <div class="pme-cover-empty-text" id="pmeCoverEmptyText"><?= $pmeE('pme_cover_default') ?></div>
            <label class="pme-mini pme-mini-cover" for="pmeCoverInput" title="<?= $pmeE('pme_cover') ?>"><?= $pmeE('pme_change') ?></label>
            <input type="file" id="pmeCoverInput" accept="<?= $pmeAccept ?>" hidden onchange="pmeCoverFile(this)">
        </div>
        <div class="pme-avatar-wrap">
            <div class="pme-avatar" id="pmeAvatar"></div>
            <label class="pme-mini pme-mini-avatar" for="pmeAvatarInput" title="<?= $pmeE('pme_avatar') ?>" aria-label="<?= $pmeE('pme_avatar') ?>">&#9998;</label>
            <input type="file" id="pmeAvatarInput" accept="<?= $pmeAccept ?>" hidden onchange="pmeAvatarFile(this)">
        </div>
    </div>

    <details class="pme-more">
        <summary><?= $pmeE('pme_more_opts') ?></summary>
        <div class="pme-actions">
            <button type="button" class="pme-btn" onclick="pmeOpenPicker('avatar')"><?= $pmeE('pme_avatar') ?>: <?= $pmeE('pme_from_album') ?></button>
            <button type="button" class="pme-btn" onclick="pmeOpenPicker('cover')"><?= $pmeE('pme_cover') ?>: <?= $pmeE('pme_from_album') ?></button>
            <button type="button" class="pme-btn" id="pmeCoverRemove" onclick="pmeCoverRemove()"><?= $pmeE('pme_remove') ?></button>
        </div>
        <div class="pme-presets">
            <span class="pme-sub"><?= $pmeE('pme_presets') ?>:</span>
            <?php foreach (\App\Controllers\PersonCoverController::PRESETS as $pmeKey): ?>
                <button type="button" class="pme-preset" onclick="pmeCoverPreset('<?= $pmeKey ?>')" title="<?= $pmeE('pme_preset_' . $pmeKey) ?>">
                    <img src="/assets/img/covers/<?= $pmeKey ?>.svg" alt="<?= $pmeE('pme_preset_' . $pmeKey) ?>" width="72" height="24">
                    <span><?= $pmeE('pme_preset_' . $pmeKey) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <div id="pmeCoverTuning" style="display:none;">
            <label class="pme-slider"><span><?= $pmeE('pme_pos') ?></span>
                <input type="range" id="pmePos" min="0" max="100" value="50" oninput="pmeTune()"></label>
            <label class="pme-slider"><span><?= $pmeE('pme_dim') ?></span>
                <input type="range" id="pmeDim" min="0" max="70" value="30" oninput="pmeTune()"></label>
        </div>
    </details>
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
            <div class="pme-cropwrap"><canvas id="pmeCropCanvas" class="pme-canvas" width="300" height="300"></canvas></div>
            <label class="pme-slider" style="max-width:300px; margin:10px auto 0;"><span><?= $pmeE('pme_zoom') ?></span>
                <input type="range" id="pmeZoom" min="100" max="400" value="100" oninput="pmeZoom()"></label>
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
</script>
<script src="/assets/js/person-media-edit.js?v=3" defer></script>
