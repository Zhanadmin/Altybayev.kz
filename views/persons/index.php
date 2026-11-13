<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
$title = I18n::t('nav_persons');
$activeNav = 'persons';
$dsThemeReady = true; // TASK-008: страница на цветах дизайн-системы — доступна тёмная тема

require __DIR__ . '/../layout/master_start.php';

$currentSort = $filters['sort'] ?? 'generation';
$isGenerationView = ($currentSort === 'generation');

// Помощник для построения URL сортировки колонок
$buildSortUrl = function(string $sortKey) use ($filters): string {
    $params = $filters;
    if (($params['sort'] ?? '') === $sortKey) {
        $params['sort'] = str_ends_with($sortKey, '_asc') ? str_replace('_asc', '_desc', $sortKey) : str_replace('_desc', '_asc', $sortKey);
    } else {
        $params['sort'] = $sortKey;
    }
    return '/persons?' . http_build_query($params);
};

$hasFilters = !empty($filters['q']) || !empty($filters['clan_id']) || !empty($filters['gender']) || !empty($filters['status']) || $currentSort !== 'generation';
?>

<div class="ds-page-head">
    <div>
        <h1><?= htmlspecialchars(I18n::t('persons_title')) ?></h1>
        <p class="sub"><?= htmlspecialchars(I18n::t('persons_subtitle')) ?></p>
    </div>
    <div class="actions">
        <nav class="seg" aria-label="<?= $lang === 'ru' ? 'Вид' : 'Көрініс' ?>">
            <a href="/persons" class="active" aria-current="page"><?= $lang === 'ru' ? 'Список' : 'Тізім' ?></a>
            <a href="/tree"><?= $lang === 'ru' ? 'Шежире-Орман' : 'Шежіре-Орман' ?></a>
        </nav>
        <?php if ($canEdit): ?>
            <button type="button" class="btn btn-primary" onclick="openGlobalPersonEditModal(0)">
                <svg class="ic" aria-hidden="true"><use href="#i-plus"/></svg>
                <span><?= $lang === 'ru' ? 'Добавить персону' : 'Тұлға қосу' ?></span>
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="pp-stats">
    <span class="badge"><?= $lang === 'ru' ? 'Всего:' : 'Барлығы:' ?> <b><?= (int)$totalAll ?></b></span>
    <span class="badge badge-success"><?= $lang === 'ru' ? 'Живых:' : 'Тірі:' ?> <b><?= (int)$totalLiving ?></b></span>
    <span class="badge badge-accent"><?= $lang === 'ru' ? 'Ушедших:' : 'Өмірден өткен:' ?> <b><?= (int)$totalDeceased ?></b></span>
</div>

<!-- Панель поиска и фильтров -->
<form method="GET" action="/persons" id="personsFilterForm" class="toolbar pp-filters">
    <div class="search">
        <svg class="ic" aria-hidden="true"><use href="#i-search"/></svg>
        <input type="text" name="q" class="input" value="<?= htmlspecialchars((string)($filters['q'] ?? '')) ?>"
               placeholder="<?= htmlspecialchars(I18n::t('search_placeholder')) ?>">
    </div>

    <select name="clan_id" class="select" onchange="document.getElementById('personsFilterForm').submit()">
        <option value="0"><?= htmlspecialchars(I18n::t('filter_all_clans')) ?></option>
        <?php foreach ($clans as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((int)($filters['clan_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="gender" class="select" onchange="document.getElementById('personsFilterForm').submit()">
        <option value=""><?= htmlspecialchars(I18n::t('filter_gender_all')) ?></option>
        <option value="M" <?= ($filters['gender'] ?? '') === 'M' ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('filter_gender_m')) ?></option>
        <option value="F" <?= ($filters['gender'] ?? '') === 'F' ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('filter_gender_f')) ?></option>
    </select>

    <select name="status" class="select" onchange="document.getElementById('personsFilterForm').submit()">
        <option value=""><?= htmlspecialchars(I18n::t('filter_status_all')) ?></option>
        <option value="living" <?= ($filters['status'] ?? '') === 'living' ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('filter_status_living')) ?></option>
        <option value="deceased" <?= ($filters['status'] ?? '') === 'deceased' ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('filter_status_deceased')) ?></option>
    </select>

    <select name="sort" class="select" onchange="document.getElementById('personsFilterForm').submit()">
        <option value="generation" <?= ($currentSort === 'generation') ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('sort_generation')) ?></option>
        <option value="name_asc" <?= ($currentSort === 'name_asc') ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('sort_name_asc')) ?></option>
        <option value="name_desc" <?= ($currentSort === 'name_desc') ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('sort_name_desc')) ?></option>
        <option value="birth_asc" <?= ($currentSort === 'birth_asc') ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('sort_birth_asc')) ?></option>
        <option value="birth_desc" <?= ($currentSort === 'birth_desc') ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('sort_birth_desc')) ?></option>
    </select>

    <button type="submit" class="btn"><?= $lang === 'ru' ? 'Найти' : 'Іздеу' ?></button>
    <?php if ($hasFilters): ?>
        <a href="/persons" class="btn btn-ghost"><?= $lang === 'ru' ? 'Сброс' : 'Тазарту' ?></a>
    <?php endif; ?>
