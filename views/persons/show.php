<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
// TASK-028: отчество автоматически по отцу (ручное значение приоритетнее); ru — по-русски, остальные языки — казахская форма
// ALT-022: en/es — в строке имени без отчества (resolve() вернёт ''), отец — отдельной строкой «Son of …».
$patrLang = in_array($lang, ['ru', 'en', 'es'], true) ? $lang : 'kk';
$parentLabel = \App\Services\PatronymicService::parentLabel($person, $father ?? null, $lang);
$patrOf = static function (array $row, ?array $fatherRow) use ($patrLang): string {
    return \App\Services\PatronymicService::resolve($row, $fatherRow, $patrLang);
};
$fullName = trim(($person['last_name'] ?? '') . ' ' . ($person['first_name'] ?? '') . ' ' . $patrOf($person, $father ?? null));
$title = $fullName;
// ALT-022 (13:30): в заголовке карточки отчество/отец через тире. kk: «Фамилия Имя Сағали-ұлы» (тире внутри отчества),
// en/es: «Zhandos Altybayev — son of Sagali» / «— hijo de Sagali». ru — как в ALT-021, без тире.
$headLine = $fullName;
if ($lang === 'en' || $lang === 'es') {
    $fullName = trim(I18n::latinize(trim(($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? '')), $lang));
    $title = $fullName;
    $headLine = $parentLabel !== '' ? $fullName . ', ' . lcfirst($parentLabel) : $fullName;
} elseif ($lang === 'kk') {
    $kkPatr = $patrOf($person, $father ?? null);
    if ($kkPatr !== '') {
        // замечание 13:59: тире только внутри отчества («Сағали-ұлы»), после фамилии его нет
        $headLine = trim(($person['last_name'] ?? '') . ' ' . ($person['first_name'] ?? '') . ' ' . $kkPatr);
    }
}
$activeNav = 'persons';
$dsThemeReady = true; // TASK-008: страница переведена на дизайн-систему (светлая и тёмная темы)

$isDeceased = !empty($person['is_deceased']);
$isMale = ($person['gender'] === 'M');
// TASK-008 (дополнение 17:11): класс пола для аватара; если пола в строке нет — по роли (отец/мать, супруг)
$pcG = static function ($row, bool $fallbackMale): string {
    $g = is_array($row) ? ($row['gender'] ?? '') : '';
    if ($g === 'M') return ' is-m';
    if ($g === 'F') return ' is-f';
    return $fallbackMale ? ' is-m' : ' is-f';
};
$initials = mb_substr((string)($person['first_name'] ?? ''), 0, 1) . mb_substr((string)($person['last_name'] ?? ''), 0, 1);

// Полные даты через единый форматтер
$birthDisp = !empty($person['birth_date']) ? I18n::formatDate($person['birth_date']) : ($person['birth_year'] ? I18n::formatDate($person['birth_year']) : (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'не указан' : 'белгісіз'));
$deathDisp = !empty($person['death_date']) ? I18n::formatDate($person['death_date']) : ($person['death_year'] ? I18n::formatDate($person['death_year']) : (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'не указан' : 'белгісіз'));

// ALT-002: у умершего вместо даты рождения — годы жизни «1940–2015»
$pcBirthY = !empty($person['birth_date']) ? substr((string)$person['birth_date'], 0, 4) : (string)($person['birth_year'] ?? '');
$pcDeathY = !empty($person['death_date']) ? substr((string)$person['death_date'], 0, 4) : (string)($person['death_year'] ?? '');
if ($isDeceased && ($pcBirthY !== '' || $pcDeathY !== '')) {
    $birthDisp = ($pcBirthY !== '' ? $pcBirthY : '?') . '–' . ($pcDeathY !== '' ? $pcDeathY : '?');
}

// Маленький круглый аватар с инициалами (родственники)
$pcInitials = static function (array $p): string {
    $i = mb_substr((string)($p['first_name'] ?? ''), 0, 1) . mb_substr((string)($p['last_name'] ?? ''), 0, 1);
    return $i !== '' ? $i : 'Ш';
};
// TASK-013 (шаг 10): у живых — возраст («38 жас»), у ушедших — годы жизни и сколько прожил; полная дата — в подсказке
$pcYear = static function (array $row): string {
    return I18n::lifeLabel($row)['short'];
};
$pcLifeMeta = static function (array $row, string $prefix = ''): string {
    $l = I18n::lifeLabel($row);
    if ($l['short'] === '') return '';
    return $prefix . '<span class="pc-age" title="' . htmlspecialchars($l['title']) . '">' . htmlspecialchars($l['short']) . '</span>';
};
$heroLife = I18n::lifeLabel($person);
// В шапке полные даты уже есть — у ушедшего добавляем только «73 жыл ғұмыр»
$heroAge = !empty($heroLife['deceased']) ? (string)(explode(' · ', $heroLife['short'], 2)[1] ?? '') : $heroLife['short'];

// TASK-022: обложка карточки (только безопасные пути: загрузки и готовые фоны)
$coverUrl = (string)($person['cover_path'] ?? '');
if ($coverUrl !== '' && !preg_match('#^/(uploads/photos|assets/img/covers)/[A-Za-z0-9._-]+$#', $coverUrl)) {
    $coverUrl = '';
}
$coverPos = max(0, min(100, (int)($person['cover_pos_y'] ?? 50)));
$coverDim = max(0, min(70, (int)($person['cover_dim'] ?? 30)));

require __DIR__ . '/../layout/master_start.php';
?>

