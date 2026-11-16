<?php
declare(strict_types=1);

use App\Core\I18n;

/** @var array $user @var bool $ready @var array $summary @var array $people @var bool $enabled @var bool $isAdmin @var string $csrf */
$lang = I18n::lang();
$ru = ($lang === 'ru');
$L = fn(string $kk, string $r) => $ru ? $r : $kk;

$title = $L('Тұлғалар', 'Лица');
$activeNav = 'faces';
$dsThemeReady = true;

$unlabeled = $summary['unlabeled'] ?? [];
$labeled = $summary['labeled'] ?? [];

require __DIR__ . '/../layout/master_start.php';
?>
<link rel="stylesheet" href="/assets/css/faces.css?v=1">

<div class="faces">
    <div class="ds-page-head">
        <div>
            <h1><?= htmlspecialchars($title) ?></h1>
            <p class="sub"><?= $L('Сайт бірдей беттерді өзі топтастырады: топқа бір рет адамды белгілесеңіз — оның барлық фотосы карточкасына байланады.',
                                   'Сайт сам группирует одинаковые лица: один раз укажите человека в группе — и все его фото привяжутся к карточке.') ?></p>
        </div>
        <?php if ($isAdmin): ?>
            <div class="actions">
                <label class="btn btn-ghost" style="gap:8px">
                    <input type="checkbox" id="faceEnabled" <?= $enabled ? 'checked' : '' ?> style="width:16px;height:16px">
                    <?= $L('Бет тану қосулы', 'Распознавание лиц включено') ?>
                </label>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$ready): ?>
        <div class="island faces-empty" style="margin-top:var(--sp-4)">
            <b><?= $L('Бет тану әлі қосылмаған', 'Распознавание лиц ещё не включено') ?></b>
            <div class="small"><?= $L('Миграцияны орындаңыз (database/migrate.php)', 'Выполните миграцию (database/migrate.php)') ?></div>
        </div>
    <?php elseif (!$enabled && !$isAdmin): ?>
        <div class="island faces-empty" style="margin-top:var(--sp-4)">
            <b><?= $L('Бет тану әкімші тарапынан өшірілген', 'Распознавание лиц отключено администратором') ?></b>
        </div>
    <?php elseif (!$unlabeled && !$labeled): ?>
        <div class="island faces-empty" style="margin-top:var(--sp-4)">
            <b><?= $L('Әзірге танылған беттер жоқ', 'Пока нет распознанных лиц') ?></b>
            <div class="small"><?= $L('Альбомды ашып «Беттерді табу» батырмасын басыңыз', 'Откройте альбом и нажмите «Найти лица»') ?></div>
        </div>
    <?php else: ?>
        <?php if ($unlabeled): ?>
            <section class="faces-section">
                <h2><?= $L('Қол қойылмаған топтар', 'Неподписанные группы') ?></h2>
                <div class="faces-grid">
                    <?php foreach ($unlabeled as $c): $f = $c['face']; ?>
                        <div class="faces-card" data-cluster="<?= (int)$c['cluster_id'] ?>" data-face="<?= (int)$f['id'] ?>">
                            <div class="fc-photo">
                                <img src="/album/media?id=<?= (int)$f['media_id'] ?>&amp;v=thumb" alt="" loading="lazy">
                            </div>
                            <div class="fc-body">
                                <div class="fc-name"><?= $L('Топ', 'Группа') ?> #<?= (int)$c['cluster_id'] ?></div>
                                <div class="fc-count"><?= (int)$c['count'] ?> <?= $L('фото', 'фото') ?></div>
                                <div class="fc-actions">
                                    <button type="button" class="btn btn-primary" data-act="assign"><?= $L('Қол қою', 'Подписать') ?></button>
                                    <button type="button" class="btn btn-ghost" data-act="remove"><?= $L('Алып тастау', 'Убрать') ?></button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($labeled): ?>
            <section class="faces-section">
                <h2><?= $L('Қол қойылған', 'Подписанные') ?></h2>
                <div class="faces-grid">
                    <?php foreach ($labeled as $c): $f = $c['face']; ?>
                        <div class="faces-card" data-cluster="<?= (int)$c['cluster_id'] ?>" data-face="<?= (int)$f['id'] ?>" data-person="<?= (int)$c['person_id'] ?>">
                            <div class="fc-photo">
                                <img src="/album/media?id=<?= (int)$f['media_id'] ?>&amp;v=thumb" alt="" loading="lazy">
                            </div>
                            <div class="fc-body">
                                <div class="fc-name"><a href="/person/<?= (int)$c['person_id'] ?>"><?= htmlspecialchars($c['name']) ?></a></div>
                                <div class="fc-count"><?= (int)$c['count'] ?> <?= $L('фото', 'фото') ?></div>
                                <div class="fc-actions">
                                    <button type="button" class="btn btn-ghost" data-act="unassign"><?= $L('Қолтаңбаны алу', 'Снять подпись') ?></button>
                                    <?php if ($isAdmin): ?>
                                        <button type="button" class="btn btn-ghost btn-danger" data-act="delete-person"><?= $L('Өшіру', 'Удалить отпечатки') ?></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Подписать группу -->
<div id="faceAssignModal" class="modal-backdrop" style="display:none;">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="faceAssignTitle" style="max-width:480px">
        <div class="modal-head">
            <h3 id="faceAssignTitle"><?= $L('Топқа адамды таңдаңыз', 'Выберите человека для группы') ?></h3>
            <button type="button" class="btn btn-ghost btn-icon" data-close-modal aria-label="<?= htmlspecialchars($L('Жабу', 'Закрыть')) ?>"><svg class="ic"><use href="#i-x"/></svg></button>
        </div>
        <div class="modal-body">
            <div class="field">
                <input id="faceAssignInput" class="input" type="search" list="facePeopleDl" autocomplete="off" placeholder="<?= htmlspecialchars($L('Атын жазыңыз…', 'Начните вводить имя…')) ?>">
                <input type="hidden" id="faceAssignCluster" value="">
            </div>
            <datalist id="facePeopleDl">
                <?php foreach ($people as $p): ?><option value="<?= htmlspecialchars($p['name']) ?>"></option><?php endforeach; ?>
            </datalist>
            <p class="small muted" style="margin-top:8px"><?= $L('Таңдалған адамның барлық фотосы оның карточкасына байланады.', 'Все фото выбранного человека привяжутся к его карточке.') ?></p>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn" data-close-modal><?= $L('Бас тарту', 'Отмена') ?></button>
            <button type="button" class="btn btn-primary" id="faceAssignSave"><?= $L('Сақтау', 'Сохранить') ?></button>
        </div>
    </div>
</div>

<script>
window.FACES = <?= json_encode([
    'csrf'     => $csrf,
    'lang'     => $lang,
    'isAdmin'  => (bool)$isAdmin,
    'enabled'  => (bool)$enabled,
    'people'   => $people,
    't'        => [
        'saved' => $L('Сақталды', 'Сохранено'), 'removed' => $L('Жойылды', 'Удалено'),
        'error' => $L('Қате', 'Ошибка'), 'confirm_delete' => $L('Өшіру керек пе?', 'Удалить?'),
        'person_not_found' => $L('Адам табылмады', 'Человек не найден'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="/assets/js/faces.js?v=1" defer></script>

<?php require __DIR__ . '/../layout/master_end.php'; ?>
