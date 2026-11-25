<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
$title = I18n::t('nav_families');
$activeNav = 'families';
$dsThemeReady = true; // TASK-008: страница на цветах дизайн-системы — доступна тёмная тема

require __DIR__ . '/../layout/master_start.php';
?>

<div class="fm">
    <div class="ds-page-head">
        <div>
            <h1><?= htmlspecialchars(I18n::t('families_title')) ?></h1>
            <p class="sub"><?= htmlspecialchars(I18n::t('families_subtitle')) ?></p>
        </div>
        <div class="actions pp-stats">
            <span class="badge"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Всего союзов:' : 'Барлық шаңырақ:' ?> <b><?= (int)$totalAll ?></b></span>
            <span class="badge badge-success"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'В браке:' : 'Некеде:' ?> <b><?= (int)$totalMarried ?></b></span>
            <span class="badge"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'В разводе:' : 'Ажырасқан:' ?> <b><?= (int)$totalDivorced ?></b></span>
        </div>
    </div>

    <!-- Фильтр и поиск -->
    <form method="GET" action="/families" id="familiesFilterForm" class="island toolbar">
        <div class="search">
            <svg class="ic" aria-hidden="true"><use href="#i-search"/></svg>
            <input type="text" name="q" class="input" value="<?= htmlspecialchars((string)($filters['q'] ?? '')) ?>"
                   placeholder="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Поиск по фамилии супругов, роду, месту...' : 'Ерлі-зайыптылардың аты-жөні, руы бойынша іздеу...' ?>">
        </div>
        <select name="status" class="select" onchange="document.getElementById('familiesFilterForm').submit()">
            <option value=""><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Статус: Все' : 'Күйі: Барлығы' ?></option>
            <option value="married" <?= ($filters['status'] ?? '') === 'married' ? 'selected' : '' ?>><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'В браке' : 'Некеде' ?></option>
            <option value="divorced" <?= ($filters['status'] ?? '') === 'divorced' ? 'selected' : '' ?>><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'В разводе' : 'Ажырасқан' ?></option>
        </select>
        <button type="submit" class="btn btn-primary"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Найти' : 'Іздеу' ?></button>
        <?php if (!empty($filters['q']) || !empty($filters['status'])): ?>
            <a href="/families" class="btn btn-ghost"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Сброс' : 'Тазарту' ?></a>
        <?php endif; ?>
    </form>

    <!-- Сетка семей с группировкой по поколениям -->
    <?php if (empty($families)): ?>
        <div class="island pp-empty">
            <svg class="pp-sprout" viewBox="0 0 140 90" aria-hidden="true" focusable="false"><use href="#o-sprout"/></svg>
            <b><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Семьи не найдены' : 'Шаңырақтар табылмады' ?></b>
            <span class="muted small"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Попробуйте изменить параметры поиска' : 'Іздеу параметрлерін өзгертіп көріңіз' ?></span>
        </div>
    <?php else: ?>
        <div class="pp-bar">
            <div class="pp-bar-actions">
                <div class="pp-toggle-btns" role="group" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Сворачивание поколений' : 'Ұрпақтарды жию немесе ашу' ?>">
                    <button type="button" class="btn btn-ghost btn-sm" id="btnCollapseAll" onclick="fmCollapseAll()">
                        <svg class="ic" aria-hidden="true"><use href="#i-chevron-up"/></svg>
                        <span><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Свернуть все' : 'Барлығын жию' ?></span>
                    </button>
                    <button type="button" class="btn btn-ghost btn-sm" id="btnExpandAll" onclick="fmExpandAll()">
                        <svg class="ic" aria-hidden="true"><use href="#i-chevron-down"/></svg>
                        <span><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Развернуть все' : 'Барлығын ашу' ?></span>
                    </button>
                </div>
                <nav class="seg" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Поколения' : 'Ұрпақтар' ?>">
                    <a href="#fm-top" class="active"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Все' : 'Барлығы' ?></a>
                    <?php foreach ($generationGroups as $gKey => $group): if (empty($group['families'])) continue; ?>
                        <a href="#gen-<?= htmlspecialchars((string)$gKey) ?>" onclick="fmEnsureOpen('<?= htmlspecialchars((string)$gKey) ?>')"><?= htmlspecialchars(($lang === 'ru' || $lang === 'en' || $lang === 'es') ? $group['title_ru'] : $group['title_kk']) ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
        <div id="fm-top"></div>
        <div class="pp-groups">
        <?php foreach ($generationGroups as $gKey => $group):
            if (empty($group['families'])) continue;
            $gTitle = (($lang === 'ru' || $lang === 'en' || $lang === 'es')) ? $group['title_ru'] : $group['title_kk'];
            $isAncestors = ($gKey === 'ancestors');
            $gk = htmlspecialchars((string)$gKey);
        ?>
            <section class="fm-group pp-group" id="gen-<?= $gk ?>" data-gen-key="<?= $gk ?>">
                <div class="pp-group-head" role="button" tabindex="0" aria-expanded="true" aria-controls="gen-body-<?= $gk ?>"
                     onclick="fmToggle('<?= $gk ?>')"
                     onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();fmToggle('<?= $gk ?>');}"
                     title="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Нажмите, чтобы свернуть или развернуть поколение' : 'Ұрпақты жию немесе ашу үшін басыңыз' ?>">
                    <div class="pp-group-head-main">
                        <svg class="ic pp-group-chevron" aria-hidden="true"><use href="#i-chevron-down"/></svg>
                        <span class="ribbon-wrap"><h3 class="ribbon ribbon-sm<?= $isAncestors ? ' gold' : '' ?>"><?= htmlspecialchars($gTitle) ?></h3></span>
                        <span class="badge pp-group-count"><?= count($group['families']) ?> <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'союзов' : 'отбасы' ?></span>
                        <span class="pp-gen-line" aria-hidden="true"></span>
                    </div>
                </div>

                <div class="fm-grid pp-group-body" id="gen-body-<?= $gk ?>">
                    <?php foreach ($group['families'] as $idx => $f):
                        $m = $f['marriage'];
                        $isMarried = ($f['status'] === 'married');
                        $h = $f['husband'];
                        $w = $f['wife'];
                        $children = $f['children'];
                    ?>
                        <article class="island fm-card<?= $isAncestors ? ' fm-ancestors' : '' ?>" id="fam-<?= (int)$m['id'] ?>">
                            <div class="fm-card-head">
                                <?php
                                    /* TASK-020: служебное «#N (ID)» скрыто; главное — «N лет вместе» */
                                    $mStart = (string)($m['start_date'] ?: $m['marriage_date']);
                                    $mEnd = (string)($m['end_date'] ?: $m['divorce_date']);
                                    // TASK-023: у разведённых без даты развода срок не считаем (иначе «до сегодня»)
                                    // в браке — до сегодня, но если супруг умер раньше — до даты смерти
                                    $endForCount = $isMarried ? I18n::earliestDeath($h ?: null, $w ?: null) : $mEnd;
                                    $together = (!$isMarried && $mEnd === '') ? '' : I18n::togetherLabel($mStart, $endForCount, $lang);
                                ?>
                                                                <span class="badge<?= $isMarried ? ' badge-success' : '' ?>">
                                    <?= $isMarried ? (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'В браке' : 'Некеде') : (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'В разводе' : 'Ажырасқан') ?>
                                </span>
                                <?php if ($canEdit): ?>
                                    <button type="button" class="btn btn-ghost btn-icon btn-sm" data-tip="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Изменить' : 'Өңдеу' ?>" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Изменить' : 'Өңдеу' ?>"
                                            onclick="openMarriageEditModal(<?= (int)$m['id'] ?>, '<?= htmlspecialchars($m['status'] ?? 'married') ?>', '<?= htmlspecialchars((string)($m['start_date'] ?: $m['marriage_date'])) ?>', '<?= htmlspecialchars((string)($m['end_date'] ?: $m['divorce_date'])) ?>', '<?= htmlspecialchars((string)($m['notes'] ?? '')) ?>')">
                                        <svg class="ic" aria-hidden="true"><use href="#i-edit"/></svg>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <!-- Супруги (Пара) -->
                            <div class="fm-pair">
                                <div class="island-sub fm-spouse is-m">
                                    <div class="pc-label"><svg class="gmark" aria-hidden="true" focusable="false"><use href="#g-m"/></svg><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Муж' : 'Отағасы' ?></div>
                                    <?php if ($h): ?>
                                        <a href="/person/<?= (int)$h['id'] ?>" class="fm-name" title="<?= htmlspecialchars($f['h_name']) ?>"><?= htmlspecialchars($f['h_name']) ?></a>
                                        <div class="small muted">
                                            <?= $h['clan_ru'] ? clan_tamga_html($h['clan_ru']) . htmlspecialchars($h['clan_ru']) : '' ?>
                                            <?php $hl = I18n::lifeLabel($h); if ($hl['short'] !== ''): ?> · <span title="<?= htmlspecialchars($hl['title']) ?>"><?= htmlspecialchars($hl['short']) ?></span><?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="muted"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Неизвестно' : 'Белгісіз' ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="fm-rings-col" style="display:flex;flex-direction:column;align-items:center;gap:2px">
                                    <svg class="couple-rings fm-rings<?= $isMarried ? '' : ' is-apart' ?>" aria-hidden="true" focusable="false"><use href="#o-rings"/></svg>
                                    <?php if ($together !== ''): ?><span class="fm-together small muted"<?= $mStart !== '' ? ' title="' . htmlspecialchars((($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Дата брака: ' : 'Неке күні: ') . I18n::marriageDateLabel($mStart, $lang)) . '"' : '' ?>><?= htmlspecialchars($together) ?></span><?php endif; ?>
                                </div>

                                <div class="island-sub fm-spouse is-f">
                                    <div class="pc-label"><svg class="gmark" aria-hidden="true" focusable="false"><use href="#g-f"/></svg><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Жена' : 'Отанасы' ?></div>
                                    <?php if ($w): ?>
                                        <a href="/person/<?= (int)$w['id'] ?>" class="fm-name" title="<?= htmlspecialchars($f['w_name']) ?>"><?= htmlspecialchars($f['w_name']) ?></a>
                                        <?php if (!empty($w['maiden_name'])): ?>
                                            <div class="small muted maiden-it">(<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'урожд.' : 'қыз кезінде:' ?> <?= htmlspecialchars($w['maiden_name']) ?>)</div>
                                        <?php endif; ?>
                                        <?php $wl = I18n::lifeLabel($w); ?>
                                        <div class="small muted"><?php if ($wl['short'] !== ''): ?><span title="<?= htmlspecialchars($wl['title']) ?>"><?= htmlspecialchars($wl['short']) ?></span><?php endif; ?></div>
                                    <?php else: ?>
                                        <span class="muted"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Неизвестно' : 'Белгісіз' ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Даты брака / развода -->
                            <?php /* TASK-020: год/дата брака в карточке не показывается — только «N лет вместе» в шапке */ ?>
                            <?php if (false): ?>
                                <div class="fm-dates small muted">
                                    <svg class="ic" aria-hidden="true"><use href="#i-calendar"/></svg>
                                    <span>
                                        <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Брак:' : 'Неке:' ?>
                                        <?= htmlspecialchars(I18n::marriageDateLabel($m['start_date'] ?: $m['marriage_date'], $lang)) ?>
                                        <?php if (!empty($m['end_date']) || !empty($m['divorce_date'])): ?>
                                            — <?= htmlspecialchars(I18n::marriageDateLabel($m['end_date'] ?: $m['divorce_date'], $lang)) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <!-- Балалары (Дети, отсортированные по старшинству) -->
                            <div>
                                <div class="pc-sub-title"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Дети (' . count($children) . ')' : 'Балалары (' . count($children) . ')' ?></div>
                                <?php if (empty($children)): ?>
                                    <div class="small muted"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Общие дети не зарегистрированы' : 'Ортақ балалар тіркелмеген' ?></div>
                                <?php else: ?>
                                    <div class="pc-tags">
                                        <?php foreach ($children as $c):
                                            if (!empty($c['uzatylgan'])):
                                                $uz = $c['uzatylgan'];
                                                $uzFam = (string)($uz['family'] ?? '');
                                                if (($lang === 'ru' || $lang === 'en' || $lang === 'es') && mb_strtolower($uzFam) === 'белгісіз') { $uzFam = 'не указан'; }
                                        ?>
                                            <a href="/families#fam-<?= (int)$uz['marriage_id'] ?>" class="badge pc-tag fm-uzatylgan"
                                               title="<?= htmlspecialchars(($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Выдана замуж — её место в семье мужа' : 'Ұзатылған — орны күйеуінің шаңырағында') ?>">
                                                <svg class="gmark" aria-hidden="true" focusable="false"><use href="#g-f"/></svg>
                                                <?= htmlspecialchars($c['first_name']) ?>
                                                <?php $ul = I18n::lifeLabel($c); if ($ul['short'] !== ''): ?>
                                                    <span title="<?= htmlspecialchars($ul['title']) ?>"><?= htmlspecialchars($ul['short']) ?></span>
                                                <?php endif; ?>
                                                <span class="fm-uz-note">⚭ <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'замужем' : 'ұзатылған' ?><?= $uzFam !== '' ? ' — ' . htmlspecialchars($uzFam) : '' ?></span>
                                            </a>
                                        <?php continue; endif; ?>
                                            <a href="/person/<?= (int)$c['id'] ?>" class="badge pc-tag" title="<?= ($c['gender'] === 'M') ? (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Сын' : 'Ұлы') : (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Дочь' : 'Қызы') ?>">
                                                <svg class="gmark" aria-hidden="true" focusable="false"><use href="#<?= ($c['gender'] === 'M') ? 'g-m' : 'g-f' ?>"/></svg>
                                                <?= htmlspecialchars($c['first_name']) ?>
                                                <?php $cl = I18n::lifeLabel($c); if ($cl['short'] !== ''): ?>
                                                    <span title="<?= htmlspecialchars($cl['title']) ?>"><?= htmlspecialchars($cl['short']) ?></span>
                                                <?php endif; ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($f['child_fams'])): ?>
                                <div class="fm-foot">
                                    <button type="button" class="btn btn-sm btn-ghost fm-kids-btn" aria-expanded="false"
                                            data-fams="<?= htmlspecialchars(implode(',', $f['child_fams'])) ?>"
                                            data-open="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Скрыть семьи детей' : 'Балаларының отбасыларын жасыру' ?>"
                                            data-closed="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Семьи детей' : 'Балаларының отбасылары' ?>">
                                        <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Семьи детей' : 'Балаларының отбасылары' ?> (<?= count($f['child_fams']) ?>)
                                    </button>
                                </div>
                            <?php endif; ?>

                            <!-- Быстрый переход в древо -->
                            <?php if ($h || $w):
                                $focalId = $h ? (int)$h['id'] : (int)$w['id'];
                            ?>
                                <div class="fm-foot">
                                    <a href="/tree?focus=<?= $focalId ?>" class="btn btn-sm btn-ghost">
                                        <svg class="ic" aria-hidden="true"><use href="#i-tree"/></svg><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Показать семью в древе' : 'Отбасын шежіреде көрсету' ?>
                                    </a>
                                    <?php /* TASK-018: фотоальбом семьи */ $famMedia = (int)(($albumCounts ?? [])[(int)$m['id']] ?? 0); ?>
                                    <a href="/family/<?= (int)$m['id'] ?>/albums" class="btn btn-sm btn-ghost">
                                        <svg class="ic" aria-hidden="true"><use href="#i-camera"/></svg><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Фотоальбом' : 'Фотоальбом' ?><?= $famMedia > 0 ? ' · ' . $famMedia : '' ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
/* ALT-005: сворачивание поколений (состояние — в localStorage) */
(function () {
    var KEY = 'shezhire_collapsed_family_generations';
    function load() { try { var a = JSON.parse(localStorage.getItem(KEY) || '[]'); return Array.isArray(a) ? a.map(String) : []; } catch (e) { return []; } }
    function save(a) { try { localStorage.setItem(KEY, JSON.stringify(a)); } catch (e) {} }
    function groups() { return document.querySelectorAll('.pp-group[data-gen-key]'); }
    function setState(g, collapsed) {
        g.classList.toggle('is-collapsed', collapsed);
        var h = g.querySelector('.pp-group-head');
        if (h) h.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }
    function collect() {
        var a = [];
        groups().forEach(function (g) { if (g.classList.contains('is-collapsed')) a.push(String(g.dataset.genKey)); });
        save(a);
    }
    window.fmToggle = function (k) {
        var g = document.querySelector('.pp-group[data-gen-key="' + k + '"]');
        if (!g) return;
        setState(g, !g.classList.contains('is-collapsed'));
        collect();
    };
    window.fmCollapseAll = function () { groups().forEach(function (g) { setState(g, true); }); collect(); };
    window.fmExpandAll = function () { groups().forEach(function (g) { setState(g, false); }); collect(); };
    window.fmEnsureOpen = function (k) {
        var g = document.querySelector('.pp-group[data-gen-key="' + k + '"]');
        if (g) { setState(g, false); collect(); }
    };
    var stored = load();
    var hash = (location.hash || '').replace('#', '');
    groups().forEach(function (g) {
        var collapsed = stored.indexOf(String(g.dataset.genKey)) !== -1;
        /* переход по ссылке на конкретную семью (#fam-N) — раскрываем её группу */
        if (collapsed && hash && g.querySelector('#' + CSS.escape(hash))) collapsed = false;
        setState(g, collapsed);
    });
    if (hash) { var t = document.getElementById(hash); if (t) t.scrollIntoView(); }
})();
</script>

<script>
/* TASK-020: «Семьи детей» — раскрытие вниз по поколениям (клоны карточек со страницы) */
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.fm-kids-btn');
    if (!btn) return;
    var card = btn.closest('.fm-card');
    /* TASK-025: дети — отдельный ряд-панель сразу под карточкой семьи, карточки в строку */
    var existing = card._kidsBox || null;
    if (existing) {
        existing.remove();
        card._kidsBox = null;
        btn.setAttribute('aria-expanded', 'false');
        btn.title = btn.dataset.closed;
        return;
    }
    var box = document.createElement('div');
    box.className = 'fm-kids-row';
    var ownKey = card.id || card.dataset.famKey || '';
    var chain = (card.dataset.chain || '') + ',' + ownKey + ',';
    var seen = {};
    chain.split(',').forEach(function (k) { if (k) seen[k] = 1; });
    var ids = (btn.dataset.fams || '').split(',');
    ids.forEach(function (id) {
        var key = 'fam-' + id;
        var src = document.querySelector('.fm-grid > #' + key);
        if (!src || seen[key]) {
            var a = document.createElement('a');
            a.href = '/families#' + key;
            a.className = 'small';
            a.textContent = '→ #' + id;
            if (!src) { box.appendChild(a); }
            return;
        }
        var clone = src.cloneNode(true);
        clone.removeAttribute('id');
        clone.classList.add('fm-nested');
        clone.dataset.famKey = key;
        clone.dataset.chain = chain;
        var ib = clone.querySelector('.fm-kids-btn');
        if (ib) { ib.setAttribute('aria-expanded', 'false'); }
        box.appendChild(clone);
    });
    /* вставляем под последней карточкой того же визуального ряда — без пустот сбоку */
    var anchor = card, top = card.offsetTop, s = card.nextElementSibling;
    while (s && s.classList.contains('fm-card') && s.offsetTop === top) { anchor = s; s = s.nextElementSibling; }
    anchor.parentNode.insertBefore(box, anchor.nextSibling);
    card._kidsBox = box;
    btn.setAttribute('aria-expanded', 'true');
    btn.title = btn.dataset.open;
});
</script>

