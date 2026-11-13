<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
$title = I18n::t('nav_admin');
$activeNav = 'admin';

require __DIR__ . '/../layout/master_start.php';
?>

<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
    <div>
        <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin-bottom: 4px;">
            <?= htmlspecialchars(I18n::t('admin_title')) ?>
        </h1>
        <p style="font-size: 13.5px; color: #64748b;">
            <?= htmlspecialchars(I18n::t('admin_subtitle')) ?>
        </p>
    </div>

    <div style="display: flex; gap: 10px;">
        <a href="/admin/import" style="display: flex; align-items: center; gap: 8px; background: #1b3b6f; color: #ffffff; padding: 9px 18px; border-radius: 8px; text-decoration: none; font-size: 13.5px; font-weight: 700; box-shadow: 0 2px 6px rgba(27,59,111,0.25);">
            <span>📥</span>
            <span><?= $lang === 'ru' ? 'Импорт GEDCOM из MyHeritage' : 'GEDCOM импорты (MyHeritage)' ?></span>
        </a>
    </div>
</div>

<!-- Статистика портала -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div style="background: #ffffff; border-radius: 12px; padding: 18px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 4px;">👥 <?= htmlspecialchars(I18n::t('stat_total_persons')) ?></div>
        <div style="font-size: 26px; font-weight: 800; color: #1e3a8a;"><?= (int)$personsCount ?></div>
    </div>
    <div style="background: #ffffff; border-radius: 12px; padding: 18px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 4px;">💍 <?= htmlspecialchars(I18n::t('stat_total_families')) ?></div>
        <div style="font-size: 26px; font-weight: 800; color: #065f46;"><?= (int)$marriagesCount ?></div>
    </div>
    <div style="background: #ffffff; border-radius: 12px; padding: 18px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 4px;">📜 <?= $lang === 'ru' ? 'Записей аудита:' : 'Аудит жазбалары:' ?></div>
        <div style="font-size: 26px; font-weight: 800; color: #92400e;"><?= (int)$auditCount ?></div>
    </div>
    <div style="background: #ffffff; border-radius: 12px; padding: 18px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 4px;">🎙️ <?= $lang === 'ru' ? 'Медиа и аудио:' : 'Медиа файлдар:' ?></div>
        <div style="font-size: 26px; font-weight: 800; color: #4338ca;"><?= (int)$mediaCount ?></div>
    </div>
</div>

<!-- Список пользователей и управление ролями -->
<div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 22px; margin-bottom: 24px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
    <h2 style="font-size: 17px; font-weight: 700; color: #0f172a; margin-bottom: 16px; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 12px;">
        👤 <?= $lang === 'ru' ? 'Пользователи портала и управление доступом' : 'Пайдаланушылар және құқықтарды басқару' ?>
    </h2>

    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1.5px solid #e2e8f0; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11px;">
                    <th style="padding: 10px 12px; width: 40px;">ID</th>
                    <th style="padding: 10px 12px;"><?= $lang === 'ru' ? 'Имя' : 'Аты' ?></th>
                    <th style="padding: 10px 12px;">Email</th>
                    <th style="padding: 10px 12px; width: 140px;"><?= $lang === 'ru' ? 'Связанная персона' : 'Байланысты тұлға' ?></th>
                    <th style="padding: 10px 12px; width: 170px;"><?= $lang === 'ru' ? 'Роль' : 'Рөлі' ?></th>
                    <th style="padding: 10px 12px; width: 140px;"><?= $lang === 'ru' ? 'Последний вход' : 'Соңғы кіруі' ?></th>
                    <th style="padding: 10px 12px; width: 90px; text-align: right;"><?= $lang === 'ru' ? 'Действие' : 'Әрекет' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 12px; color: #94a3b8; font-size: 11.5px;"><?= (int)$u['id'] ?></td>
                        <td style="padding: 12px; font-weight: 700; color: #0f172a;">
                            <?= htmlspecialchars((string)$u['name']) ?>
                        </td>
                        <td style="padding: 12px; color: #64748b; font-family: monospace;">
                            <?= htmlspecialchars((string)$u['email']) ?>
                        </td>
                        <td style="padding: 12px;">
                            <?php if (!empty($u['person_id'])): ?>
                                <a href="/person/<?= (int)$u['person_id'] ?>" style="color: #2563eb; text-decoration: none; font-weight: 600;">
                                    ID #<?= (int)$u['person_id'] ?>
                                </a>
                            <?php else: ?>
                                <span style="color: #94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px;">
                            <select id="userRoleSelect_<?= (int)$u['id'] ?>" style="padding: 5px 10px; border-radius: 6px; border: 1.5px solid #cbd5e1; font-size: 12.5px; font-weight: 600;">
                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>👑 Әкімші (Admin)</option>
                                <option value="editor" <?= $u['role'] === 'editor' ? 'selected' : '' ?>>✏️ Редактор (Editor)</option>
                                <option value="viewer" <?= $u['role'] === 'viewer' ? 'selected' : '' ?>>👁️ Оқырман (Viewer)</option>
                            </select>
                        </td>
                        <td style="padding: 12px; color: #64748b; font-size: 12px; white-space: nowrap;">
                            <?= !empty($u['last_login_at']) ? date('d.m.Y H:i', strtotime($u['last_login_at'])) : '—' ?>
                        </td>
                        <td style="padding: 12px; text-align: right;">
                            <button type="button" onclick="saveUserRole(<?= (int)$u['id'] ?>)" style="padding: 5px 10px; background: #1b3b6f; color: #ffffff; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
                                💾
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Список приглашений -->
<div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 22px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
    <h2 style="font-size: 17px; font-weight: 700; color: #0f172a; margin-bottom: 16px; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 12px;">
        ✉️ <?= $lang === 'ru' ? 'Приглашения (доступ в закрытую систему)' : 'Шақырулар (жабық жүйеге кіру)' ?>
    </h2>

    <?php if (empty($invitations)): ?>
        <div style="padding: 20px; text-align: center; color: #94a3b8; font-size: 13px;">
            <?= $lang === 'ru' ? 'Активных приглашений нет' : 'Берілген шақырулар жоқ' ?>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1.5px solid #e2e8f0; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11px;">
                        <th style="padding: 10px 12px;">Email</th>
                        <th style="padding: 10px 12px; width: 120px;"><?= $lang === 'ru' ? 'Роль' : 'Рөлі' ?></th>
                        <th style="padding: 10px 12px; width: 140px;"><?= $lang === 'ru' ? 'Дата отправки' : 'Жіберілген уақыты' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invitations as $inv): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 11px 12px; font-weight: 600; font-family: monospace;">
                                <?= htmlspecialchars((string)($inv['email'] ?? '')) ?>
                            </td>
                            <td style="padding: 11px 12px;">
                                <span style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 700;">
                                    <?= htmlspecialchars((string)($inv['role'] ?? 'viewer')) ?>
                                </span>
                            </td>
                            <td style="padding: 11px 12px; color: #64748b; font-size: 12px;">
                                <?= !empty($inv['created_at']) ? date('d.m.Y H:i', strtotime($inv['created_at'])) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
    async function saveUserRole(userId) {
        const select = document.getElementById(`userRoleSelect_${userId}`);
        const role = select.value;
        const formData = new URLSearchParams();
        formData.append('user_id', userId);
        formData.append('role', role);

        try {
            const res = await fetch('/api/admin/user/role', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            const data = await res.json();
            if (data && (data.success || data.status === 'success')) {
                alert('<?= $lang === 'ru' ? 'Роль пользователя успешно сохранена!' : 'Пайдаланушы рөлі сәтті сақталды!' ?>');
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
