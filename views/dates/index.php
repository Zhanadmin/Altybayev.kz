<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
$title = I18n::t('nav_dates');
$activeNav = 'dates';
$dsThemeReady = true; // TASK-008: страница на цветах дизайн-системы — доступна тёмная тема

$birthdays = $birthdays ?? [];
$anniversaries = $anniversaries ?? [];
$weddings = $weddings ?? [];
$allEvents = $allEvents ?? [];
$daysAhead = (int)($daysAhead ?? 90);

// TASK-008: линейные иконки вместо эмодзи по типу события
$dtIcons = ['birthday' => 'calendar', 'death_anniversary' => 'candle', 'wedding' => 'family'];

require __DIR__ . '/../layout/master_start.php';
?>

<div class="dt">
    <div class="ds-page-head">
        <div>
            <h1><?= htmlspecialchars(I18n::t('nav_dates')) ?></h1>
            <p class="sub">
                <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es')
                    ? "Ближайшие дни рождения, поминальные годовщины и юбилеи свадеб на {$daysAhead} дней вперед"
                    : "Алдағы {$daysAhead} күндегі туған күндер, ата-бабаларды еске алу және неке мерейтойлары" ?>
            </p>
        </div>
        <div class="actions">
            <!-- Выбор диапазона дней -->
            <nav class="seg" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Период' : 'Кезең' ?>">
                <a href="/dates?days=30" class="<?= $daysAhead === 30 ? 'active' : '' ?>">30 <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'дн' : 'күн' ?></a>
                <a href="/dates?days=90" class="<?= $daysAhead === 90 ? 'active' : '' ?>">90 <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'дн' : 'күн' ?></a>
                <a href="/dates?days=180" class="<?= $daysAhead === 180 ? 'active' : '' ?>">180 <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'дн' : 'күн' ?></a>
                <a href="/dates?days=365" class="<?= $daysAhead === 365 ? 'active' : '' ?>">1 <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'год' : 'жыл' ?></a>
            </nav>
            <!-- Быстрая кнопка проверки Жеті ата -->
            <a href="/zheti-ata" class="btn btn-primary">
                <svg class="ic" aria-hidden="true"><use href="#i-tamga"/></svg>
                <?= $lang === 'ru' ? 'Жеті ата' : ($lang === 'en' ? 'Zheti ata' : ($lang === 'es' ? 'Zheti ata' : 'Жеті ата')) ?>
            </a>
        </div>
    </div>

    <!-- Вкладки фильтрации дат -->
    <div class="seg dt-tabs">
        <button type="button" class="date-tab-btn active" onclick="switchDateFilter('all', this)">
            <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Все события' : 'Барлық күндер' ?> <span class="muted"><?= count($allEvents) ?></span>
        </button>
        <button type="button" class="date-tab-btn" onclick="switchDateFilter('birthday', this)">
            <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Дни рождения' : 'Туған күндер' ?> <span class="muted"><?= count($birthdays) ?></span>
        </button>
        <button type="button" class="date-tab-btn" onclick="switchDateFilter('death_anniversary', this)">
            <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Дни памяти' : 'Еске алу күндері' ?> <span class="muted"><?= count($anniversaries) ?></span>
        </button>
        <button type="button" class="date-tab-btn" onclick="switchDateFilter('wedding', this)">
            <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Годовщины свадеб' : 'Үйленген күндер' ?> <span class="muted"><?= count($weddings) ?></span>
        </button>
    </div>

    <?php if (empty($allEvents)): ?>
        <div class="island pp-empty">
            <svg class="pp-sprout" viewBox="0 0 140 90" aria-hidden="true" focusable="false"><use href="#o-sprout"/></svg>
            <b><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? "На ближайшие {$daysAhead} дней событий не найдено" : "Алдағы {$daysAhead} күнге белгіленген даталар жоқ" ?></b>
            <a href="/dates?days=365" class="btn btn-sm">
                <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Посмотреть события на целый год' : 'Бүкіл 1 жылдың оқиғаларын көру' ?>
            </a>
        </div>
    <?php else: ?>
        <!-- Сетка карточек дат -->
        <div id="eventsGrid" class="dt-grid">
            <?php foreach ($allEvents as $ev):
                $type = $ev['event_type'] ?? 'birthday';
                $isToday = !empty($ev['is_today']);
                $isTomorrow = !empty($ev['is_tomorrow']);
                $daysUntil = (int)($ev['days_until'] ?? 0);

                $person = $ev['person'] ?? null;
                $avatarPath = $ev['avatar_path'] ?? ($person['avatar_path'] ?? null);
                $initials = $ev['initials'] ?? 'Ш';
                $icon = $dtIcons[$type] ?? 'calendar';
                // TASK-008 (дополнение 17:11): пол на аватаре; нет данных о поле — без цвета
                $dtGender = (string)($ev['gender'] ?? ($ev['person']['gender'] ?? ''));
                $dtG = $dtGender === 'M' ? ' is-m' : ($dtGender === 'F' ? ' is-f' : '');
            ?>
                <article class="island event-card dt-card<?= $isToday ? ' dt-today' : '' ?>" data-type="<?= htmlspecialchars($type) ?>">
                    <div class="dt-card-head">
                        <span class="badge<?= $type === 'death_anniversary' ? ' badge-accent' : '' ?>">
                            <svg class="ic dt-ic" aria-hidden="true"><use href="#i-<?= $icon ?>"/></svg>
                            <?= htmlspecialchars(($lang === 'ru' || $lang === 'en' || $lang === 'es') ? ($ev['event_name_ru'] ?? '') : ($ev['event_name_kk'] ?? '')) ?>
                        </span>
                        <?php if ($isToday): ?>
                            <span class="badge badge-accent"><b><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Сегодня' : 'Бүгін' ?></b></span>
                        <?php elseif ($isTomorrow): ?>
                            <span class="badge badge-accent"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Завтра' : 'Ертең' ?></span>
                        <?php else: ?>
                            <span class="badge"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? "Через {$daysUntil} дн." : "{$daysUntil} күннен соң" ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="person dt-person">
                        <?php if ($type === 'wedding'): ?>
                            <span class="avatar dt-rings"><svg class="couple-rings" aria-hidden="true" focusable="false"><use href="#o-rings"/></svg></span>
                        <?php elseif ($avatarPath): ?>
                            <span class="avatar<?= $dtG ?><?= $type === 'death_anniversary' ? ' deceased' : '' ?>"><img src="<?= htmlspecialchars($avatarPath) ?>" alt="<?= htmlspecialchars($ev['full_name'] ?? '') ?>"></span>
                        <?php else: ?>
                            <span class="avatar<?= $dtG ?><?= $type === 'death_anniversary' ? ' deceased' : '' ?>"><?= htmlspecialchars($initials) ?></span>
                        <?php endif; ?>

                        <div>
                            <?php if ($type === 'wedding'): ?>
                                <div class="dt-names">
                                    <?php if (!empty($ev['husband_id'])): ?>
                                        <a href="/person/<?= (int)$ev['husband_id'] ?>" class="dt-link"><?= htmlspecialchars(trim(($ev['husband']['first_name'] ?? '') . ' ' . ($ev['husband']['last_name'] ?? ''))) ?></a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($ev['husband']['first_name'] ?? '—') ?>
                                    <?php endif; ?>
                                    <span class="muted dt-and">&amp;</span>
                                    <?php if (!empty($ev['wife_id'])): ?>
                                        <a href="/person/<?= (int)$ev['wife_id'] ?>" class="dt-link"><?= htmlspecialchars(trim(($ev['wife']['first_name'] ?? '') . ' ' . ($ev['wife']['last_name'] ?? ''))) ?></a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($ev['wife']['first_name'] ?? '—') ?>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <a href="/person/<?= (int)($ev['person_id'] ?? 0) ?>" class="name dt-link" title="<?= htmlspecialchars($ev['full_name'] ?? '') ?>"
                                   <?php if ($type === 'death_anniversary'): ?>data-tip="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Аруағы разы болсын' : 'Жатқан жері жайлы болсын' ?>"<?php endif; ?>>
                                    <?= htmlspecialchars($ev['full_name'] ?? '') ?>
                                </a>
                            <?php endif; ?>

                            <?php
                                $affText = (($lang === 'ru' || $lang === 'en' || $lang === 'es')) ? ($ev['affiliation_ru'] ?? '') : ($ev['affiliation_kk'] ?? '');
                                if ($affText !== '' && $type !== 'wedding'):
                            ?>
                                <div class="meta" title="<?= htmlspecialchars($affText) ?>"><?= htmlspecialchars($affText) ?></div>
                            <?php endif; ?>

                            <div class="dt-what">
                                <?php if ($type === 'birthday'): ?>
                                    <?= (int)($ev['age_turning'] ?? 0) ?> <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'лет исполняется' : 'жасқа толады' ?>
                                <?php elseif ($type === 'death_anniversary'): ?>
                                    <span class="pc-accent"><?= htmlspecialchars(($lang === 'ru' || $lang === 'en' || $lang === 'es') ? ($ev['memorial_label_ru'] ?? '') : ($ev['memorial_label_kk'] ?? '')) ?></span>
                                <?php elseif ($type === 'wedding'): ?>
                                    <?= htmlspecialchars(($lang === 'ru' || $lang === 'en' || $lang === 'es') ? ($ev['anniversary_text_ru'] ?? '') : ($ev['anniversary_text_kk'] ?? '')) ?>
                                    <div class="meta"><?= htmlspecialchars(($lang === 'ru' || $lang === 'en' || $lang === 'es') ? ($ev['jubilee_ru'] ?? '') : ($ev['jubilee_kk'] ?? '')) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Нижняя полоска: Дата и Родство пользователю -->
                    <div class="dt-foot">
                        <span class="dt-date">
                            <svg class="ic dt-ic" aria-hidden="true"><use href="#i-calendar"/></svg>
                            <?= htmlspecialchars((string)(($lang === 'ru' || $lang === 'en' || $lang === 'es') ? ($ev['formatted_date_ru'] ?? $ev['event_date']) : ($ev['formatted_date_kk'] ?? $ev['event_date']))) ?>
                        </span>
                        <?php
                            $kinshipText = (($lang === 'ru' || $lang === 'en' || $lang === 'es')) ? ($ev['kinship_ru'] ?? '') : ($ev['kinship_kk'] ?? '');
                            if ($kinshipText !== ''):
                        ?>
                            <span class="badge" title="<?= htmlspecialchars($kinshipText) ?>"><?= htmlspecialchars($kinshipText) ?></span>
                        <?php endif; ?>
                        <?php
                            // TASK-018: «Создать мероприятие» из даты — откроется форма с заполненным названием, датой и участниками
                            $evKind = ['birthday' => 'birthday', 'death_anniversary' => 'memorial', 'wedding' => 'wedding'][$type] ?? 'other';
                            $evQuery = ['kind' => $evKind, 'date' => (string)($ev['event_date'] ?? '')];
                            if ($type === 'wedding') {
                                $evQuery['marriage_id'] = (int)($ev['marriage_id'] ?? 0);
                            } else {
                                $evQuery['person_id'] = (int)($ev['person_id'] ?? 0);
                            }
                        ?>
                        <a class="btn btn-sm btn-ghost" href="/events?<?= htmlspecialchars(http_build_query($evQuery)) ?>"
                           data-tip="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Создать мероприятие с фотоальбомом' : 'Фотоальбомы бар іс-шара құру' ?>">
                            <svg class="ic dt-ic" aria-hidden="true"><use href="#i-camera"/></svg><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Мероприятие' : 'Іс-шара' ?>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    // ALT-089: «Жеті ата» теперь открывается на странице дерева (/tree?zheti=1)
    function switchDateFilter(filterType, btn) {
        document.querySelectorAll('.date-tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        document.querySelectorAll('.event-card').forEach(card => {
            const cType = card.getAttribute('data-type');
            card.style.display = (filterType === 'all' || cType === filterType) ? '' : 'none';
        });
    }

    function dtEsc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

</script>

<?php
require __DIR__ . '/../layout/master_end.php';
?>
