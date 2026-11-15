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

        <!-- TASK-014: форма «Шақыру / Пригласить» -->
        <form id="inviteForm" class="ad-invite" onsubmit="createInvitation(event)" novalidate>
            <div class="form-row">
                <div class="field">
                    <label for="inviteEmail">Gmail *</label>
                    <input type="email" id="inviteEmail" name="email" class="input" required maxlength="191"
                           autocomplete="off" placeholder="name@gmail.com">
                </div>
                <div class="field">
                    <label for="inviteRole"><?= $lang === 'ru' ? 'Роль' : 'Рөлі' ?></label>
                    <select id="inviteRole" name="role" class="select">
                        <?php foreach ($adRoleLabels as $roleKey => $roleLabel): ?>
                            <option value="<?= $roleKey ?>" <?= $roleKey === 'viewer' ? 'selected' : '' ?>><?= htmlspecialchars($roleLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="field">
                <label for="inviteNotes"><?= $lang === 'ru' ? 'Заметка — кто это' : 'Ескертпе — бұл кім' ?></label>
                <input type="text" id="inviteNotes" name="notes" class="input" maxlength="255"
                       placeholder="<?= $lang === 'ru' ? 'например: Әкем — Ғабит' : 'мысалы: Әкем — Ғабит' ?>">
            </div>
            <div class="ad-invite-actions">
                <button type="submit" class="btn btn-primary" id="inviteSubmit">
                    <svg class="ic" aria-hidden="true"><use href="#i-mail"/></svg>
                    <span>Шақыру / Пригласить</span>
                </button>
            </div>
        </form>

        <?php
        $invStatusLabels = [
            'pending'  => $lang === 'ru' ? 'Ожидает' : 'Күтуде',
            'accepted' => $lang === 'ru' ? 'Принято' : 'Қабылданды',
            'revoked'  => $lang === 'ru' ? 'Отозвано' : 'Қайтарылды',
        ];
        ?>
        <?php if (empty($invitations)): ?>
            <div class="hm-empty ad-empty">
                <?= $lang === 'ru' ? 'Приглашений пока нет' : 'Берілген шақырулар жоқ' ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table ad-table">
                    <thead>
                        <tr>
                            <th><?= $lang === 'ru' ? 'Email / заметка' : 'Email / ескертпе' ?></th>
                            <th class="ad-w-invrole"><?= $lang === 'ru' ? 'Роль' : 'Рөлі' ?></th>
                            <th><?= $lang === 'ru' ? 'Статус' : 'Күйі' ?></th>
                            <th class="ad-w-login col-opt-md"><?= $lang === 'ru' ? 'Дата' : 'Уақыты' ?></th>
                            <th class="actions"><span class="ad-sr"><?= $lang === 'ru' ? 'Действие' : 'Әрекет' ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invitations as $inv):
                            $invId = (int)$inv['id'];
                            $invEmail = (string)($inv['email'] ?? '');
                            $invNotes = (string)($inv['notes'] ?? '');
                            $invStatus = (string)($inv['status'] ?? 'pending');
                            $invRevoked = ($invStatus === 'revoked');
                        ?>
                            <tr>
                                <td>
                                    <div class="ad-mono clip" title="<?= htmlspecialchars($invEmail) ?>"><?= htmlspecialchars($invEmail) ?></div>
                                    <?php if ($invNotes !== ''): ?>
                                        <div class="meta muted small"><?= htmlspecialchars($invNotes) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="ad-role">
                                    <?php if ($invRevoked): ?>
                                        <span class="badge"><?= htmlspecialchars($adRoleLabels[$inv['role']] ?? (string)$inv['role']) ?></span>
                                    <?php else: ?>
                                        <select id="invRoleSelect_<?= $invId ?>" class="select" aria-label="<?= $lang === 'ru' ? 'Роль' : 'Рөлі' ?>">
                                            <?php foreach ($adRoleLabels as $roleKey => $roleLabel): ?>
                                                <option value="<?= $roleKey ?>" <?= ($inv['role'] ?? '') === $roleKey ? 'selected' : '' ?>><?= htmlspecialchars($roleLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </td>
                                <td class="clip">
                                    <span class="badge <?= $invStatus === 'accepted' ? 'badge-accent' : '' ?>"><?= htmlspecialchars($invStatusLabels[$invStatus] ?? $invStatus) ?></span>
                                </td>
                                <td class="clip muted small col-opt-md">
                                    <?= !empty($inv['created_at']) ? date('d.m.Y H:i', strtotime($inv['created_at'])) : '—' ?>
                                </td>
                                <td class="actions">
                                    <?php if ($invRevoked): ?>
                                        <button type="button" class="btn btn-sm"
                                                data-email="<?= htmlspecialchars($invEmail) ?>"
                                                data-role="<?= htmlspecialchars((string)$inv['role']) ?>"
                                                data-notes="<?= htmlspecialchars($invNotes) ?>"
                                                onclick="prefillInvite(this)">
                                            <?= $lang === 'ru' ? 'Пригласить снова' : 'Қайта шақыру' ?>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm" onclick="saveInvitationRole(<?= $invId ?>)">
                                            <?= $lang === 'ru' ? 'Сменить роль' : 'Рөлді өзгерту' ?>
                                        </button>
                                        <button type="button" class="btn btn-sm" onclick="revokeInvitation(<?= $invId ?>)">
                                            <?= $lang === 'ru' ? 'Отозвать' : 'Қайтару' ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* TASK-014: форма приглашения в стиле островов админки */
    .ad-invite { margin: 0 0 var(--sp-5, 20px); display: grid; gap: var(--sp-3, 12px); }
    .ad-invite-actions { display: flex; justify-content: flex-end; }
    .ad-table td.actions { white-space: nowrap; }
    .ad-table td.actions .btn + .btn { margin-left: 6px; }
</style>

<script>
    const AD_CSRF = <?= json_encode(\App\Core\Session::csrfToken()) ?>;
    const AD_RU = <?= $lang === 'ru' ? 'true' : 'false' ?>;

    // TASK-014: общий POST для приглашений (CSRF в теле запроса)
    async function adPost(url, fields) {
        const body = new URLSearchParams();
        body.append('csrf_token', AD_CSRF);
        Object.keys(fields).forEach(k => body.append(k, fields[k]));
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
            body: body.toString()
        });
        let data = null;
        try { data = await res.json(); } catch (e) { data = null; }
        if (!res.ok || !data || !(data.success || data.status === 'success')) {
            throw new Error((data && (data.error || data.message)) || ('HTTP ' + res.status));
        }
        return data;
    }

    async function createInvitation(e) {
        e.preventDefault();
        const email = document.getElementById('inviteEmail').value.trim().toLowerCase();
        const role = document.getElementById('inviteRole').value;
        const notes = document.getElementById('inviteNotes').value.trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showAppToast(AD_RU ? 'Введите корректный e-mail' : 'Дұрыс e-mail енгізіңіз', 'error');
            document.getElementById('inviteEmail').focus();
            return;
        }
        const btn = document.getElementById('inviteSubmit');
        btn.disabled = true;
        try {
            await adPost('/api/admin/invitations/create', { email, role, notes });
            showAppToast(AD_RU ? 'Приглашение сохранено. Человек может входить через Google.' : 'Шақыру сақталды. Енді ол Google арқылы кіре алады.', 'success');
            setTimeout(() => window.location.reload(), 900);
        } catch (err) {
            showAppToast('Қате / Ошибка: ' + err.message, 'error');
            btn.disabled = false;
        }
    }

    async function saveInvitationRole(id) {
        const role = document.getElementById(`invRoleSelect_${id}`).value;
        try {
            await adPost('/api/admin/invitations/role', { id, role });
            showAppToast(AD_RU ? 'Роль изменена' : 'Рөл өзгертілді', 'success');
            setTimeout(() => window.location.reload(), 900);
        } catch (err) {
            showAppToast('Қате / Ошибка: ' + err.message, 'error');
        }
    }

    async function revokeInvitation(id) {
        const msg = AD_RU
            ? 'Отозвать приглашение? Если человек уже входил, его доступ будет закрыт (данные не удаляются).'
            : 'Шақыруды қайтару керек пе? Егер ол кірген болса, кіру құқығы жабылады (деректер өшірілмейді).';
        if (!window.confirm(msg)) return;
        try {
            await adPost('/api/admin/invitations/revoke', { id });
            showAppToast(AD_RU ? 'Приглашение отозвано' : 'Шақыру қайтарылды', 'success');
            setTimeout(() => window.location.reload(), 900);
        } catch (err) {
            showAppToast('Қате / Ошибка: ' + err.message, 'error');
        }
    }

    function prefillInvite(btn) {
        document.getElementById('inviteEmail').value = btn.dataset.email || '';
        document.getElementById('inviteRole').value = btn.dataset.role || 'viewer';
        document.getElementById('inviteNotes').value = btn.dataset.notes || '';
        document.getElementById('inviteForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
        document.getElementById('inviteEmail').focus();
    }

    async function saveUserRole(userId) {
        const select = document.getElementById(`userRoleSelect_${userId}`);
        const role = select.value;
        const formData = new URLSearchParams();
        formData.append('csrf_token', AD_CSRF);
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
