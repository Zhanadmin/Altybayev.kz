<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
$title = I18n::t('nav_families');
$activeNav = 'families';

require __DIR__ . '/../layout/master_start.php';
?>

<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
    <div>
        <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin-bottom: 4px;">
            <?= htmlspecialchars(I18n::t('families_title')) ?>
        </h1>
        <p style="font-size: 13.5px; color: #64748b;">
            <?= htmlspecialchars(I18n::t('families_subtitle')) ?>
        </p>
    </div>

    <div style="display: flex; gap: 8px; font-size: 12.5px; font-weight: 600;">
        <span style="background: #e0f2fe; color: #0369a1; padding: 5px 12px; border-radius: 20px; border: 1px solid #bae6fd;">
            <?= $lang === 'ru' ? 'Всего союзов:' : 'Барлық шаңырақ:' ?> <?= (int)$totalAll ?>
        </span>
        <span style="background: #dcfce7; color: #15803d; padding: 5px 12px; border-radius: 20px; border: 1px solid #bbf7d0;">
            💍 <?= $lang === 'ru' ? 'В браке:' : 'Некеде:' ?> <?= (int)$totalMarried ?>
        </span>
        <span style="background: #f1f5f9; color: #64748b; padding: 5px 12px; border-radius: 20px; border: 1px solid #cbd5e1;">
            💔 <?= $lang === 'ru' ? 'В разводе:' : 'Ажырасқан:' ?> <?= (int)$totalDivorced ?>
        </span>
    </div>
</div>

