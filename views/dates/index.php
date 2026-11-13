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
$dtIcons = ['birthday' => 'calendar', 'death_anniversary' => 'history', 'wedding' => 'family'];

require __DIR__ . '/../layout/master_start.php';
?>

<div class="dt">
    <div class="ds-page-head">
        <div>
            <h1><?= htmlspecialchars(I18n::t('nav_dates')) ?></h1>
            <p class="sub">
                <?= $lang === 'ru'
                    ? "Ближайшие дни рождения, поминальные годовщины и юбилеи свадеб на {$daysAhead} дней вперед"
                    : "Алдағы {$daysAhead} күндегі туған күндер, ата-бабаларды еске алу және неке мерейтойлары" ?>
            </p>
        </div>
        <div class="actions">
            <!-- Выбор диапазона дней -->
            <nav class="seg" aria-label="<?= $lang === 'ru' ? 'Период' : 'Кезең' ?>">
                <a href="/dates?days=30" class="<?= $daysAhead === 30 ? 'active' : '' ?>">30 <?= $lang === 'ru' ? 'дн' : 'күн' ?></a>
                <a href="/dates?days=90" class="<?= $daysAhead === 90 ? 'active' : '' ?>">90 <?= $lang === 'ru' ? 'дн' : 'күн' ?></a>
                <a href="/dates?days=180" class="<?= $daysAhead === 180 ? 'active' : '' ?>">180 <?= $lang === 'ru' ? 'дн' : 'күн' ?></a>
                <a href="/dates?days=365" class="<?= $daysAhead === 365 ? 'active' : '' ?>">1 <?= $lang === 'ru' ? 'год' : 'жыл' ?></a>
            </nav>
            <!-- Быстрая кнопка проверки Жеті ата -->
            <button type="button" class="btn btn-primary" onclick="openZhetiAtaPageModal()">
                <svg class="ic" aria-hidden="true"><use href="#i-tamga"/></svg>
                <?= $lang === 'ru' ? 'Калькулятор «Жеті ата»' : '«Жеті ата» тексеру' ?>
            </button>
        </div>
    </div>

    <!-- Вкладки фильтрации дат -->
    <div class="seg dt-tabs">
        <button type="button" class="date-tab-btn active" onclick="switchDateFilter('all', this)">
            <?= $lang === 'ru' ? 'Все события' : 'Барлық күндер' ?> <span class="muted"><?= count($allEvents) ?></span>
        </button>
        <button type="button" class="date-tab-btn" onclick="switchDateFilter('birthday', this)">
            <?= $lang === 'ru' ? 'Дни рождения' : 'Туған күндер' ?> <span class="muted"><?= count($birthdays) ?></span>
        </button>
        <button type="button" class="date-tab-btn" onclick="switchDateFilter('death_anniversary', this)">
            <?= $lang === 'ru' ? 'Дни памяти' : 'Еске алу күндері' ?> <span class="muted"><?= count($anniversaries) ?></span>
        </button>
        <button type="button" class="date-tab-btn" onclick="switchDateFilter('wedding', this)">
            <?= $lang === 'ru' ? 'Годовщины свадеб' : 'Үйленген күндер' ?> <span class="muted"><?= count($weddings) ?></span>
        </button>
    </div>

    <?php if (empty($allEvents)): ?>
        <div class="island pp-empty">
            <svg class="ic" aria-hidden="true"><use href="#i-calendar"/></svg>
            <b><?= $lang === 'ru' ? "На ближайшие {$daysAhead} дней событий не найдено" : "Алдағы {$daysAhead} күнге белгіленген даталар жоқ" ?></b>
            <a href="/dates?days=365" class="btn btn-sm">
                <?= $lang === 'ru' ? 'Посмотреть события на целый год' : 'Бүкіл 1 жылдың оқиғаларын көру' ?>
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
            ?>
                <article class="island event-card dt-card<?= $isToday ? ' dt-today' : '' ?>" data-type="<?= htmlspecialchars($type) ?>">
                    <div class="dt-card-head">
                        <span class="badge<?= $type === 'death_anniversary' ? ' badge-accent' : '' ?>">
                            <svg class="ic dt-ic" aria-hidden="true"><use href="#i-<?= $icon ?>"/></svg>
                            <?= htmlspecialchars($lang === 'ru' ? ($ev['event_name_ru'] ?? '') : ($ev['event_name_kk'] ?? '')) ?>
                        </span>
                        <?php if ($isToday): ?>
                            <span class="badge badge-accent"><b><?= $lang === 'ru' ? 'Сегодня' : 'Бүгін' ?></b></span>
                        <?php elseif ($isTomorrow): ?>
                            <span class="badge badge-accent"><?= $lang === 'ru' ? 'Завтра' : 'Ертең' ?></span>
                        <?php else: ?>
                            <span class="badge"><?= $lang === 'ru' ? "Через {$daysUntil} дн." : "{$daysUntil} күннен соң" ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="person dt-person">
                        <?php if ($type === 'wedding'): ?>
                            <span class="avatar"><svg class="ic" aria-hidden="true"><use href="#i-family"/></svg></span>
                        <?php elseif ($avatarPath): ?>
                            <span class="avatar<?= $type === 'death_anniversary' ? ' deceased' : '' ?>"><img src="<?= htmlspecialchars($avatarPath) ?>" alt="<?= htmlspecialchars($ev['full_name'] ?? '') ?>"></span>
                        <?php else: ?>
                            <span class="avatar<?= $type === 'death_anniversary' ? ' deceased' : '' ?>"><?= htmlspecialchars($initials) ?></span>
                        <?php endif; ?>

                        <div>
                            <?php if ($type === 'wedding'): ?>
                                <div class="dt-names">
                                    <?php if (!empty($ev['husband_id'])): ?>
                                        <a href="/person/<?= (int)$ev['husband_id'] ?>" class="dt-link"><?= htmlspecialchars(trim(($ev['husband']['first_name'] ?? '') . ' ' . ($ev['husband']['last_name'] ?? ''))) ?></a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($ev['husband']['first_name'] ?? '—') ?>
                                    <?php endif; ?>
                                    <span class="muted">&amp;</span>
                                    <?php if (!empty($ev['wife_id'])): ?>
                                        <a href="/person/<?= (int)$ev['wife_id'] ?>" class="dt-link"><?= htmlspecialchars(trim(($ev['wife']['first_name'] ?? '') . ' ' . ($ev['wife']['last_name'] ?? ''))) ?></a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($ev['wife']['first_name'] ?? '—') ?>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <a href="/person/<?= (int)($ev['person_id'] ?? 0) ?>" class="name dt-link" title="<?= htmlspecialchars($ev['full_name'] ?? '') ?>"
                                   <?php if ($type === 'death_anniversary'): ?>data-tip="<?= $lang === 'ru' ? 'Аруағы разы болсын' : 'Жатқан жері жайлы болсын' ?>"<?php endif; ?>>
                                    <?= htmlspecialchars($ev['full_name'] ?? '') ?>
                                </a>
                            <?php endif; ?>

                            <?php
                                $affText = ($lang === 'ru') ? ($ev['affiliation_ru'] ?? '') : ($ev['affiliation_kk'] ?? '');
                                if ($affText !== '' && $type !== 'wedding'):
                            ?>
                                <div class="meta" title="<?= htmlspecialchars($affText) ?>"><?= htmlspecialchars($affText) ?></div>
                            <?php endif; ?>

                            <div class="dt-what">
                                <?php if ($type === 'birthday'): ?>
                                    <?= (int)($ev['age_turning'] ?? 0) ?> <?= $lang === 'ru' ? 'лет исполняется' : 'жасқа толады' ?>
                                <?php elseif ($type === 'death_anniversary'): ?>
                                    <span class="pc-accent"><?= htmlspecialchars($lang === 'ru' ? ($ev['memorial_label_ru'] ?? '') : ($ev['memorial_label_kk'] ?? '')) ?></span>
                                <?php elseif ($type === 'wedding'): ?>
                                    <?= htmlspecialchars($lang === 'ru' ? ($ev['jubilee_ru'] ?? '') : ($ev['jubilee_kk'] ?? '')) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Нижняя полоска: Дата и Родство пользователю -->
                    <div class="dt-foot">
                        <span class="dt-date">
                            <svg class="ic dt-ic" aria-hidden="true"><use href="#i-calendar"/></svg>
                            <?= htmlspecialchars((string)($lang === 'ru' ? ($ev['formatted_date_ru'] ?? $ev['event_date']) : ($ev['formatted_date_kk'] ?? $ev['event_date']))) ?>
                        </span>
                        <?php
                            $kinshipText = ($lang === 'ru') ? ($ev['kinship_ru'] ?? '') : ($ev['kinship_kk'] ?? '');
                            if ($kinshipText !== ''):
                        ?>
                            <span class="badge" title="<?= htmlspecialchars($kinshipText) ?>"><?= htmlspecialchars($kinshipText) ?></span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Модальное окно Жеті ата — белый остров поверх затемнения -->
