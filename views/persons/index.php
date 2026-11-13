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
            ? 'Отображено персон: <b id="ppTotalDisplayed">' . (int)$totalCount . '</b> из ' . (int)$totalAll
            : 'Көрсетілген тұлғалар: <b id="ppTotalDisplayed">' . (int)$totalCount . '</b> (' . (int)$totalAll . ' ішінен)' ?>
    </div>
    <?php if ($isGenerationView && !empty($persons)): ?>
        <div class="pp-bar-actions">
            <div class="pp-toggle-btns" role="group" aria-label="<?= $lang === 'ru' ? 'Сворачивание поколений' : 'Ұрпақтарды жию немесе ашу' ?>">
                <button type="button" class="btn btn-ghost btn-sm" id="btnCollapseAll" onclick="collapseAllGenerations()" title="<?= $lang === 'ru' ? 'Свернуть все поколения' : 'Барлық ұрпақтарды жию' ?>">
                    <svg class="ic" aria-hidden="true"><use href="#i-chevron-up"/></svg>
                    <span><?= $lang === 'ru' ? 'Свернуть все' : 'Барлығын жию' ?></span>
                </button>
                <button type="button" class="btn btn-ghost btn-sm" id="btnExpandAll" onclick="expandAllGenerations()" title="<?= $lang === 'ru' ? 'Развернуть все поколения' : 'Барлық ұрпақтарды ашу' ?>">
                    <svg class="ic" aria-hidden="true"><use href="#i-chevron-down"/></svg>
                    <span><?= $lang === 'ru' ? 'Развернуть все' : 'Барлығын ашу' ?></span>
                </button>
            </div>
            <nav class="seg" aria-label="<?= $lang === 'ru' ? 'Поколения' : 'Ұрпақтар' ?>">
                <a href="#pp-top" class="active"><?= $lang === 'ru' ? 'Все' : 'Барлығы' ?></a>
                <?php foreach ($generationGroups as $gKey => $group): ?>
                    <?php if (count($group['persons']) === 0) continue; ?>
                    <a href="#gen-<?= htmlspecialchars((string)$gKey) ?>" onclick="ensureGenerationExpanded('<?= htmlspecialchars((string)$gKey) ?>')"><?= htmlspecialchars($lang === 'ru' ? $group['title_ru'] : $group['title_kk']) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
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
                    <span class="avatar <?= $isMale ? 'is-m' : 'is-f' ?><?= $isDeceased ? ' deceased' : '' ?>"<?= $isDeceased ? ' title="' . ($lang === 'ru' ? 'Светлая память' : 'Жарқын бейне') . '"' : '' ?>>
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
            <td class="col-opt-lg"><span class="g-badge <?= $isMale ? 'is-m' : 'is-f' ?>"><span class="g-dot <?= $isMale ? 'is-m' : 'is-f' ?>"></span><?= htmlspecialchars($genderText) ?></span></td>

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
    <!-- ГРУППИРОВКА ПО ПОКОЛЕНИЯМ (По умолчанию, TASK-007, TASK-010) -->
    <div class="pp-groups" id="ppGroupsContainer">
        <?php
        $globalOffset = 0;
        foreach ($generationGroups as $gKey => $group):
            $countInGroup = count($group['persons']);
            if ($countInGroup === 0) continue;
            $gTitle = $lang === 'ru' ? $group['title_ru'] : $group['title_kk'];
        ?>
            <section class="pp-group" id="gen-<?= htmlspecialchars((string)$gKey) ?>" data-gen-key="<?= htmlspecialchars((string)$gKey) ?>">
                <div class="pp-group-head" role="button" tabindex="0"
                     aria-expanded="true" aria-controls="gen-body-<?= htmlspecialchars((string)$gKey) ?>"
                     onclick="toggleGeneration('<?= htmlspecialchars((string)$gKey) ?>')"
                     onkeydown="handleGroupHeadKeydown(event, '<?= htmlspecialchars((string)$gKey) ?>')"
                     title="<?= $lang === 'ru' ? 'Нажмите, чтобы свернуть или развернуть поколение' : 'Ұрпақты жию немесе ашу үшін басыңыз' ?>">
                    <div class="pp-group-head-main">
                        <svg class="ic pp-group-chevron" aria-hidden="true"><use href="#i-chevron-down"/></svg>
                        <h3><?= htmlspecialchars($gTitle) ?></h3>
                        <span class="badge pp-group-count" data-initial-count="<?= $countInGroup ?>">
                            <?= $countInGroup ?> <?= $lang === 'ru' ? 'персон' : 'тұлға' ?>
                        </span>
                    </div>
                    <span class="pp-group-hint desktop-only muted small">
                        <span class="pp-hint-collapse"><?= $lang === 'ru' ? 'жию / свернуть' : 'жию' ?></span>
                        <span class="pp-hint-expand"><?= $lang === 'ru' ? 'ашу / развернуть' : 'ашу' ?></span>
                    </span>
                </div>
                <div class="table-wrap pp-group-body" id="gen-body-<?= htmlspecialchars((string)$gKey) ?>">
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

