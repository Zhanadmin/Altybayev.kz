<?php
declare(strict_types=1);

use App\Core\I18n;

/** @var array $family @var array $albums @var bool $canContribute */
$lang = I18n::lang();
$ru = (($lang === 'ru' || $lang === 'en'));
$L = fn(string $kk, string $r) => $ru ? $r : $kk;

$title = $L('Фотоальбом', 'Фотоальбом') . ': ' . $family['title'];
$activeNav = 'families';
$dsThemeReady = true;

require __DIR__ . '/../layout/master_start.php';
?>
<link rel="stylesheet" href="/assets/css/album.css?v=5">

<div class="al">
    <div class="ds-page-head">
        <div style="min-width:0">
            <div class="small muted"><a href="/families#fam-<?= (int)$family['id'] ?>"><?= $L('Отбасылар', 'Семьи') ?></a></div>
            <h1><?= htmlspecialchars($family['title']) ?></h1>
            <p class="sub"><?= $L('Отбасының фотоальбомдары', 'Фотоальбомы семьи') ?></p>
        </div>
        <svg class="al-head-orn desktop-only" viewBox="10 22 100 60" aria-hidden="true" focusable="false"><use href="#o-horn"/></svg>
    </div>

    <div class="al-cards" style="margin-top:var(--sp-4)">
        <?php foreach ($albums as $a): ?>
            <a class="island al-card" href="/album/<?= (int)$a['id'] ?>">
                <div class="al-cover">
                    <?php if (!empty($a['cover_id'])): ?>
                        <img src="/album/media?id=<?= (int)$a['cover_id'] ?>&amp;v=thumb" alt="" loading="lazy">
                    <?php else: ?>
                        <svg class="al-cover-orn" viewBox="0 0 120 120" aria-hidden="true" focusable="false"><use href="#o-rosette"/></svg>
                    <?php endif; ?>
                    <span class="al-count"><?= (int)$a['media_count'] ?></span>
                </div>
                <div class="al-card-body">
                    <b><?= htmlspecialchars((string)$a['title']) ?></b>
                    <?php if (!empty($a['album_date'])): ?><span class="small"><?= htmlspecialchars(I18n::formatDate($a['album_date'])) ?></span><?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>

        <?php if ($canContribute): ?>
            <button type="button" class="island al-card is-new" data-open-modal="alNewModal">
                <svg class="ic" aria-hidden="true"><use href="#i-plus"/></svg>
                <span><?= $L('Жаңа альбом', 'Новый альбом') ?></span>
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($canContribute): ?>
<div id="alNewModal" class="modal-backdrop" style="display:none;">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="alNewTitle" style="max-width:480px">
        <div class="modal-head">
            <h3 id="alNewTitle"><?= $L('Жаңа альбом', 'Новый альбом') ?></h3>
            <button type="button" class="btn btn-ghost btn-icon" data-close-modal aria-label="<?= htmlspecialchars($L('Жабу', 'Закрыть')) ?>"><svg class="ic"><use href="#i-x"/></svg></button>
        </div>
        <form data-api="/api/album/save">
            <input type="hidden" name="owner_id" value="<?= (int)$family['id'] ?>">
            <div class="modal-body">
                <div class="field"><label for="alNewName"><?= $L('Атауы', 'Название') ?></label>
                    <input id="alNewName" name="title" class="input" required maxlength="200" placeholder="<?= htmlspecialchars($L('Мысалы: Жазғы демалыс 2026', 'Например: Летний отдых 2026')) ?>"></div>
                <div class="field"><label for="alNewDate"><?= $L('Күні', 'Дата') ?></label>
                    <input id="alNewDate" name="album_date" type="date" class="input"></div>
                <div class="field"><label for="alNewDescr"><?= $L('Сипаттама', 'Описание') ?></label>
                    <textarea id="alNewDescr" name="description" class="input textarea" maxlength="2000"></textarea></div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" data-close-modal><?= $L('Бас тарту', 'Отмена') ?></button>
                <button type="submit" class="btn btn-primary"><?= $L('Құру', 'Создать') ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
window.AL = <?= json_encode([
    'csrf' => $csrf,
    't'    => [
        'err_network' => $L('Байланыс қатесі', 'Ошибка связи'),
        'err_server'  => $L('Сервер қатесі', 'Ошибка сервера'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="/assets/js/album.js?v=1" defer></script>

<?php require __DIR__ . '/../layout/master_end.php'; ?>