</form>

<div class="pp-bar">
    <div class="small muted">
        <?= $lang === 'ru'
            ? 'Отображено персон: <b>' . (int)$totalCount . '</b> из ' . (int)$totalAll
            : 'Көрсетілген тұлғалар: <b>' . (int)$totalCount . '</b> (' . (int)$totalAll . ' ішінен)' ?>
    </div>
    <?php if ($isGenerationView && !empty($persons)): ?>
        <nav class="seg" aria-label="<?= $lang === 'ru' ? 'Поколения' : 'Ұрпақтар' ?>">
            <a href="#pp-top" class="active"><?= $lang === 'ru' ? 'Все' : 'Барлығы' ?></a>
            <?php foreach ($generationGroups as $gKey => $group): ?>
                <?php if (count($group['persons']) === 0) continue; ?>
                <a href="#gen-<?= htmlspecialchars((string)$gKey) ?>"><?= htmlspecialchars($lang === 'ru' ? $group['title_ru'] : $group['title_kk']) ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</div>

<?php
// Функция вывода строк персон в таблице
$renderTableRows = function(array $items, int $offset = 0) use ($lang, $canEdit) {
    if (empty($items)) {
        return;
    }
    foreach ($items as $i => $p) {
        $idx = $offset + $i + 1;
        $isDeceased = !empty($p['is_deceased']);
        $isMale = ($p['gender'] === 'M');
        $initials = mb_substr((string)($p['first_name'] ?? ''), 0, 1) . mb_substr((string)($p['last_name'] ?? ''), 0, 1);

        $birthDisp = !empty($p['birth_date']) ? I18n::formatDate($p['birth_date']) : ($p['birth_year'] ? I18n::formatDate($p['birth_year']) : '—');
        $deathDisp = !empty($p['death_date']) ? I18n::formatDate($p['death_date']) : ($p['death_year'] ? I18n::formatDate($p['death_year']) : '†');
        $yearsDisp = $isDeceased ? "{$birthDisp} — {$deathDisp}" : $birthDisp;

        $kin = $p['kinship'] ?? null;
        $genderText = I18n::t($isMale ? 'filter_gender_m' : 'filter_gender_f');
        $clanText = $p['clan_ru'] ?: ($lang === 'ru' ? 'не указан' : 'белгісіз');

        // Строка-подпись под именем: на узких экранах сюда попадают скрытые колонки
        $metaParts = [];
        if (!empty($p['maiden_name'])) {
            $metaParts[] = ($lang === 'ru' ? 'урожд. ' : 'қыз кезіндегі: ') . $p['maiden_name'];
        }
        if (!empty($p['birth_place'])) {
            $metaParts[] = $p['birth_place'];
        }
        ?>
        <tr>
            <td class="num col-opt-md"><?= $idx ?></td>

            <!-- Тұлға -->
            <td>
                <div class="person">
                    <span class="avatar<?= $isDeceased ? ' deceased' : '' ?>"<?= $isDeceased ? ' title="' . ($lang === 'ru' ? 'Светлая память' : 'Жарқын бейне') . '"' : '' ?>>
                        <?php if (!empty($p['avatar_path'])): ?>
                            <img src="<?= htmlspecialchars((string)$p['avatar_path']) ?>" alt="">
                        <?php else: ?>
                            <?= htmlspecialchars($initials ?: 'Ш') ?>
                        <?php endif; ?>
                    </span>
                    <div>
                        <a href="/person/<?= (int)$p['id'] ?>" class="name pp-name" title="<?= htmlspecialchars($p['full_name']) ?>"><?= htmlspecialchars($p['full_name']) ?></a>
                        <?php if ($metaParts): ?>
                            <div class="meta" title="<?= htmlspecialchars(implode(' · ', $metaParts)) ?>"><?= htmlspecialchars(implode(' · ', $metaParts)) ?></div>
                        <?php endif; ?>
                        <div class="meta pp-narrow-meta"><span class="pp-nm-xl"><?= htmlspecialchars($clanText) ?></span><span class="pp-nm-lg"><?= htmlspecialchars($genderText) ?></span><span class="pp-nm-md"><?= htmlspecialchars($yearsDisp) ?></span></div>
                    </div>
                </div>
            </td>

            <!-- Жынысы -->
            <td class="col-opt-lg"><span class="muted small"><?= htmlspecialchars($genderText) ?></span></td>

            <!-- Руы -->
            <td class="col-opt-xl clip"><span class="badge" title="<?= htmlspecialchars($clanText) ?>"><?= htmlspecialchars($clanText) ?></span></td>

            <!-- Жылдары -->
            <td class="clip pp-years col-opt-md" title="<?= htmlspecialchars($yearsDisp) ?>"><?= htmlspecialchars($yearsDisp) ?></td>

            <!-- Ата-анасы -->
            <td class="col-opt-lg pp-rel">
                <?php if ($p['father']): ?>
                    <div class="clip">
                        <span class="pp-lbl"><?= $lang === 'ru' ? 'Отец' : 'Әкесі' ?></span>
                        <a href="/person/<?= (int)$p['father']['id'] ?>"><?= htmlspecialchars(trim(($p['father']['last_name'] ?? '') . ' ' . $p['father']['first_name'])) ?></a>
                    </div>
                <?php endif; ?>
                <?php if ($p['mother']): ?>
                    <div class="clip">
                        <span class="pp-lbl"><?= $lang === 'ru' ? 'Мать' : 'Анасы' ?></span>
                        <a href="/person/<?= (int)$p['mother']['id'] ?>"><?= htmlspecialchars(trim(($p['mother']['last_name'] ?? '') . ' ' . $p['mother']['first_name'])) ?></a>
                    </div>
                <?php endif; ?>
                <?php if (!$p['father'] && !$p['mother']): ?>
                    <span class="pp-none">—</span>
                <?php endif; ?>
            </td>

            <!-- Супруги -->
            <td class="col-opt-xl pp-rel">
                <?php if (!empty($p['spouses'])): ?>
                    <?php foreach ($p['spouses'] as $sp):
                        $isDiv = ($sp['status'] === 'divorced');
                    ?>
                        <div class="clip<?= $isDiv ? ' pp-div' : '' ?>"<?= $isDiv ? ' title="' . ($lang === 'ru' ? 'В разводе' : 'Ажырасқан') . '"' : '' ?>>
                            <a href="/person/<?= (int)$sp['spouse_id'] ?>"><?= htmlspecialchars($sp['name']) ?></a>
                            <?php if (!empty($sp['years'])): ?>
                                <span class="pp-lbl">(<?= htmlspecialchars($sp['years']) ?>)</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="pp-none">—</span>
                <?php endif; ?>
            </td>

            <!-- Үш жұрт / Сізге туыстығы -->
            <td class="col-opt-md pp-rel">
                <?php if ($kin && !empty($kin['zhurt'])):
                    $isOwn = !str_contains($kin['zhurt'], 'Нағашы') && !str_contains($kin['zhurt'], 'Қайын')
                        && !str_contains($kin['zhurt'], 'Төркін') && !str_contains($kin['zhurt'], 'Күйеу')
                        && !str_contains($kin['zhurt'], 'Құда');
                    $termText = I18n::formatKinship($kin['term_kk'] ?? '', $kin['term_ru'] ?? null);
                ?>
                    <span class="badge<?= $isOwn ? ' badge-accent' : '' ?>"><?= htmlspecialchars($kin['zhurt']) ?></span>
                    <div class="clip pp-term" title="<?= htmlspecialchars($termText) ?>"><?= htmlspecialchars($termText) ?></div>
                <?php elseif ($kin && !empty($kin['term_kk'])):
                    $termText = I18n::formatKinship($kin['term_kk'], $kin['term_ru'] ?? null);
                ?>
                    <div class="clip pp-term" title="<?= htmlspecialchars($termText) ?>"><?= htmlspecialchars($termText) ?></div>
                <?php else: ?>
                    <span class="pp-none">—</span>
                <?php endif; ?>
            </td>

            <!-- Әрекеттер -->
            <td class="actions">
                <a href="/person/<?= (int)$p['id'] ?>" class="btn btn-ghost btn-icon btn-action" aria-label="<?= $lang === 'ru' ? 'Карточка' : 'Парақшасы' ?>" data-tip="<?= $lang === 'ru' ? 'Карточка' : 'Парақша' ?>">
                    <svg class="ic" aria-hidden="true"><use href="#i-eye"/></svg>
                </a>
                <a href="/tree?focus=<?= (int)$p['id'] ?>" class="btn btn-ghost btn-icon btn-action" aria-label="<?= $lang === 'ru' ? 'Показать в древе' : 'Древода көрсету' ?>" data-tip="<?= $lang === 'ru' ? 'В древе' : 'Древода' ?>">
                    <svg class="ic" aria-hidden="true"><use href="#i-tree"/></svg>
                </a>
                <?php if ($canEdit): ?>
                    <button type="button" class="btn btn-ghost btn-icon" onclick="openGlobalPersonEditModal(<?= (int)$p['id'] ?>)" aria-label="<?= $lang === 'ru' ? 'Редактировать' : 'Өңдеу' ?>" data-tip="<?= $lang === 'ru' ? 'Редактировать' : 'Өңдеу' ?>">
                        <svg class="ic" aria-hidden="true"><use href="#i-edit"/></svg>
                    </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }
};