<!-- Фильтр и поиск -->
<div style="background: #ffffff; border-radius: 12px; padding: 18px; border: 1px solid #e2e8f0; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
    <form method="GET" action="/families" id="familiesFilterForm" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 240px;">
            <input type="text" name="q" value="<?= htmlspecialchars((string)($filters['q'] ?? '')) ?>" 
                   placeholder="<?= $lang === 'ru' ? 'Поиск по фамилии супругов, роду, месту...' : 'Ерлі-зайыптылардың аты-жөні, руы бойынша іздеу...' ?>" 
                   style="width: 100%; padding: 9px 14px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; outline: none;">
        </div>

        <div style="width: 180px;">
            <select name="status" onchange="document.getElementById('familiesFilterForm').submit()" 
                    style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #fff;">
                <option value=""><?= $lang === 'ru' ? 'Статус: Все' : 'Күйі: Барлығы' ?></option>
                <option value="married" <?= ($filters['status'] ?? '') === 'married' ? 'selected' : '' ?>>💍 <?= $lang === 'ru' ? 'В браке' : 'Некеде' ?></option>
                <option value="divorced" <?= ($filters['status'] ?? '') === 'divorced' ? 'selected' : '' ?>>💔 <?= $lang === 'ru' ? 'В разводе' : 'Ажырасқан' ?></option>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" style="padding: 9px 16px; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer;">
                <?= $lang === 'ru' ? 'Найти' : 'Іздеу' ?>
            </button>
            <?php if (!empty($filters['q']) || !empty($filters['status'])): ?>
                <a href="/families" style="padding: 9px 12px; background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; text-decoration: none; font-weight: 600;">
                    <?= $lang === 'ru' ? 'Сброс' : 'Тазарту' ?>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Сетка семей -->
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 18px;">
    <?php if (empty($families)): ?>
        <div style="grid-column: 1 / -1; background: #ffffff; border-radius: 12px; padding: 40px; text-align: center; color: #64748b; border: 1px solid #e2e8f0;">
            <div style="font-size: 36px; margin-bottom: 8px;">👨‍👩‍👧‍👦</div>
            <div style="font-weight: 600; font-size: 16px; color: #0f172a; margin-bottom: 4px;">
                <?= $lang === 'ru' ? 'Семьи не найдены' : 'Шаңырақтар табылмады' ?>
            </div>
            <div style="font-size: 13px;">
                <?= $lang === 'ru' ? 'Попробуйте изменить параметры поиска' : 'Іздеу параметрлерін өзгертіп көріңіз' ?>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($families as $idx => $f): 
            $m = $f['marriage'];
            $isMarried = ($f['status'] === 'married');
            $h = $f['husband'];
            $w = $f['wife'];
            $children = $f['children'];
        ?>
            <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <!-- Заголовок карточки семьи -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                        <span style="font-size: 12px; font-weight: 700; color: #94a3b8;">
                            #<?= $idx + 1 ?> (ID <?= (int)$m['id'] ?>)
                        </span>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11.5px; font-weight: 700; <?= $isMarried ? 'background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0;' : 'background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1;' ?>">
                                <?= $isMarried ? '💍 ' . ($lang === 'ru' ? 'В браке' : 'Некеде') : '💔 ' . ($lang === 'ru' ? 'В разводе' : 'Ажырасқан') ?>
                            </span>
                            <?php if ($canEdit): ?>
                                <button type="button" onclick="openMarriageEditModal(<?= (int)$m['id'] ?>, '<?= htmlspecialchars($m['status'] ?? 'married') ?>', '<?= htmlspecialchars((string)($m['start_date'] ?: $m['marriage_date'])) ?>', '<?= htmlspecialchars((string)($m['end_date'] ?: $m['divorce_date'])) ?>', '<?= htmlspecialchars((string)($m['notes'] ?? '')) ?>')" style="background: none; border: none; cursor: pointer; font-size: 13px;" title="Өңдеу">
                                    ✏️
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Супруги (Пара) -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <!-- Муж -->
                        <div style="padding: 10px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <div style="font-size: 10.5px; color: #64748b; font-weight: 600; margin-bottom: 2px; text-transform: uppercase;">
                                👨 <?= $lang === 'ru' ? 'Муж' : 'Отағасы' ?>
                            </div>
                            <?php if ($h): ?>
                                <a href="/person/<?= (int)$h['id'] ?>" style="font-size: 13px; font-weight: 700; color: #1e3a8a; text-decoration: none; display: block; line-height: 1.2;">
                                    <?= htmlspecialchars($f['h_name']) ?>
                                </a>
                                <div style="font-size: 11px; color: #94a3b8; margin-top: 3px;">
                                    <?= $h['clan_ru'] ? htmlspecialchars($h['clan_ru']) : '' ?>
                                    <?= $h['birth_year'] ? ' • ' . $h['birth_year'] . ' ж.т.' : '' ?>
                                </div>
                            <?php else: ?>
                                <span style="font-size: 12.5px; color: #94a3b8;">Белгісіз</span>
                            <?php endif; ?>
                        </div>

                        <!-- Жена -->
                        <div style="padding: 10px; background: #fdf2f8; border-radius: 8px; border: 1px solid #fbcfe8;">
                            <div style="font-size: 10.5px; color: #be185d; font-weight: 600; margin-bottom: 2px; text-transform: uppercase;">
                                👩 <?= $lang === 'ru' ? 'Жена' : 'Отанасы' ?>
                            </div>
                            <?php if ($w): ?>
                                <a href="/person/<?= (int)$w['id'] ?>" style="font-size: 13px; font-weight: 700; color: #831843; text-decoration: none; display: block; line-height: 1.2;">
                                    <?= htmlspecialchars($f['w_name']) ?>
                                </a>
                                <?php if (!empty($w['maiden_name'])): ?>
                                    <div style="font-size: 10.5px; color: #be185d; margin-top: 1px;">
                                        (<?= $lang === 'ru' ? 'урожд.' : 'қыз кезінде:' ?> <?= htmlspecialchars($w['maiden_name']) ?>)
                                    </div>
                                <?php endif; ?>
                                <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">
                                    <?= $w['birth_year'] ? $w['birth_year'] . ' ж.т.' : '' ?>
                                </div>
                            <?php else: ?>
                                <span style="font-size: 12.5px; color: #94a3b8;">Белгісіз</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Даты брака / развода -->
                    <?php if (!empty($m['marriage_date']) || !empty($m['start_date']) || !empty($m['end_date'])): ?>
                        <div style="font-size: 11.5px; color: #64748b; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                            <span>🗓️</span>
                            <span>
                                <?= $lang === 'ru' ? 'Период союза:' : 'Неке мерзімі:' ?>
                                <strong><?= htmlspecialchars((string)($m['start_date'] ?: $m['marriage_date'])) ?></strong>
                                <?php if (!empty($m['end_date']) || !empty($m['divorce_date'])): ?>
                                    — <strong><?= htmlspecialchars((string)($m['end_date'] ?: $m['divorce_date'])) ?></strong>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <!-- Балалары (Дети) -->
                    <div>
                        <div style="font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                            👶 <?= $lang === 'ru' ? 'Дети (' . count($children) . '):' : 'Балалары (' . count($children) . '):' ?>
                        </div>
                        <?php if (empty($children)): ?>
                            <div style="font-size: 12px; color: #94a3b8; font-style: italic;">
                                <?= $lang === 'ru' ? 'Общие дети не зарегистрированы' : 'Ортақ балалар тіркелмеген' ?>
                            </div>
                        <?php else: ?>
                            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                <?php foreach ($children as $c): 
                                    $cIsMale = ($c['gender'] === 'M');
                                ?>
                                    <a href="/person/<?= (int)$c['id'] ?>" style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 6px; font-size: 11.5px; font-weight: 600; text-decoration: none; <?= $cIsMale ? 'background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd;' : 'background:#fce7f3; color:#be185d; border:1px solid #fbcfe8;' ?>">
                                        <span><?= $cIsMale ? '👦' : '👧' ?></span>
                                        <span><?= htmlspecialchars($c['first_name']) ?></span>
                                        <?php if (!empty($c['birth_year'])): ?>
                                            <span style="font-size: 10px; opacity: 0.8;">(<?= htmlspecialchars($c['birth_year']) ?>)</span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Быстрый переход в древо -->
                <?php if ($h || $w): 
                    $focalId = $h ? (int)$h['id'] : (int)$w['id'];
                ?>
                    <div style="margin-top: 16px; pt: 10px; border-top: 1px solid #f1f5f9; text-align: right;">
                        <a href="/tree?focus=<?= $focalId ?>" style="font-size: 12px; font-weight: 700; color: #2563eb; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                            <span>🎯 <?= $lang === 'ru' ? 'Показать семью в древе' : 'Древода көрсету' ?> ➔</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Модальное окно редактирования брака -->
