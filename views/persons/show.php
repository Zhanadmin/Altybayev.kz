<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
$fullName = trim(($person['last_name'] ?? '') . ' ' . ($person['first_name'] ?? '') . ' ' . ($person['middle_name'] ?? ''));
$title = $fullName;
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
$birthDisp = !empty($person['birth_date']) ? I18n::formatDate($person['birth_date']) : ($person['birth_year'] ? I18n::formatDate($person['birth_year']) : ($lang === 'ru' ? 'не указан' : 'белгісіз'));
$deathDisp = !empty($person['death_date']) ? I18n::formatDate($person['death_date']) : ($person['death_year'] ? I18n::formatDate($person['death_year']) : ($lang === 'ru' ? 'не указан' : 'белгісіз'));

// Маленький круглый аватар с инициалами (родственники)
$pcInitials = static function (array $p): string {
    $i = mb_substr((string)($p['first_name'] ?? ''), 0, 1) . mb_substr((string)($p['last_name'] ?? ''), 0, 1);
    return $i !== '' ? $i : 'Ш';
};
$pcYear = static function ($y): string {
    return !empty($y) ? ((string)$y . ' ж.т.') : '';
};

require __DIR__ . '/../layout/master_start.php';
?>

<div class="pc">

    <!-- Заголовок карточки: фото, имя, статус, действия -->
    <div class="ds-page-head pc-head">
        <div class="pc-head-main">
            <div class="avatar avatar-lg pc-avatar<?= $isMale ? ' is-m' : ' is-f' ?><?= $isDeceased ? ' deceased' : '' ?>">
                <?php if (!empty($person['avatar_path'])): ?>
                    <img id="personAvatarImg" src="<?= htmlspecialchars((string)$person['avatar_path']) ?>" alt="<?= htmlspecialchars($fullName) ?>">
                <?php else: ?>
                    <span id="personAvatarImg"><?= htmlspecialchars($initials ?: 'Ш') ?></span>
                <?php endif; ?>
            </div>
            <div class="pc-head-text">
                <h1><?= htmlspecialchars($fullName) ?></h1>
                <div class="sub pc-head-sub">
                    <?php if ($isDeceased): ?>
                        <span class="badge badge-accent"><?= $lang === 'ru' ? 'Ушедший(ая) предок' : 'Уақыттан озған баба' ?></span>
                    <?php else: ?>
                        <span class="badge badge-success"><span class="pc-dot" aria-hidden="true"></span><?= $lang === 'ru' ? 'Живущий(ая) родственник' : 'Тірі ұрпақ' ?></span>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($birthDisp) ?><?= $isDeceased ? ' — ' . htmlspecialchars($deathDisp) : '' ?></span>
                    <?php if (!empty($person['maiden_name'])): ?>
                        <span>· <?= $lang === 'ru' ? 'урожд.' : 'қыз кезіндегі тегі:' ?> <?= htmlspecialchars((string)$person['maiden_name']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="actions">
            <a href="/tree?focus=<?= (int)$person['id'] ?>" class="btn">
                <svg class="ic" aria-hidden="true"><use href="#i-tree"/></svg>
                <span><?= htmlspecialchars(I18n::t('btn_show_in_tree')) ?></span>
            </a>
            <?php if ($canEdit): ?>
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
                    <div class="pc-kin-term"><?= htmlspecialchars($termText) ?></div>
                <?php elseif ($kinship && !empty($kinship['term_kk'])): ?>
                    <div class="pc-kin-term"><?= htmlspecialchars(I18n::formatKinship($kinship['term_kk'], $kinship['term_ru'] ?? null)) ?></div>
                <?php else: ?>
                    <div class="muted"><?= $lang === 'ru' ? 'Родственная связь уточняется' : 'Туыстық байланысы анықталуда' ?></div>
                <?php endif; ?>

                <!-- Безопасная загрузка фото -->
                <?php if ($canEdit): ?>
                    <div class="pc-upload">
                        <form id="avatarUploadForm" enctype="multipart/form-data" onsubmit="handlePhotoUpload(event)">
                            <input type="hidden" name="person_id" value="<?= (int)$person['id'] ?>">
                            <input type="hidden" name="set_as_avatar" value="1">
                            <label for="photoFileInput" class="pc-drop">
                                <svg class="ic" aria-hidden="true"><use href="#i-camera"/></svg>
                                <span><?= $lang === 'ru' ? 'Загрузить / Сменить фото' : 'Фотосурет жүктеу' ?></span>
                            </label>
                            <input type="file" id="photoFileInput" name="photo" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="this.form.dispatchEvent(new Event('submit'))">
                        </form>
                        <div id="uploadStatusMsg" class="small" style="margin-top: 6px; display: none;"></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Краткая информация о роде -->
            <div class="island">
                <div class="island-head"><h3><?= $lang === 'ru' ? 'Родовая принадлежность (Ру)' : 'Рулық мәліметі' ?></h3></div>
                <dl class="props pc-props-tight">
                    <dt><?= $lang === 'ru' ? 'Род (Ру)' : 'Руы' ?></dt>
                    <dd><?= htmlspecialchars((string)($person['clan_ru'] ?: ($lang === 'ru' ? 'не указан' : 'белгісіз'))) ?></dd>
                    <?php if (!empty($person['clan_subru'])): ?>
                        <dt><?= $lang === 'ru' ? 'Подрод' : 'Тармағы' ?></dt>
                        <dd><?= htmlspecialchars((string)$person['clan_subru']) ?></dd>
                    <?php endif; ?>
                    <dt><?= $lang === 'ru' ? 'Пол' : 'Жынысы' ?></dt>
                    <dd><?= $isMale ? ($lang === 'ru' ? 'Мужской' : 'Ер') : ($lang === 'ru' ? 'Женский' : 'Әйел') ?></dd>
                </dl>
            </div>
        </div>

        <!-- Правая колонка: сведения, родители, супруги, дети, братья/сёстры, медиа, үш жұрт -->
        <div class="pc-col">

            <!-- Основные даты и места, биография -->
            <div class="island">
                <div class="island-head"><h3><?= htmlspecialchars(I18n::t('personal_info')) ?></h3></div>
                <dl class="props">
                    <dt><?= htmlspecialchars(I18n::t('field_birth_date')) ?></dt>
                    <dd><?= htmlspecialchars($birthDisp) ?></dd>
                    <dt><?= htmlspecialchars(I18n::t('field_birth_place')) ?></dt>
                    <dd><?= htmlspecialchars((string)($person['birth_place'] ?: ($lang === 'ru' ? 'не указано' : 'көрсетілмеген'))) ?></dd>
                    <?php if ($isDeceased): ?>
                        <dt><?= htmlspecialchars(I18n::t('field_death_date')) ?></dt>
                        <dd class="pc-accent"><?= htmlspecialchars($deathDisp) ?></dd>
                        <dt><?= htmlspecialchars(I18n::t('field_death_place')) ?></dt>
                        <dd><?= htmlspecialchars((string)($person['death_place'] ?: ($lang === 'ru' ? 'не указано' : 'көрсетілмеген'))) ?></dd>
                    <?php endif; ?>
                </dl>

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
                    <div class="island-head"><h3><?= htmlspecialchars(I18n::t('parents_title')) ?></h3></div>
                    <div class="list">
                        <?php foreach ([['p' => $father, 'label' => I18n::t('father_label'), 'none' => ($lang === 'ru' ? 'Не указан' : 'Көрсетілмеген')],
                                        ['p' => $mother, 'label' => I18n::t('mother_label'), 'none' => ($lang === 'ru' ? 'Не указана' : 'Көрсетілмеген')]] as $par): ?>
                            <div class="list-row">
                                <?php if ($par['p']):
                                    $pp = $par['p'];
                                    $ppName = trim(($pp['last_name'] ?? '') . ' ' . $pp['first_name'] . ' ' . ($pp['middle_name'] ?? ''));
                                ?>
                                    <a href="/person/<?= (int)$pp['id'] ?>" class="person pc-link">
                                        <span class="avatar<?= $pcG($pp, $pp === $father) ?><?= !empty($pp['is_deceased']) ? ' deceased' : '' ?>"><?= htmlspecialchars($pcInitials($pp)) ?></span>
                                        <span>
                                            <span class="meta"><?= htmlspecialchars($par['label']) ?></span>
                                            <span class="name"><?= htmlspecialchars($ppName) ?></span>
                                            <?php if (!empty($pp['birth_year'])): ?><span class="meta"><?= htmlspecialchars($pcYear($pp['birth_year'])) ?></span><?php endif; ?>
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
                    <div class="island-head"><h3><?= htmlspecialchars(I18n::t('spouses_title')) ?></h3><span class="more"><?= count($spouses) ?></span></div>
                    <?php if (empty($spouses)): ?>
                        <div class="muted"><?= $lang === 'ru' ? 'Сведения о браке отсутствуют' : 'Неке туралы мәлімет жоқ' ?></div>
                    <?php else: ?>
                        <div class="list">
                            <?php foreach ($spouses as $sp):
                                $isDiv = (($sp['status'] ?? '') === 'divorced');
                                $spName = trim(($sp['last_name'] ?? '') . ' ' . $sp['first_name'] . ' ' . ($sp['middle_name'] ?? ''));
                                $spDate = (string)(($sp['start_date'] ?? '') ?: ($sp['marriage_date'] ?? ''));
                            ?>
                                <div class="list-row<?= $isDiv ? ' pc-divorced' : '' ?>">
                                    <a href="/person/<?= (int)$sp['spouse_id'] ?>" class="person pc-link">
                                        <span class="avatar<?= $pcG($sp, !$isMale) ?><?= !empty($sp['is_deceased']) ? ' deceased' : '' ?>"><?= htmlspecialchars($pcInitials($sp)) ?></span>
                                        <span>
                                            <span class="name"><?= htmlspecialchars($spName) ?></span>
                                            <?php if (!empty($sp['maiden_name'])): ?>
                                                <span class="meta"><?= $lang === 'ru' ? 'урожд.' : 'қыз кезіндегі:' ?> <?= htmlspecialchars((string)$sp['maiden_name']) ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </a>
                                    <span class="right">
                                        <span class="badge"><?= $isDiv ? ($lang === 'ru' ? 'Развод' : 'Ажырасқан') : ($lang === 'ru' ? 'Брак' : 'Некеде') ?></span>
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
                <div class="island-head"><h3><?= htmlspecialchars(I18n::t('children_title')) ?></h3><span class="more"><?= count($children) ?></span></div>
                <?php if (empty($children)): ?>
                    <div class="muted"><?= $lang === 'ru' ? 'Дети не зарегистрированы' : 'Балалары тіркелмеген' ?></div>
                <?php else: ?>
                    <div class="pc-people">
                        <?php foreach ($children as $ch):
                            $chIsMale = ($ch['gender'] === 'M');
                            $chName = trim(($ch['last_name'] ?? '') . ' ' . $ch['first_name'] . ' ' . ($ch['middle_name'] ?? ''));
                        ?>
                            <a href="/person/<?= (int)$ch['id'] ?>" class="person pc-chip">
                                <span class="avatar<?= $chIsMale ? ' is-m' : ' is-f' ?><?= !empty($ch['is_deceased']) ? ' deceased' : '' ?>"><?= htmlspecialchars($pcInitials($ch)) ?></span>
                                <span>
                                    <span class="name" title="<?= htmlspecialchars($chName) ?>"><?= htmlspecialchars($chName) ?></span>
                                    <span class="meta"><?= $chIsMale ? ($lang === 'ru' ? 'Сын' : 'Ұлы') : ($lang === 'ru' ? 'Дочь' : 'Қызы') ?><?= !empty($ch['birth_year']) ? ' · ' . htmlspecialchars($pcYear($ch['birth_year'])) : '' ?></span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Бауырлары (Братья и сёстры) -->
            <?php if (!empty($siblings)): ?>
                <div class="island">
                    <div class="island-head"><h3><?= htmlspecialchars(I18n::t('brothers_sisters')) ?></h3><span class="more"><?= count($siblings) ?></span></div>
                    <div class="pc-people">
                        <?php foreach ($siblings as $sib):
                            $sibIsMale = ($sib['gender'] === 'M');
                            $sibName = trim(($sib['last_name'] ?? '') . ' ' . $sib['first_name'] . ' ' . ($sib['middle_name'] ?? ''));
                        ?>
                            <a href="/person/<?= (int)$sib['id'] ?>" class="person pc-chip">
                                <span class="avatar<?= $sibIsMale ? ' is-m' : ' is-f' ?><?= !empty($sib['is_deceased']) ? ' deceased' : '' ?>"><?= htmlspecialchars($pcInitials($sib)) ?></span>
                                <span>
                                    <span class="name" title="<?= htmlspecialchars($sibName) ?>"><?= htmlspecialchars($sibName) ?></span>
                                    <span class="meta"><?= $sibIsMale ? ($lang === 'ru' ? 'Брат' : 'Бауыры') : ($lang === 'ru' ? 'Сестра' : 'Қарындасы/Әпкесі') ?><?= !empty($sib['birth_year']) ? ' · ' . htmlspecialchars($pcYear($sib['birth_year'])) : '' ?></span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
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
                                <div class="pc-sub-title"><?= htmlspecialchars((string)($aud['title'] ?: ($lang === 'ru' ? 'Аудиозапись воспоминания' : 'Дыбыс жазбасы'))) ?></div>
                                <audio controls class="pc-audio">
                                    <source src="/media/stream?id=<?= (int)$aud['id'] ?>" type="audio/mpeg">
                                </audio>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach (($mediaList['stories'] ?? []) as $st): ?>
                            <div class="island-sub">
                                <div class="pc-sub-title"><?= htmlspecialchars((string)($st['title'] ?: ($lang === 'ru' ? 'Воспоминание' : 'Естелік'))) ?></div>
                                <div class="pc-bio-text"><?= htmlspecialchars((string)$st['content']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Үш жұрт (три родственных круга с семейными папками, TASK-007) -->
            <?php if (!empty($ushZhurt)):
                $oz = $ushZhurt['oz_zhurt'];
                $nagashy = $ushZhurt['nagashy_zhurt'];
                $kaiyn = $ushZhurt['kaiyn_zhurt'];
            ?>
                <div class="island">
                    <div class="island-head pc-zhurt-head">
                        <h3><?= htmlspecialchars(I18n::t('ush_zhurt_title')) ?></h3>
                        <div class="seg" role="group" aria-label="<?= htmlspecialchars(I18n::t('ush_zhurt_title')) ?>">
                            <?php foreach (['oz' => $oz, 'nagashy' => $nagashy, 'kaiyn' => $kaiyn] as $zKey => $zData): ?>
                                <button type="button" onclick="switchZhurtTab('<?= $zKey ?>')" id="tabBtn_<?= $zKey ?>" class="<?= $zKey === 'oz' ? 'active' : '' ?>">
                                    <?= htmlspecialchars((string)($lang === 'ru' ? $zData['title_ru'] : $zData['title_kk'])) ?> <span class="muted">(<?= (int)$zData['total_count'] ?>)</span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php
                    $renderZhurtPane = function (array $zhurtData, string $paneId, bool $isActive) use ($lang) {
                        ?>
                        <div id="zhurtPane_<?= $paneId ?>" style="display: <?= $isActive ? 'block' : 'none' ?>;">
                            <?php if (empty($zhurtData['families']) && empty($zhurtData['standalone'])): ?>
                                <div class="pp-empty muted">
                                    <?= $lang === 'ru' ? 'Сведения о родне в этом круге пока не внесены' : 'Бұл жұрт бойынша мәліметтер әзірге енгізілмеген' ?>
                                </div>
                            <?php else: ?>
                                <div class="pc-stack">
                                    <?php foreach ($zhurtData['families'] as $fam):
                                        $h = $fam['husband'];
                                        $w = $fam['wife'];
                                        $hName = $h ? trim(($h['last_name'] ?? '') . ' ' . $h['first_name']) : 'Белгісіз';
                                        $wName = $w ? trim(($w['last_name'] ?? '') . ' ' . $w['first_name']) : 'Белгісіз';
                                        $childCount = count($fam['children']);
                                    ?>
                                        <details open class="pc-family">
                                            <summary>
                                                <span class="pc-family-names">
                                                    <?php if ($h): ?>
                                                        <a href="/person/<?= (int)$h['id'] ?>"><?= htmlspecialchars($hName) ?></a>
                                                        <?php if (!empty($fam['husband_kinship']['term_kk'])): ?>
                                                            <span class="badge"><?= htmlspecialchars(I18n::formatKinship($fam['husband_kinship']['term_kk'], $fam['husband_kinship']['term_ru'] ?? null)) ?></span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="muted">Белгісіз</span>
                                                    <?php endif; ?>
                                                    <span class="pc-plus">+</span>
                                                    <?php if ($w): ?>
                                                        <a href="/person/<?= (int)$w['id'] ?>"><?= htmlspecialchars($wName) ?></a>
                                                        <?php if (!empty($fam['wife_kinship']['term_kk'])): ?>
                                                            <span class="badge"><?= htmlspecialchars(I18n::formatKinship($fam['wife_kinship']['term_kk'], $fam['wife_kinship']['term_ru'] ?? null)) ?></span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="muted">Белгісіз</span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="badge"><?= $childCount ?> <?= $lang === 'ru' ? 'детей' : 'бала' ?></span>
                                            </summary>
                                            <div class="pc-family-body">
                                                <?php if ($childCount === 0): ?>
                                                    <div class="muted small"><?= $lang === 'ru' ? 'Дети не зарегистрированы' : 'Балалары тіркелмеген' ?></div>
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
                                                                        <span class="meta"><?= ($ch['gender'] === 'M') ? ($lang === 'ru' ? 'Сын' : 'Ұлы') : ($lang === 'ru' ? 'Дочь' : 'Қызы') ?><?= !empty($ch['birth_year']) ? ' · ' . htmlspecialchars((string)$ch['birth_year']) : '' ?></span>
                                                                    </span>
                                                                </a>
                                                                <?php if (!empty($chKin['term_kk'])): ?>
                                                                    <span class="right"><span class="badge"><?= htmlspecialchars(I18n::formatKinship($chKin['term_kk'], $chKin['term_ru'] ?? null)) ?></span></span>
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
                                            <div class="pc-label"><?= $lang === 'ru' ? 'Другие родственники этого круга' : 'Осы жұрттағы басқа туыстар' ?></div>
                                            <div class="pc-tags">
                                                <?php foreach ($zhurtData['standalone'] as $stItem):
                                                    $stP = $stItem['person'];
                                                    $stKin = $stItem['kinship'];
                                                ?>
                                                    <a href="/person/<?= (int)$stP['id'] ?>" class="badge pc-tag">
                                                        <b><?= htmlspecialchars(trim(($stP['last_name'] ?? '') . ' ' . $stP['first_name'])) ?></b>
                                                        <?php if (!empty($stKin['term_kk'])): ?>
                                                            <span>· <?= htmlspecialchars(I18n::formatKinship($stKin['term_kk'], $stKin['term_ru'] ?? null)) ?></span>
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

                    $renderZhurtPane($oz, 'oz', true);
                    $renderZhurtPane($nagashy, 'nagashy', false);
                    $renderZhurtPane($kaiyn, 'kaiyn', false);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Служебное (только для администратора, свёрнуто) -->
            <?php if ($isAdmin): ?>
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
            ? '<?= $lang === 'ru' ? 'Добавить сына' : 'Ұл қосу' ?>'
            : '<?= $lang === 'ru' ? 'Добавить дочь' : 'Қыз қосу' ?>';
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
        msg.innerText = '<?= $lang === 'ru' ? 'Проверка и пересохранение фото...' : 'Сурет тексеріліп, сақталуда...' ?>';

        const formData = new FormData(form);

        try {
            const res = await fetch('/api/photo/upload', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data && data.success) {
                msg.style.color = 'var(--c-success)';
                msg.innerText = '<?= $lang === 'ru' ? 'Фото успешно обновлено!' : 'Фото сәтті жаңартылды!' ?>';
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
        const tabs = ['oz', 'nagashy', 'kaiyn'];
        tabs.forEach(t => {
            const pane = document.getElementById('zhurtPane_' + t);
            const btn = document.getElementById('tabBtn_' + t);
            if (pane) pane.style.display = (t === tab) ? 'block' : 'none';
            if (btn) btn.classList.toggle('active', t === tab);
        });
    }
</script>

<?php
require __DIR__ . '/../layout/master_end.php';
?>
