<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
$fullName = trim(($person['last_name'] ?? '') . ' ' . ($person['first_name'] ?? '') . ' ' . ($person['middle_name'] ?? ''));
$title = $fullName;
$activeNav = 'persons';

$isDeceased = !empty($person['is_deceased']);
$isMale = ($person['gender'] === 'M');
$initials = mb_substr((string)($person['first_name'] ?? ''), 0, 1) . mb_substr((string)($person['last_name'] ?? ''), 0, 1);

// Полные даты через единый форматтер
$birthDisp = !empty($person['birth_date']) ? I18n::formatDate($person['birth_date']) : ($person['birth_year'] ? I18n::formatDate($person['birth_year']) : ($lang === 'ru' ? 'не указан' : 'белгісіз'));
$deathDisp = !empty($person['death_date']) ? I18n::formatDate($person['death_date']) : ($person['death_year'] ? I18n::formatDate($person['death_year']) : ($lang === 'ru' ? 'не указан' : 'белгісіз'));

require __DIR__ . '/../layout/master_start.php';
?>

<!-- Верхняя навигационная панель страницы персоны -->
<div style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
    <div style="display: flex; align-items: center; gap: 12px;">
        <a href="/persons" style="padding: 7px 14px; background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 8px; color: #475569; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
            <span>←</span>
            <span><?= $lang === 'ru' ? 'Назад к списку людей' : 'Адамдар тізіміне оралу' ?></span>
        </a>
        <span style="color: #cbd5e1;">/</span>
        <span style="font-size: 13.5px; color: #0f172a; font-weight: 700;">
            <?= htmlspecialchars($fullName) ?>
        </span>
    </div>

    <!-- Кнопки быстрых действий -->
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <!-- Древода көрсету (открыть древо с фокусом) -->
        <a href="/tree?focus=<?= (int)$person['id'] ?>" style="display: flex; align-items: center; gap: 8px; background: #eff6ff; color: #1d4ed8; border: 1.5px solid #bfdbfe; padding: 8px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 700; text-decoration: none; box-shadow: 0 1px 3px rgba(37,99,235,0.1);">
            <span>🎯</span>
            <span><?= htmlspecialchars(I18n::t('btn_show_in_tree')) ?></span>
        </a>

        <?php if ($canEdit): ?>
            <!-- + Ұл / Қыз -->
            <button type="button" onclick="openAddChildModal('M')" style="display: flex; align-items: center; gap: 6px; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
                <span>+ 👦</span>
                <span><?= htmlspecialchars(I18n::t('btn_add_son')) ?></span>
            </button>
            <button type="button" onclick="openAddChildModal('F')" style="display: flex; align-items: center; gap: 6px; background: #fce7f3; color: #be185d; border: 1px solid #fbcfe8; padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
                <span>+ 👧</span>
                <span><?= htmlspecialchars(I18n::t('btn_add_daughter')) ?></span>
            </button>

            <!-- Өзгерту -->
            <button type="button" onclick="openGlobalPersonEditModal(<?= (int)$person['id'] ?>)" style="display: flex; align-items: center; gap: 6px; background: #1b3b6f; color: #ffffff; border: none; padding: 8px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 600; cursor: pointer; box-shadow: 0 2px 6px rgba(27,59,111,0.2);">
                <span>✏️</span>
                <span><?= htmlspecialchars(I18n::t('btn_edit')) ?></span>
            </button>
        <?php endif; ?>
    </div>
</div>

