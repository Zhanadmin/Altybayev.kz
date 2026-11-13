<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
$title = I18n::t('nav_history');
$activeNav = 'history';

require __DIR__ . '/../layout/master_start.php';
?>

<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
    <div>
        <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin-bottom: 4px;">
            <?= htmlspecialchars(I18n::t('history_title')) ?>
        </h1>
        <p style="font-size: 13.5px; color: #64748b;">
            <?= htmlspecialchars(I18n::t('history_subtitle')) ?>
        </p>
    </div>

    <div style="font-size: 13px; color: #64748b; background: #ffffff; padding: 6px 14px; border-radius: 20px; border: 1px solid #e2e8f0; font-weight: 600;">
        <?= $lang === 'ru' ? 'Всего записей в аудите:' : 'Барлық жазбалар:' ?> <?= (int)$totalCount ?>
    </div>
</div>

<!-- Фильтры истории -->
<div style="background: #ffffff; border-radius: 12px; padding: 18px; border: 1px solid #e2e8f0; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
    <form method="GET" action="/history" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <div style="width: 200px;">
            <select name="action" onchange="this.form.submit()" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #fff;">
                <option value=""><?= $lang === 'ru' ? 'Действие: Все' : 'Әрекет: Барлығы' ?></option>
                <option value="CREATE" <?= ($filters['action'] ?? '') === 'CREATE' ? 'selected' : '' ?>>CREATE (Қосу)</option>
                <option value="UPDATE" <?= ($filters['action'] ?? '') === 'UPDATE' ? 'selected' : '' ?>>UPDATE (Өзгерту)</option>
                <option value="DELETE" <?= ($filters['action'] ?? '') === 'DELETE' ? 'selected' : '' ?>>DELETE (Өшіру)</option>
                <option value="ROLLBACK" <?= ($filters['action'] ?? '') === 'ROLLBACK' ? 'selected' : '' ?>>ROLLBACK (Откат)</option>
                <option value="IMPORT_GEDCOM" <?= ($filters['action'] ?? '') === 'IMPORT_GEDCOM' ? 'selected' : '' ?>>IMPORT_GEDCOM (Импорт)</option>
            </select>
        </div>

        <div style="width: 200px;">
            <select name="table" onchange="this.form.submit()" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #fff;">
                <option value=""><?= $lang === 'ru' ? 'Объект: Все' : 'Нысан: Барлығы' ?></option>
                <option value="persons" <?= ($filters['table'] ?? '') === 'persons' ? 'selected' : '' ?>>persons (Тұлғалар)</option>
                <option value="marriages" <?= ($filters['table'] ?? '') === 'marriages' ? 'selected' : '' ?>>marriages (Некелер)</option>
                <option value="person_media" <?= ($filters['table'] ?? '') === 'person_media' ? 'selected' : '' ?>>person_media (Медиа)</option>
                <option value="clans" <?= ($filters['table'] ?? '') === 'clans' ? 'selected' : '' ?>>clans (Рулар)</option>
            </select>
        </div>

        <?php if (!empty($filters['action']) || !empty($filters['table'])): ?>
            <a href="/history" style="padding: 9px 12px; background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; text-decoration: none; font-weight: 600;">
                <?= $lang === 'ru' ? 'Сбросить фильтр' : 'Сүзгіні тазарту' ?>
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Таблица аудита -->
<div style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1.5px solid #e2e8f0; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">
                    <th style="padding: 12px 14px; width: 50px;">ID</th>
                    <th style="padding: 12px 14px; width: 140px;"><?= $lang === 'ru' ? 'Время' : 'Уақыты' ?></th>
                    <th style="padding: 12px 14px; width: 180px;"><?= $lang === 'ru' ? 'Пользователь' : 'Пайдаланушы' ?></th>
                    <th style="padding: 12px 14px; width: 100px;"><?= $lang === 'ru' ? 'Действие' : 'Әрекет' ?></th>
                    <th style="padding: 12px 14px; width: 120px;"><?= $lang === 'ru' ? 'Объект' : 'Нысан' ?></th>
                    <th style="padding: 12px 14px;"><?= $lang === 'ru' ? 'Описание события' : 'Оқиға сипаттамасы' ?></th>
                    <th style="padding: 12px 14px; width: 110px; text-align: right;"><?= $lang === 'ru' ? 'Откат' : 'Откат' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" style="padding: 30px; text-align: center; color: #94a3b8;">
                            <?= $lang === 'ru' ? 'Записи аудита отсутствуют' : 'Өзгертулер журналы бос' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): 
                        $action = $l['action'];
                        $actBadge = match ($action) {
                            'CREATE' => 'background:#dcfce7; color:#15803d; border:1px solid #bbf7d0;',
                            'UPDATE' => 'background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd;',
                            'DELETE' => 'background:#fee2e2; color:#b91c1c; border:1px solid #fecaca;',
                            'ROLLBACK'=> 'background:#fef3c7; color:#b45309; border:1px solid #fde68a;',
                            default  => 'background:#f1f5f9; color:#475569; border:1px solid #cbd5e1;',
                        };
                        $canRollback = ($isAdmin && !empty($l['old_values']) && ($action === 'UPDATE' || $action === 'DELETE'));
                    ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 11px 14px; color: #94a3b8; font-size: 11.5px;"><?= (int)$l['id'] ?></td>
                            <td style="padding: 11px 14px; white-space: nowrap; color: #475569; font-size: 12px;">
                                <?= date('d.m.Y H:i', strtotime($l['created_at'])) ?>
                            </td>
                            <td style="padding: 11px 14px; font-weight: 600; color: #0f172a;">
                                <?= htmlspecialchars((string)($l['user_name'] ?: 'Система')) ?>
                            </td>
                            <td style="padding: 11px 14px;">
                                <span style="display: inline-block; padding: 2px 7px; border-radius: 6px; font-size: 11px; font-weight: 700; <?= $actBadge ?>">
                                    <?= htmlspecialchars($action) ?>
                                </span>
                            </td>
                            <td style="padding: 11px 14px; font-size: 12px; color: #334155;">
                                <?php if ($l['entity_table'] === 'persons'): ?>
                                    <a href="/person/<?= (int)$l['entity_id'] ?>" style="color: #2563eb; text-decoration: none; font-weight: 600;">
                                        persons #<?= (int)$l['entity_id'] ?>
                                    </a>
                                <?php else: ?>
                                    <span style="font-family: monospace;"><?= htmlspecialchars($l['entity_table']) ?> #<?= (int)$l['entity_id'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 11px 14px; color: #334155;">
                                <?= htmlspecialchars((string)$l['summary']) ?>
                            </td>
                            <td style="padding: 11px 14px; text-align: right; white-space: nowrap;">
                                <?php if ($canRollback): ?>
                                    <button type="button" onclick="handleRollback(<?= (int)$l['id'] ?>)" style="padding: 4px 10px; border-radius: 6px; background: #fff; border: 1px solid #cbd5e1; color: #b45309; font-size: 11.5px; font-weight: 600; cursor: pointer;">
                                        ↩️ <?= $lang === 'ru' ? 'Откатить' : 'Откат' ?>
                                    </button>
                                <?php else: ?>
                                    <span style="color: #cbd5e1; font-size: 11px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    async function handleRollback(logId) {
        if (!confirm('<?= $lang === 'ru' ? 'Вы уверены, что хотите откатить это изменение?' : 'Бұл өзгертуді кері қайтарғыңыз келетініне сенімдісіз бе?' ?>')) {
            return;
        }

        const formData = new URLSearchParams();
        formData.append('log_id', logId);

        try {
            const res = await fetch('/api/history/rollback', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            const data = await res.json();
            if (data && (data.success || data.status === 'success')) {
                alert('<?= $lang === 'ru' ? 'Изменение успешно откачено!' : 'Өзгерту сәтті кері қайтарылды!' ?>');
                window.location.reload();
            } else {
                alert('Қате: ' + (data.error || 'Откат жасау мүмкін болмады'));
            }
        } catch (err) {
            alert('Сұраныс қатесі: ' + err.message);
        }
    }
</script>

<?php
require __DIR__ . '/../layout/master_end.php';
?>
