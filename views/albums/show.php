<?php
declare(strict_types=1);

use App\Controllers\AlbumController;
use App\Core\I18n;

/** @var array $album @var ?array $family @var ?array $event @var array $items @var array $people */
$lang = I18n::lang();
$ru = (($lang === 'ru' || $lang === 'en'));
$L = fn(string $kk, string $r) => $ru ? $r : $kk;

$isEvent = !empty($event);
$title = $isEvent ? (string)$event['title'] : (string)$album['title'];
$activeNav = $isEvent ? 'events' : 'families';
$dsThemeReady = true;

$jsItems = array_map(fn($m) => AlbumController::itemForJs($m, $user, (bool)$canEditAll), $items);
$fmtDate = fn(?string $d) => $d ? I18n::formatDate($d) : '';

$faceSvc = new \App\Services\FaceRecognitionService();
$faceReady = $faceSvc->isReady() && $faceSvc->enabled();

require __DIR__ . '/../layout/master_start.php';
?>
<link rel="stylesheet" href="/assets/css/album.css?v=5">

<div class="al">
    <div class="ds-page-head">
        <div style="min-width:0">
            <div class="small muted">
                <?php if ($isEvent): ?>
                    <a href="/events"><?= $L('Іс-шаралар', 'Мероприятия') ?></a>
                <?php elseif ($family): ?>
                    <a href="/family/<?= (int)$family['id'] ?>/albums"><?= htmlspecialchars($family['title']) ?></a>
                <?php endif; ?>
            </div>
            <h1><?= htmlspecialchars($title) ?></h1>
            <div class="al-meta">
                <?php $d = $isEvent ? ($event['event_date'] ?? null) : ($album['album_date'] ?? null); if ($d): ?>
                    <span><svg class="ic" aria-hidden="true"><use href="#i-calendar"/></svg><?= htmlspecialchars($fmtDate($d)) ?></span>
                <?php endif; ?>
                <?php if ($isEvent && !empty($event['place'])): ?>
                    <span>· <?= htmlspecialchars((string)$event['place']) ?></span>
                <?php endif; ?>
                <span>· <b id="alCount"><?= count($items) ?></b> <?= $L('фото мен бейне', 'фото и видео') ?></span>
            </div>
            <?php $desc = $isEvent ? ($event['description'] ?? '') : ($album['description'] ?? ''); if ($desc): ?>
                <p class="al-desc"><?= htmlspecialchars((string)$desc) ?></p>
            <?php endif; ?>
            <?php if ($isEvent && (!empty($event['families']) || !empty($event['people']))): ?>
                <div class="al-chips">
                    <?php foreach ($event['families'] as $f): ?>
                        <a class="badge" href="/family/<?= (int)$f['id'] ?>/albums"><svg class="ic" aria-hidden="true" style="width:14px;height:14px"><use href="#i-family"/></svg><?= htmlspecialchars($f['name']) ?></a>
                    <?php endforeach; ?>
                    <?php foreach ($event['people'] as $p): ?>
                        <a class="badge" href="/person/<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="actions">
            <?php if ($canContribute && $faceReady): ?>
                <button type="button" class="btn btn-ghost" id="alFindFaces" title="<?= htmlspecialchars($L('Беттерді табу', 'Найти лица')) ?>">
                    <svg class="ic" aria-hidden="true"><use href="#i-face"/></svg><?= $L('Беттерді табу', 'Найти лица') ?>
                </button>
            <?php endif; ?>
            <?php if ($canManage || $canEditAll): ?>
                <button type="button" class="btn btn-ghost" data-open-modal="alInfoModal">
                    <svg class="ic" aria-hidden="true"><use href="#i-edit"/></svg><?= $L('Өзгерту', 'Изменить') ?>
                </button>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <button type="button" class="btn btn-ghost btn-danger"
                        data-delete-api="<?= $isEvent ? '/api/event/delete' : '/api/album/delete' ?>"
                        data-id="<?= (int)($isEvent ? $event['id'] : $album['id']) ?>"
                        data-confirm="<?= htmlspecialchars($L('Жою керек пе? (тек бос болса жойылады)', 'Удалить? (удаляется, только если пусто)')) ?>">
                    <svg class="ic" aria-hidden="true"><use href="#i-x"/></svg><?= $L('Жою', 'Удалить') ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($canContribute): ?>
        <section class="island" style="margin-top:var(--sp-4)">
            <div id="alDrop" class="al-drop">
                <svg class="ic" aria-hidden="true"><use href="#i-upload"/></svg>
                <b><?= $L('Фото мен бейне қосу', 'Добавить фото и видео') ?></b>
                <span class="small"><?= $L('Бірнеше файлды бірден таңдаңыз немесе осында сүйреңіз', 'Выберите сразу несколько файлов или перетащите их сюда') ?></span>
                <div>
                    <label class="btn btn-primary" for="alFiles">
                        <svg class="ic" aria-hidden="true"><use href="#i-camera"/></svg><?= $L('Файлдарды таңдау', 'Выбрать файлы') ?>
                    </label>
                    <input id="alFiles" type="file" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm,.jpg,.jpeg,.png,.webp,.mp4,.m4v,.mov,.webm" hidden>
                </div>
                <div class="al-note" style="margin-top:8px">
                    <?= $L('Фото: JPEG, PNG, WebP — '. (int)$photoMaxMb .' МБ дейін. Бейне: MP4, MOV, WebM — ' . (int)$videoMaxMb . ' МБ дейін.',
                           'Фото: JPEG, PNG, WebP — до ' . (int)$photoMaxMb . ' МБ. Видео: MP4, MOV, WebM — до ' . (int)$videoMaxMb . ' МБ.') ?>
                </div>
            </div>
            <details style="margin-top:var(--sp-3)">
                <summary class="small" style="cursor:pointer"><?= $L('Жүктелетін файлдарға сипаттама, күн, адамдар', 'Подпись, дата и люди для загружаемых файлов') ?></summary>
                <div class="al-upload-opts">
                    <div class="field">
                        <label for="alCaption"><?= $L('Сипаттама', 'Подпись') ?></label>
                        <input id="alCaption" class="input" type="text" maxlength="500">
                    </div>
                    <div class="field">
                        <label for="alDate"><?= $L('Түсірілген күні', 'Дата съёмки') ?></label>
                        <input id="alDate" class="input" type="date" value="<?= htmlspecialchars((string)($isEvent ? ($event['event_date'] ?? '') : ($album['album_date'] ?? ''))) ?>">
                    </div>
                </div>
                <div class="field al-picker" id="alUploadPeople" style="margin-top:var(--sp-3)">
                    <label for="alUploadPeopleInput"><?= $L('Фотодағы адамдар', 'Кто на фото') ?></label>
                    <input type="text" id="alUploadPeopleInput" class="input" list="alPeopleList" placeholder="<?= htmlspecialchars($L('Атын жазыңыз…', 'Начните вводить имя…')) ?>" autocomplete="off">
                    <input type="hidden" id="alUploadPeopleIds" value="">
                    <div class="al-picked"></div>
                </div>
            </details>
            <div id="alQueue" class="al-queue" aria-live="polite"></div>
        </section>
    <?php else: ?>
        <p class="al-note" style="margin-top:var(--sp-3)"><?= $L('Сіз альбомды қарай аласыз. Фото қосу — отбасы мүшелері мен редакторларға.', 'Вы можете смотреть альбом. Добавлять фото могут участники семьи и редакторы.') ?></p>
    <?php endif; ?>

    <section style="margin-top:var(--sp-4)">
        <div id="alGrid" class="al-grid"></div>
        <div id="alEmpty" class="island al-empty" <?= $items ? 'style="display:none"' : '' ?>>
            <svg viewBox="0 0 140 90" aria-hidden="true" focusable="false"><use href="#o-sprout"/></svg>
            <div><b><?= $L('Альбом әзірге бос', 'Альбом пока пуст') ?></b></div>
            <div class="small"><?= $canContribute ? $L('Алғашқы фотоны қосыңыз', 'Добавьте первое фото') : $L('Туыстар фото қосқанда осында көрінеді', 'Фото появятся, когда родственники их добавят') ?></div>
        </div>
    </section>
