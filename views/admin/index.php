<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
$title = I18n::t('nav_admin');
$activeNav = 'admin';
$dsThemeReady = true; // TASK-008: страница на цветах дизайн-системы — доступна тёмная тема

require __DIR__ . '/../layout/master_start.php';

$adRoleLabels = [
    'admin'  => 'Әкімші (Admin)',
    'editor' => 'Редактор (Editor)',
    'viewer' => 'Оқырман (Viewer)',
];
?>

<div class="ad">
    <div class="ds-page-head">
        <div>
            <h1><?= htmlspecialchars(I18n::t('admin_title')) ?></h1>
            <p class="sub"><?= htmlspecialchars(I18n::t('admin_subtitle')) ?></p>
        </div>
        <div class="actions">
            <a href="/admin/import" class="btn btn-primary">
                <svg class="ic" aria-hidden="true"><use href="#i-upload"/></svg>
                <span><?= $lang === 'ru' ? 'Импорт GEDCOM из MyHeritage' : 'GEDCOM импорты (MyHeritage)' ?></span>
            </a>
        </div>
    </div>

    <!-- Статистика портала -->
    <div class="grid grid-stats">
        <div class="island stat">
            <div class="label"><svg class="ic" aria-hidden="true"><use href="#i-users"/></svg><?= htmlspecialchars(I18n::t('stat_total_persons')) ?></div>
            <div class="value"><?= (int)$personsCount ?></div>
        </div>
        <div class="island stat">
            <div class="label"><svg class="ic" aria-hidden="true"><use href="#i-family"/></svg><?= htmlspecialchars(I18n::t('stat_total_families')) ?></div>
            <div class="value"><?= (int)$marriagesCount ?></div>
        </div>
        <div class="island stat">
            <div class="label"><svg class="ic" aria-hidden="true"><use href="#i-history"/></svg><?= $lang === 'ru' ? 'Записей аудита:' : 'Аудит жазбалары:' ?></div>
            <div class="value"><?= (int)$auditCount ?></div>
        </div>
        <div class="island stat">
            <div class="label"><svg class="ic" aria-hidden="true"><use href="#i-media"/></svg><?= $lang === 'ru' ? 'Медиа и аудио:' : 'Медиа файлдар:' ?></div>
            <div class="value"><?= (int)$mediaCount ?></div>
        </div>
    </div>

    <!-- Список пользователей и управление ролями -->
    <div class="island ad-island">
        <div class="island-head ad-head">
            <svg class="ic" aria-hidden="true"><use href="#i-users"/></svg>
            <h3><?= $lang === 'ru' ? 'Пользователи портала и управление доступом' : 'Пайдаланушылар және құқықтарды басқару' ?></h3>
        </div>

        <div class="table-wrap">
            <table class="table ad-table">
                <thead>
                    <tr>
                        <th class="num col-opt-lg">ID</th>
                        <th><?= $lang === 'ru' ? 'Имя / Email' : 'Аты / Email' ?></th>
                        <th class="ad-w-person col-opt-lg"><?= $lang === 'ru' ? 'Связанная персона' : 'Байланысты тұлға' ?></th>
                        <th class="ad-w-role"><?= $lang === 'ru' ? 'Роль' : 'Рөлі' ?></th>
                        <th class="ad-w-login col-opt-md"><?= $lang === 'ru' ? 'Последний вход' : 'Соңғы кіруі' ?></th>
                        <th class="ad-w-save actions"><span class="ad-sr"><?= $lang === 'ru' ? 'Действие' : 'Әрекет' ?></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u):
                        $uName = (string)$u['name'];
                        $uEmail = (string)$u['email'];
                        $uInitial = mb_substr($uName !== '' ? $uName : $uEmail, 0, 1);
                    ?>
                        <tr>
                            <td class="num col-opt-lg"><?= (int)$u['id'] ?></td>
                            <td>
                                <div class="person">
                                    <span class="avatar" aria-hidden="true"><?= htmlspecialchars($uInitial) ?></span>
                                    <div>
                                        <div class="name" title="<?= htmlspecialchars($uName) ?>"><?= htmlspecialchars($uName) ?></div>
                                        <div class="meta ad-mono" title="<?= htmlspecialchars($uEmail) ?>"><?= htmlspecialchars($uEmail) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="clip col-opt-lg">
                                <?php if (!empty($u['person_id'])): ?>
                                    <a href="/person/<?= (int)$u['person_id'] ?>" class="pp-name">ID #<?= (int)$u['person_id'] ?></a>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="ad-role">
                                <select id="userRoleSelect_<?= (int)$u['id'] ?>" class="select" aria-label="<?= $lang === 'ru' ? 'Роль' : 'Рөлі' ?>">
                                    <?php foreach ($adRoleLabels as $roleKey => $roleLabel): ?>
                                        <option value="<?= $roleKey ?>" <?= $u['role'] === $roleKey ? 'selected' : '' ?>><?= htmlspecialchars($roleLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="clip muted small col-opt-md">
                                <?= !empty($u['last_login_at']) ? date('d.m.Y H:i', strtotime($u['last_login_at'])) : '—' ?>
                            </td>
                            <td class="actions">
                                <button type="button" class="btn btn-sm btn-icon" onclick="saveUserRole(<?= (int)$u['id'] ?>)"
                                        aria-label="<?= $lang === 'ru' ? 'Сохранить роль' : 'Рөлді сақтау' ?>"
                                        data-tip="<?= $lang === 'ru' ? 'Сохранить' : 'Сақтау' ?>">
                                    <svg class="ic" aria-hidden="true"><use href="#i-save"/></svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Список приглашений -->
    <div class="island ad-island">
        <div class="island-head ad-head">
            <svg class="ic" aria-hidden="true"><use href="#i-mail"/></svg>
            <h3><?= $lang === 'ru' ? 'Приглашения (доступ в закрытую систему)' : 'Шақырулар (жабық жүйеге кіру)' ?></h3>
        </div>

        <?php if (empty($invitations)): ?>
            <div class="hm-empty ad-empty">
                <?= $lang === 'ru' ? 'Активных приглашений нет' : 'Берілген шақырулар жоқ' ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table ad-table">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th class="ad-w-invrole"><?= $lang === 'ru' ? 'Роль' : 'Рөлі' ?></th>
                            <th class="ad-w-login col-opt-md"><?= $lang === 'ru' ? 'Дата отправки' : 'Жіберілген уақыты' ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invitations as $inv):
                            $invEmail = (string)($inv['email'] ?? '');
                        ?>
                            <tr>
                                <td class="clip ad-mono" title="<?= htmlspecialchars($invEmail) ?>"><?= htmlspecialchars($invEmail) ?></td>
                                <td class="clip">
                                    <span class="badge <?= ($inv['role'] ?? '') === 'admin' ? 'badge-accent' : '' ?>"><?= htmlspecialchars((string)($inv['role'] ?? 'viewer')) ?></span>
                                </td>
                                <td class="clip muted small col-opt-md">
                                    <?= !empty($inv['created_at']) ? date('d.m.Y H:i', strtotime($inv['created_at'])) : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
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
                showAppToast(<?= json_encode($lang === 'ru' ? 'Роль пользователя успешно сохранена!' : 'Пайдаланушы рөлі сәтті сақталды!', JSON_UNESCAPED_UNICODE) ?>, 'success');
            } else {
                showAppToast('Қате: ' + ((data && data.error) || 'Сақтау мүмкін болмады'), 'error');
            }
        } catch (err) {
            showAppToast('Сұраныс қатесі: ' + err.message, 'error');
        }
    }
</script>

<?php
require __DIR__ . '/../layout/master_end.php';
?>
