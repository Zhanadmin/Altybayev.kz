<?php
// TASK-022 (доп.): поля «телефон(ы) + email» и приглашение в окнах редактирования человека.
// Подключается внутрь формы редактирования (master_end.php и tree.php).
// Параметры (необязательные): $pcfInputCls — класс input, $pcfBtnCls / $pcfBtnPrimaryCls — классы кнопок.
declare(strict_types=1);

use App\Core\I18n;

$pcfInputCls = $pcfInputCls ?? 'input';
$pcfBtnCls = $pcfBtnCls ?? 'btn btn-ghost btn-sm';
$pcfBtnPrimaryCls = $pcfBtnPrimaryCls ?? 'btn btn-primary';
$pcfE = static fn(string $k): string => htmlspecialchars(I18n::t($k), ENT_QUOTES);
?>
<div id="pcfBox" style="display:none; margin-top:14px; padding-top:12px; border-top:1px solid rgba(128,128,128,.25);">
    <div style="font-weight:600; margin-bottom:8px;"><?= $pcfE('contacts_title') ?></div>
    <div style="margin-bottom:8px;">
        <label style="display:block; margin-bottom:4px;"><?= $pcfE('contacts_phones') ?> (+7)</label>
        <div id="pcfPhoneRows"></div>
        <button type="button" class="<?= htmlspecialchars($pcfBtnCls) ?>" id="pcfAddPhone" onclick="pcfAddPhone('')"><?= $pcfE('contacts_add_phone') ?></button>
    </div>
    <div style="margin-bottom:8px;">
        <label for="pcfEmail" style="display:block; margin-bottom:4px;"><?= $pcfE('contacts_email') ?></label>
        <input type="email" id="pcfEmail" class="<?= htmlspecialchars($pcfInputCls) ?>" maxlength="191" autocomplete="off" oninput="pcfOnEmailInput()">
    </div>
    <div style="font-size:12px; opacity:.7; margin-bottom:8px;"><?= $pcfE('contacts_hint') ?></div>
    <div id="pcfInvite" style="display:none; align-items:center; gap:8px; flex-wrap:wrap;">
        <span id="pcfInviteBadge" style="display:none; padding:2px 10px; border-radius:999px; background:rgba(128,128,128,.2); font-size:13px;"></span>
        <span id="pcfInviteForm" style="display:none; align-items:center; gap:8px; flex-wrap:wrap;">
            <label for="pcfInviteRole" style="font-size:13px;"><?= $pcfE('invite_role') ?></label>
            <select id="pcfInviteRole" class="<?= htmlspecialchars($pcfInputCls) ?>" style="width:auto;">
                <option value="viewer" selected><?= $pcfE('role_viewer') ?></option>
                <option value="editor"><?= $pcfE('role_editor') ?></option>
                <option value="admin" id="pcfRoleAdmin"><?= $pcfE('role_admin') ?></option>
            </select>
            <button type="button" class="<?= htmlspecialchars($pcfBtnPrimaryCls) ?>" id="pcfInviteBtn" onclick="pcfSendInvite()"><?= $pcfE('invite_send') ?></button>
        </span>
    </div>
