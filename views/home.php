<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
$title = I18n::t('nav_home');
$activeNav = 'home';
$dsThemeReady = true; // TASK-008: страница на цветах дизайн-системы — доступна тёмная тема

$userName = $user['name'] ?? 'Туысқан';
$userEmail = $user['email'] ?? '';
$userRole = $user['role'] ?? 'viewer';
$birthdays = $birthdays ?? [];
$anniversaries = $anniversaries ?? [];

// TASK-008: «плашка даты» (число + месяц) из даты вида ГГГГ-ММ-ДД; если дата в другом виде — плашку не показываем
$hmMonths = $lang === 'ru'
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
    <!-- Приветствие -->
    <div class="ds-page-head">
        <div>
            <h1><?= htmlspecialchars(I18n::t('welcome_heading')) ?>, <?= htmlspecialchars($userName) ?>!</h1>
            <p class="sub hm-intro">
                <?= $lang === 'ru'
                    ? 'Добро пожаловать в закрытую систему родословной. Здесь бережно сохраняется преемственность поколений, история семьи, памятные даты и воспоминания.'
                    : 'Әулет шежіресінің жабық отбасылық порталына қош келдіңіз. Мұнда ата-бабалар тарихы, ұрпақтар сабақтастығы, естеліктер мен маңызды күндер сақталады.' ?>
            </p>
            <?php if ($youAreText !== ''): ?>
                <span class="badge badge-accent hm-you"><?= htmlspecialchars($youAreText) ?></span>
            <?php endif; ?>
        </div>
        <div class="actions">
            <a href="/persons" class="btn">
                <svg class="ic" aria-hidden="true"><use href="#i-users"/></svg><?= htmlspecialchars(I18n::t('nav_persons')) ?>
            </a>
            <a href="/tree" class="btn btn-primary">
                <svg class="ic" aria-hidden="true"><use href="#i-tree"/></svg><?= $lang === 'ru' ? 'Открыть шежире' : 'Шежірені ашу' ?>
            </a>
        </div>
    </div>

    <!-- Жылдам сілтемелер (Быстрые ссылки) -->
    <div class="grid grid-stats">
        <a href="/persons" class="island stat hm-link">
            <div class="label"><svg class="ic" aria-hidden="true"><use href="#i-users"/></svg><?= htmlspecialchars(I18n::t('nav_persons')) ?></div>
            <div class="value">270</div>
            <div class="note"><?= $lang === 'ru' ? 'человек, поиск, карточки' : 'тұлға, іздеу, парақшалар' ?></div>
        </a>
        <a href="/families" class="island stat hm-link">
            <div class="label"><svg class="ic" aria-hidden="true"><use href="#i-family"/></svg><?= htmlspecialchars(I18n::t('nav_families')) ?></div>
            <div class="value">71</div>
            <div class="note"><?= $lang === 'ru' ? 'семья, союзы и дети' : 'шаңырақ, некелер және балалар' ?></div>
        </a>
        <a href="/tree" class="island stat hm-link">
            <div class="label"><svg class="ic" aria-hidden="true"><use href="#i-tree"/></svg><?= htmlspecialchars(I18n::t('nav_tree')) ?></div>
            <div class="value hm-value-text">Өңдеу · Орман</div>
            <div class="note"><?= $lang === 'ru' ? 'Виды: Өңдеу, Шежіре-Орман' : 'Өңдеу және Шежіре-Орман түрлері' ?></div>
        </a>
        <a href="/dates" class="island stat hm-link">
            <div class="label"><svg class="ic" aria-hidden="true"><use href="#i-calendar"/></svg><?= htmlspecialchars(I18n::t('nav_dates')) ?></div>
            <div class="value hm-value-text">Жеті ата</div>
            <div class="note"><?= $lang === 'ru' ? 'Дни рождения, годовщины, Жеті ата' : 'Туған күндер, ас беру, Жеті ата' ?></div>
        </a>
    </div>

    <!-- Ближайшие дни рождения и памятные даты -->
    <div class="grid grid-2 hm-lists">

        <!-- 1. Дни рождения -->
        <section class="island">
            <div class="island-head">
                <svg class="ic" aria-hidden="true"><use href="#i-calendar"/></svg>
                <h3><?= htmlspecialchars(I18n::t('upcoming_birthdays')) ?></h3>
                <a href="/dates" class="more"><?= $lang === 'ru' ? 'Все даты' : 'Барлығы' ?></a>
            </div>

            <?php if (empty($birthdays)): ?>
                <div class="hm-empty"><?= htmlspecialchars(I18n::t('no_upcoming_birthdays')) ?></div>
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
                                <span class="avatar"><?= htmlspecialchars(mb_substr((string)($b['person']['first_name'] ?? 'Ш'), 0, 1)) ?></span>
                                <div>
                                    <div class="name" title="<?= htmlspecialchars($pName) ?>"><?= htmlspecialchars($pName) ?></div>
                                    <div class="meta">
                                        <?= htmlspecialchars((string)(($lang === 'ru' ? ($b['formatted_date_ru'] ?? null) : null) ?? $b['formatted_date_kk'] ?? $b['birth_date'])) ?>
                                        <?php if (!empty($b['age_turning'])): ?>
                                            · <?= (int)$b['age_turning'] ?> <?= $lang === 'ru' ? 'лет' : 'жас' ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                            <div class="right">
                                <?php if ($isToday): ?>
                                    <span class="badge badge-accent"><?= $lang === 'ru' ? 'Сегодня' : 'Бүгін' ?></span>
                                <?php else: ?>
                                    <span class="badge"><?= $lang === 'ru' ? 'Через ' . (int)$b['days_until'] . ' дн.' : (int)$b['days_until'] . ' күннен соң' ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- 2. Памятные даты предков -->
        <section class="island">
            <div class="island-head">
                <svg class="ic" aria-hidden="true"><use href="#i-history"/></svg>
                <h3><?= htmlspecialchars(I18n::t('memorial_anniversaries')) ?></h3>
                <a href="/dates" class="more"><?= $lang === 'ru' ? 'Все даты' : 'Барлығы' ?></a>
            </div>

            <?php if (empty($anniversaries)): ?>
                <div class="hm-empty"><?= htmlspecialchars(I18n::t('no_memorial_dates')) ?></div>
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
                                <span class="avatar deceased"><?= htmlspecialchars(mb_substr((string)($a['person']['first_name'] ?? 'Ш'), 0, 1)) ?></span>
                                <div>
                                    <div class="name" title="<?= htmlspecialchars($pName) ?>"><?= htmlspecialchars($pName) ?></div>
                                    <div class="meta">
                                        <?= htmlspecialchars((string)(($lang === 'ru' ? ($a['formatted_date_ru'] ?? null) : null) ?? $a['formatted_date_kk'] ?? $a['death_date'])) ?>
                                        <?php if (!empty($a['years_since'])): ?>
                                            · <?= (int)$a['years_since'] ?> <?= $lang === 'ru' ? 'лет со дня ухода' : 'жыл өтті' ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                            <div class="right">
                                <?php if ($isToday): ?>
                                    <span class="badge badge-accent"><?= $lang === 'ru' ? 'Сегодня' : 'Бүгін' ?></span>
                                <?php else: ?>
                                    <span class="badge badge-accent"><?= $lang === 'ru' ? 'Через ' . (int)$a['days_until'] . ' дн.' : (int)$a['days_until'] . ' күннен соң' ?></span>
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
