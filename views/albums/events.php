<?php
declare(strict_types=1);

use App\Core\I18n;

/** @var array $events @var array $people @var array $families @var bool $canCreate @var ?array $prefill */
$lang = I18n::lang();
$ru = ($lang === 'ru');
$L = fn(string $kk, string $r) => $ru ? $r : $kk;

$title = $L('Іс-шаралар', 'Мероприятия');
$activeNav = 'events';
$dsThemeReady = true;

$today = date('Y-m-d');
$upcoming = array_values(array_filter($events, fn($e) => !empty($e['event_date']) && $e['event_date'] >= $today));
$past = array_values(array_filter($events, fn($e) => empty($e['event_date']) || $e['event_date'] < $today));
usort($upcoming, fn($a, $b) => strcmp((string)$a['event_date'], (string)$b['event_date']));

require __DIR__ . '/../layout/master_start.php';
?>
<link rel="stylesheet" href="/assets/css/album.css?v=5">

<div class="al">
    <div class="ds-page-head">
        <div>
            <h1><?= htmlspecialchars($title) ?></h1>
            <p class="sub"><?= $L('Туған күн, той, еске алу — әр іс-шараның өз альбомы бар. Туыстар фото мен бейнені осында қосады.',
                                   'Дни рождения, тои, поминальные дни — у каждого мероприятия свой альбом. Родственники добавляют сюда фото и видео.') ?></p>
        </div>
        <div class="actions">
            <?php if ($canCreate): ?>
                <button type="button" class="btn btn-primary" data-open-modal="evNewModal">
                    <svg class="ic" aria-hidden="true"><use href="#i-plus"/></svg><?= $L('Іс-шара құру', 'Создать мероприятие') ?>
                </button>
            <?php endif; ?>
            <a class="btn btn-ghost" href="/dates"><svg class="ic" aria-hidden="true"><use href="#i-calendar"/></svg><?= $L('Есте қаларлық күндер', 'Памятные даты') ?></a>
        </div>
    </div>

    <?php if (!$events): ?>
        <div class="island al-empty" style="margin-top:var(--sp-4)">
            <svg viewBox="0 0 140 90" aria-hidden="true" focusable="false"><use href="#o-sprout"/></svg>
            <div><b><?= $L('Әзірге іс-шара жоқ', 'Пока нет мероприятий') ?></b></div>
            <div class="small"><?= $L('«Іс-шара құру» батырмасын басыңыз немесе «Есте қаларлық күндер» бетінен жасаңыз', 'Нажмите «Создать мероприятие» или создайте его из «Памятных дат»') ?></div>
        </div>
    <?php endif; ?>

    <?php foreach ([[$upcoming, $L('Алдағы', 'Предстоящие')], [$past, $L('Өткен', 'Прошедшие')]] as [$list, $sectionTitle]): if (!$list) continue; ?>
        <div class="al-section-title"><h2><?= htmlspecialchars($sectionTitle) ?></h2><span class="line"></span></div>
        <div class="al-cards">
            <?php foreach ($list as $e): ?>
                <a class="island al-card" href="/event/<?= (int)$e['id'] ?>">
                    <div class="al-cover">
                        <?php if (!empty($e['cover_id'])): ?>
                            <img src="/album/media?id=<?= (int)$e['cover_id'] ?>&amp;v=thumb" alt="" loading="lazy">
                        <?php else: ?>
                            <svg class="al-cover-orn" viewBox="0 0 120 120" aria-hidden="true" focusable="false"><use href="#o-rosette"/></svg>
                        <?php endif; ?>
                        <span class="al-count"><?= (int)$e['media_count'] ?></span>
                    </div>
                    <div class="al-card-body">
                        <b><?= htmlspecialchars((string)$e['title']) ?></b>
                        <span class="small"><?= !empty($e['event_date']) ? htmlspecialchars(I18n::formatDate($e['event_date'])) : $L('күні белгісіз', 'дата не указана') ?><?= !empty($e['place']) ? ' · ' . htmlspecialchars((string)$e['place']) : '' ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <?php if (\App\Core\Auth::isAdmin() && $diskFree !== null): ?>
        <p class="al-note" style="margin-top:var(--sp-5)">
            <?= $L('Сервердегі бос орын (тек әкімшіге көрінеді):', 'Свободно на сервере (видно только администратору):') ?>
            <b><?= number_format($diskFree / 1073741824, 1, ',', ' ') ?> ГБ</b>.
            <?= $L('Бейне шегі:', 'Лимит видео:') ?> <?= (int)$videoMaxMb ?> МБ.
        </p>
    <?php endif; ?>
</div>

<datalist id="alPeopleList">
    <?php foreach ($people as $p): ?><option value="<?= htmlspecialchars($p['name']) ?>"></option><?php endforeach; ?>
</datalist>

<?php if ($canCreate): ?>
<div id="evNewModal" class="modal-backdrop" style="display:<?= $prefill ? 'flex' : 'none' ?>;">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="evNewTitle" style="max-width:540px">
        <div class="modal-head">
            <h3 id="evNewTitle"><?= $L('Жаңа іс-шара', 'Новое мероприятие') ?></h3>
            <button type="button" class="btn btn-ghost btn-icon" data-close-modal aria-label="<?= htmlspecialchars($L('Жабу', 'Закрыть')) ?>"><svg class="ic"><use href="#i-x"/></svg></button>
        </div>
        <form data-api="/api/event/save">
            <input type="hidden" name="kind" value="<?= htmlspecialchars((string)($prefill['kind'] ?? 'other')) ?>">
            <input type="hidden" name="source_person_id" value="<?= (int)($prefill['person'] ?? 0) ?>">
            <div class="modal-body">
                <?php
                $ev = [
                    'title'      => $prefill['title'] ?? '',
                    'event_date' => $prefill['date'] ?? '',
                    'people'     => $prefill['people'] ?? [],
                    'families'   => $prefill['families'] ?? [],
                ];
                require __DIR__ . '/_event_fields.php';
                ?>
                <span class="al-note"><?= $L('Сақтағаннан кейін альбом ашылады — фото мен бейнені сонда қосасыз.', 'После сохранения откроется альбом — туда добавляются фото и видео.') ?></span>
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
    'csrf'   => $csrf,
    'people' => $people,
    't'      => [
        'remove' => $L('Алып тастау', 'Убрать'),
        'err_network' => $L('Байланыс қатесі', 'Ошибка связи'),
        'err_server' => $L('Сервер қатесі', 'Ошибка сервера'),
        'confirm_delete' => $L('Жою керек пе?', 'Удалить?'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="/assets/js/album.js?v=1" defer></script>

<?php require __DIR__ . '/../layout/master_end.php'; ?>