<style>
/* ALT-001: обложка заполняет весь фон шапки (как в Facebook), аватарка и текст поверх */
.pc-book.has-cover { position: relative; }
.pc-book .pc-cover { position: absolute; inset: 0; z-index: 0; background-size: cover; background-repeat: no-repeat; pointer-events: none; }
.pc-book .pc-cover::after { content: ""; position: absolute; inset: 0; background: #000; opacity: var(--cover-dim, .3); }
.pc-book.has-cover .photo-frame,
.pc-book.has-cover .ribbon-wrap,
.pc-book.has-cover .years,
.pc-book.has-cover .place,
.pc-book.has-cover .chips,
.pc-book.has-cover .actions { position: relative; z-index: 1; }
.pc-book.has-cover > .orn-corner { z-index: 2; }
/* подложка под текстом, чтобы читался на любой картинке */
.pc-book.has-cover .years,
.pc-book.has-cover .place { width: fit-content; max-width: 100%; margin-left: auto; margin-right: auto; padding: 2px 14px; border-radius: 999px; background: rgba(255,255,255,.82); color: #222; }
.pc-book.has-cover .years b { color: #222; }
.pc-book.has-cover .place { margin-top: 6px; }
.pc-book.has-cover .photo-frame .oval { border: 3px solid var(--c-island, #fff); box-sizing: border-box; }
/* ALT-001 note: тон надписей и кнопок подстраивается под яркость обложки (data-tone ставит JS ниже) */
.pc-book.has-cover .btn:not(.btn-primary),
.pc-book.has-cover .chips .badge { backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); box-shadow: 0 1px 4px rgba(0,0,0,.25); }
.pc-book.has-cover[data-tone="dark"] .btn:not(.btn-primary),
.pc-book.has-cover[data-tone="dark"] .chips .badge { background: rgba(0,0,0,.5); color: #fff; border-color: rgba(255,255,255,.55); text-shadow: 0 1px 2px rgba(0,0,0,.6); }
.pc-book.has-cover[data-tone="dark"] .years,
.pc-book.has-cover[data-tone="dark"] .place,
.pc-book.has-cover[data-tone="dark"] .years b { background: rgba(0,0,0,.5); color: #fff; }
.pc-book.has-cover[data-tone="dark"] .years b,
.pc-book.has-cover[data-tone="dark"] .pc-age { background: transparent; color: #fff; }
.pc-book.has-cover[data-tone="light"] .btn:not(.btn-primary),
.pc-book.has-cover[data-tone="light"] .chips .badge { background: rgba(255,255,255,.85); color: #222; border-color: rgba(0,0,0,.2); }
</style>
<script>
/* ALT-001 note: средняя яркость обложки под областью кнопок/надписей → data-tone="dark|light" */
window.pcCoverTone = function () {
    var book = document.querySelector('.pc-book'), cov = book && book.querySelector('.pc-cover');
    if (!book || !cov) { if (book) { book.removeAttribute('data-tone'); } return; }
    var m = /url\(["']?(.*?)["']?\)/.exec(cov.style.backgroundImage || '');
    if (!m) { return; }
    var img = new Image();
    img.onload = function () {
        try {
            var bw = book.clientWidth, bh = book.clientHeight, iw = img.naturalWidth, ih = img.naturalHeight;
            if (!bw || !bh || !iw || !ih) { return; }
            var k = 0.25, cw = Math.max(1, Math.round(bw * k)), ch = Math.max(1, Math.round(bh * k));
            var sc = Math.max(bw / iw, bh / ih), dw = iw * sc, dh = ih * sc;
            var posY = parseFloat((cov.style.backgroundPosition || '50% 50%').split(' ')[1]);
            if (isNaN(posY)) { posY = 50; }
            var cv = document.createElement('canvas'); cv.width = cw; cv.height = ch;
            var cx = cv.getContext('2d');
            cx.drawImage(img, (bw - dw) / 2 * k, (bh - dh) * posY / 100 * k, dw * k, dh * k);
            var br = book.getBoundingClientRect(), sum = 0, n = 0;
            ['.years', '.place', '.chips', '.actions'].forEach(function (sel) {
                var e = book.querySelector(sel); if (!e) { return; }
                var r = e.getBoundingClientRect();
                var x = Math.max(0, Math.floor((r.left - br.left) * k)), y = Math.max(0, Math.floor((r.top - br.top) * k));
                var w = Math.min(cw - x, Math.ceil(r.width * k)), h = Math.min(ch - y, Math.ceil(r.height * k));
                if (w < 1 || h < 1) { return; }
                var d = cx.getImageData(x, y, w, h).data;
                for (var i = 0; i < d.length; i += 4) { sum += 0.2126 * d[i] + 0.7152 * d[i + 1] + 0.0722 * d[i + 2]; n++; }
            });
            if (!n) { return; }
            var dim = parseFloat(getComputedStyle(cov).getPropertyValue('--cover-dim')); if (isNaN(dim)) { dim = 0.3; }
            var lum = (sum / n) * (1 - dim); /* с учётом затемнения обложки */
            book.setAttribute('data-tone', lum > 140 ? 'light' : 'dark');
        } catch (e) { book.setAttribute('data-tone', 'dark'); }
    };
    img.onerror = function () { book.setAttribute('data-tone', 'dark'); };
    img.crossOrigin = 'anonymous';
    img.src = m[1];
};
window.addEventListener('load', window.pcCoverTone);
window.addEventListener('resize', (function () { var t; return function () { clearTimeout(t); t = setTimeout(window.pcCoverTone, 250); }; })());
</script>

<div class="pc">

    <!-- TASK-012: заголовок карточки — страница семейной книги: фото в рисованной рамке, имя на ленте, годы, действия -->
    <div class="island book-page pc-book<?= $coverUrl !== '' ? ' has-cover' : '' ?>">
        <?php if ($coverUrl !== ''): ?>
            <div class="pc-cover" style="background-image:url('<?= htmlspecialchars($coverUrl, ENT_QUOTES) ?>'); background-position:50% <?= $coverPos ?>%; --cover-dim:<?= $coverDim / 100 ?>;" aria-hidden="true"></div>
        <?php endif; ?>
        <?php if ($canEdit || !empty($canEditContacts)): /* TASK-037: смена обложки/аватарки прямо на карточке */ ?>
            <button type="button" class="pme-fab pme-fab-cover" id="pmeCoverBtn" onclick="pmeMenuToggle(event)" title="<?= htmlspecialchars(I18n::t('pme_change_cover'), ENT_QUOTES) ?>" aria-label="<?= htmlspecialchars(I18n::t('pme_change_cover'), ENT_QUOTES) ?>">
                <svg class="ic" aria-hidden="true"><use href="#i-camera"/></svg><span><?= htmlspecialchars(I18n::t('pme_change_cover')) ?></span>
            </button>
            <?php require __DIR__ . '/../layout/person_media_card.php'; ?>
        <?php endif; ?>
        <svg class="orn orn-corner tl" aria-hidden="true"><use href="#o-corner"/></svg><svg class="orn orn-corner tr" aria-hidden="true"><use href="#o-corner"/></svg>
        <svg class="orn orn-corner bl" aria-hidden="true"><use href="#o-corner"/></svg><svg class="orn orn-corner br" aria-hidden="true"><use href="#o-corner"/></svg>
        <div class="photo-frame">
            <div class="oval<?= $isMale ? ' is-m' : ' is-f' ?><?= $isDeceased ? ' deceased' : '' ?>">
                <?php if (!empty($person['avatar_path'])): ?>
                    <img id="personAvatarImg" src="<?= htmlspecialchars((string)$person['avatar_path']) ?>" alt="<?= htmlspecialchars($fullName) ?>">
                <?php else: ?>
                    <span id="personAvatarImg"><?= htmlspecialchars($initials ?: 'Ш') ?></span>
                <?php endif; ?>
            </div>
            <?php if ($canEdit || !empty($canEditContacts)): ?>
                <label class="pme-fab pme-fab-avatar" for="pmeAvatarInput" title="<?= htmlspecialchars(I18n::t('pme_change_avatar'), ENT_QUOTES) ?>" aria-label="<?= htmlspecialchars(I18n::t('pme_change_avatar'), ENT_QUOTES) ?>" role="button" tabindex="0" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click();}">
                    <svg class="ic" aria-hidden="true"><use href="#i-camera"/></svg>
                </label>
            <?php endif; ?>
            <!-- рисованная рамка: мүйіз сверху, венок из листьев снизу (у марқұм — золотые листья) -->
            <svg class="wreath" viewBox="0 0 168 196" aria-hidden="true" focusable="false">
                <use href="#o-horn" x="62" y="-8" width="44" height="26" class="pc-horn"/>
                <path d="M40,150 C52,178 74,188 84,190 C94,188 116,178 128,150" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <?php $leafId = $isDeceased ? '#m-leafG' : '#m-leaf'; ?>
                <use href="<?= $leafId ?>" x="34" y="150" width="22" height="14" transform="rotate(-120 45 157)"/>
                <use href="<?= $leafId ?>" x="46" y="166" width="22" height="14" transform="rotate(-150 57 173)"/>
                <use href="<?= $leafId ?>" x="60" y="176" width="20" height="13" transform="rotate(-170 70 182)"/>
                <use href="<?= $leafId ?>" x="112" y="150" width="22" height="14" transform="rotate(-60 123 157)"/>
                <use href="<?= $leafId ?>" x="100" y="166" width="22" height="14" transform="rotate(-30 111 173)"/>
                <use href="<?= $leafId ?>" x="88" y="176" width="20" height="13" transform="rotate(-10 98 182)"/>
                <circle cx="84" cy="190" r="4" fill="#f3b3bd"/><circle cx="84" cy="190" r="1.6" fill="#e3b343"/>
            </svg>
        </div>
        <div class="ribbon-wrap"><h1 class="ribbon ribbon-lg<?= $isDeceased ? ' gold' : '' ?>"><?= htmlspecialchars($headLine) ?></h1></div>
        <div class="years"><b><?= htmlspecialchars($birthDisp) ?></b><?php if ($heroAge !== ''): ?> · <span class="pc-age"><?= htmlspecialchars($heroAge) ?></span><?php endif; ?></div>
        <?php if (!empty($person['birth_place'])): ?>
            <div class="place"><?= htmlspecialchars((string)$person['birth_place']) ?></div>
        <?php endif; ?>
        <div class="chips">
            <?php if (!empty($person['maiden_name'])): ?>
                <span class="badge maiden-it"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'урожд.' : 'қыз кезіндегі тегі:' ?> <?= htmlspecialchars((string)$person['maiden_name']) ?></span>
            <?php endif; ?>
        </div>

        <div class="actions">
            <a href="/tree?focus=<?= (int)$person['id'] ?>" class="btn">
                <svg class="ic" aria-hidden="true"><use href="#i-tree"/></svg>
                <span><?= htmlspecialchars(I18n::t('btn_show_in_tree')) ?></span>
            </a>
            <?php if ($canEdit || !empty($canEditContacts)): /* TASK-032: «своя семья» тоже */ ?>
                <button type="button" class="btn" onclick="openAddChildModal('M')">
                    <svg class="ic" aria-hidden="true"><use href="#i-plus"/></svg>
                    <span><?= htmlspecialchars(I18n::t('btn_add_son')) ?></span>
                </button>
                <button type="button" class="btn" onclick="openAddChildModal('F')">
                    <svg class="ic" aria-hidden="true"><use href="#i-plus"/></svg>
                    <span><?= htmlspecialchars(I18n::t('btn_add_daughter')) ?></span>
                </button>
                <button type="button" class="btn btn-primary" onclick="openGlobalPersonEditModal(<?= (int)$person['id'] ?>)">
                    <svg class="ic" aria-hidden="true"><use href="#i-edit"/></svg>
                    <span><?= htmlspecialchars(I18n::t('btn_edit')) ?></span>
                </button>
            <?php elseif (false): ?>
                <!-- TASK-022: сам человек (не editor) — фото, обложка, контакты в том же окне -->
                <button type="button" class="btn btn-primary" onclick="openGlobalPersonEditModal(<?= (int)$person['id'] ?>)">
                    <svg class="ic" aria-hidden="true"><use href="#i-edit"/></svg>
                    <span><?= htmlspecialchars(I18n::t('btn_edit')) ?></span>
                </button>
            <?php endif; ?>
            <?php if (!empty($canDelete)): /* ALT-018: админ и тот, кто добавил человека */ ?>
                <button type="button" class="btn btn-danger" id="btnPersonDelete" data-name="<?= htmlspecialchars($fullName) ?>">
                    <svg class="ic" aria-hidden="true" viewBox="0 0 24 24"><path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12"/></svg>
                    <span><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Удалить' : 'Жою' ?></span>
                </button>
                <script src="/assets/js/person-delete.js?v=1"></script>
                <script>
                document.getElementById('btnPersonDelete').addEventListener('click', function () {
                    altDeletePerson(<?= (int)$person['id'] ?>, this.dataset.name, {
                        lang: <?= json_encode($lang) ?>, csrf: <?= json_encode($csrfToken ?? '') ?>,
                        onDone: function () { window.location.href = '/persons'; }
                    });
                });
                </script>
            <?php endif; ?>
        </div>
    </div>

    <div class="pc-layout">

        <!-- Левая колонка: родство, фото, род -->
        <div class="pc-col">
            <div class="island">
                <div class="pc-label"><?= htmlspecialchars(I18n::t('kinship_to_you')) ?></div>
                <?php if ($kinship && !empty($kinship['zhurt'])):
                    $isOwnZhurt = !preg_match('/Нағашы|Қайын|Төркін|Күйеу|Құда/u', (string)$kinship['zhurt']);
                    $termText = I18n::formatKinship($kinship['term_kk'] ?? '', $kinship['term_ru'] ?? null);
                ?>
                    <span class="badge <?= $isOwnZhurt ? 'badge-accent' : '' ?>"><?= htmlspecialchars((string)$kinship['zhurt']) ?></span>
                    <div class="pc-kin-term"><?= I18n::kinshipHtml($kinship['term_kk'] ?? '', $kinship['term_ru'] ?? null) ?></div>
                <?php elseif ($kinship && !empty($kinship['term_kk'])): ?>
                    <div class="pc-kin-term"><?= I18n::kinshipHtml($kinship['term_kk'], $kinship['term_ru'] ?? null) ?></div>
                <?php else: ?>
                    <div class="muted"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Родственная связь уточняется' : 'Туыстық байланысы анықталуда' ?></div>
                <?php endif; ?>

                <!-- Безопасная загрузка фото -->
                <!-- TASK-037: фото и обложка меняются кнопками прямо на карточке (подсказка в окне «Өңдеу» убрана) -->
            </div>

            <!-- Краткая информация о роде -->
            <div class="island">
                <div class="island-head pc-kin-head"><svg class="orn orn-horn pc-horn-sm" aria-hidden="true"><use href="#o-horn"/></svg><h3><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Родовая принадлежность (Ру)' : 'Рулық мәліметі' ?></h3></div>
                <dl class="props pc-props-tight">
                    <dt><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Род (Ру)' : 'Руы' ?></dt>
                    <dd><?= clan_tamga_html($person['clan_ru'] ?? '') ?><?= htmlspecialchars((string)($person['clan_ru'] ?:(($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'не указан' : 'белгісіз'))) ?></dd>
                    <?php if (!empty($person['clan_subru'])): ?>
                        <dt><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Подрод' : 'Тармағы' ?></dt>
                        <dd><?= htmlspecialchars((string)$person['clan_subru']) ?></dd>
                    <?php endif; ?>
                    <dt><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Пол' : 'Жынысы' ?></dt>
                    <dd><?= $isMale ? (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Мужской' : 'Ер') : (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Женский' : 'Әйел') ?></dd>
                </dl>
            </div>

            <?php
            // TASK-022: контакты — видят все вошедшие (маршрут за auth), менять — сам человек, editor, admin
            $ctPhones = $contacts['phones'] ?? [];
            $ctEmail = ($contacts['emails'][0] ?? '');
            $ctState = $inviteState['state'] ?? 'none';
            $ctCsrf = \App\Core\Session::csrfToken();
            ?>
            <div class="island" id="contactsIsland">
                <div class="island-head pc-kin-head"><h3><?= htmlspecialchars(I18n::t('contacts_title')) ?></h3>
                    <?php if ($canEditContacts): ?>
                        <button type="button" class="btn btn-ghost btn-sm" id="ctEditBtn" onclick="openGlobalPersonEditModal(<?= (int)$person['id'] ?>)"><?= htmlspecialchars(I18n::t('btn_edit')) ?></button>
                    <?php endif; ?>
                </div>

                <div id="ctView">
                    <?php
                    // TASK-035: соцсети — только вошедшим (контакты уже за auth), только https-ссылки
                    $ctSocials = $contacts['socials'] ?? [];
                    $ctSocialMeta = ['instagram' => ['IG', '#c13584'], 'facebook' => ['f', '#1877f2'], 'telegram' => ['TG', '#229ed9'],
                        'whatsapp' => ['WA', '#25d366'], 'tiktok' => ['TT', '#111'], 'youtube' => ['YT', '#e00'],
                        'vk' => ['VK', '#0077ff'], 'linkedin' => ['in', '#0a66c2'], 'other' => ['↗', '#6b7280']];
                    ?>
                    <?php if (empty($ctPhones) && $ctEmail === '' && empty($ctSocials)): ?>
                        <div class="muted"><?= htmlspecialchars(I18n::t('contacts_empty')) ?></div>
                    <?php else: ?>
                        <dl class="props pc-props-tight">
                            <?php if (!empty($ctPhones)): ?>
                                <dt><?= htmlspecialchars(I18n::t('contacts_phones')) ?></dt>
                                <dd>
                                    <?php foreach ($ctPhones as $ph):
                                        $phDigits = preg_replace('/\D+/', '', $ph);
                                    ?>
                                        <div>
                                            <a href="tel:<?= htmlspecialchars('+' . $phDigits) ?>"><?= htmlspecialchars(\App\Services\PersonContactService::formatPhone($ph)) ?></a>
                                            <a class="badge" target="_blank" rel="noopener noreferrer" href="https://wa.me/<?= htmlspecialchars($phDigits) ?>" title="<?= htmlspecialchars(I18n::t('contacts_whatsapp')) ?>">WhatsApp</a>
                                        </div>
                                    <?php endforeach; ?>
                                </dd>
                            <?php endif; ?>
                            <?php if ($ctEmail !== ''): ?>
                                <dt><?= htmlspecialchars(I18n::t('contacts_email')) ?></dt>
                                <dd><a href="mailto:<?= htmlspecialchars($ctEmail) ?>"><?= htmlspecialchars($ctEmail) ?></a></dd>
                            <?php endif; ?>
                            <?php if (!empty($ctSocials)): ?>
                                <dt><?= htmlspecialchars(I18n::t('socials_title')) ?></dt>
                                <dd>
                                    <span class="ct-socials" style="display:inline-flex; gap:8px; flex-wrap:wrap;">
                                    <?php foreach ($ctSocials as $so):
                                        if (!preg_match('#^https://#', (string)$so['url'])) { continue; }
                                        $sm = $ctSocialMeta[$so['type']] ?? $ctSocialMeta['other'];
                                        $sName = \App\Services\PersonContactService::SOCIALS[$so['type']][0] ?? '';
                                        if ($sName === '') { $sName = (string)(parse_url($so['url'], PHP_URL_HOST) ?: I18n::t('socials_other')); }
                                    ?>
                                        <a class="ct-social" href="<?= htmlspecialchars($so['url'], ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer nofollow"
                                           title="<?= htmlspecialchars($sName, ENT_QUOTES) ?>" aria-label="<?= htmlspecialchars($sName, ENT_QUOTES) ?>"
                                           style="display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:50%; background:<?= $sm[1] ?>; color:#fff; font-weight:700; font-size:13px; text-decoration:none;"><?= htmlspecialchars($sm[0]) ?></a>
                                    <?php endforeach; ?>
                                    </span>
                                </dd>
                            <?php endif; ?>
                        </dl>
                    <?php endif; ?>

                    <?php if ($ctEmail !== ''): ?>
                        <div class="pc-upload" id="ctInviteBox">
                            <?php if ($ctState === 'accepted'): ?>
                                <span class="badge badge-leaf"><?= htmlspecialchars(I18n::t('invite_accepted')) ?></span>
                            <?php elseif ($ctState === 'pending'): ?>
                                <span class="badge badge-accent"><?= htmlspecialchars(I18n::t('invite_invited')) ?></span>
                            <?php elseif ($canInvite): ?>
                                <label for="ctInviteRole" class="small"><?= htmlspecialchars(I18n::t('invite_role')) ?></label>
                                <select id="ctInviteRole" class="input">
                                    <option value="viewer" selected><?= htmlspecialchars(I18n::t('role_viewer')) ?></option>
                                    <option value="editor"><?= htmlspecialchars(I18n::t('role_editor')) ?></option>
                                    <?php if ($isAdmin): ?><option value="admin"><?= htmlspecialchars(I18n::t('role_admin')) ?></option><?php endif; ?>
                                </select>
                                <button type="button" class="btn btn-primary" id="ctInviteBtn" onclick="ctSendInvite()"><?= htmlspecialchars(I18n::t('invite_send')) ?></button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($canEditContacts): ?>
                    <form id="ctForm" style="display:none;" onsubmit="ctSave(event)">
                        <div class="field">
                            <label><?= htmlspecialchars(I18n::t('contacts_phones')) ?> (+7)</label>
                            <div id="ctPhoneRows">
                                <?php foreach (($ctPhones ?: ['']) as $ph): ?>
                                    <div class="ct-phone-row" style="display:flex; gap:6px; margin-bottom:6px;">
                                        <input type="tel" class="input ct-phone" inputmode="tel" placeholder="+7 (___) ___-__-__" maxlength="20" value="<?= htmlspecialchars($ph !== '' ? \App\Services\PersonContactService::formatPhone($ph) : '') ?>">
                                        <button type="button" class="btn btn-ghost btn-icon" onclick="ctRemovePhone(this)" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Удалить номер' : 'Нөмірді өшіру' ?>">×</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="ctAddPhone()"><?= htmlspecialchars(I18n::t('contacts_add_phone')) ?></button>
                        </div>
                        <div class="field">
                            <label for="ctEmailInput"><?= htmlspecialchars(I18n::t('contacts_email')) ?></label>
                            <input type="email" id="ctEmailInput" class="input" maxlength="191" autocomplete="off" value="<?= htmlspecialchars($ctEmail) ?>">
                        </div>
                        <div class="small muted" style="margin-bottom:8px;"><?= htmlspecialchars(I18n::t('contacts_hint')) ?></div>
                        <div style="display:flex; gap:8px;">
                            <button type="submit" class="btn btn-primary"><?= htmlspecialchars(I18n::t('btn_save')) ?></button>
                            <button type="button" class="btn" onclick="ctToggleEdit(false)"><?= htmlspecialchars(I18n::t('btn_cancel')) ?></button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Правая колонка: сведения, родители, супруги, дети, братья/сёстры, медиа, үш жұрт -->
        <div class="pc-col">

            <!-- Основные даты и места, биография -->
            <div class="island">
                <div class="island-head pc-kin-head"><svg class="orn orn-branch pc-branch" aria-hidden="true"><use href="#o-branch"/></svg><h3><?= htmlspecialchars(I18n::t('personal_info')) ?></h3></div>
                <dl class="props">
                    <dt><?= htmlspecialchars(I18n::t('field_birth_date')) ?></dt>
                    <dd><?= htmlspecialchars($birthDisp) ?></dd>
                    <dt><?= htmlspecialchars(I18n::t('field_birth_place')) ?></dt>
                    <dd><?= htmlspecialchars((string)($person['birth_place'] ?: (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'не указано' : 'көрсетілмеген'))) ?></dd>
                    <?php
                    // TASK-036: тұрғылықты жері (история переездов), дін, ұлт, білім — пустые не показываем
                    $pcResSvc = new \App\Services\PersonResidenceService(\App\Core\Database::getConnection());
                    $pcRes = $pcResSvc->list((int)$person['id']);
                    $pcResCur = \App\Services\PersonResidenceService::current($pcRes);
                    if ($pcResCur): ?>
                        <dt><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Город проживания' : 'Тұрғылықты жері' ?></dt>
                        <dd>
                            <?php if (count($pcRes) > 1): ?>
                                <details class="pc-res">
                                    <summary style="cursor:pointer;"><?= htmlspecialchars((string)$pcResCur['place']) ?></summary>
                                    <ol style="margin:6px 0 0; padding-left:18px;">
                                        <?php foreach ($pcRes as $r):
                                            $yrs = !empty($r['is_current'])
                                                ? trim((string)$r['year_from'] . ' — қазір')
                                                : trim((string)$r['year_from'] . (($r['year_from'] && $r['year_to']) ? ' — ' : '') . (string)$r['year_to']);
                                        ?>
                                            <li><?= htmlspecialchars((string)$r['place']) ?><?= $yrs !== '' ? ' <span class="muted small">(' . htmlspecialchars($yrs) . ')</span>' : '' ?></li>
                                        <?php endforeach; ?>
                                    </ol>
                                </details>
                            <?php else: ?>
                                <?= htmlspecialchars((string)$pcResCur['place']) ?>
                                <?php if (!empty($pcRes[0]['year_from'])): ?><span class="muted small">(<?= htmlspecialchars((string)$pcRes[0]['year_from']) ?><?= !empty($pcRes[0]['is_current']) ? ' — қазір' : '' ?>)</span><?php endif; ?>
                            <?php endif; ?>
                        </dd>
                    <?php endif;
                    foreach ([
                        'religion'    => ['Діні', 'Религия'],
                        'nationality' => ['Ұлты', 'Национальность'],
                        'education'   => ['Білімі', 'Образование'],
                    ] as $pcKey => $pcLbl):
                        if (!empty($person[$pcKey])): ?>
                            <dt><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? $pcLbl[1] : $pcLbl[0] ?></dt>
                            <dd><?= htmlspecialchars((string)$person[$pcKey]) ?></dd>
                        <?php endif;
                    endforeach; ?>
                    <?php if ($isDeceased): ?>
                        <dt><?= htmlspecialchars(I18n::t('field_death_place')) ?></dt>
                        <dd><?= htmlspecialchars((string)($person['death_place'] ?: (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'не указано' : 'көрсетілмеген'))) ?></dd>
                        <?php
                        // ALT-002: подробности места упокоения — только заполненное
                        foreach ([
                            'burial_settlement' => ['Елді мекен', 'Населённый пункт'],
                            'burial_cemetery'   => ['Зират', 'Кладбище'],
                            'burial_address'    => ['Мекенжайы', 'Адрес'],
                            'burial_plot'       => ['Қабірдің орны', 'Расположение могилы'],
                        ] as $bKey => $bLbl):
                            if (!empty($person[$bKey])): ?>
                                <dt><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? $bLbl[1] : $bLbl[0] ?></dt>
                                <dd><?= htmlspecialchars((string)$person[$bKey]) ?></dd>
                            <?php endif;
                        endforeach; ?>
                    <?php endif; ?>
                </dl>

                <?php
                // ALT-002: мини-карта OpenStreetMap и маршрут — только если у умершего есть точка
                $bLat = (isset($person['burial_lat']) && $person['burial_lat'] !== '' && is_numeric($person['burial_lat'])) ? (float)$person['burial_lat'] : null;
                $bLng = (isset($person['burial_lng']) && $person['burial_lng'] !== '' && is_numeric($person['burial_lng'])) ? (float)$person['burial_lng'] : null;
                if ($isDeceased && $bLat !== null && $bLng !== null):
                    $bLatS = number_format($bLat, 6, '.', '');
                    $bLngS = number_format($bLng, 6, '.', '');
                ?>
                    <div class="pc-burial-map">
                        <div id="burialViewMap" style="height:220px; border-radius:8px; overflow:hidden; border:1px solid var(--border, #d0d5dd);"></div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:8px; align-items:center;">
                            <span class="muted small"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Маршрут:' : 'Бағыт:' ?></span>
                            <a class="btn btn-sm" target="_blank" rel="noopener" href="https://2gis.kz/?m=<?= $bLngS ?>,<?= $bLatS ?>/17">2GIS</a>
                            <a class="btn btn-sm" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=<?= $bLatS ?>,<?= $bLngS ?>">Google</a>
                            <a class="btn btn-sm" target="_blank" rel="noopener" href="https://yandex.kz/maps/?rtext=~<?= $bLatS ?>,<?= $bLngS ?>&rtt=auto">Яндекс</a>
                        </div>
                    </div>
                    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
                    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                    <script>
                    (function () {
                        var lat = <?= $bLatS ?>, lng = <?= $bLngS ?>;
                        var m = L.map('burialViewMap', { scrollWheelZoom: false }).setView([lat, lng], 15);
                        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                        }).addTo(m);
                        L.marker([lat, lng]).addTo(m);
                    })();
                    </script>
                <?php endif; ?>

                <?php if (!empty($person['biography'])):
                    $bioLines = explode("\n", trim((string)$person['biography']));
                    $isLong = mb_strlen((string)$person['biography']) > 160 || count($bioLines) > 3;
                ?>
                    <div class="pc-bio">
                        <div class="pc-bio-head">
                            <b><?= htmlspecialchars(I18n::t('biography_title')) ?></b>
                            <?php if ($isLong): ?>
                                <button type="button" class="btn btn-ghost btn-sm" onclick="toggleBiographyCollapse()">
                                    <span id="toggleBioLabel"><?= htmlspecialchars(I18n::t('btn_read_more')) ?></span>
                                    <span id="toggleBioArrow" aria-hidden="true">▼</span>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div id="biographyContainer" class="pc-bio-text" style="<?= $isLong ? 'max-height: 4.8em; overflow: hidden; position: relative;' : '' ?>"><?= htmlspecialchars(trim((string)$person['biography'])) ?><?php if ($isLong): ?><div id="biographyFadeOverlay" class="pc-bio-fade"></div><?php endif; ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Ата-анасы мен ерлі-зайыптылары -->
            <div class="grid pc-grid-2">
                <div class="island">
                    <div class="island-head pc-kin-head"><svg class="gmark" aria-hidden="true"><use href="#g-m"/></svg><h3><?= htmlspecialchars(I18n::t('parents_title')) ?></h3></div>
                    <div class="list">
                        <?php foreach ([['p' => $father, 'label' => I18n::t('father_label'), 'none' => (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Не указан' : 'Көрсетілмеген')],
                                        ['p' => $mother, 'label' => I18n::t('mother_label'), 'none' => (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Не указана' : 'Көрсетілмеген')]] as $par): ?>
                            <div class="list-row">
                                <?php if ($par['p']):
                                    $pp = $par['p'];
                                    $ppName = trim(($pp['last_name'] ?? '') . ' ' . $pp['first_name'] . ' ' . $patrOf($pp, null));
                                ?>
                                    <a href="/person/<?= (int)$pp['id'] ?>" class="person pc-link">
                                        <span class="avatar<?= $pcG($pp, $pp === $father) ?><?= !empty($pp['is_deceased']) ? ' deceased' : '' ?>"><?= htmlspecialchars($pcInitials($pp)) ?></span>
                                        <span>
                                            <span class="meta"><?= htmlspecialchars($par['label']) ?></span>
                                            <span class="name"><?= htmlspecialchars($ppName) ?></span>
                                            <?php if ($pcYear($pp) !== ''): ?><span class="meta"><?= $pcLifeMeta($pp) ?></span><?php endif; ?>
                                        </span>
                                    </a>
                                <?php else: ?>
                                    <div class="person">
                                        <span class="avatar">—</span>
                                        <span>
                                            <span class="meta"><?= htmlspecialchars($par['label']) ?></span>
                                            <span class="muted"><?= htmlspecialchars($par['none']) ?></span>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="island">
                    <div class="island-head pc-kin-head"><svg class="couple-rings" aria-hidden="true"><use href="#o-rings"/></svg><h3><?= htmlspecialchars(I18n::t('spouses_title')) ?></h3><span class="more"><?= count($spouses) ?></span></div>
                    <?php if (empty($spouses)): ?>
                        <div class="muted"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Сведения о браке отсутствуют' : 'Неке туралы мәлімет жоқ' ?></div>
                    <?php else: ?>
                        <div class="list">
                            <?php foreach ($spouses as $sp):
                                $isDiv = (($sp['status'] ?? '') === 'divorced');
                                $spName = trim(($sp['last_name'] ?? '') . ' ' . $sp['first_name'] . ' ' . $patrOf($sp, null));
                                $spDate = (string)(($sp['start_date'] ?? '') ?: ($sp['marriage_date'] ?? ''));
                            ?>
                                <div class="list-row<?= $isDiv ? ' pc-divorced' : '' ?>">
                                    <a href="/person/<?= (int)$sp['spouse_id'] ?>" class="person pc-link">
                                        <span class="avatar<?= $pcG($sp, !$isMale) ?><?= !empty($sp['is_deceased']) ? ' deceased' : '' ?>"><?= htmlspecialchars($pcInitials($sp)) ?></span>
                                        <span>
                                            <span class="name"><?= htmlspecialchars($spName) ?></span>
                                            <?php if (!empty($sp['maiden_name'])): ?>
                                                <span class="meta maiden-it"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'урожд.' : 'қыз кезіндегі:' ?> <?= htmlspecialchars((string)$sp['maiden_name']) ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </a>
                                    <span class="right">
                                        <span class="badge"><?= $isDiv ? (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Развод' : 'Ажырасқан') : (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Брак' : 'Некеде') ?></span>
                                        <?php
                                            // TASK-023: «N жыл бірге» / «N лет вместе» — полные годы по дате свадьбы
                                            $spEnd = (string)(($sp['end_date'] ?? '') ?: ($sp['divorce_date'] ?? ''));
                                            $spEndForCount = $isDiv ? $spEnd : I18n::earliestDeath($person ?? null, $sp);
                                            $spTogether = ($isDiv && $spEnd === '') ? '' : I18n::togetherLabel($spDate, $spEndForCount, $lang);
                                        ?>
                                        <?php if ($spTogether !== ''): ?><span class="meta pc-block"><strong><?= htmlspecialchars($spTogether) ?></strong></span><?php endif; ?>
                                        <?php if ($spDate !== ''): ?><span class="meta pc-block"><?= htmlspecialchars($spDate) ?></span><?php endif; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Балалары (Дети) -->
            <div class="island">
                <div class="island-head pc-kin-head"><svg class="orn pc-sprout" aria-hidden="true"><use href="#o-sprout"/></svg><h3><?= htmlspecialchars(I18n::t('children_title')) ?></h3><span class="more"><?= count($children) ?></span></div>
                <?php if (empty($children)): ?>
                    <div class="muted"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Дети не зарегистрированы' : 'Балалары тіркелмеген' ?></div>
                <?php else: ?>
                    <div class="pc-people">
                        <?php foreach ($children as $ch):
                            $chIsMale = ($ch['gender'] === 'M');
                            $chName = trim(($ch['last_name'] ?? '') . ' ' . $ch['first_name'] . ' ' . $patrOf($ch, $isMale ? $person : null));
                        ?>
                            <a href="/person/<?= (int)$ch['id'] ?>" class="person pc-chip">
                                <span class="avatar<?= $chIsMale ? ' is-m' : ' is-f' ?><?= !empty($ch['is_deceased']) ? ' deceased' : '' ?>"><?= htmlspecialchars($pcInitials($ch)) ?></span>
                                <span>
                                    <span class="name" title="<?= htmlspecialchars($chName) ?>"><?= htmlspecialchars($chName) ?></span>
                                    <span class="meta"><?= $chIsMale ? (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Сын' : 'Ұлы') : (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Дочь' : 'Қызы') ?><?= $pcLifeMeta($ch, ' · ') ?></span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Бауырлары (Братья и сёстры) -->
            <?php if (!empty($siblings)): ?>
                <div class="island">
                    <div class="island-head pc-kin-head"><svg class="gmark" aria-hidden="true"><use href="#g-f"/></svg><h3><?= htmlspecialchars(I18n::t('brothers_sisters')) ?></h3><span class="more"><?= count($siblings) ?></span></div>
                    <div class="pc-people">
                        <?php foreach ($siblings as $sib):
                            $sibIsMale = ($sib['gender'] === 'M');
                            $sibName = trim(($sib['last_name'] ?? '') . ' ' . $sib['first_name'] . ' ' . $patrOf($sib, (!empty($sib['father_id']) && (int)$sib['father_id'] === (int)($person['father_id'] ?? 0)) ? ($father ?? null) : null));
                        ?>
                            <a href="/person/<?= (int)$sib['id'] ?>" class="person pc-chip">
                                <span class="avatar<?= $sibIsMale ? ' is-m' : ' is-f' ?><?= !empty($sib['is_deceased']) ? ' deceased' : '' ?>"><?= htmlspecialchars($pcInitials($sib)) ?></span>
                                <span>
                                    <span class="name" title="<?= htmlspecialchars($sibName) ?>"><?= htmlspecialchars($sibName) ?></span>
                                    <span class="meta"><?= $sibIsMale ? (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Брат' : 'Бауыры') : (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Сестра' : 'Қарындасы/Әпкесі') ?><?= $pcLifeMeta($sib, ' · ') ?></span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TASK-018 (note 16.11 15:49): мероприятия человека и фото, где он отмечен -->
            <?php if (!empty($albumsReady)):
                $pcRu = (($lang === 'ru' || $lang === 'en' || $lang === 'es'));
                $pcEvents = $personEvents ?? [];
                $pcPhotos = $personPhotos['items'] ?? [];
                $pcPhotosTotal = (int)($personPhotos['total'] ?? 0);
                $pcNewEventUrl = '/events?' . http_build_query(['kind' => 'other', 'person_id' => (int)$person['id']]);

                // TASK-019: проверка готовности распознавания лиц и прав на приватность
                $faceSvc = new \App\Services\FaceRecognitionService();
                $faceReady = $faceSvc->isReady() && $faceSvc->enabled();
                $canManageFacePrivacy = ($isAdmin || (!empty($userPersonId) && (int)$userPersonId === (int)$person['id']));
                $neededPersonRefs = [];
                if ($faceReady && !empty($person['avatar_path']) && empty($person['face_opt_out'])) {
                    $neededPersonRefs = $faceSvc->referencesNeeded(1, [(int)$person['id']]);
                }
            ?>
                <link rel="stylesheet" href="/assets/css/album.css?v=5">
                <div class="island" id="pcEventsIsland">
                    <div class="island-head pc-kin-head">
                        <svg class="ic" aria-hidden="true"><use href="#i-camera"/></svg>
                        <h3><?= $pcRu ? 'Мероприятия' : 'Іс-шаралар' ?></h3>
                        <span class="more"><?= count($pcEvents) ?></span>
                        <?php if (!empty($canCreateEvent)): ?>
                            <a class="btn btn-sm btn-ghost pc-ev-add" href="<?= htmlspecialchars($pcNewEventUrl) ?>"
                               data-tip="<?= $pcRu ? 'Создать мероприятие для этого человека' : 'Осы адамға іс-шара құру' ?>">
                                <svg class="ic" aria-hidden="true"><use href="#i-plus"/></svg><?= $pcRu ? 'Мероприятие' : 'Іс-шара' ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php if (!$pcEvents): ?>
                        <div class="muted"><?= $pcRu ? 'Пока нет мероприятий с этим человеком.' : 'Бұл адаммен байланысты іс-шара әзірге жоқ.' ?></div>
                    <?php else: ?>
                        <div class="pc-ev-list">
                            <?php foreach ($pcEvents as $pe): ?>
                                <a class="island-sub pc-ev" href="/event/<?= (int)$pe['id'] ?>">
                                    <span class="pc-ev-thumbs">
                                        <?php if (!empty($pe['thumbs'])): foreach ($pe['thumbs'] as $th): ?>
                                            <img src="/album/media?id=<?= (int)$th['id'] ?>&amp;v=thumb" alt="" loading="lazy" decoding="async">
                                        <?php endforeach; else: ?>
                                            <span class="pc-ev-noimg"><svg class="ic" aria-hidden="true"><use href="#i-camera"/></svg></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="pc-ev-body">
                                        <b><?= htmlspecialchars((string)$pe['title']) ?></b>
                                        <span class="small muted">
                                            <?= !empty($pe['event_date']) ? htmlspecialchars(I18n::formatDate($pe['event_date'])) : ($pcRu ? 'дата не указана' : 'күні белгісіз') ?><?= !empty($pe['place']) ? ' · ' . htmlspecialchars((string)$pe['place']) : '' ?>
                                            · <?= (int)$pe['media_count'] ?> <?= $pcRu ? 'фото/видео' : 'фото/бейне' ?>
                                        </span>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($pcPhotos): ?>
                        <div class="pc-sub-title pc-ev-photos-title">
                            <?= $pcRu ? 'Фото и видео, где отмечен(а)' : 'Белгіленген фото мен бейнелер' ?> · <?= $pcPhotosTotal ?>
                        </div>
                        <div class="al-grid pc-ev-grid">
                            <?php foreach ($pcPhotos as $ph):
                                $phUrl = ($ph['owner_type'] === 'event' ? '/event/' . (int)$ph['owner_id'] : '/album/' . (int)$ph['album_id']) . '#m' . (int)$ph['id'];
                                $phLabel = (string)($ph['caption'] ?: $ph['album_title']);
                            ?>
                                <a class="al-tile" href="<?= htmlspecialchars($phUrl) ?>" title="<?= htmlspecialchars($phLabel) ?>" aria-label="<?= htmlspecialchars(($ph['media_type'] === 'video' ? ($pcRu ? 'Видео' : 'Бейне') : 'Фото') . ': ' . $phLabel) ?>">
                                    <?php if (!empty($ph['has_thumb'])): ?>
                                        <img src="/album/media?id=<?= (int)$ph['id'] ?>&amp;v=thumb" alt="" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <span class="al-noposter"><svg class="ic" aria-hidden="true"><use href="#i-camera"/></svg></span>
                                    <?php endif; ?>
                                    <?php if ($ph['media_type'] === 'video'): ?>
                                        <span class="al-play"><span><svg viewBox="0 0 24 24"><path d="M7 4.5v15l13-7.5z"/></svg></span></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($canManageFacePrivacy && $faceReady): ?>
                        <div class="island-sub pc-face-optout" style="margin-top:14px;padding:12px;background:var(--c-island-2,#f9fafb);border-radius:8px;border:1px solid var(--c-border,#e5e7eb);">
                            <div style="font-weight:600;font-size:13px;margin-bottom:6px;"><?= $pcRu ? 'Приватность и распознавание лиц' : 'Құпиялылық және бет тану' ?></div>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;margin:4px 0 8px;">
                                <input type="checkbox" id="personFaceOptOut" <?= !empty($person['face_opt_out']) ? 'checked' : '' ?> onchange="togglePersonFaceOptOut(<?= (int)$person['id'] ?>, this.checked)">
                                <span><?= $pcRu ? 'Запретить распознавание этого человека на фото' : 'Бұл адамды фотолардан тануға тыйым салу' ?></span>
                            </label>
                            <div>
                                <button type="button" class="btn btn-ghost btn-sm" style="color:var(--danger,#b42318);" onclick="deletePersonFaceData(<?= (int)$person['id'] ?>)">
                                    <svg class="ic" aria-hidden="true"><use href="#i-trash"/></svg>
                                    <span><?= $pcRu ? 'Удалить отпечатки лица' : 'Бет бейнелерін өшіру' ?></span>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Дыбыс жазбалары мен естеліктер (TASK-004 Media) -->
            <div class="island">
                <div class="island-head"><h3><?= htmlspecialchars(I18n::t('audio_and_photos')) ?></h3></div>
                <?php if (empty($mediaList['audio']) && empty($mediaList['stories']) && empty($mediaList['photos'])): ?>
                    <div class="muted"><?= htmlspecialchars(I18n::t('no_media_yet')) ?></div>
                <?php else: ?>
                    <div class="pc-stack">
                        <?php foreach (($mediaList['audio'] ?? []) as $aud): ?>
                            <div class="island-sub">
                                <div class="pc-sub-title"><?= htmlspecialchars((string)($aud['title'] ?: (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Аудиозапись воспоминания' : 'Дыбыс жазбасы'))) ?></div>
                                <audio controls class="pc-audio">
                                    <source src="/media/stream?id=<?= (int)$aud['id'] ?>" type="audio/mpeg">
                                </audio>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach (($mediaList['stories'] ?? []) as $st): ?>
                            <div class="island-sub">
                                <div class="pc-sub-title"><?= htmlspecialchars((string)($st['title'] ?: (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Воспоминание' : 'Естелік'))) ?></div>
                                <div class="pc-bio-text"><?= htmlspecialchars((string)$st['content']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Үш жұрт (три родственных круга с семейными папками, TASK-007) -->
            <?php if (!empty($ushZhurt)):
                // TASK-013: стороны по порядку с сервера (женщина: Төркіні / Өз жұрты / Нағашы; мужчина: Өз / Қайын / Нағашы)
                $zhurtOrder = $ushZhurt['order'] ?? ['oz', 'kaiyn', 'nagashy'];
                $zhurtList = [];
                foreach ($zhurtOrder as $zk) {
                    if (!empty($ushZhurt[$zk . '_zhurt'])) {
                        $zhurtList[$zk] = $ushZhurt[$zk . '_zhurt'];
                    }
                }
                $firstZhurtKey = (string)array_key_first($zhurtList);
                $zhurtIsFemale = !empty($ushZhurt['is_female']);
            ?>
                <div class="island">
                    <div class="island-head pc-zhurt-head">
                        <h3><?= htmlspecialchars(I18n::t('ush_zhurt_title')) ?></h3>
                        <div class="seg" role="group" aria-label="<?= htmlspecialchars(I18n::t('ush_zhurt_title')) ?>">
                            <?php foreach ($zhurtList as $zKey => $zData): ?>
                                <button type="button" onclick="switchZhurtTab('<?= htmlspecialchars($zKey) ?>')" id="tabBtn_<?= htmlspecialchars($zKey) ?>" class="<?= $zKey === $firstZhurtKey ? 'active' : '' ?>">
                                    <?= htmlspecialchars((string)(($lang === 'ru' || $lang === 'en' || $lang === 'es') ? $zData['title_ru'] : $zData['title_kk'])) ?> <span class="muted">(<?= (int)$zData['total_count'] ?>)</span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php
                    $renderZhurtPane = function (array $zhurtData, string $paneId, bool $isActive) use ($lang, $person, $pcLifeMeta) {
                        ?>
                        <div id="zhurtPane_<?= htmlspecialchars($paneId) ?>" style="display: <?= $isActive ? 'block' : 'none' ?>;">
                            <?php if (empty($zhurtData['families']) && empty($zhurtData['standalone'])): ?>
                                <div class="pp-empty muted">
                                    <?= htmlspecialchars((string)(($lang === 'ru' || $lang === 'en' || $lang === 'es') ? ($zhurtData['empty_ru'] ?? 'Пока никого не добавили') : ($zhurtData['empty_kk'] ?? 'Әзірге ешкім қосылмаған'))) ?>
                                    <a class="btn" href="/tree?focus=<?= (int)($person['id'] ?? 0) ?>"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Добавить' : 'Қосу' ?></a>
                                </div>
                            <?php else: ?>
                                <div class="pc-stack">
                                    <?php foreach ($zhurtData['families'] as $fam):
                                        $h = $fam['husband'];
                                        $w = $fam['wife'];
                                        $hName = $h ? trim(($h['last_name'] ?? '') . ' ' . $h['first_name']) : (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Неизвестно' : 'Белгісіз');
                                        $wName = $w ? trim(($w['last_name'] ?? '') . ' ' . $w['first_name']) : (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Неизвестно' : 'Белгісіз');
                                        $childCount = count($fam['children']);
                                    ?>
                                        <details open class="pc-family">
                                            <summary>
                                                <span class="pc-family-names">
                                                    <?php if ($h): ?>
                                                        <a href="/person/<?= (int)$h['id'] ?>"><?= htmlspecialchars($hName) ?></a>
                                                        <?php if (!empty($fam['husband_kinship']['term_kk'])): ?>
                                                            <span class="badge"><?= I18n::kinshipHtml($fam['husband_kinship']['term_kk'], $fam['husband_kinship']['term_ru'] ?? null) ?></span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="muted"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Неизвестно' : 'Белгісіз' ?></span>
                                                    <?php endif; ?>
                                                    <svg class="couple-rings pc-plus" aria-hidden="true" focusable="false"><use href="#o-rings"/></svg>
                                                    <?php if ($w): ?>
                                                        <a href="/person/<?= (int)$w['id'] ?>"><?= htmlspecialchars($wName) ?></a>
                                                        <?php if (!empty($fam['wife_kinship']['term_kk'])): ?>
                                                            <span class="badge"><?= I18n::kinshipHtml($fam['wife_kinship']['term_kk'], $fam['wife_kinship']['term_ru'] ?? null) ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($fam['wife_kinship']['uzatylgan']['husband_id'])):
                                                            $uzInfo = $fam['wife_kinship']['uzatylgan'];
                                                            $uzFam = $uzInfo['husband_last_name'] ?: ($uzInfo['husband_clan'] ?: $uzInfo['husband_name']);
                                                        ?>
                                                            <span class="muted small" style="opacity:.7;"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'замужем' : 'ұзатылған' ?> — <?= htmlspecialchars((string)$uzFam) ?></span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="muted"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Неизвестно' : 'Белгісіз' ?></span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="badge"><?= $childCount ?> <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'детей' : 'бала' ?></span>
                                            </summary>
                                            <div class="pc-family-body">
                                                <?php if ($childCount === 0): ?>
                                                    <div class="muted small"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Дети не зарегистрированы' : 'Балалары тіркелмеген' ?></div>
                                                <?php else: ?>
                                                    <div class="list">
                                                        <?php foreach ($fam['children'] as $chItem):
                                                            $ch = $chItem['person'];
                                                            $chKin = $chItem['kinship'];
                                                            $chName = trim(($ch['last_name'] ?? '') . ' ' . $ch['first_name']);
                                                        ?>
                                                            <div class="list-row">
                                                                <a href="/person/<?= (int)$ch['id'] ?>" class="person pc-link">
                                                                    <span class="avatar<?= (($ch['gender'] ?? '') === 'M') ? ' is-m' : ' is-f' ?><?= !empty($ch['is_deceased']) ? ' deceased' : '' ?>"><?= htmlspecialchars(mb_substr((string)($ch['first_name'] ?? ''), 0, 1) . mb_substr((string)($ch['last_name'] ?? ''), 0, 1) ?: 'Ш') ?></span>
                                                                    <span>
                                                                        <span class="name"><?= htmlspecialchars($chName) ?></span>
                                                                        <span class="meta"><?= ($ch['gender'] === 'M') ? (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Сын' : 'Ұлы') : (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Дочь' : 'Қызы') ?><?= is_array($ch) ? $pcLifeMeta($ch, ' · ') : '' ?></span>
                                                                    </span>
                                                                </a>
                                                                <?php if (!empty($chKin['term_kk'])): ?>
                                                                    <span class="right"><span class="badge"><?= I18n::kinshipHtml($chKin['term_kk'], $chKin['term_ru'] ?? null) ?></span></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    <?php endforeach; ?>

                                    <?php if (!empty($zhurtData['standalone'])): ?>
                                        <div class="island-sub">
                                            <div class="pc-label"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Другие родственники этого круга' : 'Осы жұрттағы басқа туыстар' ?></div>
                                            <div class="pc-tags">
                                                <?php foreach ($zhurtData['standalone'] as $stItem):
                                                    $stP = $stItem['person'];
                                                    $stKin = $stItem['kinship'];
                                                ?>
                                                    <a href="/person/<?= (int)$stP['id'] ?>" class="badge pc-tag">
                                                        <b><?= htmlspecialchars(trim(($stP['last_name'] ?? '') . ' ' . $stP['first_name'])) ?></b>
                                                        <?php if (!empty($stKin['term_kk'])): ?>
                                                            <span>· <?= I18n::kinshipHtml($stKin['term_kk'], $stKin['term_ru'] ?? null) ?></span>
                                                        <?php endif; ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php
                    };

                    foreach ($zhurtList as $zKey => $zData) {
                        $renderZhurtPane($zData, (string)$zKey, $zKey === $firstZhurtKey);
                    }
                    ?>
                </div>
            <?php endif; ?>

            <!-- Служебное (только для администратора, свёрнуто) -->
            <?php if ($isAdmin): /* ALT-004: история изменений этого человека */ ?>
                <details class="island-sub pc-tech" id="personHistory">
                    <summary>
                        <svg class="ic" aria-hidden="true"><use href="#i-history"/></svg>
                        <span><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'История изменений' : 'Өзгерістер тарихы' ?> (<?= count($auditLogs ?? []) ?>)</span>
                    </summary>
                    <?php if (empty($auditLogs)): ?>
                        <p class="muted small"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Изменений пока нет' : 'Өзгерістер әлі жоқ' ?></p>
                    <?php else: ?>
                        <ul class="pc-hist" style="list-style:none;margin:0;padding:0;display:grid;gap:10px">
                            <?php foreach ($auditLogs as $l): ?>
                                <li data-log-id="<?= (int)$l['id'] ?>">
                                    <div class="small">
                                        <span class="muted"><?= htmlspecialchars(date('H:i · d.m.Y', strtotime((string)$l['created_at']))) ?></span>
                                        · <b><?= htmlspecialchars((string)($l['who'] ?: '—')) ?></b>
                                        · <span class="badge"><?= htmlspecialchars($l['verb']) ?></span>
                                        <?php if ($l['entity_table'] !== 'persons'): ?><span class="muted">(<?= htmlspecialchars($l['object_label']) ?>)</span><?php endif; ?>
                                        <?php if (!empty($l['rolled_back'])): ?>
                                            <span class="badge"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'откачено' : 'қайтарылды' ?></span>
                                        <?php elseif (!empty($l['can_rollback'])): ?>
                                            <button type="button" class="btn btn-sm" onclick="pcRollback(<?= (int)$l['id'] ?>)"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Откатить' : 'Қайтару' ?></button>
                                        <?php endif; ?>
                                    </div>
                                    <?php include __DIR__ . '/../history/_changes.php'; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="small"><a href="/history?person=<?= (int)$person['id'] ?>"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Открыть в журнале изменений →' : 'Өзгерістер журналында ашу →' ?></a></p>
                    <?php endif; ?>
                </details>
                <script>
                    async function pcRollback(logId) {
                        if (!confirm('<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Вернуть прежние значения?' : 'Бұрынғы мәндерді қайтару керек пе?' ?>')) return;
                        try {
                            const res = await fetch('/api/history/rollback', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({ log_id: logId }).toString()
                            });
                            const data = await res.json();
                            if (data && data.success) { window.location.reload(); }
                            else { alert(data.error || 'Error'); }
                        } catch (e) { alert(e.message); }
                    }
                </script>
                <details class="island-sub pc-tech">
                    <summary>
                        <svg class="ic" aria-hidden="true"><use href="#i-settings"/></svg>
                        <span><?= htmlspecialchars(I18n::t('technical_details')) ?> (ID, GEDCOM, Audit)</span>
                    </summary>
                    <div class="pc-tech-grid">
                        <div>ID: <strong><?= (int)$person['id'] ?></strong></div>
                        <div>GEDCOM: <strong><?= htmlspecialchars(str_replace('@', '', (string)($person['gedcom_id'] ?? '—'))) ?></strong></div>
                        <div>Clan ID: <strong><?= (int)($person['clan_id'] ?? 0) ?></strong></div>
                        <div>Father ID: <strong><?= (int)($person['father_id'] ?? 0) ?></strong></div>
                        <div>Mother ID: <strong><?= (int)($person['mother_id'] ?? 0) ?></strong></div>
                        <div>Created: <strong><?= htmlspecialchars((string)($person['created_at'] ?? '—')) ?></strong></div>
                        <div>Updated: <strong><?= htmlspecialchars((string)($person['updated_at'] ?? '—')) ?></strong></div>
                    </div>
                </details>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Модальное окно добавления сына / дочери — белый остров поверх затемнения -->
<div id="addChildModal" class="modal-backdrop" style="display:none;">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="addChildModalTitle" style="max-width: 480px;">
        <div class="modal-head">
            <h3 id="addChildModalTitle">Баланы қосу</h3>
            <button type="button" class="btn btn-ghost btn-icon" onclick="closeAddChildModal()" aria-label="Жабу / Закрыть">
                <svg class="ic"><use href="#i-x"/></svg>
            </button>
        </div>
        <form id="addChildForm" onsubmit="handleAddChildSubmit(event)">
            <input type="hidden" name="target_id" value="<?= (int)$person['id'] ?>">
            <input type="hidden" id="addChildGender" name="gender" value="M">
            <input type="hidden" name="relation_type" value="child">

            <div class="modal-body">
                <div class="field">
                    <label for="addChildFirstName">Аты / Имя *</label>
                    <input type="text" id="addChildFirstName" class="input" required>
                </div>
                <div class="field">
                    <label for="addChildLastName">Тегі / Фамилия</label>
                    <input type="text" id="addChildLastName" class="input" value="<?= htmlspecialchars((string)($person['last_name'] ?? '')) ?>">
                </div>
                <div class="field">
                    <label for="addChildMiddleName">Әкесінің аты / Отчество</label>
                    <input type="text" id="addChildMiddleName" class="input" value="<?= $isMale ? htmlspecialchars((string)($person['first_name'] . 'ұлы')) : '' ?>">
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="addChildBirthYear">Туған жылы / Год</label>
                        <input type="text" id="addChildBirthYear" class="input" placeholder="2020">
                    </div>
                    <div class="field">
                        <label for="addChildBirthDate">Туған күні / Дата</label>
                        <input type="date" id="addChildBirthDate" class="input">
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" onclick="closeAddChildModal()">Бас тарту / Отмена</button>
                <button type="submit" class="btn btn-primary">Қосу / Добавить</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddChildModal(gender) {
        document.getElementById('addChildGender').value = gender;
        document.getElementById('addChildModalTitle').innerText = (gender === 'M')
            ? '<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Добавить сына' : 'Ұл қосу' ?>'
            : '<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Добавить дочь' : 'Қыз қосу' ?>';
        document.getElementById('addChildModal').style.display = 'flex';
    }

    function closeAddChildModal() {
        document.getElementById('addChildModal').style.display = 'none';
    }

    function pcNotify(message) {
        if (typeof showAppToast === 'function') { showAppToast(message, 'error'); } else { alert(message); }
    }

    async function handleAddChildSubmit(e) {
        e.preventDefault();
        const targetId = <?= (int)$person['id'] ?>;
        const gender = document.getElementById('addChildGender').value;
        const relType = (gender === 'M') ? 'son' : 'daughter';

        const formData = new URLSearchParams();
        formData.append('target_id', targetId);
        formData.append('relation_type', relType);
        formData.append('first_name', document.getElementById('addChildFirstName').value.trim());
        formData.append('last_name', document.getElementById('addChildLastName').value.trim());
        formData.append('middle_name', document.getElementById('addChildMiddleName').value.trim());
        formData.append('gender', gender);
        formData.append('birth_year', document.getElementById('addChildBirthYear').value.trim());
        formData.append('birth_date', document.getElementById('addChildBirthDate').value);

        try {
            const res = await fetch('/api/person/create-relative', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            const data = await res.json();
            if (data && (data.status === 'success' || data.success)) {
                window.location.reload();
            } else {
                pcNotify('Қате: ' + (data.error || 'Қосу мүмкін болмады'));
            }
        } catch (err) {
            pcNotify('Сұраныс қатесі: ' + err.message);
        }
    }

    async function handlePhotoUpload(e) {
        e.preventDefault();
        const form = document.getElementById('avatarUploadForm');
        const fileInput = document.getElementById('photoFileInput');
        const msg = document.getElementById('uploadStatusMsg');

        if (!fileInput.files || fileInput.files.length === 0) return;

        msg.style.display = 'block';
        msg.style.color = 'var(--c-text-2)';
        msg.innerText = '<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Проверка и пересохранение фото...' : 'Сурет тексеріліп, сақталуда...' ?>';

        const formData = new FormData(form);

        try {
            const res = await fetch('/api/photo/upload', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data && data.success) {
                msg.style.color = 'var(--c-success)';
                msg.innerText = '<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Фото успешно обновлено!' : 'Фото сәтті жаңартылды!' ?>';
                setTimeout(() => window.location.reload(), 800);
            } else {
                msg.style.color = 'var(--c-danger)';
                msg.innerText = 'Қате: ' + (data.error || 'Жүктеу сәтсіз аяқталды');
            }
        } catch (err) {
            msg.style.color = 'var(--c-danger)';
            msg.innerText = 'Сұраныс қатесі: ' + err.message;
        }
    }

    // ---- TASK-022: контакты и приглашение ----
    const CT_PERSON_ID = <?= (int)$person['id'] ?>;
    const CT_CSRF = <?= json_encode(\App\Core\Session::csrfToken()) ?>;

    function ctMask(input) {
        let d = input.value.replace(/\D+/g, '');
        if (input.value.trim().startsWith('+') && !d.startsWith('7')) { return; } // иностранный номер — без маски
        if (d.startsWith('8')) d = '7' + d.slice(1);
        if (d && !d.startsWith('7')) d = '7' + d;
        d = d.slice(0, 11);
        const p = d.slice(1);
        let out = d ? '+7' : '';
        if (p.length > 0) out += ' (' + p.slice(0, 3);
        if (p.length >= 3) out += ')';
        if (p.length > 3) out += ' ' + p.slice(3, 6);
        if (p.length > 6) out += '-' + p.slice(6, 8);
        if (p.length > 8) out += '-' + p.slice(8, 10);
        input.value = out;
    }
    function ctBindMask(input) {
        input.addEventListener('input', function () { ctMask(input); });
    }
    document.querySelectorAll('.ct-phone').forEach(ctBindMask);

    function ctToggleEdit(on) {
        const f = document.getElementById('ctForm');
        const v = document.getElementById('ctView');
        const b = document.getElementById('ctEditBtn');
        if (!f || !v) return;
        f.style.display = on ? 'block' : 'none';
        v.style.display = on ? 'none' : 'block';
        if (b) b.style.display = on ? 'none' : '';
    }
    function ctAddPhone() {
        const rows = document.getElementById('ctPhoneRows');
        if (rows.querySelectorAll('.ct-phone-row').length >= <?= \App\Services\PersonContactService::MAX_PHONES ?>) return;
        const row = document.createElement('div');
        row.className = 'ct-phone-row';
        row.style.cssText = 'display:flex; gap:6px; margin-bottom:6px;';
        const inp = document.createElement('input');
        inp.type = 'tel'; inp.className = 'input ct-phone'; inp.inputMode = 'tel'; inp.maxLength = 20;
        inp.placeholder = '+7 (___) ___-__-__';
        ctBindMask(inp);
        const btn = document.createElement('button');
        btn.type = 'button'; btn.className = 'btn btn-ghost btn-icon'; btn.textContent = '×';
        btn.addEventListener('click', function () { ctRemovePhone(btn); });
        row.appendChild(inp); row.appendChild(btn);
        rows.appendChild(row);
        inp.focus();
    }
    function ctRemovePhone(btn) {
        const row = btn.closest('.ct-phone-row');
        const rows = document.getElementById('ctPhoneRows');
        if (rows.querySelectorAll('.ct-phone-row').length > 1) { row.remove(); }
        else { row.querySelector('input').value = ''; }
    }
    async function ctPost(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        });
        let data = {};
        try { data = await res.json(); } catch (e) {}
        if (!res.ok || !data.success) { throw new Error(data.error || ('HTTP ' + res.status)); }
        return data;
    }
    async function ctSave(e) {
        e.preventDefault();
        const body = new URLSearchParams();
        body.append('csrf_token', CT_CSRF);
        body.append('person_id', CT_PERSON_ID);
        document.querySelectorAll('.ct-phone').forEach(function (i) {
            if (i.value.trim() !== '') body.append('phones[]', i.value.trim());
        });
        body.append('email', document.getElementById('ctEmailInput').value.trim());
        try {
            await ctPost('/api/person/contacts/save', body);
            window.location.reload();
        } catch (err) {
            if (typeof showAppToast === 'function') { showAppToast(err.message, 'error'); } else { alert(err.message); }
        }
    }
    async function ctSendInvite() {
        const btn = document.getElementById('ctInviteBtn');
        if (btn) btn.disabled = true;
        const body = new URLSearchParams();
        body.append('csrf_token', CT_CSRF);
        body.append('person_id', CT_PERSON_ID);
        body.append('role', document.getElementById('ctInviteRole').value);
        try {
            const data = await ctPost('/api/person/invite', body);
            if (typeof showAppToast === 'function') { showAppToast(data.message || 'OK', 'success'); }
            setTimeout(function () { window.location.reload(); }, 900);
        } catch (err) {
            if (btn) btn.disabled = false;
            if (typeof showAppToast === 'function') { showAppToast(err.message, 'error'); } else { alert(err.message); }
        }
    }

    function toggleBiographyCollapse() {
        const container = document.getElementById('biographyContainer');
        const overlay = document.getElementById('biographyFadeOverlay');
        const label = document.getElementById('toggleBioLabel');
        const arrow = document.getElementById('toggleBioArrow');
        if (!container) return;
        const isCollapsed = container.style.maxHeight !== 'none';
        if (isCollapsed) {
            container.style.maxHeight = 'none';
            if (overlay) overlay.style.display = 'none';
            if (label) label.innerText = <?= json_encode(I18n::t('btn_collapse'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            if (arrow) arrow.innerText = '▲';
        } else {
            container.style.maxHeight = '4.8em';
            if (overlay) overlay.style.display = 'block';
            if (label) label.innerText = <?= json_encode(I18n::t('btn_read_more'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            if (arrow) arrow.innerText = '▼';
        }
    }

    function switchZhurtTab(tab) {
        const tabs = ['torkin', 'oz', 'nagashy', 'kaiyn'];
        tabs.forEach(t => {
            const pane = document.getElementById('zhurtPane_' + t);
            const btn = document.getElementById('tabBtn_' + t);
            if (pane) pane.style.display = (t === tab) ? 'block' : 'none';
            if (btn) btn.classList.toggle('active', t === tab);
        });
    }

    async function togglePersonFaceOptOut(personId, optOut) {
        try {
            const res = await fetch('/api/face/opt-out', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ csrf_token: <?= json_encode(\App\Core\Session::csrfToken()) ?>, person_id: personId, opt_out: optOut })
            });
            const data = await res.json();
            if (res.ok && data && (data.success || data.status === 'success')) {
                showAppToast(<?= json_encode(($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Настройки приватности обновлены' : 'Құпиялылық баптаулары сақталды', JSON_UNESCAPED_UNICODE) ?>, 'success');
            } else {
                showAppToast('Қате / Ошибка: ' + ((data && data.error) || 'Ошибка'), 'error');
            }
        } catch (e) {
            showAppToast('Қате / Ошибка: ' + e.message, 'error');
        }
    }

    async function deletePersonFaceData(personId) {
        const msg = <?= json_encode(($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Удалить все отпечатки лица этого человека? Ручные отметки на фото останутся.' : 'Бұл адамның барлық бет бейнелерін өшіру керек пе?', JSON_UNESCAPED_UNICODE) ?>;
        if (!window.confirm(msg)) return;
        try {
            const res = await fetch('/api/face/delete-person', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ csrf_token: <?= json_encode(\App\Core\Session::csrfToken()) ?>, person_id: personId })
            });
            const data = await res.json();
            if (res.ok && data && (data.success || data.status === 'success')) {
                showAppToast(<?= json_encode(($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Отпечатки лица удалены' : 'Бет бейнелері өшірілді', JSON_UNESCAPED_UNICODE) ?>, 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showAppToast('Қате / Ошибка: ' + ((data && data.error) || 'Ошибка'), 'error');
            }
        } catch (e) {
            showAppToast('Қате / Ошибка: ' + e.message, 'error');
        }
    }
</script>

<?php if (!empty($neededPersonRefs)): ?>
<script>
window.FR_PERSON = <?= json_encode([
    'csrf' => \App\Core\Session::csrfToken(),
    'lang' => $lang,
    'refs' => $neededPersonRefs,
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/assets/js/face-recognition.js?v=2" defer></script>
<?php endif; ?>

<script src="/assets/js/photo-viewer.js?v=2" defer></script>

<?php
require __DIR__ . '/../layout/master_end.php';
?>
