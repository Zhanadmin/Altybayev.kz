<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
$title = I18n::t('nav_persons');
$activeNav = 'persons';

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
?>

<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
    <div>
        <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin-bottom: 4px;">
            <?= htmlspecialchars(I18n::t('persons_title')) ?>
        </h1>
        <p style="font-size: 13.5px; color: #64748b;">
            <?= htmlspecialchars(I18n::t('persons_subtitle')) ?>
        </p>
    </div>
    
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <div style="display: flex; gap: 8px; font-size: 12.5px; font-weight: 600;">
            <span style="background: #e0f2fe; color: #0369a1; padding: 5px 12px; border-radius: 20px; border: 1px solid #bae6fd;">
                <?= $lang === 'ru' ? 'Всего:' : 'Барлығы:' ?> <?= (int)$totalAll ?>
            </span>
            <span style="background: #dcfce7; color: #15803d; padding: 5px 12px; border-radius: 20px; border: 1px solid #bbf7d0;">
                <?= $lang === 'ru' ? 'Живых:' : 'Тірі:' ?> <?= (int)$totalLiving ?>
            </span>
            <span style="background: #fef3c7; color: #b45309; padding: 5px 12px; border-radius: 20px; border: 1px solid #fde68a;">
                <?= $lang === 'ru' ? 'Ушедших:' : 'Өмірден өткен:' ?> <?= (int)$totalDeceased ?>
            </span>
        </div>
        
        <?php if ($canEdit): ?>
            <button type="button" onclick="openGlobalPersonEditModal(0)" style="display: flex; align-items: center; gap: 6px; background: #1b3b6f; color: #ffffff; border: none; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; box-shadow: 0 2px 6px rgba(27,59,111,0.2);">
                <span>+</span>
                <span><?= $lang === 'ru' ? 'Добавить персону' : 'Тұлға қосу' ?></span>
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Панель поиска и фильтров -->
<div style="background: #ffffff; border-radius: 12px; padding: 18px; border: 1px solid #e2e8f0; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
    <form method="GET" action="/persons" id="personsFilterForm" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1.3fr auto; gap: 12px; align-items: center;">
        <div>
            <input type="text" name="q" value="<?= htmlspecialchars((string)($filters['q'] ?? '')) ?>" 
                   placeholder="<?= htmlspecialchars(I18n::t('search_placeholder')) ?>" 
                   style="width: 100%; padding: 9px 14px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; outline: none;">
        </div>

        <div>
            <select name="clan_id" onchange="document.getElementById('personsFilterForm').submit()" 
                    style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #fff;">
                <option value="0"><?= htmlspecialchars(I18n::t('filter_all_clans')) ?></option>
                <?php foreach ($clans as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= ((int)($filters['clan_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <select name="gender" onchange="document.getElementById('personsFilterForm').submit()" 
                    style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #fff;">
                <option value=""><?= htmlspecialchars(I18n::t('filter_gender_all')) ?></option>
                <option value="M" <?= ($filters['gender'] ?? '') === 'M' ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('filter_gender_m')) ?></option>
                <option value="F" <?= ($filters['gender'] ?? '') === 'F' ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('filter_gender_f')) ?></option>
            </select>
        </div>

        <div>
            <select name="status" onchange="document.getElementById('personsFilterForm').submit()" 
                    style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #fff;">
                <option value=""><?= htmlspecialchars(I18n::t('filter_status_all')) ?></option>
                <option value="living" <?= ($filters['status'] ?? '') === 'living' ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('filter_status_living')) ?></option>
                <option value="deceased" <?= ($filters['status'] ?? '') === 'deceased' ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('filter_status_deceased')) ?></option>
            </select>
        </div>

        <div>
            <select name="sort" onchange="document.getElementById('personsFilterForm').submit()" 
                    style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #fff;">
                <option value="generation" <?= ($currentSort === 'generation') ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('sort_generation')) ?></option>
                <option value="name_asc" <?= ($currentSort === 'name_asc') ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('sort_name_asc')) ?></option>
                <option value="name_desc" <?= ($currentSort === 'name_desc') ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('sort_name_desc')) ?></option>
                <option value="birth_asc" <?= ($currentSort === 'birth_asc') ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('sort_birth_asc')) ?></option>
                <option value="birth_desc" <?= ($currentSort === 'birth_desc') ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('sort_birth_desc')) ?></option>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" style="padding: 9px 16px; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer;">
                <?= $lang === 'ru' ? 'Найти' : 'Іздеу' ?>
            </button>
            <?php if (!empty($filters['q']) || !empty($filters['clan_id']) || !empty($filters['gender']) || !empty($filters['status']) || $currentSort !== 'generation'): ?>
                <a href="/persons" style="padding: 9px 12px; background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; text-decoration: none; font-weight: 600;">
                    <?= $lang === 'ru' ? 'Сброс' : 'Тазарту' ?>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Статистика фильтра -->