<!-- Модальное окно редактирования брака — белый остров поверх затемнения -->
<div id="marriageEditModal" class="modal-backdrop" style="display:none;">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="marriageEditTitle" style="max-width: 440px;">
        <div class="modal-head">
            <h3 id="marriageEditTitle"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Редактирование брака' : 'Некені өңдеу' ?></h3>
            <button type="button" class="btn btn-ghost btn-icon" onclick="closeMarriageEditModal()" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Закрыть' : 'Жабу' ?>">
                <svg class="ic"><use href="#i-x"/></svg>
            </button>
        </div>
        <form id="marriageEditForm" onsubmit="handleMarriageSave(event)">
            <input type="hidden" id="editMarriageId" name="id" value="">
            <div class="modal-body">
                <div class="field">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" id="editMarriageDivorced" onchange="toggleMarriageDivorce()">
                        <span><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Развелись' : 'Ажырасты' ?></span>
                    </label>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="editMarriageStartDate"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Дата брака' : 'Неке күні' ?></label>
                        <input type="date" id="editMarriageStartDate" class="input">
                    </div>
                    <div class="field" id="editMarriageEndField" style="display:none">
                        <label for="editMarriageEndDate"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Дата развода' : 'Ажырасқан күні' ?></label>
                        <input type="date" id="editMarriageEndDate" class="input">
                    </div>
                </div>
                <div class="field">
                    <label for="editMarriageNotes"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Примечание' : 'Жазба' ?></label>
                    <input type="text" id="editMarriageNotes" class="input">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" onclick="closeMarriageEditModal()"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Отмена' : 'Бас тарту' ?></button>
                <button type="submit" class="btn btn-primary"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Сохранить' : 'Сақтау' ?></button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/marriage-date.js?v=1"></script>