</div>
<script>
(function () {
    var MAX_PHONES = <?= (int)\App\Services\PersonContactService::MAX_PHONES ?>;
    var T = {
        invited: <?= json_encode(I18n::t('invite_invited'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        accepted: <?= json_encode(I18n::t('invite_accepted'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        removePhone: <?= json_encode(I18n::t('lbl_remove_phone'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    };
    var st = { personId: 0, csrf: '', savedEmail: '', state: 'none', canInvite: false, available: false };

    function toast(msg, type) {
        if (typeof showAppToast === 'function') { showAppToast(msg, type); } else { alert(msg); }
    }

    function mask(input) {
        var d = input.value.replace(/\D+/g, '');
        if (input.value.trim().indexOf('+') === 0 && d.indexOf('7') !== 0) { return; } // иностранный номер
        if (d.indexOf('8') === 0) d = '7' + d.slice(1);
        if (d && d.indexOf('7') !== 0) d = '7' + d;
        d = d.slice(0, 11);
        var p = d.slice(1);
        var out = d ? '+7' : '';
        if (p.length > 0) out += ' (' + p.slice(0, 3);
        if (p.length >= 3) out += ')';
        if (p.length > 3) out += ' ' + p.slice(3, 6);
        if (p.length > 6) out += '-' + p.slice(6, 8);
        if (p.length > 8) out += '-' + p.slice(8, 10);
        input.value = out;
    }

    window.pcfAddPhone = function (value) {
        var rows = document.getElementById('pcfPhoneRows');
        if (!rows || rows.children.length >= MAX_PHONES) return;
        var row = document.createElement('div');
        row.className = 'pcf-phone-row';
        row.style.cssText = 'display:flex; gap:6px; margin-bottom:6px;';
        var inp = document.createElement('input');
        inp.type = 'tel'; inp.className = <?= json_encode($pcfInputCls) ?> + ' pcf-phone'; inp.inputMode = 'tel'; inp.maxLength = 20;
        inp.placeholder = '+7 (___) ___-__-__';
        inp.value = value || '';
        inp.addEventListener('input', function () { mask(inp); });
        var btn = document.createElement('button');
        btn.type = 'button'; btn.textContent = '×'; btn.setAttribute('aria-label', T.removePhone);
        btn.className = <?= json_encode($pcfBtnCls) ?>;
        btn.addEventListener('click', function () {
            if (rows.children.length > 1) { row.remove(); } else { inp.value = ''; }
        });
        row.appendChild(inp); row.appendChild(btn);
        rows.appendChild(row);
    };

    function renderInvite() {
        var box = document.getElementById('pcfInvite');
        var badge = document.getElementById('pcfInviteBadge');
        var form = document.getElementById('pcfInviteForm');
        if (!box) return;
        badge.style.display = 'none'; form.style.display = 'none';
        // статус/кнопка относятся к уже СОХРАНЁННОМУ email
        if (!st.savedEmail) { box.style.display = 'none'; return; }
        box.style.display = 'flex';
        if (st.state === 'accepted') { badge.textContent = T.accepted; badge.style.display = 'inline-block'; }
        else if (st.state === 'pending') { badge.textContent = T.invited; badge.style.display = 'inline-block'; }
        else if (st.canInvite) { form.style.display = 'inline-flex'; }
        else { box.style.display = 'none'; }
        var emailNow = (document.getElementById('pcfEmail').value || '').trim().toLowerCase();
        if (emailNow !== st.savedEmail) { form.style.display = 'none'; } // email изменён, но не сохранён
    }

    window.pcfOnEmailInput = renderInvite;

    // data — поле contacts из GET /api/person (null — контакты этому пользователю недоступны)
    window.pcfLoad = function (personId, data) {
        var box = document.getElementById('pcfBox');
        document.getElementById('pcfPhoneRows').innerHTML = '';
        st = { personId: personId, csrf: '', savedEmail: '', state: 'none', canInvite: false, available: false };
        if (!box) return;
        if (!data || !personId) { box.style.display = 'none'; return; }
        st.available = true; st.csrf = data.csrf_token || '';
        st.savedEmail = (data.email || '').toLowerCase(); st.state = data.invite_state || 'none';
        st.canInvite = !!data.can_invite;
        (data.phones && data.phones.length ? data.phones : ['']).forEach(function (p) { window.pcfAddPhone(p); });
        document.getElementById('pcfEmail').value = data.email || '';
        var adm = document.getElementById('pcfRoleAdmin');
        if (adm) { adm.hidden = !data.is_admin; adm.disabled = !data.is_admin; }
        document.getElementById('pcfInviteRole').value = 'viewer';
        box.style.display = 'block';
        renderInvite();
    };

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok || !data.success) { throw new Error(data.error || ('HTTP ' + res.status)); }
                return data;
            });
        });
    }

    // Сохранить контакты (после сохранения данных человека). Возвращает
    // Promise<{ok:boolean, offerInvite:boolean}>; ошибки показывает сама.
    window.pcfSave = function () {
        if (!st.available) { return Promise.resolve({ ok: true, offerInvite: false }); }
        var body = new URLSearchParams();
        body.append('csrf_token', st.csrf);
        body.append('person_id', st.personId);
        document.querySelectorAll('#pcfPhoneRows .pcf-phone').forEach(function (i) {
            if (i.value.trim() !== '') body.append('phones[]', i.value.trim());
        });
        body.append('email', document.getElementById('pcfEmail').value.trim());
        return post('/api/person/contacts/save', body).then(function (data) {
            st.savedEmail = (data.email || '').toLowerCase();
            st.state = data.invite_state || 'none';
            renderInvite();
            return { ok: true, offerInvite: st.savedEmail !== '' && st.state === 'none' && st.canInvite };
        }).catch(function (err) {
            toast(err.message, 'error');
            return { ok: false, offerInvite: false };
        });
    };

    window.pcfSendInvite = function () {
        var btn = document.getElementById('pcfInviteBtn');
        if (btn) btn.disabled = true;
        var body = new URLSearchParams();
        body.append('csrf_token', st.csrf);
        body.append('person_id', st.personId);
        body.append('role', document.getElementById('pcfInviteRole').value);
        post('/api/person/invite', body).then(function (data) {
            st.state = data.invite_state || 'pending';
            renderInvite();
            window.pcfDirty = true;
            toast(data.message || 'OK', 'success');
        }).catch(function (err) {
            toast(err.message, 'error');
        }).then(function () { if (btn) btn.disabled = false; });
    };
})();
</script>