</div>

<datalist id="alPeopleList">
    <?php foreach ($people as $p): ?><option value="<?= htmlspecialchars($p['name']) ?>"></option><?php endforeach; ?>
</datalist>

<!-- Полноэкранный просмотр -->
<div id="alLb" class="al-lb" role="dialog" aria-modal="true" aria-label="<?= htmlspecialchars($L('Фото қарау', 'Просмотр фото')) ?>">
    <div class="al-lb-top">
        <button type="button" class="btn-lb" data-lb="close" aria-label="<?= htmlspecialchars($L('Жабу', 'Закрыть')) ?>"><svg class="ic"><use href="#i-x"/></svg></button>
        <span class="al-lb-count"></span>
        <span class="spacer"></span>
        <a class="btn-lb" data-lb="download" href="#" download aria-label="<?= htmlspecialchars($L('Жүктеп алу', 'Скачать')) ?>"><svg class="ic"><use href="#i-save"/></svg><span class="desktop-only"><?= $L('Жүктеп алу', 'Скачать') ?></span></a>
        <button type="button" class="btn-lb" data-lb="avatar" aria-label="<?= htmlspecialchars($L('Аватарға қою', 'Поставить на аватар')) ?>"><svg class="ic"><use href="#i-users"/></svg><span class="desktop-only"><?= $L('Аватарға қою', 'На аватар') ?></span></button>
        <button type="button" class="btn-lb" data-lb="edit" aria-label="<?= htmlspecialchars($L('Өзгерту', 'Изменить')) ?>"><svg class="ic"><use href="#i-edit"/></svg></button>
        <button type="button" class="btn-lb danger" data-lb="delete" aria-label="<?= htmlspecialchars($L('Жою', 'Удалить')) ?>"><svg class="ic"><use href="#i-x"/></svg><span class="desktop-only"><?= $L('Жою', 'Удалить') ?></span></button>
    </div>
    <div class="al-lb-stage">
        <button type="button" class="al-lb-nav prev" data-lb="prev" aria-label="<?= htmlspecialchars($L('Алдыңғы', 'Предыдущее')) ?>"><svg class="ic" style="transform:scaleX(-1)"><use href="#i-chevron-r"/></svg></button>
        <button type="button" class="al-lb-nav next" data-lb="next" aria-label="<?= htmlspecialchars($L('Келесі', 'Следующее')) ?>"><svg class="ic"><use href="#i-chevron-r"/></svg></button>
    </div>
    <div class="al-lb-info"></div>