<div style="display: grid; grid-template-columns: 320px 1fr; gap: 24px; align-items: start;">
    
    <!-- Левая колонка: Фото, Аватар, Үш жұрт, Статус -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
        <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 24px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <!-- Аватар / Фото -->
            <div style="position: relative; width: 140px; height: 140px; margin: 0 auto 16px; border-radius: 50%; overflow: hidden; background: <?= $isDeceased ? '#334155' : ($isMale ? '#1e3a8a' : '#831843') ?>; border: 4px solid <?= $isDeceased ? '#d4af37' : '#ffffff' ?>; box-shadow: 0 4px 14px rgba(0,0,0,0.15);">
                <?php if (!empty($person['avatar_path'])): ?>
                    <img id="personAvatarImg" src="<?= htmlspecialchars((string)$person['avatar_path']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <div id="personAvatarImg" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 44px; font-weight: 800; color: #ffffff;">
                        <?= htmlspecialchars($initials ?: 'Ш') ?>
                    </div>
                <?php endif; ?>
            </div>

            <h2 style="font-size: 20px; font-weight: 800; color: #0f172a; margin-bottom: 4px; line-height: 1.2;">
                <?= htmlspecialchars($fullName) ?>
            </h2>
            <?php if (!empty($person['maiden_name'])): ?>
                <div style="font-size: 13px; color: #db2777; font-weight: 600; margin-bottom: 8px;">
                    (<?= $lang === 'ru' ? 'урожд.' : 'қыз кезіндегі тегі:' ?> <?= htmlspecialchars($person['maiden_name']) ?>)
                </div>
            <?php endif; ?>

            <!-- Бейдж статуса: Тірі немесе Уақыттан озған -->
            <div style="margin-bottom: 14px;">
                <?php if ($isDeceased): ?>
                    <span style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; background: #fef3c7; color: #92400e; border: 1px solid #fde68a;">
                        <span>✨</span>
                        <span><?= $lang === 'ru' ? 'Ушедший(ая) предок' : 'Уақыттан озған баба' ?></span>
                    </span>
                <?php else: ?>
                    <span style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
                        <span><?= $lang === 'ru' ? 'Живущий(ая) родственник' : 'Тірі ұрпақ' ?></span>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Родство к вошедшему пользователю -->
            <div style="background: #f8fafc; border-radius: 10px; padding: 14px; border: 1px solid #e2e8f0; margin-bottom: 16px; text-align: left;">
                <div style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 6px;">
                    <?= htmlspecialchars(I18n::t('kinship_to_you')) ?>
                </div>
                <?php if ($kinship && !empty($kinship['zhurt'])): 
                    $zClass = match (true) {
                        str_contains($kinship['zhurt'], 'Нағашы') => 'background:#dbeafe; color:#1e40af; border:1px solid #bfdbfe;',
                        str_contains($kinship['zhurt'], 'Қайын')  => 'background:#fce7f3; color:#9d174d; border:1px solid #fbcfe8;',
                        str_contains($kinship['zhurt'], 'Төркін') => 'background:#ede9fe; color:#6d28d9; border:1px solid #ddd6fe;',
                        str_contains($kinship['zhurt'], 'Күйеу')  => 'background:#e0e7ff; color:#3730a3; border:1px solid #c7d2fe;',
                        str_contains($kinship['zhurt'], 'Құда')   => 'background:#fef9c3; color:#854d0e; border:1px solid #fef08a;',
                        default => 'background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0;',
                    };
                    $termText = I18n::formatKinship($kinship['term_kk'] ?? '', $kinship['term_ru'] ?? null);
                ?>
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <span style="display: inline-block; padding: 3px 9px; border-radius: 12px; font-size: 11.5px; font-weight: 700; <?= $zClass ?>">
                            <?= htmlspecialchars($kinship['zhurt']) ?>
                        </span>
                    </div>
                    <div style="font-size: 14px; font-weight: 700; color: #1e3a8a;">
                        <?= htmlspecialchars($termText) ?>
                    </div>
                <?php elseif ($kinship && !empty($kinship['term_kk'])): ?>
                    <div style="font-size: 14px; font-weight: 700; color: #1e3a8a;">
                        <?= htmlspecialchars(I18n::formatKinship($kinship['term_kk'], $kinship['term_ru'] ?? null)) ?>
                    </div>
                <?php else: ?>
                    <div style="font-size: 13px; color: #64748b;">
                        <?= $lang === 'ru' ? 'Родственная связь уточняется' : 'Туыстық байланысы анықталуда' ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Безопасная загрузка фото -->
            <?php if ($canEdit): ?>
                <div style="border-top: 1px solid #f1f5f9; padding-top: 16px;">
                    <form id="avatarUploadForm" enctype="multipart/form-data" onsubmit="handlePhotoUpload(event)">
                        <input type="hidden" name="person_id" value="<?= (int)$person['id'] ?>">
                        <input type="hidden" name="set_as_avatar" value="1">
                        <label for="photoFileInput" style="display: block; padding: 8px 14px; background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 8px; font-size: 12px; font-weight: 600; color: #475569; cursor: pointer; transition: all 0.2s ease;">
                            📷 <?= $lang === 'ru' ? 'Загрузить / Сменить фото' : 'Фотосурет жүктеу' ?>
                        </label>
                        <input type="file" id="photoFileInput" name="photo" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="this.form.dispatchEvent(new Event('submit'))">
                    </form>
                    <div id="uploadStatusMsg" style="font-size: 11.5px; margin-top: 6px; display: none;"></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Краткая информация о роде -->
        <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <h3 style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                <span>🛡️</span>
                <span><?= $lang === 'ru' ? 'Родовая принадлежность (Ру)' : 'Рулық мәліметі' ?></span>
            </h3>
            <div style="display: flex; flex-direction: column; gap: 8px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px;">
                    <span style="color: #64748b;"><?= $lang === 'ru' ? 'Род (Ру):' : 'Руы:' ?></span>
                    <strong style="color: #0f172a;"><?= htmlspecialchars($person['clan_ru'] ?: ($lang === 'ru' ? 'не указан' : 'белгісіз')) ?></strong>
                </div>
                <?php if (!empty($person['clan_subru'])): ?>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px;">
                        <span style="color: #64748b;"><?= $lang === 'ru' ? 'Подрод:' : 'Тармағы:' ?></span>
                        <strong style="color: #0f172a;"><?= htmlspecialchars($person['clan_subru']) ?></strong>
                    </div>
                <?php endif; ?>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;"><?= $lang === 'ru' ? 'Пол:' : 'Жынысы:' ?></span>
                    <strong style="color: #0f172a;"><?= $isMale ? ($lang === 'ru' ? 'Мужской' : 'Ер') : ($lang === 'ru' ? 'Женский' : 'Әйел') ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Правая колонка: Все поля, Родители, Супруги, Дети, Братья/Сестры, Биография -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
        
        <!-- Блок 1: Основные биографические даты и места -->
        <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
                📋 <?= htmlspecialchars(I18n::t('personal_info')) ?>
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; font-size: 13.5px;">
                <div>
                    <div style="color: #64748b; font-size: 11.5px; margin-bottom: 2px;"><?= htmlspecialchars(I18n::t('field_birth_date')) ?></div>
                    <div style="font-weight: 700; color: #0f172a;">
                        <?= htmlspecialchars($birthDisp) ?>
                    </div>
                </div>

                <div>
                    <div style="color: #64748b; font-size: 11.5px; margin-bottom: 2px;"><?= htmlspecialchars(I18n::t('field_birth_place')) ?></div>
                    <div style="font-weight: 600; color: #334155;">
                        <?= htmlspecialchars($person['birth_place'] ?: ($lang === 'ru' ? 'не указано' : 'көрсетілмеген')) ?>
                    </div>
                </div>

                <?php if ($isDeceased): ?>
                    <div>
                        <div style="color: #64748b; font-size: 11.5px; margin-bottom: 2px;"><?= htmlspecialchars(I18n::t('field_death_date')) ?></div>
                        <div style="font-weight: 700; color: #92400e;">
                            <?= htmlspecialchars($deathDisp) ?> ✨
                        </div>
                    </div>

                    <div>
                        <div style="color: #64748b; font-size: 11.5px; margin-bottom: 2px;"><?= htmlspecialchars(I18n::t('field_death_place')) ?></div>
                        <div style="font-weight: 600; color: #334155;">
                            <?= htmlspecialchars($person['death_place'] ?: ($lang === 'ru' ? 'не указано' : 'көрсетілмеген')) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($person['biography'])): 
                $bioLines = explode("\n", trim((string)$person['biography']));
                $isLong = mb_strlen((string)$person['biography']) > 160 || count($bioLines) > 3;
            ?>
                <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid #f1f5f9;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <div style="color: #0f172a; font-size: 13.5px; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                            <span>📜</span>
                            <span><?= htmlspecialchars(I18n::t('biography_title')) ?></span>
                        </div>
                        <?php if ($isLong): ?>
                            <button type="button" onclick="toggleBiographyCollapse()" style="background: none; border: none; color: #2563eb; font-size: 12.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                <span id="toggleBioLabel"><?= htmlspecialchars(I18n::t('btn_read_more')) ?></span>
                                <span id="toggleBioArrow">▼</span>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div id="biographyContainer" style="<?= $isLong ? 'max-height: 4.8em; overflow: hidden; position: relative;' : '' ?> font-size: 13.5px; line-height: 1.6; color: #334155; white-space: pre-line; transition: max-height 0.3s ease;">
                        <?= htmlspecialchars(trim((string)$person['biography'])) ?>
                        <?php if ($isLong): ?>
                            <div id="biographyFadeOverlay" style="position: absolute; bottom: 0; left: 0; right: 0; height: 28px; background: linear-gradient(transparent, #ffffff); pointer-events: none;"></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Блок 2: Ата-анасы мен ерлі-зайыптылары -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Родители -->
            <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between;">
                    <span>👨‍👩‍👦 <?= htmlspecialchars(I18n::t('parents_title')) ?></span>
                </h3>

                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <!-- Отец -->
                    <div style="padding: 10px 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 11px; color: #64748b; font-weight: 600; margin-bottom: 2px;"><?= htmlspecialchars(I18n::t('father_label')) ?></div>
                        <?php if ($father): ?>
                            <a href="/person/<?= (int)$father['id'] ?>" style="font-size: 13.5px; font-weight: 700; color: #1e3a8a; text-decoration: none;">
                                <?= htmlspecialchars(trim(($father['last_name'] ?? '') . ' ' . $father['first_name'] . ' ' . ($father['middle_name'] ?? ''))) ?>
                            </a>
                            <div style="font-size: 11px; color: #94a3b8;">
                                <?= $father['birth_year'] ? $father['birth_year'] . ' ж.т.' : '' ?>
                            </div>
                        <?php else: ?>
                            <span style="font-size: 13px; color: #94a3b8;"><?= $lang === 'ru' ? 'Не указан' : 'Көрсетілмеген' ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Мать -->
                    <div style="padding: 10px 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 11px; color: #64748b; font-weight: 600; margin-bottom: 2px;"><?= htmlspecialchars(I18n::t('mother_label')) ?></div>
                        <?php if ($mother): ?>
                            <a href="/person/<?= (int)$mother['id'] ?>" style="font-size: 13.5px; font-weight: 700; color: #831843; text-decoration: none;">
                                <?= htmlspecialchars(trim(($mother['last_name'] ?? '') . ' ' . $mother['first_name'] . ' ' . ($mother['middle_name'] ?? ''))) ?>
                            </a>
                            <div style="font-size: 11px; color: #94a3b8;">
                                <?= $mother['birth_year'] ? $mother['birth_year'] . ' ж.т.' : '' ?>
                            </div>
                        <?php else: ?>
                            <span style="font-size: 13px; color: #94a3b8;"><?= $lang === 'ru' ? 'Не указана' : 'Көрсетілмеген' ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Супруги -->
            <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 14px;">
                    💍 <?= htmlspecialchars(I18n::t('spouses_title')) ?> (<?= count($spouses) ?>)
                </h3>

                <?php if (empty($spouses)): ?>
                    <div style="font-size: 13px; color: #94a3b8; padding: 12px 0;">
                        <?= $lang === 'ru' ? 'Сведения о браке отсутствуют' : 'Неке туралы мәлімет жоқ' ?>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($spouses as $sp): 
                            $isDiv = ($sp['status'] === 'divorced');
                            $spName = trim(($sp['last_name'] ?? '') . ' ' . $sp['first_name'] . ' ' . ($sp['middle_name'] ?? ''));
                        ?>
                            <div style="padding: 10px 12px; background: #fdf2f8; border-radius: 8px; border: 1px solid #fbcfe8;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                                    <span style="font-size: 11px; font-weight: 700; color: <?= $isDiv ? '#64748b' : '#be185d' ?>;">
                                        <?= $isDiv ? '💔 ' . ($lang === 'ru' ? 'Развод' : 'Ажырасқан') : '💍 ' . ($lang === 'ru' ? 'Брак' : 'Некеде') ?>
                                    </span>
                                    <?php if (!empty($sp['marriage_date']) || !empty($sp['start_date'])): ?>
                                        <span style="font-size: 10.5px; color: #94a3b8;">
                                            <?= htmlspecialchars((string)($sp['start_date'] ?: $sp['marriage_date'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <a href="/person/<?= (int)$sp['spouse_id'] ?>" style="font-size: 13.5px; font-weight: 700; color: #831843; text-decoration: none;">
                                    <?= htmlspecialchars($spName) ?>
                                </a>
                                <?php if (!empty($sp['maiden_name'])): ?>
                                    <div style="font-size: 11px; color: #be185d;">
                                        (<?= $lang === 'ru' ? 'урожд.' : 'қыз кезіндегі:' ?> <?= htmlspecialchars($sp['maiden_name']) ?>)
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Блок 3: Балалары (Дети) -->
        <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 22px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="font-size: 16px; font-weight: 700; color: #0f172a;">
                    👶 <?= htmlspecialchars(I18n::t('children_title')) ?> (<?= count($children) ?>)
                </h3>
            </div>

            <?php if (empty($children)): ?>
                <div style="font-size: 13px; color: #94a3b8; padding: 12px 0;">
                    <?= $lang === 'ru' ? 'Дети не зарегистрированы' : 'Балалары тіркелмеген' ?>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px;">
                    <?php foreach ($children as $ch): 
                        $chIsMale = ($ch['gender'] === 'M');
                        $chName = trim(($ch['last_name'] ?? '') . ' ' . $ch['first_name'] . ' ' . ($ch['middle_name'] ?? ''));
                        $chInitials = mb_substr((string)($ch['first_name'] ?? ''), 0, 1) . mb_substr((string)($ch['last_name'] ?? ''), 0, 1);
                    ?>
                        <a href="/person/<?= (int)$ch['id'] ?>" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.15s ease;" onmouseover="this.style.background='#eff6ff'; this.style.borderColor='#bfdbfe';" onmouseout="this.style.background='#f8fafc'; this.style.borderColor='#e2e8f0';">
                            <div style="width: 34px; height: 34px; border-radius: 50%; background: <?= $chIsMale ? '#1e3a8a' : '#831843' ?>; color: #fff; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <?= htmlspecialchars($chInitials ?: 'Ш') ?>
                            </div>
                            <div style="min-width: 0;">
                                <div style="font-size: 13px; font-weight: 700; color: #1e3a8a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($chName) ?>
                                </div>
                                <div style="font-size: 11px; color: #64748b;">
                                    <?= $chIsMale ? ($lang === 'ru' ? 'Сын' : 'Ұлы') : ($lang === 'ru' ? 'Дочь' : 'Қызы') ?>
                                    <?= $ch['birth_year'] ? ' • ' . $ch['birth_year'] . ' ж.т.' : '' ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Блок 4: Бауырлары (Братья и сестры) -->
        <?php if (!empty($siblings)): ?>
            <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 22px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 14px;">
                    🤝 <?= htmlspecialchars(I18n::t('brothers_sisters')) ?> (<?= count($siblings) ?>)
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px;">
                    <?php foreach ($siblings as $sib): 
                        $sibIsMale = ($sib['gender'] === 'M');
                        $sibName = trim(($sib['last_name'] ?? '') . ' ' . $sib['first_name'] . ' ' . ($sib['middle_name'] ?? ''));
                        $sibInitials = mb_substr((string)($sib['first_name'] ?? ''), 0, 1) . mb_substr((string)($sib['last_name'] ?? ''), 0, 1);
                    ?>
                        <a href="/person/<?= (int)$sib['id'] ?>" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.15s ease;" onmouseover="this.style.background='#eff6ff'; this.style.borderColor='#bfdbfe';" onmouseout="this.style.background='#f8fafc'; this.style.borderColor='#e2e8f0';">
                            <div style="width: 34px; height: 34px; border-radius: 50%; background: <?= $sibIsMale ? '#1e3a8a' : '#831843' ?>; color: #fff; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <?= htmlspecialchars($sibInitials ?: 'Ш') ?>
                            </div>
                            <div style="min-width: 0;">
                                <div style="font-size: 13px; font-weight: 700; color: #1e3a8a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($sibName) ?>
                                </div>
                                <div style="font-size: 11px; color: #64748b;">
                                    <?= $sibIsMale ? ($lang === 'ru' ? 'Брат' : 'Бауыры') : ($lang === 'ru' ? 'Сестра' : 'Қарындасы/Әпкесі') ?>
                                    <?= $sib['birth_year'] ? ' • ' . $sib['birth_year'] . ' ж.т.' : '' ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Блок 5: Дыбыс жазбалары мен естеліктер (TASK-004 Media) -->
        <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 22px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 14px;">
                🎙️ <?= htmlspecialchars(I18n::t('audio_and_photos')) ?>
            </h3>

            <?php if (empty($mediaList['audio']) && empty($mediaList['stories']) && empty($mediaList['photos'])): ?>
                <div style="font-size: 13px; color: #94a3b8; padding: 12px 0;">
                    <?= htmlspecialchars(I18n::t('no_media_yet')) ?>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php if (!empty($mediaList['audio'])): ?>
                        <?php foreach ($mediaList['audio'] as $aud): ?>
                            <div style="padding: 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                                <div style="font-size: 13px; font-weight: 700; color: #1e3a8a; margin-bottom: 6px;">
                                    🎵 <?= htmlspecialchars($aud['title'] ?: ($lang === 'ru' ? 'Аудиозапись воспоминания' : 'Дыбыс жазбасы')) ?>
                                </div>
                                <audio controls style="width: 100%; height: 36px;">
                                    <source src="/media/stream?id=<?= (int)$aud['id'] ?>" type="audio/mpeg">
                                </audio>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($mediaList['stories'])): ?>
                        <?php foreach ($mediaList['stories'] as $st): ?>
                            <div style="padding: 14px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                                <div style="font-size: 13.5px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">
                                    📜 <?= htmlspecialchars($st['title'] ?: ($lang === 'ru' ? 'Воспоминание' : 'Естелік')) ?>
                                </div>
                                <div style="font-size: 13px; color: #334155; line-height: 1.5; white-space: pre-line;">
                                    <?= htmlspecialchars($st['content']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Блок 6: Үш жұрт (Три родственных круга с семейными папками, TASK-007) -->
        <?php if (!empty($ushZhurt)): 
            $oz = $ushZhurt['oz_zhurt'];
            $nagashy = $ushZhurt['nagashy_zhurt'];
            $kaiyn = $ushZhurt['kaiyn_zhurt'];
        ?>
            <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                    <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                        <span>🌳</span>
                        <span><?= htmlspecialchars(I18n::t('ush_zhurt_title')) ?></span>
                    </h3>

                    <!-- Переключатель табов 3 жұрт -->
                    <div style="display: flex; gap: 6px; background: #f1f5f9; padding: 4px; border-radius: 10px; flex-wrap: wrap;">
                        <button type="button" onclick="switchZhurtTab('oz')" id="tabBtn_oz" style="padding: 6px 12px; border-radius: 8px; border: none; font-size: 12.5px; font-weight: 700; cursor: pointer; background: #1b3b6f; color: #ffffff;">
                            <?= $oz['icon'] ?> <?= $lang === 'ru' ? $oz['title_ru'] : $oz['title_kk'] ?> (<?= $oz['total_count'] ?>)
                        </button>
                        <button type="button" onclick="switchZhurtTab('nagashy')" id="tabBtn_nagashy" style="padding: 6px 12px; border-radius: 8px; border: none; font-size: 12.5px; font-weight: 600; cursor: pointer; background: transparent; color: #475569;">
                            <?= $nagashy['icon'] ?> <?= $lang === 'ru' ? $nagashy['title_ru'] : $nagashy['title_kk'] ?> (<?= $nagashy['total_count'] ?>)
                        </button>
                        <button type="button" onclick="switchZhurtTab('kaiyn')" id="tabBtn_kaiyn" style="padding: 6px 12px; border-radius: 8px; border: none; font-size: 12.5px; font-weight: 600; cursor: pointer; background: transparent; color: #475569;">
                            <?= $kaiyn['icon'] ?> <?= $lang === 'ru' ? $kaiyn['title_ru'] : $kaiyn['title_kk'] ?> (<?= $kaiyn['total_count'] ?>)
                        </button>
                    </div>
                </div>

                <?php
                $renderZhurtPane = function(array $zhurtData, string $paneId, bool $isActive) use ($lang) {
                    ?>
                    <div id="zhurtPane_<?= $paneId ?>" style="display: <?= $isActive ? 'block' : 'none' ?>;">
                        <?php if (empty($zhurtData['families']) && empty($zhurtData['standalone'])): ?>
                            <div style="padding: 24px; text-align: center; color: #94a3b8; font-size: 13px;">
                                <?= $lang === 'ru' ? 'Сведения о родне в этом круге пока не внесены' : 'Бұл жұрт бойынша мәліметтер әзірге енгізілмеген' ?>
                            </div>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <?php foreach ($zhurtData['families'] as $fam): 
                                    $h = $fam['husband'];
                                    $w = $fam['wife'];
                                    $hName = $h ? trim(($h['last_name'] ?? '') . ' ' . $h['first_name']) : 'Белгісіз';
                                    $wName = $w ? trim(($w['last_name'] ?? '') . ' ' . $w['first_name']) : 'Белгісіз';
                                    $childCount = count($fam['children']);
                                ?>
                                    <details open style="border: 1.5px solid #e2e8f0; border-radius: 10px; background: #ffffff; overflow: hidden;">
                                        <summary style="background: #f8fafc; padding: 12px 16px; cursor: pointer; display: flex; align-items: center; justify-content: space-between; font-size: 13.5px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #f1f5f9; outline: none;">
                                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                                <span>📁</span>
                                                <span>
                                                    <?php if ($h): ?>
                                                        <a href="/person/<?= (int)$h['id'] ?>" style="color: #1e3a8a; text-decoration: none;"><?= htmlspecialchars($hName) ?></a>
                                                        <?php if (!empty($fam['husband_kinship']['term_kk'])): ?>
                                                            <span style="font-size: 11px; font-weight: 600; color: #2563eb; background: #eff6ff; padding: 2px 7px; border-radius: 10px; margin-left: 4px;">
                                                                <?= htmlspecialchars(I18n::formatKinship($fam['husband_kinship']['term_kk'], $fam['husband_kinship']['term_ru'] ?? null)) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span>Белгісіз</span>
                                                    <?php endif; ?>
                                                    <span style="color: #cbd5e1; margin: 0 4px;">+</span>
                                                    <?php if ($w): ?>
                                                        <a href="/person/<?= (int)$w['id'] ?>" style="color: #831843; text-decoration: none;"><?= htmlspecialchars($wName) ?></a>
                                                        <?php if (!empty($fam['wife_kinship']['term_kk'])): ?>
                                                            <span style="font-size: 11px; font-weight: 600; color: #db2777; background: #fdf2f8; padding: 2px 7px; border-radius: 10px; margin-left: 4px;">
                                                                <?= htmlspecialchars(I18n::formatKinship($fam['wife_kinship']['term_kk'], $fam['wife_kinship']['term_ru'] ?? null)) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span>Белгісіз</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <span style="background: #e2e8f0; color: #475569; font-size: 11.5px; padding: 3px 9px; border-radius: 12px; font-weight: 600;">
                                                <?= $childCount ?> <?= $lang === 'ru' ? 'детей' : 'бала' ?>
                                            </span>
                                        </summary>
                                        <div style="padding: 12px 16px 14px 28px; background: #ffffff;">
                                            <?php if ($childCount === 0): ?>
                                                <div style="font-size: 12px; color: #94a3b8; font-style: italic;">
                                                    <?= $lang === 'ru' ? 'Дети не зарегистрированы' : 'Балалары тіркелмеген' ?>
                                                </div>
                                            <?php else: ?>
                                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                                    <?php foreach ($fam['children'] as $chItem): 
                                                        $ch = $chItem['person'];
                                                        $chKin = $chItem['kinship'];
                                                        $chIsMale = ($ch['gender'] === 'M');
                                                        $chName = trim(($ch['last_name'] ?? '') . ' ' . $ch['first_name']);
                                                    ?>
                                                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 7px 10px; background: #f8fafc; border-radius: 6px; font-size: 13px; border-left: 3px solid <?= $chIsMale ? '#2563eb' : '#db2777' ?>;">
                                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                                <span><?= $chIsMale ? '👦' : '👧' ?></span>
                                                                <a href="/person/<?= (int)$ch['id'] ?>" style="color: #0f172a; text-decoration: none; font-weight: 600;">
                                                                    <?= htmlspecialchars($chName) ?>
                                                                </a>
                                                                <?php if (!empty($ch['birth_year'])): ?>
                                                                    <span style="font-size: 11.5px; color: #64748b;">(<?= $ch['birth_year'] ?>)</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if (!empty($chKin['term_kk'])): ?>
                                                                <span style="font-size: 11.5px; font-weight: 700; color: #1e3a8a; background: #e0f2fe; padding: 2px 8px; border-radius: 10px;">
                                                                    <?= htmlspecialchars(I18n::formatKinship($chKin['term_kk'], $chKin['term_ru'] ?? null)) ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                <?php endforeach; ?>

                                <?php if (!empty($zhurtData['standalone'])): ?>
                                    <div style="margin-top: 12px; padding: 12px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">
                                        <div style="font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 8px;">
                                            <?= $lang === 'ru' ? 'Другие родственники этого круга:' : 'Осы жұрттағы басқа туыстар:' ?>
                                        </div>
                                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                            <?php foreach ($zhurtData['standalone'] as $stItem): 
                                                $stP = $stItem['person'];
                                                $stKin = $stItem['kinship'];
                                            ?>
                                                <a href="/person/<?= (int)$stP['id'] ?>" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none; font-size: 12px; color: #1e3a8a;">
                                                    <strong><?= htmlspecialchars(trim(($stP['last_name'] ?? '') . ' ' . $stP['first_name'])) ?></strong>
                                                    <?php if (!empty($stKin['term_kk'])): ?>
                                                        <span style="color: #64748b; font-size: 11px;">(<?= htmlspecialchars(I18n::formatKinship($stKin['term_kk'], $stKin['term_ru'] ?? null)) ?>)</span>
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

        <!-- Блок 7: Служебное / Технические данные (Только для администратора, в свёрнутом виде) -->
        <?php if ($isAdmin): ?>
            <div style="margin-top: 10px;">
                <details style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 16px;">
                    <summary style="font-size: 12px; font-weight: 700; color: #64748b; cursor: pointer;">
                        ⚙️ <?= htmlspecialchars(I18n::t('technical_details')) ?> (ID, GEDCOM, Audit)
                    </summary>
                    <div style="margin-top: 10px; font-size: 12px; color: #475569; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px;">
                        <div>ID: <strong><?= (int)$person['id'] ?></strong></div>
                        <div>GEDCOM: <strong><?= htmlspecialchars(str_replace('@', '', (string)($person['gedcom_id'] ?? '—'))) ?></strong></div>
                        <div>Clan ID: <strong><?= (int)($person['clan_id'] ?? 0) ?></strong></div>
                        <div>Father ID: <strong><?= (int)($person['father_id'] ?? 0) ?></strong></div>
                        <div>Mother ID: <strong><?= (int)($person['mother_id'] ?? 0) ?></strong></div>
                        <div>Created: <strong><?= htmlspecialchars((string)($person['created_at'] ?? '—')) ?></strong></div>
                        <div>Updated: <strong><?= htmlspecialchars((string)($person['updated_at'] ?? '—')) ?></strong></div>
                    </div>
                </details>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Модальное окно добавления сына / дочери -->
<div id="addChildModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.65); z-index:999; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#ffffff; border-radius:14px; width:100%; max-width:480px; box-shadow:0 20px 40px rgba(0,0,0,0.3); padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">
            <h3 id="addChildModalTitle" style="font-size:17px; font-weight:700; color:#0f172a;">+ Баланы қосу</h3>
            <button type="button" onclick="closeAddChildModal()" style="background:none; border:none; font-size:22px; cursor:pointer; color:#64748b;">&times;</button>
        </div>
        <form id="addChildForm" onsubmit="handleAddChildSubmit(event)">
            <input type="hidden" name="target_id" value="<?= (int)$person['id'] ?>">
            <input type="hidden" id="addChildGender" name="gender" value="M">
            <input type="hidden" name="relation_type" value="child">

            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Аты / Имя *</label>
                <input type="text" id="addChildFirstName" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
            </div>

            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Тегі / Фамилия</label>
                <input type="text" id="addChildLastName" value="<?= htmlspecialchars((string)($person['last_name'] ?? '')) ?>" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
            </div>

            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Әкесінің аты / Отчество</label>
                <input type="text" id="addChildMiddleName" value="<?= $isMale ? htmlspecialchars((string)($person['first_name'] . 'ұлы')) : '' ?>" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:16px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Туған жылы / Год</label>
                    <input type="text" id="addChildBirthYear" placeholder="2020" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Туған күні / Дата</label>
                    <input type="date" id="addChildBirthDate" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeAddChildModal()" style="padding:9px 16px; border:1px solid #cbd5e1; background:#ffffff; color:#475569; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer;">Бас тарту / Отмена</button>
                <button type="submit" style="padding:9px 18px; border:none; background:#1b3b6f; color:#ffffff; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer;">Қосу / Добавить</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddChildModal(gender) {
        document.getElementById('addChildGender').value = gender;
        document.getElementById('addChildModalTitle').innerText = (gender === 'M') 
            ? '<?= $lang === 'ru' ? '+ Добавить сына' : '+ Ұл қосу' ?>' 
            : '<?= $lang === 'ru' ? '+ Добавить дочь' : '+ Қыз қосу' ?>';
        document.getElementById('addChildModal').style.display = 'flex';
    }

    function closeAddChildModal() {
        document.getElementById('addChildModal').style.display = 'none';
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
                alert('Қате: ' + (data.error || 'Қосу мүмкін болмады'));
            }
        } catch (err) {
            alert('Сұраныс қатесі: ' + err.message);
        }
    }

    async function handlePhotoUpload(e) {
        e.preventDefault();
        const form = document.getElementById('avatarUploadForm');
        const fileInput = document.getElementById('photoFileInput');
        const msg = document.getElementById('uploadStatusMsg');

        if (!fileInput.files || fileInput.files.length === 0) return;

        msg.style.display = 'block';
        msg.style.color = '#2563eb';
        msg.innerText = '<?= $lang === 'ru' ? 'Проверка и пересохранение фото...' : 'Сурет тексеріліп, сақталуда...' ?>';

        const formData = new FormData(form);

        try {
            const res = await fetch('/api/photo/upload', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data && data.success) {
                msg.style.color = '#15803d';
                msg.innerText = '<?= $lang === 'ru' ? 'Фото успешно обновлено!' : 'Фото сәтті жаңартылды!' ?>';
                setTimeout(() => window.location.reload(), 800);
            } else {
                msg.style.color = '#dc2626';
                msg.innerText = 'Қате: ' + (data.error || 'Жүктеу сәтсіз аяқталды');
            }
        } catch (err) {
            msg.style.color = '#dc2626';
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
            if (label) label.innerText = '<?= htmlspecialchars(I18n::t('btn_collapse')) ?>';
            if (arrow) arrow.innerText = '▲';
        } else {
            container.style.maxHeight = '4.8em';
            if (overlay) overlay.style.display = 'block';
            if (label) label.innerText = '<?= htmlspecialchars(I18n::t('btn_read_more')) ?>';
            if (arrow) arrow.innerText = '▼';
        }
    }

    function switchZhurtTab(tab) {
        const tabs = ['oz', 'nagashy', 'kaiyn'];
        tabs.forEach(t => {
            const pane = document.getElementById('zhurtPane_' + t);
            const btn = document.getElementById('tabBtn_' + t);
            if (pane) pane.style.display = (t === tab) ? 'block' : 'none';
            if (btn) {
                btn.style.background = (t === tab) ? '#1b3b6f' : 'transparent';
                btn.style.color = (t === tab) ? '#ffffff' : '#475569';
                btn.style.fontWeight = (t === tab) ? '700' : '600';
            }
        });
    }
</script>

<?php
require __DIR__ . '/../layout/master_end.php';
?>
