<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
$title = I18n::t('nav_home');
$activeNav = 'home';
$dsThemeReady = true; // TASK-008: страница на цветах дизайн-системы — доступна тёмная тема

$userName = 'Туысқан'; // ALT-062: имя из Google не используем; итоговое имя из шежіре задаёт master_start.php
$userEmail = $user['email'] ?? '';
$userRole = $user['role'] ?? 'viewer';
$birthdays = $birthdays ?? [];
$anniversaries = $anniversaries ?? [];

// TASK-008: «плашка даты» (число + месяц) из даты вида ГГГГ-ММ-ДД; если дата в другом виде — плашку не показываем
$hmMonths = ($lang === 'ru' || $lang === 'en' || $lang === 'es')
    ? ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек']
    : ['қаң', 'ақп', 'нау', 'сәу', 'мам', 'мау', 'шіл', 'там', 'қыр', 'қаз', 'қар', 'жел'];
$hmDateChip = static function ($date) use ($hmMonths): ?array {
    if (!is_string($date) || !preg_match('/^\d{4}-(\d{2})-(\d{2})/', $date, $m)) {
        return null;
    }
    $month = (int)$m[1];
    if ($month < 1 || $month > 12) {
        return null;
    }
    return ['day' => $m[2], 'month' => $hmMonths[$month - 1]];
};

require __DIR__ . '/layout/master_start.php';
?>