<div style="font-size: 13px; color: #64748b; margin-bottom: 12px; padding: 0 4px;">
    <?= $lang === 'ru' ? "Отображено персон: <strong>{$totalCount}</strong> из {$totalAll}" : "Көрсетілген тұлғалар: <strong>{$totalCount}</strong> ({$totalAll} ішінен)" ?>
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
        
        $yearsDisp = $isDeceased 
            ? "{$birthDisp} — {$deathDisp} <span title='Уақыттан озған баба / Память' style='color:#f59e0b;'>✨</span>" 
            : $birthDisp;

        $kin = $p['kinship'] ?? null;
        ?>
        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.1s ease;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
            <td style="padding: 12px 14px; color: #94a3b8; font-size: 11.5px;"><?= $idx ?></td>
            
            <!-- Тұлға -->
            <td style="padding: 12px 14px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 38px; height: 38px; border-radius: 50%; background: <?= $isDeceased ? '#334155' : ($isMale ? '#1e3a8a' : '#831843') ?>; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; flex-shrink: 0; border: 1.5px solid <?= $isDeceased ? '#d4af37' : 'rgba(255,255,255,0.2)' ?>;">
                        <?php if (!empty($p['avatar_path'])): ?>
                            <img src="<?= htmlspecialchars((string)$p['avatar_path']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <?= htmlspecialchars($initials ?: 'Ш') ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="/person/<?= (int)$p['id'] ?>" style="font-weight: 700; color: #1e3a8a; text-decoration: none; font-size: 13.5px;">
                            <?= htmlspecialchars($p['full_name']) ?>
                        </a>
                        <?php if (!empty($p['maiden_name'])): ?>
                            <div style="font-size: 11.5px; color: #db2777;">
                                (<?= $lang === 'ru' ? 'урожд.' : 'қыз кезіндегі:' ?> <?= htmlspecialchars($p['maiden_name']) ?>)
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($p['birth_place'])): ?>
                            <div style="font-size: 11px; color: #94a3b8; margin-top: 1px;">
                                📍 <?= htmlspecialchars($p['birth_place']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </td>

            <!-- Жынысы -->
            <td style="padding: 12px 14px;">
                <?php if ($isMale): ?>
                    <span style="display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11.5px; font-weight: 600; background: #e0f2fe; color: #0369a1;">
                        <?= htmlspecialchars(I18n::t('filter_gender_m')) ?>
                    </span>
                <?php else: ?>
                    <span style="display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11.5px; font-weight: 600; background: #fce7f3; color: #be185d;">
                        <?= htmlspecialchars(I18n::t('filter_gender_f')) ?>
                    </span>
                <?php endif; ?>
            </td>

            <!-- Руы -->
            <td style="padding: 12px 14px;">
                <span style="display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11.5px; font-weight: 600; background: #fef3c7; color: #92400e; border: 1px solid #fde68a;">
                    <?= htmlspecialchars($p['clan_ru'] ?: ($lang === 'ru' ? 'не указан' : 'белгісіз')) ?>
                </span>
            </td>

            <!-- Жылдары -->
            <td style="padding: 12px 14px; white-space: nowrap; font-size: 12.5px; color: #334155;">
                <?= $yearsDisp ?>
            </td>

            <!-- Ата-анасы -->
            <td style="padding: 12px 14px; font-size: 12px; color: #475569; line-height: 1.4;">
                <?php if ($p['father']): ?>
                    <div>
                        <span style="color: #94a3b8; font-size: 10.5px;"><?= $lang === 'ru' ? 'Отец:' : 'Әкесі:' ?></span>
                        <a href="/person/<?= (int)$p['father']['id'] ?>" style="color: #2563eb; text-decoration: none;">
                            <?= htmlspecialchars(trim(($p['father']['last_name'] ?? '') . ' ' . $p['father']['first_name'])) ?>
                        </a>
                    </div>
                <?php endif; ?>
                <?php if ($p['mother']): ?>
                    <div>
                        <span style="color: #94a3b8; font-size: 10.5px;"><?= $lang === 'ru' ? 'Мать:' : 'Анасы:' ?></span>
                        <a href="/person/<?= (int)$p['mother']['id'] ?>" style="color: #2563eb; text-decoration: none;">
                            <?= htmlspecialchars(trim(($p['mother']['last_name'] ?? '') . ' ' . $p['mother']['first_name'])) ?>
                        </a>
                    </div>
                <?php endif; ?>
                <?php if (!$p['father'] && !$p['mother']): ?>
                    <span style="color: #94a3b8;">—</span>
                <?php endif; ?>
            </td>

            <!-- Супруги -->
            <td style="padding: 12px 14px; font-size: 12px; line-height: 1.4;">
                <?php if (!empty($p['spouses'])): ?>
                    <?php foreach ($p['spouses'] as $sp): 
                        $isDiv = ($sp['status'] === 'divorced');
                    ?>
                        <div>
                            <span><?= $isDiv ? '💔' : '💍' ?></span>
                            <a href="/person/<?= (int)$sp['spouse_id'] ?>" style="color: <?= $isDiv ? '#64748b' : '#be185d' ?>; text-decoration: none; font-weight: 500;">
                                <?= htmlspecialchars($sp['name']) ?>
                            </a>
                            <?php if (!empty($sp['years'])): ?>
                                <span style="font-size: 10.5px; color: #94a3b8;">(<?= htmlspecialchars($sp['years']) ?>)</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span style="color: #94a3b8;">—</span>
                <?php endif; ?>
            </td>

            <!-- Үш жұрт / Сізге туыстығы -->
            <td style="padding: 12px 14px;">
                <?php if ($kin && !empty($kin['zhurt'])): 
                    $zClass = match (true) {
                        str_contains($kin['zhurt'], 'Нағашы') => 'background:#dbeafe; color:#1e40af; border:1px solid #bfdbfe;',
                        str_contains($kin['zhurt'], 'Қайын')  => 'background:#fce7f3; color:#9d174d; border:1px solid #fbcfe8;',
                        str_contains($kin['zhurt'], 'Төркін') => 'background:#ede9fe; color:#6d28d9; border:1px solid #ddd6fe;',
                        str_contains($kin['zhurt'], 'Күйеу')  => 'background:#e0e7ff; color:#3730a3; border:1px solid #c7d2fe;',
                        str_contains($kin['zhurt'], 'Құда')   => 'background:#fef9c3; color:#854d0e; border:1px solid #fef08a;',
                        default => 'background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0;',
                    };
                    $termText = I18n::formatKinship($kin['term_kk'] ?? '', $kin['term_ru'] ?? null);
                ?>
                    <span style="display:inline-block; padding: 2px 7px; border-radius: 12px; font-size: 11px; font-weight: 700; <?= $zClass ?>">
                        <?= htmlspecialchars($kin['zhurt']) ?>
                    </span>
                    <div style="font-size: 11.5px; color: #475569; margin-top: 2px; font-weight: 500;">
                        <?= htmlspecialchars($termText) ?>
                    </div>
                <?php elseif ($kin && !empty($kin['term_kk'])): ?>
                    <div style="font-size: 11.5px; color: #475569; font-weight: 500;">
                        <?= htmlspecialchars(I18n::formatKinship($kin['term_kk'], $kin['term_ru'] ?? null)) ?>
                    </div>
                <?php else: ?>
                    <span style="color: #94a3b8;">—</span>
                <?php endif; ?>
            </td>

            <!-- Әрекеттер -->
            <td style="padding: 12px 14px; text-align: right; white-space: nowrap;">
                <a href="/person/<?= (int)$p['id'] ?>" class="btn-action" title="<?= $lang === 'ru' ? 'Карточка' : 'Парақшасы' ?>" style="display: inline-block; padding: 5px 9px; border-radius: 6px; background: #f1f5f9; color: #1e3a8a; border: 1px solid #cbd5e1; text-decoration: none; font-size: 12px; font-weight: 600; margin-right: 4px;">
                    👤 <?= $lang === 'ru' ? 'Карточка' : 'Парақша' ?>
                </a>
                <a href="/tree?focus=<?= (int)$p['id'] ?>" class="btn-action" title="<?= $lang === 'ru' ? 'Показать в древе' : 'Древода көрсету' ?>" style="display: inline-block; padding: 5px 9px; border-radius: 6px; background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; text-decoration: none; font-size: 12px; font-weight: 600; margin-right: 4px;">
                    🎯 <?= $lang === 'ru' ? 'Древо' : 'Древо' ?>
                </a>
                <?php if ($canEdit): ?>
                    <button type="button" onclick="openGlobalPersonEditModal(<?= (int)$p['id'] ?>)" title="<?= $lang === 'ru' ? 'Редактировать' : 'Өңдеу' ?>" style="padding: 5px 8px; border-radius: 6px; background: #ffffff; color: #475569; border: 1px solid #cbd5e1; cursor: pointer; font-size: 12px;">
                        ✏️
                    </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }
};