</div>

<!-- Подпись, дата, люди на фото -->
<div id="alEditModal" class="modal-backdrop" style="display:none;">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="alEditTitle" style="max-width:480px">
        <div class="modal-head">
            <h3 id="alEditTitle"><?= $L('Фото туралы', 'О фото') ?></h3>
            <button type="button" class="btn btn-ghost btn-icon" data-close-modal aria-label="<?= htmlspecialchars($L('Жабу', 'Закрыть')) ?>"><svg class="ic"><use href="#i-x"/></svg></button>
        </div>
        <form id="alEditForm">
            <input type="hidden" name="id" value="">
            <div class="modal-body">
                <div class="field">
                    <label for="alEditCaption"><?= $L('Сипаттама', 'Подпись') ?></label>
                    <textarea id="alEditCaption" name="caption" class="input textarea" maxlength="500"></textarea>
                </div>
                <div class="field">
                    <label for="alEditDate"><?= $L('Түсірілген күні', 'Дата съёмки') ?></label>
                    <input id="alEditDate" name="taken_date" type="date" class="input">
                </div>
                <div class="field al-picker" id="alEditPeople">
                    <label for="alEditPeopleInput"><?= $L('Фотодағы адамдар', 'Кто на фото') ?></label>
                    <input type="text" id="alEditPeopleInput" class="input" list="alPeopleList" placeholder="<?= htmlspecialchars($L('Атын жазыңыз…', 'Начните вводить имя…')) ?>" autocomplete="off">
                    <input type="hidden" name="people" value="">
                    <div class="al-picked"></div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" data-close-modal><?= $L('Бас тарту', 'Отмена') ?></button>
                <button type="submit" class="btn btn-primary"><?= $L('Сақтау', 'Сохранить') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- TASK-018: аватар из фото — квадратная обрезка -->