<div id="zhetiAtaModal" class="modal-backdrop" style="display:none;">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="zhetiAtaTitle" style="max-width: 640px;">
        <div class="modal-head">
            <h3 id="zhetiAtaTitle"><?= $lang === 'ru' ? 'Проверка традиции «Жеті ата»' : '«Жеті ата» тексеру дәстүрі' ?></h3>
            <button type="button" class="btn btn-ghost btn-icon" onclick="closeZhetiAtaPageModal()" aria-label="Жабу / Закрыть">
                <svg class="ic"><use href="#i-x"/></svg>
            </button>
        </div>
        <form onsubmit="handleZhetiAtaCalc(event)">
            <div class="modal-body">
                <p class="muted small">
                    <?= $lang === 'ru' ? 'Выберите двух людей из шежіре, чтобы проверить наличие общего предка по мужской линии до 7 колен.' : 'Шежіредегі екі адамды таңдап, олардың 7 буын аралығында ортақ атасының бар-жоғын тексеріңіз.' ?>
                </p>
                <div class="form-row">
                    <div class="field">
                        <label for="zhetiIdA">1-тұлға / 1-я персона (ID)</label>
                        <input type="number" id="zhetiIdA" class="input" value="<?= (int)($userPersonId ?? 6) ?>" required>
                    </div>
                    <div class="field">
                        <label for="zhetiIdB">2-тұлға / 2-я персона (ID)</label>
                        <input type="number" id="zhetiIdB" class="input" value="25" required>
                    </div>
                </div>
                <div id="zhetiResultBox" class="alert dt-result" style="display:none;"></div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" onclick="closeZhetiAtaPageModal()">Жабу / Закрыть</button>
                <button type="submit" class="btn btn-primary"><?= $lang === 'ru' ? 'Проверить 7 колен' : '7 буынды тексеру' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
    function openZhetiAtaPageModal() {
        document.getElementById('zhetiAtaModal').style.display = 'flex';
    }

    function closeZhetiAtaPageModal() {
        document.getElementById('zhetiAtaModal').style.display = 'none';
    }

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

    async function handleZhetiAtaCalc(e) {
        e.preventDefault();
        const a = document.getElementById('zhetiIdA').value;
        const b = document.getElementById('zhetiIdB').value;
        const box = document.getElementById('zhetiResultBox');

        box.className = 'alert dt-result';
        box.style.display = 'flex';
        box.textContent = 'Есептелуде...';

        try {
            const res = await fetch(`/api/zheti-ata?a=${encodeURIComponent(a)}&b=${encodeURIComponent(b)}`);
            const data = await res.json();
            if (data && data.success) {
                const z = data.zheti_ata;
                const isWithin = z.is_within_7_generations;
                box.className = 'alert dt-result ' + (isWithin ? 'error' : 'dt-ok');
                box.innerHTML = `
                    <svg class="ic" aria-hidden="true"><use href="#i-${isWithin ? 'alert' : 'check'}"/></svg>
                    <div>
                        <div class="dt-result-title">
                            ${isWithin ? 'Жеті атаға тола қоймаған (Некеге тыйым салынады)' : 'Жеті атадан асқан (Құда болуға рұқсат)'}
                        </div>
                        <div>${dtEsc(z.description_kk || '')}</div>
                        ${z.common_ancestor ? `<div class="small">Ортақ баба: <strong>${dtEsc(z.common_ancestor.first_name)} ${dtEsc(z.common_ancestor.last_name || '')}</strong></div>` : ''}
                    </div>
                `;
            } else {
                box.className = 'alert dt-result error';
                box.textContent = 'Қате: ' + ((data && data.error) || 'Есептеу мүмкін болмады');
            }
        } catch (err) {
            box.className = 'alert dt-result error';
            box.textContent = 'Сұраныс қатесі: ' + err.message;
        }
    }
</script>

<?php
require __DIR__ . '/../layout/master_end.php';
?>