<div id="marriageEditModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.65); z-index:999; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#ffffff; border-radius:14px; width:100%; max-width:440px; box-shadow:0 20px 40px rgba(0,0,0,0.3); padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">
            <h3 style="font-size:17px; font-weight:700; color:#0f172a;">Некені өңдеу / Редактирование брака</h3>
            <button type="button" onclick="closeMarriageEditModal()" style="background:none; border:none; font-size:22px; cursor:pointer; color:#64748b;">&times;</button>
        </div>
        <form id="marriageEditForm" onsubmit="handleMarriageSave(event)">
            <input type="hidden" id="editMarriageId" name="id" value="">
            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Күйі / Статус</label>
                <select id="editMarriageStatus" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                    <option value="married">💍 Некеде / В браке</option>
                    <option value="divorced">💔 Ажырасқан / В разводе</option>
                </select>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Неке күні / Дата</label>
                    <input type="text" id="editMarriageStartDate" placeholder="1995" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Ажырасу / Дата</label>
                    <input type="text" id="editMarriageEndDate" placeholder="2010" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                </div>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Жазба / Примечание</label>
                <input type="text" id="editMarriageNotes" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeMarriageEditModal()" style="padding:9px 16px; border:1px solid #cbd5e1; background:#ffffff; color:#475569; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer;">Бас тарту / Отмена</button>
                <button type="submit" style="padding:9px 18px; border:none; background:#1b3b6f; color:#ffffff; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer;">Сақтау / Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openMarriageEditModal(id, status, startDate, endDate, notes) {
        document.getElementById('editMarriageId').value = id;
        document.getElementById('editMarriageStatus').value = status;
        document.getElementById('editMarriageStartDate').value = startDate || '';
        document.getElementById('editMarriageEndDate').value = endDate || '';
        document.getElementById('editMarriageNotes').value = notes || '';
        document.getElementById('marriageEditModal').style.display = 'flex';
    }

    function closeMarriageEditModal() {
        document.getElementById('marriageEditModal').style.display = 'none';
    }

    async function handleMarriageSave(e) {
        e.preventDefault();
        const id = document.getElementById('editMarriageId').value;
        const formData = new URLSearchParams();
        formData.append('id', id);
        formData.append('status', document.getElementById('editMarriageStatus').value);
        formData.append('start_date', document.getElementById('editMarriageStartDate').value.trim());
        formData.append('end_date', document.getElementById('editMarriageEndDate').value.trim());
        formData.append('notes', document.getElementById('editMarriageNotes').value.trim());

        try {
            const res = await fetch('/api/marriage/update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            const data = await res.json();
            if (data && (data.status === 'success' || data.success)) {
                window.location.reload();
            } else {
                alert('Қате: ' + (data.error || 'Сақтау мүмкін болмады'));
            }
        } catch (err) {
            alert('Сұраныс қатесі: ' + err.message);
        }
    }
</script>

<?php
require __DIR__ . '/../layout/master_end.php';
?>