$renderTableHeader = function() use ($buildSortUrl) {
    ?>
    <thead>
        <tr style="background: #f8fafc; border-bottom: 1.5px solid #e2e8f0; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11.5px; letter-spacing: 0.5px;">
            <th style="padding: 12px 14px; width: 40px;">#</th>
            <th style="padding: 12px 14px; min-width: 220px;">
                <a href="<?= $buildSortUrl('name_asc') ?>" style="color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                    <span><?= htmlspecialchars(I18n::t('th_person')) ?></span>
                    <span>⇅</span>
                </a>
            </th>
            <th style="padding: 12px 14px; width: 80px;"><?= htmlspecialchars(I18n::t('th_gender')) ?></th>
            <th style="padding: 12px 14px; min-width: 110px;"><?= htmlspecialchars(I18n::t('th_clan')) ?></th>
            <th style="padding: 12px 14px; min-width: 140px;">
                <a href="<?= $buildSortUrl('birth_asc') ?>" style="color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                    <span><?= htmlspecialchars(I18n::t('th_years')) ?></span>
                    <span>⇅</span>
                </a>
            </th>
            <th style="padding: 12px 14px; min-width: 160px;"><?= htmlspecialchars(I18n::t('th_parents')) ?></th>
            <th style="padding: 12px 14px; min-width: 160px;"><?= htmlspecialchars(I18n::t('th_spouses')) ?></th>
            <th style="padding: 12px 14px; min-width: 170px;"><?= htmlspecialchars(I18n::t('th_kinship')) ?></th>
            <th style="padding: 12px 14px; width: 140px; text-align: right;"><?= htmlspecialchars(I18n::t('th_actions')) ?></th>
        </tr>
    </thead>
    <?php
};
?>