$renderTableHeader = function() use ($buildSortUrl, $canEdit) {
    ?>
    <thead>
        <tr>
            <th class="num col-opt-md">#</th>
            <th class="pp-w-person">
                <a href="<?= htmlspecialchars($buildSortUrl('name_asc')) ?>" class="pp-sort"><?= htmlspecialchars(I18n::t('th_person')) ?> <span aria-hidden="true">↕</span></a>
            </th>
            <th class="col-opt-lg pp-w-gender"><?= htmlspecialchars(I18n::t('th_gender')) ?></th>
            <th class="col-opt-xl pp-w-clan"><?= htmlspecialchars(I18n::t('th_clan')) ?></th>
            <th class="col-opt-md pp-w-years">
                <a href="<?= htmlspecialchars($buildSortUrl('birth_asc')) ?>" class="pp-sort"><?= htmlspecialchars(I18n::t('th_years')) ?> <span aria-hidden="true">↕</span></a>
            </th>
            <th class="col-opt-lg pp-w-rel"><?= htmlspecialchars(I18n::t('th_parents')) ?></th>
            <th class="col-opt-xl pp-w-rel"><?= htmlspecialchars(I18n::t('th_spouses')) ?></th>
            <th class="col-opt-md pp-w-kin"><?= htmlspecialchars(I18n::t('th_kinship')) ?></th>
            <th class="actions <?= $canEdit ? 'pp-w-act3' : 'pp-w-act2' ?>"><span class="pp-sr"><?= htmlspecialchars(I18n::t('th_actions')) ?></span></th>
        </tr>
    </thead>
    <?php
};
?>

