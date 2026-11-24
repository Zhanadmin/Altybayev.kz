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
                <span><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Импорт GEDCOM из MyHeritage' : 'GEDCOM импорты (MyHeritage)' ?></span>
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
            <div class="label"><svg class="ic" aria-hidden="true"><use href="#i-history"/></svg><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Записей аудита:' : 'Аудит жазбалары:' ?></div>
            <div class="value"><?= (int)$auditCount ?></div>
        </div>
        <div class="island stat">
            <div class="label"><svg class="ic" aria-hidden="true"><use href="#i-media"/></svg><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Медиа и аудио:' : 'Медиа файлдар:' ?></div>
            <div class="value"><?= (int)$mediaCount ?></div>
        </div>
    </div>

    <!-- TASK-031: единый раздел «Доступ» — пользователи и приглашения -->
    <?php
    $acStatusLabels = [
        'invited'  => ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Приглашён' : 'Шақырылды',
        'joined'   => ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Вошёл' : 'Кірді',
        'disabled' => ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Отключён' : 'Өшірулі',
    ];
    $superEmail = strtolower(trim((string)\App\Core\Config::get('SUPER_ADMIN_EMAIL', 'zhandos.alt06@gmail.com')));
    $meEmail = strtolower((string)($user['email'] ?? ''));
    ?>
    <div class="island ad-island" id="access">
        <div class="island-head ad-head">
            <svg class="ic" aria-hidden="true"><use href="#i-users"/></svg>
            <h3><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Доступ' : 'Қолжетімділік' ?></h3>
            <div class="ad-head-actions">
                <button type="button" class="btn btn-primary" id="inviteToggle" onclick="toggleInviteForm(true)">
                    <svg class="ic" aria-hidden="true"><use href="#i-mail"/></svg>
                    <span><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Пригласить' : 'Шақыру' ?></span>
                </button>
            </div>
        </div>

        <!-- Форма «Пригласить» (email, роль, карточка) -->
        <form id="inviteForm" class="ad-invite" onsubmit="createInvitation(event)" novalidate hidden>
            <div class="form-row">
                <div class="field">
                    <label for="inviteEmail">Gmail *</label>
                    <input type="email" id="inviteEmail" name="email" class="input" required maxlength="191"
                           autocomplete="off" placeholder="name@gmail.com">
                </div>
                <div class="field">
                    <label for="inviteRole"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Роль' : 'Рөлі' ?></label>
                    <select id="inviteRole" name="role" class="select">
                        <?php foreach ($adRoleLabels as $roleKey => $roleLabel): ?>
                            <option value="<?= $roleKey ?>" <?= $roleKey === 'viewer' ? 'selected' : '' ?>><?= htmlspecialchars($roleLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="invitePerson"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Карточка в шежіре (№)' : 'Шежіредегі карточка (№)' ?></label>
                    <input type="number" id="invitePerson" name="person_id" class="input" min="1" step="1" inputmode="numeric"
                           placeholder="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'необязательно' : 'міндетті емес' ?>">
                </div>
            </div>
            <div class="field">
                <label for="inviteNotes"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Заметка — кто это' : 'Ескертпе — бұл кім' ?></label>
                <input type="text" id="inviteNotes" name="notes" class="input" maxlength="255"
                       placeholder="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'например: Әкем — Ғабит' : 'мысалы: Әкем — Ғабит' ?>">
            </div>
            <div class="ad-invite-actions">
                <button type="button" class="btn" onclick="toggleInviteForm(false)"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Отмена' : 'Бас тарту' ?></button>
                <button type="submit" class="btn btn-primary" id="inviteSubmit">
                    <svg class="ic" aria-hidden="true"><use href="#i-mail"/></svg>
                    <span>Шақыру / Пригласить</span>
                </button>
            </div>
        </form>

        <?php if (empty($accessRows)): ?>
            <div class="hm-empty ad-empty"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Пользователей и приглашений пока нет' : 'Пайдаланушылар мен шақырулар жоқ' ?></div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table ad-table">
                <thead>
                    <tr>
                        <th><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Имя / Email' : 'Аты / Email' ?></th>
                        <th><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Статус' : 'Күйі' ?></th>
                        <th class="ad-w-role"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Роль' : 'Рөлі' ?></th>
                        <th class="ad-w-person col-opt-lg"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Карточка в шежіре' : 'Шежіредегі карточка' ?></th>
                        <th class="ad-w-login col-opt-md"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Последний вход' : 'Соңғы кіруі' ?></th>
                        <th class="actions"><span class="ad-sr"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Действия' : 'Әрекеттер' ?></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accessRows as $r):
                        $rEmail = (string)$r['email'];
                        $rName = (string)$r['name'];
                        $isUser = $r['kind'] === 'user';
                        $isProtected = (strtolower($rEmail) === $superEmail) || (strtolower($rEmail) === $meEmail);
                        $rid = $isUser ? 'u' . (int)$r['user_id'] : 'i' . (int)$r['invitation_id'];
                        $rInitial = mb_substr($rName !== '' ? $rName : $rEmail, 0, 1);
                        $rStatus = (string)$r['status'];
                    ?>
                        <tr>
                            <td>
                                <div class="person">
                                    <span class="avatar" aria-hidden="true"><?= htmlspecialchars($rInitial) ?></span>
                                    <div>
                                        <?php if ($rName !== ''): ?>
                                            <div class="name" title="<?= htmlspecialchars($rName) ?>"><?= htmlspecialchars($rName) ?></div>
                                        <?php endif; ?>
                                        <div class="meta ad-mono" title="<?= htmlspecialchars($rEmail) ?>"><?= htmlspecialchars($rEmail) ?></div>
                                        <?php if ((string)$r['notes'] !== ''): ?>
                                            <div class="meta muted small"><?= htmlspecialchars((string)$r['notes']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="clip">
                                <span class="badge <?= $rStatus === 'joined' ? 'badge-accent' : '' ?>"><?= htmlspecialchars($acStatusLabels[$rStatus] ?? $rStatus) ?></span>
                            </td>
                            <td class="ad-role">
                                <?php if ($isProtected): ?>
                                    <span class="badge"><?= htmlspecialchars($adRoleLabels[$r['role']] ?? (string)$r['role']) ?></span>
                                <?php else: ?>
                                    <select id="roleSelect_<?= $rid ?>" class="select" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Роль' : 'Рөлі' ?>">
                                        <?php foreach ($adRoleLabels as $roleKey => $roleLabel): ?>
                                            <option value="<?= $roleKey ?>" <?= $r['role'] === $roleKey ? 'selected' : '' ?>><?= htmlspecialchars($roleLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </td>
                            <td class="clip col-opt-lg">
                                <?php if ((int)$r['person_id'] > 0):
                                    $pName = trim((string)$r['person_name']);
                                ?>
                                    <a href="/person/<?= (int)$r['person_id'] ?>" class="pp-name"
                                       title="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Карточка в шежіре' : 'Шежіредегі карточка' ?> #<?= (int)$r['person_id'] ?>"><?= htmlspecialchars($pName !== '' ? $pName : ('#' . (int)$r['person_id'])) ?></a>
                                    <?php if ((int)$r['person_shared'] > 1): ?>
                                        <div class="small ad-warn" role="note"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? '⚠ Эта карточка привязана к нескольким пользователям' : '⚠ Бұл карточка бірнеше пайдаланушыға байланған' ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="clip muted small col-opt-md">
                                <?= !empty($r['last_login_at']) ? date('d.m.Y H:i', strtotime((string)$r['last_login_at'])) : '—' ?>
                            </td>
                            <td class="actions">
                                <div class="ad-actions">
                                <?php if (!$isProtected): ?>
                                    <button type="button" class="btn btn-sm" onclick="saveAccessRole('<?= $rid ?>')">
                                        <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Сменить роль' : 'Рөлді өзгерту' ?>
                                    </button>
                                    <?php if ($isUser && (int)$r['person_id'] > 0): ?>
                                        <button type="button" class="btn btn-sm" onclick="unlinkUser(<?= (int)$r['user_id'] ?>)">
                                            <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Отвязать' : 'Ажырату' ?>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($rStatus !== 'disabled' && (int)$r['invitation_id'] > 0): ?>
                                        <button type="button" class="btn btn-sm" onclick="revokeInvitation(<?= (int)$r['invitation_id'] ?>)">
                                            <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Отключить' : 'Бұғаттау' ?>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($rStatus === 'invited'): ?>
                                        <button type="button" class="btn btn-sm" onclick="copyInviteText(this)"
                                                data-email="<?= htmlspecialchars($rEmail) ?>">
                                            <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Повторно отправить' : 'Қайта жіберу' ?>
                                        </button>
                                    <?php elseif ($rStatus === 'disabled'): ?>
                                        <button type="button" class="btn btn-sm"
                                                data-email="<?= htmlspecialchars($rEmail) ?>"
                                                data-role="<?= htmlspecialchars((string)$r['role']) ?>"
                                                data-notes="<?= htmlspecialchars((string)$r['notes']) ?>"
                                                data-person="<?= (int)$r['person_id'] ?>"
                                                onclick="prefillInvite(this)">
                                            <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Пригласить снова' : 'Қайта шақыру' ?>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($isUser): ?>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteUser(<?= (int)$r['user_id'] ?>)">
                                            <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Удалить' : 'Жою' ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- TASK-027: контакты владельца и обращения с приветственной страницы -->
    <?php $adRu = ($lang === 'ru' || $lang === 'en' || $lang === 'es'); ?>
    <div class="island ad-island" id="feedback">
        <div class="island-head ad-head">
            <h3><?= $adRu ? 'Обратная связь и контакты' : 'Кері байланыс және байланыс деректері' ?>
                <?php if (!empty($feedbackUnread)): ?><span class="badge"><?= (int)$feedbackUnread ?></span><?php endif; ?></h3>
        </div>
        <p class="muted" style="margin:0 0 12px;font-size:14px;">
            <?= $adRu
                ? 'Эти контакты показываются на приветственной странице (кнопки WhatsApp и «Написать на почту»). Обращения из формы приходят в список ниже'
                    . (!empty($smtpReady) ? ' и на указанную почту.' : '; письмо на почту будет отправляться, когда в окружении задан SMTP (сейчас не задан).')
                : 'Бұл байланыстар сәлемдесу бетінде көрсетіледі. Өтініштер төмендегі тізімге түседі.' ?>
        </p>
        <form id="contactsForm" class="ad-invite" style="display:grid;gap:12px;max-width:520px;" onsubmit="return saveContacts(event)">
            <label><?= $adRu ? 'Имя владельца' : 'Иесінің аты' ?>
                <input class="input" name="name" maxlength="120" value="<?= htmlspecialchars((string)($contacts['name'] ?? '')) ?>">
            </label>
            <label>E-mail
                <input class="input" type="email" name="email" maxlength="190" value="<?= htmlspecialchars((string)($contacts['email'] ?? '')) ?>">
            </label>
            <label>WhatsApp (<?= $adRu ? 'номер с кодом страны' : 'ел кодымен нөмір' ?>)
                <input class="input" name="whatsapp" maxlength="25" placeholder="+7 777 000 00 00" value="<?= htmlspecialchars((string)($contacts['whatsapp'] ?? '')) ?>">
            </label>
            <div><button type="submit" class="btn btn-primary"><?= $adRu ? 'Сохранить контакты' : 'Сақтау' ?></button>
                <span id="contactsMsg" class="small muted" role="status"></span></div>
        </form>

        <div class="ad-head" style="margin-top:16px;">
            <strong><?= $adRu ? 'Обращения' : 'Өтініштер' ?></strong>
            <?php if (!empty($feedbackUnread)): ?>
                <button type="button" class="btn btn-ghost ad-head-actions" onclick="markFeedbackRead(0, 1)"><?= $adRu ? 'Отметить все прочитанными' : 'Барлығын оқылған деп белгілеу' ?></button>
            <?php endif; ?>
        </div>
        <?php if (empty($feedbackRows)): ?>
            <p class="muted" style="margin:8px 0 0;"><?= $adRu ? 'Пока обращений нет.' : 'Әзірге өтініш жоқ.' ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th><?= $adRu ? 'Дата' : 'Күні' ?></th><th><?= $adRu ? 'Имя' : 'Аты' ?></th>
                        <th><?= $adRu ? 'Контакт' : 'Байланыс' ?></th><th><?= $adRu ? 'Сообщение' : 'Хабарлама' ?></th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($feedbackRows as $fb): ?>
                        <tr<?= (int)$fb['is_read'] === 0 ? ' style="font-weight:600"' : '' ?> data-fb="<?= (int)$fb['id'] ?>">
                            <td><?= htmlspecialchars((string)$fb['created_at']) ?></td>
                            <td><?= htmlspecialchars((string)$fb['name']) ?></td>
                            <td><?= htmlspecialchars((string)$fb['contact']) ?></td>
                            <td style="white-space:pre-wrap;max-width:360px;"><?= htmlspecialchars((string)$fb['message']) ?></td>
                            <td class="actions">
                                <?php if ((int)$fb['is_read'] === 0): ?>
                                    <button type="button" class="btn btn-ghost" onclick="markFeedbackRead(<?= (int)$fb['id'] ?>, 1)"><?= $adRu ? 'Прочитано' : 'Оқылды' ?></button>
                                <?php else: ?>
                                    <span class="small muted"><?= $adRu ? 'прочитано' : 'оқылған' ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- TASK-032: добавления и правки обычных родственников -->
    <div class="island ad-island" id="additions">
        <div class="island-head ad-head">
            <h3><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Новое от родственников' : 'Туыстардан жаңалар' ?></h3>
        </div>
        <?php if (empty($recentAdditions)): ?>
            <p class="muted" style="margin:0 0 12px;"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Пока ничего не добавляли.' : 'Әзірге ештеңе қосылған жоқ.' ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Когда' : 'Уақыты' ?></th>
                        <th><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Кто' : 'Кім' ?></th>
                        <th><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Что' : 'Не' ?></th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($recentAdditions as $ra): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$ra['created_at']) ?></td>
                            <td><?= htmlspecialchars((string)$ra['who']) ?></td>
                            <td><?= htmlspecialchars((string)$ra['action']) ?> · <?= htmlspecialchars((string)$ra['summary']) ?></td>
                            <td><a href="/history?table=<?= urlencode((string)$ra['entity_table']) ?>"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'История / откат' : 'Тарих / қайтару' ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- ALT-018: корзина — удалённые люди, админ может восстановить -->
    <div class="island ad-island" id="trash">
        <div class="island-head ad-head">
            <h3><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Корзина (удалённые люди)' : 'Себет (жойылған адамдар)' ?></h3>
        </div>
        <p class="muted" id="trashEmpty" style="margin:0 0 12px;"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Загрузка…' : 'Жүктелуде…' ?></p>
        <div class="table-wrap">
            <table class="table"><tbody id="trashBody"></tbody></table>
        </div>
    </div>
    <script>
    (function () { // ALT-018
        const ru = <?= json_encode($lang === 'ru' || $lang === 'en' || $lang === 'es') ?>;
        const csrf = <?= json_encode(\App\Core\Session::csrfToken()) ?>;
        const body = document.getElementById('trashBody');
        const empty = document.getElementById('trashEmpty');
        async function load() {
            body.textContent = '';
            try {
                const res = await fetch('/api/admin/persons/trash');
                const data = await res.json();
                const items = data.items || [];
                empty.textContent = items.length ? '' : (ru ? 'Корзина пуста.' : 'Себет бос.');
                items.forEach(function (it) {
                    const tr = document.createElement('tr');
                    const td1 = document.createElement('td');
                    td1.textContent = ((it.last_name || '') + ' ' + (it.first_name || '')).trim() + ' (#' + it.id + ')';
                    const td2 = document.createElement('td');
                    td2.textContent = it.updated_at || '';
                    const td3 = document.createElement('td');
                    const b = document.createElement('button');
                    b.type = 'button'; b.className = 'btn';
                    b.textContent = ru ? 'Восстановить' : 'Қалпына келтіру';
                    b.addEventListener('click', async function () {
                        b.disabled = true;
                        const r = await fetch('/api/admin/person/restore', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ id: String(it.id), csrf_token: csrf }).toString()
                        });
                        const d = await r.json().catch(function () { return {}; });
                        if (d.status === 'success') { load(); } else { b.disabled = false; alert(d.error || 'Error'); }
                    });
                    td3.appendChild(b);
                    tr.append(td1, td2, td3);
                    body.appendChild(tr);
                });
            } catch (e) { empty.textContent = (ru ? 'Ошибка загрузки' : 'Жүктеу қатесі'); }
        }
        load();
    })();
    </script>

    <!-- TASK-019: Управление распознаванием лиц (приватность и включение) -->
    <div class="island ad-island">
        <div class="island-head ad-head">
            <svg class="ic" aria-hidden="true"><use href="#i-face"/></svg>
            <h3><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Распознавание лиц в фотоальбомах' : 'Фотоальбомдардағы бет тану жүйесі' ?></h3>
        </div>
        <div style="padding: 4px 0 12px; display: flex; flex-direction: column; gap: 12px;">
            <p class="muted" style="margin: 0; font-size: 14px;">
                <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es')
                    ? 'Распознавание работает исключительно в браузере (без внешних облаков). Сервер хранит только анонимные координаты рамок и векторные отпечатки. Эталон лица каждого человека — его аватарка.'
                    : 'Бет тану тек браузерде жұмыс істейді (сыртқы бұлтсыз). Сервер тек жақтау координаталары мен векторлық бейнелерді сақтайды. Әр адамның бет эталоны — оның аватары.' ?>
            </p>
            <?php if (!$faceRecognitionReady): ?>
                <div style="padding: 8px 12px; background: #fef3c7; color: #92400e; border-radius: 8px; font-size: 13px;">
                    <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Таблицы распознавания лиц ещё не созданы (нужно применить миграцию database/migrate.php).' : 'Бет тану кестелері әлі жасалмаған (database/migrate.php миграциясын орындау қажет).' ?>
                </div>
            <?php else: ?>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: var(--c-island-2, #f9fafb); border-radius: 8px; border: 1px solid var(--c-border, #e5e7eb);">
                    <div>
                        <strong><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Статус распознавания лиц для всего сайта' : 'Бүкіл сайт үшін бет тану күйі' ?></strong>
                        <div class="small muted"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? ($faceRecognitionEnabled ? 'Включено: фото в альбомах проверяются, лица сопоставляются с аватарками' : 'Выключено: фото не сканируются') : ($faceRecognitionEnabled ? 'Қосулы: альбомдардағы фотолар тексеріледі' : 'Өшірулі: фотолар тексерілмейді') ?></div>
                    </div>
                    <div>
                        <button type="button" class="btn <?= $faceRecognitionEnabled ? 'btn-danger' : 'btn-primary' ?>" onclick="toggleFaceRecognition(<?= $faceRecognitionEnabled ? 'false' : 'true' ?>)">
                            <?= $faceRecognitionEnabled ? (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Выключить' : 'Өшіру') : (($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Включить' : 'Қосу') ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    /* TASK-014/031: раздел «Доступ» */
    .ad-warn { color: var(--danger, #b42318); white-space: normal; }
    .ad-head { display: flex; align-items: center; gap: var(--sp-3, 12px); flex-wrap: wrap; }
    .ad-head h3 { margin: 0; }
    .ad-head-actions { margin-left: auto; }
    .ad-invite { margin: 0 0 var(--sp-5, 20px); display: grid; gap: var(--sp-3, 12px); }
    .ad-invite[hidden] { display: none; }
    .ad-invite-actions { display: flex; justify-content: flex-end; gap: 8px; }
    .ad-table td.actions { white-space: nowrap; }
    /* кнопки строки — всегда в одну линию, при нехватке места прокручивается таблица, а не ломаются кнопки */
    .ad-actions { display: flex; flex-wrap: nowrap; gap: 6px; justify-content: flex-end; align-items: center; }
    .ad-actions .btn { white-space: nowrap; flex: 0 0 auto; }
</style>

<script>
    const AD_CSRF = <?= json_encode(\App\Core\Session::csrfToken()) ?>;
    const AD_RU = <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'true' : 'false' ?>;

    // общий POST для действий админки (CSRF в теле запроса)
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

    // TASK-027: контакты владельца и отметка «прочитано»
    async function saveContacts(ev) {
        ev.preventDefault();
        const f = document.getElementById('contactsForm');
        const msg = document.getElementById('contactsMsg');
        try {
            const fd = new FormData(f);
            await adPost('/api/admin/contacts/save', {
                name: fd.get('name'), email: fd.get('email'), whatsapp: fd.get('whatsapp')
            });
            msg.textContent = AD_RU ? ' Сохранено.' : ' Сақталды.';
        } catch (e) {
            msg.textContent = ' ' + e.message;
        }
        return false;
    }
    async function markFeedbackRead(id, read) {
        try {
            await adPost('/api/admin/feedback/read', { id: id, read: read });
            location.reload();
        } catch (e) { alert(e.message); }
    }

    function toggleInviteForm(show) {
        const f = document.getElementById('inviteForm');
        f.hidden = !show;
        if (show) {
            f.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            document.getElementById('inviteEmail').focus();
        }
    }

    async function createInvitation(e) {
        e.preventDefault();
        const email = document.getElementById('inviteEmail').value.trim().toLowerCase();
        const role = document.getElementById('inviteRole').value;
        const notes = document.getElementById('inviteNotes').value.trim();
        const person_id = document.getElementById('invitePerson').value.trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showAppToast(AD_RU ? 'Введите корректный e-mail' : 'Дұрыс e-mail енгізіңіз', 'error');
            document.getElementById('inviteEmail').focus();
            return;
        }
        const btn = document.getElementById('inviteSubmit');
        btn.disabled = true;
        try {
            await adPost('/api/admin/invitations/create', { email, role, notes, person_id });
            showAppToast(AD_RU ? 'Приглашение сохранено. Человек может входить через Google.' : 'Шақыру сақталды. Енді ол Google арқылы кіре алады.', 'success');
            setTimeout(() => window.location.reload(), 900);
        } catch (err) {
            showAppToast('Қате / Ошибка: ' + err.message, 'error');
            btn.disabled = false;
        }
    }

    // Смена роли: строка пользователя (u<id>) или приглашения без входа (i<id>)
    async function saveAccessRole(rid) {
        const sel = document.getElementById('roleSelect_' + rid);
        if (!sel) return;
        const role = sel.value;
        const id = parseInt(rid.slice(1), 10);
        try {
            if (rid.charAt(0) === 'u') {
                await adPost('/api/admin/user/role', { user_id: id, role });
            } else {
                await adPost('/api/admin/invitations/role', { id, role });
            }
            showAppToast(AD_RU ? 'Роль изменена' : 'Рөл өзгертілді', 'success');
            setTimeout(() => window.location.reload(), 900);
        } catch (err) {
            showAppToast('Қате / Ошибка: ' + err.message, 'error');
        }
    }

    async function revokeInvitation(id) {
        const msg = AD_RU
            ? 'Отключить доступ? Если человек уже входил, его аккаунт будет заблокирован (данные не удаляются). Потом можно пригласить снова.'
            : 'Қолжетімділікті өшіру керек пе? Егер ол кірген болса, аккаунты бұғатталады (деректер өшірілмейді).';
        if (!window.confirm(msg)) return;
        try {
            await adPost('/api/admin/invitations/revoke', { id });
            showAppToast(AD_RU ? 'Доступ отключён' : 'Қолжетімділік өшірілді', 'success');
            setTimeout(() => window.location.reload(), 900);
        } catch (err) {
            showAppToast('Қате / Ошибка: ' + err.message, 'error');
        }
    }

    // TASK-024: отвязка аккаунта / удаление пользователя (люди в дереве не удаляются)
    async function unlinkUser(id) {
        const msg = AD_RU
            ? 'Отвязать аккаунт? Сбросятся привязка к карточке и Google-ID; роль и правки сохранятся. Человек сможет войти заново.'
            : 'Аккаунтты ажырату керек пе? Карточкаға байлану мен Google-ID тазаланады; рөл мен өзгерістер сақталады.';
        if (!window.confirm(msg)) return;
        try {
            await adPost('/api/admin/user/unlink', { user_id: id });
            showAppToast(AD_RU ? 'Аккаунт отвязан' : 'Аккаунт ажыратылды', 'success');
            setTimeout(() => window.location.reload(), 900);
        } catch (err) {
            showAppToast('Қате / Ошибка: ' + err.message, 'error');
        }
    }

    async function deleteUser(id) {
        const msg = AD_RU
            ? 'Удалить пользователя? Запись входа будет удалена, люди в дереве и их данные останутся. При новом входе через Google будет создан новый ID.'
            : 'Пайдаланушыны өшіру керек пе? Кіру жазбасы өшеді, шежіредегі адамдар сақталады.';
        if (!window.confirm(msg)) return;
        try {
            await adPost('/api/admin/user/delete', { user_id: id });
            showAppToast(AD_RU ? 'Пользователь удалён' : 'Пайдаланушы өшірілді', 'success');
            setTimeout(() => window.location.reload(), 900);
        } catch (err) {
            showAppToast('Қате / Ошибка: ' + err.message, 'error');
        }
    }

    // Отключённого — заново пригласить: открыть форму с его данными
    function prefillInvite(btn) {
        toggleInviteForm(true);
        document.getElementById('inviteEmail').value = btn.dataset.email || '';
        document.getElementById('inviteRole').value = btn.dataset.role || 'viewer';
        document.getElementById('inviteNotes').value = btn.dataset.notes || '';
        const p = parseInt(btn.dataset.person || '0', 10);
        document.getElementById('invitePerson').value = p > 0 ? String(p) : '';
    }

    // «Повторно отправить»: письма с сайта не уходят — копируем текст приглашения, чтобы переслать вручную
    async function copyInviteText(btn) {
        const url = window.location.origin + '/login';
        const text = AD_RU
            ? 'Вас пригласили в семейное шежіре. Войдите через Google (' + btn.dataset.email + '): ' + url
            : 'Сізді шежіреге шақырды. Google (' + btn.dataset.email + ') арқылы кіріңіз: ' + url;
        try {
            await navigator.clipboard.writeText(text);
            showAppToast(AD_RU ? 'Текст приглашения скопирован — отправьте его человеку' : 'Шақыру мәтіні көшірілді — адамға жіберіңіз', 'success');
        } catch (e) {
            window.prompt(AD_RU ? 'Скопируйте текст приглашения:' : 'Шақыру мәтінін көшіріңіз:', text);
        }
    }

    async function toggleFaceRecognition(enable) {
        try {
            const res = await fetch('/api/face/settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ csrf_token: AD_CSRF, enabled: enable })
            });
            const data = await res.json();
            if (res.ok && data && (data.success || data.status === 'success')) {
                showAppToast(AD_RU ? 'Настройки распознавания лиц сохранены' : 'Бет тану баптаулары сақталды', 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showAppToast('Қате / Ошибка: ' + ((data && data.error) || 'Ошибка сохранения'), 'error');
            }
        } catch (e) {
            showAppToast('Қате / Ошибка: ' + e.message, 'error');
        }
    }
</script>

<?php
require __DIR__ . '/../layout/master_end.php';
?>