<div id="alAvaModal" class="modal-backdrop" style="display:none;">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="alAvaTitle" style="max-width:560px">
        <div class="modal-head">
            <h3 id="alAvaTitle"><?= $L('Аватарға қою', 'Поставить на аватар') ?></h3>
            <button type="button" class="btn btn-ghost btn-icon" data-close-modal aria-label="<?= htmlspecialchars($L('Жабу', 'Закрыть')) ?>"><svg class="ic"><use href="#i-x"/></svg></button>
        </div>
        <div class="modal-body">
            <!-- TASK-018 (note 16.11 15:51): явный выбор человека — отмеченные на фото сверху, поиск по всему шежіре -->
            <div class="field" id="alAvaWhoField">
                <label for="alAvaSearch"><?= $L('Кімге аватар қоямыз', 'Кому ставим аватар') ?></label>
                <input id="alAvaSearch" class="input" type="search" autocomplete="off" placeholder="<?= htmlspecialchars($L('Атын жазыңыз…', 'Начните вводить имя…')) ?>">
                <div id="alAvaList" class="al-ava-list" role="listbox" aria-label="<?= htmlspecialchars($L('Адамдар', 'Люди')) ?>"></div>
            </div>
            <p id="alAvaChosen" class="al-ava-chosen" aria-live="polite"></p>
            <p class="small muted" style="margin:0 0 8px"><?= $L('Шеңберді жылжытып, өлшемін таңдаңыз', 'Передвиньте круг и выберите размер') ?></p>
            <div class="al-crop" id="alCrop">
                <img id="alCropImg" alt="" draggable="false">
                <div class="al-crop-box" id="alCropBox" tabindex="0" aria-label="<?= htmlspecialchars($L('Кесу аймағы', 'Область обрезки')) ?>"></div>
            </div>
            <div class="field" style="margin-top:10px">
                <label for="alCropSize"><?= $L('Өлшемі', 'Размер') ?></label>
                <input id="alCropSize" type="range" min="20" max="100" value="100" step="1">
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn" data-close-modal><?= $L('Бас тарту', 'Отмена') ?></button>
            <button type="button" class="btn btn-primary" id="alAvaSave" disabled><?= $L('Сақтау', 'Сохранить') ?></button>
        </div>
    </div>
</div>

<?php if ($canManage || $canEditAll): ?>
<!-- Изменить альбом / мероприятие -->
<div id="alInfoModal" class="modal-backdrop" style="display:none;">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="alInfoTitle" style="max-width:520px">
        <div class="modal-head">
            <h3 id="alInfoTitle"><?= $isEvent ? $L('Іс-шараны өзгерту', 'Изменить мероприятие') : $L('Альбомды өзгерту', 'Изменить альбом') ?></h3>
            <button type="button" class="btn btn-ghost btn-icon" data-close-modal aria-label="<?= htmlspecialchars($L('Жабу', 'Закрыть')) ?>"><svg class="ic"><use href="#i-x"/></svg></button>
        </div>
        <?php if ($isEvent): ?>
            <form data-api="/api/event/save">
                <input type="hidden" name="id" value="<?= (int)$event['id'] ?>">
                <div class="modal-body">
                    <?php $ev = $event; require __DIR__ . '/_event_fields.php'; ?>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn" data-close-modal><?= $L('Бас тарту', 'Отмена') ?></button>
                    <button type="submit" class="btn btn-primary"><?= $L('Сақтау', 'Сохранить') ?></button>
                </div>
            </form>
        <?php else: ?>
            <form data-api="/api/album/save">
                <input type="hidden" name="id" value="<?= (int)$album['id'] ?>">
                <div class="modal-body">
                    <div class="field"><label for="alTitle"><?= $L('Атауы', 'Название') ?></label>
                        <input id="alTitle" name="title" class="input" required maxlength="200" value="<?= htmlspecialchars((string)$album['title']) ?>"></div>
                    <div class="field"><label for="alAlbumDate"><?= $L('Күні', 'Дата') ?></label>
                        <input id="alAlbumDate" name="album_date" type="date" class="input" value="<?= htmlspecialchars((string)($album['album_date'] ?? '')) ?>"></div>
                    <div class="field"><label for="alDescr"><?= $L('Сипаттама', 'Описание') ?></label>
                        <textarea id="alDescr" name="description" class="input textarea" maxlength="2000"><?= htmlspecialchars((string)($album['description'] ?? '')) ?></textarea></div>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn" data-close-modal><?= $L('Бас тарту', 'Отмена') ?></button>
                    <button type="submit" class="btn btn-primary"><?= $L('Сақтау', 'Сохранить') ?></button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