<div class="hm">
    <!-- TASK-012: титульный лист семейной книги — крона, лента с названием, приветствие -->
    <section class="island title-page">
        <svg class="orn orn-corner tl" aria-hidden="true" focusable="false"><use href="#o-corner"/></svg>
        <svg class="orn orn-corner tr" aria-hidden="true" focusable="false"><use href="#o-corner"/></svg>
        <svg class="orn orn-corner bl" aria-hidden="true" focusable="false"><use href="#o-corner"/></svg>
        <svg class="orn orn-corner br" aria-hidden="true" focusable="false"><use href="#o-corner"/></svg>
        <!-- маленькая крона: ствол, ветви, три тюльпана -->
        <svg class="crown" viewBox="0 0 520 150" aria-hidden="true" focusable="false">
            <path d="M252,150 C254,120 256,98 258,80 L262,80 C264,98 266,120 268,150 Z" fill="currentColor"/>
            <g fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round">
                <path d="M260,96 C220,82 170,74 110,74"/><path d="M260,96 C300,82 350,74 410,74"/>
                <path d="M259,84 C240,62 214,46 188,38"/><path d="M261,84 C280,62 306,46 332,38"/><path d="M260,80 V30"/>
            </g>
            <g>
                <use href="#m-leaf" x="96" y="56" width="28" height="18" transform="rotate(-160 110 65)"/><use href="#m-leaf" x="122" y="66" width="26" height="16" transform="rotate(30 135 74)"/>
                <use href="#m-leaf" x="150" y="58" width="26" height="16" transform="rotate(-140 163 66)"/><use href="#m-leaf" x="398" y="56" width="28" height="18" transform="rotate(-20 412 65)"/>
                <use href="#m-leaf" x="372" y="66" width="26" height="16" transform="rotate(150 385 74)"/><use href="#m-leaf" x="344" y="58" width="26" height="16" transform="rotate(-40 357 66)"/>
                <use href="#m-leafG" x="196" y="76" width="22" height="14" transform="rotate(160 207 83)"/><use href="#m-leafG" x="302" y="76" width="22" height="14" transform="rotate(20 313 83)"/>
                <use href="#m-leaf" x="200" y="40" width="22" height="14" transform="rotate(-150 211 47)"/><use href="#m-leaf" x="298" y="40" width="22" height="14" transform="rotate(-30 309 47)"/>
            </g>
            <use href="#o-tulip" x="172" y="0" width="34" height="68"/><use href="#o-tulip" x="243" y="-8" width="34" height="68"/><use href="#o-tulip" x="314" y="0" width="34" height="68"/>
        </svg>
        <div class="ribbon-wrap"><h1 class="ribbon ribbon-lg"><?= htmlspecialchars($siteTitle) ?></h1></div>
        <p class="lead"><?= htmlspecialchars($greetingText) ?></p>
        <p class="hm-intro">
            <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es')
                ? 'Добро пожаловать в закрытую систему родословной. Здесь бережно сохраняется преемственность поколений, история семьи, памятные даты и воспоминания.'
                : 'Әулет шежіресінің жабық отбасылық порталына қош келдіңіз. Мұнда ата-бабалар тарихы, ұрпақтар сабақтастығы, естеліктер мен маңызды күндер сақталады.' ?>
        </p>
        <hr class="oyu-rule">
        <div class="actions">
            <a href="/persons" class="btn">
                <svg class="ic" aria-hidden="true"><use href="#i-users"/></svg><?= htmlspecialchars(I18n::t('nav_persons')) ?>
            </a>
            <a href="/tree" class="btn btn-primary">
                <svg class="ic" aria-hidden="true"><use href="#i-tree"/></svg><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Открыть шежире' : 'Шежірені ашу' ?>
            </a>
        </div>
    </section>

    <!-- Жылдам сілтемелер (Быстрые ссылки) — «листы» книги -->
    <div class="hm-stats">
        <a href="/persons" class="stat-leaf hm-link">
            <svg class="ic" aria-hidden="true"><use href="#i-users"/></svg>
            <div class="value"><?= \App\Services\PeopleCountService::people() ?></div>
            <div class="label"><?= htmlspecialchars(I18n::t('nav_persons')) ?></div>
            <div class="note"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'человек, поиск, карточки' : 'тұлға, іздеу, парақшалар' ?></div>
        </a>
        <a href="/families" class="stat-leaf hm-link">
            <svg class="ic" aria-hidden="true"><use href="#i-family"/></svg>
            <div class="value"><?= \App\Services\PeopleCountService::families() ?></div>
            <div class="label"><?= htmlspecialchars(I18n::t('nav_families')) ?></div>
            <div class="note"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'семья, союзы и дети' : 'шаңырақ, некелер және балалар' ?></div>
        </a>
        <a href="/tree" class="stat-leaf hm-link">
            <svg class="ic" aria-hidden="true"><use href="#i-tree"/></svg>
            <div class="value hm-value-text">Өңдеу · Орман</div>
            <div class="label"><?= htmlspecialchars(I18n::t('nav_tree')) ?></div>
            <div class="note"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Виды: Өңдеу, Шежіре-Орман' : 'Өңдеу және Шежіре-Орман түрлері' ?></div>
        </a>
        <a href="/zheti-ata" class="stat-leaf hm-link"><?php /* ALT-091: сразу «Жеті ата», без перехода через «Памятные даты» */ ?>
            <svg class="ic" aria-hidden="true"><use href="#i-calendar"/></svg>
            <div class="value hm-value-text">Жеті ата</div>
            <div class="label"><?= htmlspecialchars(I18n::t('nav_dates')) ?></div>
            <div class="note"><?= htmlspecialchars(I18n::t('home_zheti_note')) ?></div>
        </a>
    </div>

    <!-- Ближайшие дни рождения и памятные даты -->
    <div class="grid grid-2 hm-lists">

        <!-- 1. Дни рождения -->
        <section class="island page">
            <div class="sec-title">
                <svg class="orn orn-branch" aria-hidden="true" focusable="false"><use href="#o-branch"/></svg>
                <h3><?= htmlspecialchars(I18n::t('upcoming_birthdays')) ?></h3>
                <a href="/dates" class="more"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Все даты' : 'Барлығы' ?></a>
            </div>

            <?php if (empty($birthdays)): ?>
                <div class="empty-state compact">
                    <svg viewBox="0 0 140 90" aria-hidden="true" focusable="false"><use href="#o-sprout"/></svg>
                    <p><?= htmlspecialchars(I18n::t('no_upcoming_birthdays')) ?></p>
                </div>
            <?php else: ?>
                <div class="list">
                    <?php foreach (array_slice($birthdays, 0, 5) as $b):
                        $isToday = ($b['days_until'] === 0);
                        $pName = trim(($b['person']['last_name'] ?? '') . ' ' . ($b['person']['first_name'] ?? ''));
                        $chip = $hmDateChip($b['event_date'] ?? $b['birth_date'] ?? null);
                    ?>
                        <div class="list-row<?= $isToday ? ' hm-today' : '' ?>">
                            <?php if ($chip !== null): ?>
                                <div class="date-chip"><b><?= htmlspecialchars($chip['day']) ?></b><span><?= htmlspecialchars($chip['month']) ?></span></div>
                            <?php endif; ?>
                            <a href="/person/<?= (int)$b['person']['id'] ?>" class="person hm-person">
                                <span class="avatar<?= (($b['person']['gender'] ?? '') === 'F') ? ' is-f' : ((($b['person']['gender'] ?? '') === 'M') ? ' is-m' : '') ?>"><?= htmlspecialchars(mb_substr((string)($b['person']['first_name'] ?? 'Ш'), 0, 1)) ?></span>
                                <div>
                                    <div class="name" title="<?= htmlspecialchars($pName) ?>"><?= htmlspecialchars($pName) ?></div>
                                    <div class="meta">
                                        <?= htmlspecialchars((string)((($lang === 'ru' || $lang === 'en' || $lang === 'es') ? ($b['formatted_date_ru'] ?? null) : null) ?? $b['formatted_date_kk'] ?? $b['birth_date'])) ?>
                                    </div>
                                </div>
                            </a>
                            <?php if (!empty($b['age_turning'])): ?>
                                <div class="age-big" title="<?= (int)$b['age_turning'] ?> <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'лет исполняется' : 'жасқа толады' ?>"><b><?= (int)$b['age_turning'] ?></b><span><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'лет' : 'жас' ?></span></div>
                            <?php endif; ?>
                            <div class="right">
                                <?php if ($isToday): ?>
                                    <span class="badge badge-accent"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Сегодня' : 'Бүгін' ?></span>
                                <?php else: ?>
                                    <span class="badge"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Через ' . (int)$b['days_until'] . ' дн.' : (int)$b['days_until'] . ' күннен соң' ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- 2. Памятные даты предков -->
        <section class="island page">
            <div class="sec-title">
                <svg class="orn orn-branch" aria-hidden="true" focusable="false"><use href="#o-branch"/></svg>
                <h3><?= htmlspecialchars(I18n::t('memorial_anniversaries')) ?></h3>
                <a href="/dates" class="more"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Все даты' : 'Барлығы' ?></a>
            </div>

            <?php if (empty($anniversaries)): ?>
                <div class="empty-state compact">
                    <svg viewBox="0 0 140 90" aria-hidden="true" focusable="false"><use href="#o-sprout"/></svg>
                    <p><?= htmlspecialchars(I18n::t('no_memorial_dates')) ?></p>
                </div>
            <?php else: ?>
                <div class="list">
                    <?php foreach (array_slice($anniversaries, 0, 5) as $a):
                        $pName = trim(($a['person']['last_name'] ?? '') . ' ' . ($a['person']['first_name'] ?? ''));
                        $chip = $hmDateChip($a['event_date'] ?? $a['death_date'] ?? null);
                        $isToday = ((int)($a['days_until'] ?? -1) === 0);
                    ?>
                        <div class="list-row<?= $isToday ? ' hm-today' : '' ?>">
                            <?php if ($chip !== null): ?>
                                <div class="date-chip"><b><?= htmlspecialchars($chip['day']) ?></b><span><?= htmlspecialchars($chip['month']) ?></span></div>
                            <?php endif; ?>
                            <a href="/person/<?= (int)$a['person']['id'] ?>" class="person hm-person">
                                <span class="avatar deceased<?= (($a['person']['gender'] ?? '') === 'F') ? ' is-f' : ((($a['person']['gender'] ?? '') === 'M') ? ' is-m' : '') ?>"><?= htmlspecialchars(mb_substr((string)($a['person']['first_name'] ?? 'Ш'), 0, 1)) ?></span>
                                <div>
                                    <div class="name" title="<?= htmlspecialchars($pName) ?>"><?= htmlspecialchars($pName) ?></div>
                                    <div class="meta">
                                        <?= htmlspecialchars((string)((($lang === 'ru' || $lang === 'en' || $lang === 'es') ? ($a['formatted_date_ru'] ?? null) : null) ?? $a['formatted_date_kk'] ?? $a['death_date'])) ?>
                                        <?php if (!empty($a['years_since'])): ?>
                                            · <?= (int)$a['years_since'] ?> <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'лет со дня ухода' : 'жыл өтті' ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                            <div class="right">
                                <?php if ($isToday): ?>
                                    <span class="badge badge-accent"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Сегодня' : 'Бүгін' ?></span>
                                <?php else: ?>
                                    <span class="badge badge-accent"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Через ' . (int)$a['days_until'] . ' дн.' : (int)$a['days_until'] . ' күннен соң' ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php
require __DIR__ . '/layout/master_end.php';
?>