<div id="pp-top"></div>
<?php if (empty($persons)): ?>
    <div class="island pp-empty">
        <svg class="ic" aria-hidden="true"><use href="#i-search"/></svg>
        <b><?= $lang === 'ru' ? 'Персоны не найдены' : 'Сұранысқа сай тұлғалар табылмады' ?></b>
        <span class="muted small"><?= $lang === 'ru' ? 'Попробуйте изменить поисковый запрос или сбросить фильтры' : 'Іздеу сөзін өзгертіп көріңіз немесе сүзгілерді тазартыңыз' ?></span>
    </div>
<?php elseif ($isGenerationView): ?>
    <!-- ГРУППИРОВКА ПО ПОКОЛЕНИЯМ (По умолчанию, TASK-007) -->
    <div class="pp-groups">
        <?php
        $globalOffset = 0;
        foreach ($generationGroups as $gKey => $group):
            $countInGroup = count($group['persons']);
            if ($countInGroup === 0) continue;
        ?>
            <section class="pp-group" id="gen-<?= htmlspecialchars((string)$gKey) ?>">
                <div class="island-head">
                    <h3><?= htmlspecialchars($lang === 'ru' ? $group['title_ru'] : $group['title_kk']) ?></h3>
                    <span class="badge"><?= $countInGroup ?> <?= $lang === 'ru' ? 'персон' : 'тұлға' ?></span>
                </div>
                <div class="table-wrap">
                    <table class="table pp-table">
                        <?php $renderTableHeader(); ?>
                        <tbody>
                            <?php
                            $renderTableRows($group['persons'], $globalOffset);
                            $globalOffset += $countInGroup;
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <!-- ПЛОСКИЙ СПИСОК (При явной сортировке по имени или годам) -->
    <div class="table-wrap">
        <table class="table pp-table">
            <?php $renderTableHeader(); ?>
            <tbody>
                <?php $renderTableRows($persons, 0); ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
require __DIR__ . '/../layout/master_end.php';
?>