window.AL = <?= json_encode([
    'csrf'       => $csrf,
    'albumId'    => (int)$album['id'],
    'chunkSize'  => (int)$chunkSize,
    'photoMaxMb' => (int)$photoMaxMb,
    'videoMaxMb' => (int)$videoMaxMb,
    'items'      => $jsItems,
    'people'     => $people,
    'canEditAll' => (bool)$canEditAll,
    'myPersonId' => (int)($user['person_id'] ?? 0),
    'lang'       => $lang,
    'face'       => [
        'enabled'       => (bool)$faceReady,
        'canContribute' => (bool)$canContribute,
    ],
    't'          => [
        'photo' => $L('Фото', 'Фото'), 'video' => $L('Бейне', 'Видео'),
        'waiting' => $L('кезекте', 'в очереди'), 'starting' => $L('басталды…', 'начинаем…'),
        'processing' => $L('тексерілуде…', 'проверка…'), 'done' => $L('дайын', 'готово'),
        'retry' => $L('байланыс үзілді, қайталаймыз…', 'связь прервалась, повторяем…'),
        'err_type' => $L('тек JPEG, PNG, WebP, MP4, MOV, WebM', 'только JPEG, PNG, WebP, MP4, MOV, WebM'),
        'err_big' => $L('тым үлкен, шегі', 'слишком большой, лимит'),
        'err_network' => $L('Байланыс қатесі', 'Ошибка связи'), 'err_server' => $L('Сервер қатесі', 'Ошибка сервера'),
        'remove' => $L('Алып тастау', 'Убрать'),
        'confirm_delete' => $L('Жою керек пе?', 'Удалить?'),
        'confirm_delete_media' => $L('Бұл файлды жою керек пе?', 'Удалить этот файл?'),
        'deleted' => $L('Жойылды', 'Удалено'), 'saved' => $L('Сақталды', 'Сохранено'),
        'added_by' => $L('қосқан:', 'добавил(а):'),
        'avatar_set' => $L('Аватарға қою', 'На аватар'),
        'avatar_hint' => $L('Аватарды қою үшін «Өзгерту» арқылы фотодағы адамды белгілеңіз', 'Чтобы поставить аватар, отметьте человека на фото через «Изменить»'),
        'avatar_saving' => $L('Сақталуда…', 'Сохраняем…'),
        'video_unsupported' => $L('Бұл браузер бейнені ойната алмайды.', 'Этот браузер не может воспроизвести видео.'),
        'download' => $L('Жүктеп алу', 'Скачать'),
        'avatar_done' => $L('Аватар қойылды:', 'Аватар установлен:'),
        'avatar_title' => $L('Аватарға қою', 'Поставить на аватар'),
        'avatar_for' => $L('{name} аватар қою', 'Поставить аватар: {name}'),
        'avatar_choose' => $L('Тізімнен адамды таңдаңыз', 'Выберите человека из списка'),
        'avatar_chosen' => $L('Аватар қойылады:', 'Аватар будет поставлен:'),
        'avatar_tagged' => $L('фотода', 'на фото'),
        'avatar_not_tagged' => $L('бұл фотода белгіленбеген', 'на этом фото не отмечен(а)'),
        'avatar_none' => $L('Ешкім табылмады', 'Никого не найдено'),
        'avatar_open' => $L('Карточкасын ашу', 'Открыть карточку'),
        'save' => $L('Сақтау', 'Сохранить'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="/assets/js/album.js?v=7" defer></script>
<?php if ($faceReady): ?>
<link rel="stylesheet" href="/assets/css/faces.css?v=2">
<script src="/assets/js/face-recognition.js?v=2" defer></script>
<?php endif; ?>

<?php require __DIR__ . '/../layout/master_end.php'; ?>