<script>
(function() {
    'use strict';
    const STORAGE_KEY = 'shezhire_collapsed_generations';
    const lang = <?= json_encode($lang) ?>;
    const initialQuery = <?= json_encode((string)($filters['q'] ?? '')) ?>.trim().toLowerCase();

    function getStoredCollapsed() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return [];
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed.map(String) : [];
        } catch (e) {
            return [];
        }
    }

    function saveStoredCollapsed(keys) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(keys));
        } catch (e) {}
    }

    function setGroupState(groupEl, isCollapsed) {
        if (!groupEl) return;
        const head = groupEl.querySelector('.pp-group-head');
        if (isCollapsed) {
            groupEl.classList.add('is-collapsed');
            if (head) head.setAttribute('aria-expanded', 'false');
        } else {
            groupEl.classList.remove('is-collapsed');
            if (head) head.setAttribute('aria-expanded', 'true');
        }
    }

    window.toggleGeneration = function(genKey) {
        const groupEl = document.querySelector('.pp-group[data-gen-key="' + genKey + '"]');
        if (!groupEl) return;
        const currentlyCollapsed = groupEl.classList.contains('is-collapsed');
        const nextCollapsed = !currentlyCollapsed;
        setGroupState(groupEl, nextCollapsed);

        let list = getStoredCollapsed();
        const keyStr = String(genKey);
        if (nextCollapsed) {
            if (!list.includes(keyStr)) list.push(keyStr);
        } else {
            list = list.filter(function(k) { return k !== keyStr; });
        }
        saveStoredCollapsed(list);
    };

    window.handleGroupHeadKeydown = function(e, genKey) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            window.toggleGeneration(genKey);
        }
    };

    window.collapseAllGenerations = function() {
        const groups = document.querySelectorAll('.pp-group[data-gen-key]');
        const keys = [];
        groups.forEach(function(g) {
            setGroupState(g, true);
            const k = g.getAttribute('data-gen-key');
            if (k) keys.push(String(k));
        });
        saveStoredCollapsed(keys);
    };

    window.expandAllGenerations = function() {
        const groups = document.querySelectorAll('.pp-group[data-gen-key]');
        groups.forEach(function(g) {
            setGroupState(g, false);
        });
        saveStoredCollapsed([]);
    };

    window.ensureGenerationExpanded = function(genKey) {
        const groupEl = document.querySelector('.pp-group[data-gen-key="' + genKey + '"]');
        if (groupEl) {
            setGroupState(groupEl, false);
            let list = getStoredCollapsed().filter(function(k) { return k !== String(genKey); });
            saveStoredCollapsed(list);
        }
    };

    function applySavedState() {
        const stored = getStoredCollapsed();
        document.querySelectorAll('.pp-group[data-gen-key]').forEach(function(group) {
            const key = String(group.getAttribute('data-gen-key'));
            const shouldCollapse = stored.includes(key);
            setGroupState(group, shouldCollapse);
        });

        // Если при загрузке страницы уже был серверный поисковый запрос (filters[q]),
        // раскрываем все группы с найденными персонами!
        if (initialQuery !== '') {
            document.querySelectorAll('.pp-group[data-gen-key]').forEach(function(group) {
                const rowCount = group.querySelectorAll('.pp-table tbody tr').length;
                if (rowCount > 0) {
                    setGroupState(group, false);
                }
            });
        }
    }

    applySavedState();

    // Клиентский поиск в поле поиска (раскрывает группу с найденным человеком мгновенно)
    const searchInput = document.querySelector('.pp-filters input[name="q"]');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const val = this.value.trim().toLowerCase();
            if (val === '') {
                applySavedState();
                document.querySelectorAll('.pp-table tbody tr').forEach(function(tr) {
                    tr.style.display = '';
                });
                document.querySelectorAll('.pp-group[data-gen-key]').forEach(function(group) {
                    group.style.display = '';
                    const badge = group.querySelector('.pp-group-count');
                    const initCount = badge ? badge.getAttribute('data-initial-count') : null;
                    if (badge && initCount) {
                        badge.textContent = initCount + ' ' + (lang === 'ru' ? 'персон' : 'тұлға');
                    }
                });
                const totalEl = document.getElementById('ppTotalDisplayed');
                if (totalEl) {
                    const allRows = document.querySelectorAll('.pp-table tbody tr').length;
                    totalEl.textContent = String(allRows);
                }
                return;
            }

            let grandTotal = 0;
            document.querySelectorAll('.pp-group[data-gen-key]').forEach(function(group) {
                const rows = group.querySelectorAll('.pp-table tbody tr');
                let groupMatch = 0;
                rows.forEach(function(tr) {
                    const txt = tr.textContent.toLowerCase();
                    if (txt.includes(val)) {
                        tr.style.display = '';
                        groupMatch++;
                        grandTotal++;
                    } else {
                        tr.style.display = 'none';
                    }
                });

                if (groupMatch > 0) {
                    // НАЙДЕН ЧЕЛОВЕК: обязательно раскрываем группу!
                    setGroupState(group, false);
                    group.style.display = '';
                    const badge = group.querySelector('.pp-group-count');
                    if (badge) {
                        badge.textContent = groupMatch + ' ' + (lang === 'ru' ? 'найдено' : 'табылды');
                    }
                } else {
                    group.style.display = 'none';
                }
            });

            const totalEl = document.getElementById('ppTotalDisplayed');
            if (totalEl) {
                totalEl.textContent = String(grandTotal);
            }
        });
    }
})();
</script>

<?php
require __DIR__ . '/../layout/master_end.php';
?>