<script>
    function openMarriageEditModal(id, status, startDate, endDate, notes) {
        document.getElementById('editMarriageId').value = id;
        document.getElementById('editMarriageDivorced').checked = (status === 'divorced' || !!endDate);
        mdSet(document.getElementById('editMarriageStartDate'), startDate);
        mdSet(document.getElementById('editMarriageEndDate'), endDate);
        toggleMarriageDivorce();
        document.getElementById('editMarriageNotes').value = notes || '';
        document.getElementById('marriageEditModal').style.display = 'flex';
    }

    // ALT-051: возврат в карточку человека после сохранения; допустим только /person/N
    const FM_RETURN = <?= json_encode(preg_match('#^/person/\d+$#', (string)($_GET['return'] ?? '')) ? (string)$_GET['return'] : '') ?>;
    document.addEventListener('DOMContentLoaded', function () {
        const mid = <?= (int)($_GET['edit_marriage'] ?? 0) ?>;
        if (!mid || !FM_RETURN) { return; }
        const card = document.getElementById('fam-' + mid);
        const btn = card && card.querySelector('.fm-card-head button[onclick^="openMarriageEditModal"]');
        if (!btn) { fmNotify('Бұл некені табу мүмкін болмады / Этот брак не найден на странице'); return; }
        btn.click();
        const cb = document.getElementById('editMarriageDivorced');
        cb.checked = true;
        toggleMarriageDivorce();
        cb.closest('.field').style.cssText = 'background:#fff7d6;border:2px solid #e0a800;border-radius:8px;padding:8px;';
        document.getElementById('editMarriageEndField').style.cssText = 'background:#fff7d6;border:2px solid #e0a800;border-radius:8px;padding:8px;';
        document.getElementById('editMarriageEndDate').focus();
    });

    function toggleMarriageDivorce() {
        const on = document.getElementById('editMarriageDivorced').checked;
        document.getElementById('editMarriageEndField').style.display = on ? '' : 'none';
        if (!on) { mdClear(document.getElementById('editMarriageEndDate')); }
    }

    function closeMarriageEditModal() {
        document.getElementById('marriageEditModal').style.display = 'none';
    }

    function fmNotify(message) {
        if (typeof showAppToast === 'function') { showAppToast(message, 'error'); } else { alert(message); }
    }

    async function handleMarriageSave(e) {
        e.preventDefault();
        const id = document.getElementById('editMarriageId').value;
        const formData = new URLSearchParams();
        formData.append('id', id);
        const divorced = document.getElementById('editMarriageDivorced').checked;
        formData.append('status', divorced ? 'divorced' : 'married');
        formData.append('start_date', mdGet(document.getElementById('editMarriageStartDate')));
        formData.append('end_date', divorced ? mdGet(document.getElementById('editMarriageEndDate')) : '');
        formData.append('notes', document.getElementById('editMarriageNotes').value.trim());

        try {
            const res = await fetch('/api/marriage/update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            const data = await res.json();
            if (data && (data.status === 'success' || data.success)) {
                if (FM_RETURN) { window.location.href = FM_RETURN; } else { window.location.reload(); }
            } else {
                fmNotify('Қате: ' + (data.error || 'Сақтау мүмкін болмады'));
            }
        } catch (err) {
            fmNotify('Сұраныс қатесі: ' + err.message);
        }
    }
</script>

<?php
require __DIR__ . '/../layout/master_end.php';
?>