<?php if (empty($persons)): ?>
    <div style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 40px; text-align: center; color: #64748b;">
        <div style="font-size: 32px; margin-bottom: 8px;">🔍</div>
        <div style="font-weight: 600; font-size: 15px; color: #0f172a; margin-bottom: 4px;">
            <?= $lang === 'ru' ? 'Персоны не найдены' : 'Сұранысқа сай тұлғалар табылмады' ?>
        </div>
        <div style="font-size: 13px;">
            <?= $lang === 'ru' ? 'Попробуйте изменить поисковый запрос или сбросить фильтры' : 'Іздеу сөзін өзгертіп көріңіз немесе сүзгілерді тазартыңыз' ?>
        </div>
    </div>
<?php elseif ($isGenerationView): ?>
    <!-- ГРУППИРОВКА ПО ПОКОЛЕНИЯМ (По умолчанию, TASK-007) -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <?php 
        $globalOffset = 0;
        foreach ($generationGroups as $gKey => $group): 
            $countInGroup = count($group['persons']);
            if ($countInGroup === 0) continue;
        ?>
            <div style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <!-- Заголовок поколения -->
                <div style="background: linear-gradient(135deg, #1b3b6f, #0d1b2a); color: #ffffff; padding: 14px 20px; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;"><?= $group['badge'] ?></span>
                        <h2 style="font-size: 16px; font-weight: 800; margin: 0; letter-spacing: 0.3px;">
                            <?= $lang === 'ru' ? $group['title_ru'] : $group['title_kk'] ?>
                        </h2>
                    </div>
                    <span style="background: rgba(255,255,255,0.18); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; color: #f8fafc;">
                        <?= $countInGroup ?> <?= $lang === 'ru' ? 'персон' : 'тұлға' ?>
                    </span>
                </div>
                
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                        <?php $renderTableHeader(); ?>
                        <tbody>
                            <?php 
                            $renderTableRows($group['persons'], $globalOffset); 
                            $globalOffset += $countInGroup;
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <!-- ПЛОСКИЙ СПИСОК (При явной сортировке по имени или годам) -->
    <div style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                <?php $renderTableHeader(); ?>
                <tbody>
                    <?php $renderTableRows($persons, 0); ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php
require __DIR__ . '/../layout/master_end.php';
?>
