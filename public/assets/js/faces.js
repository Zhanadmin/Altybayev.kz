/* TASK-019: экран «Лица» — подписать/снять группу, убрать лицо, удалить отпечатки, переключатель. */
(function () {
    'use strict';
    var F = window.FACES || {};
    var T = F.t || {};
    function t(k) { return T[k] || k; }

    function toast(msg, kind) {
        if (typeof window.showAppToast === 'function') { window.showAppToast(msg, kind || 'info'); }
        else { alert(msg); }
    }

    function post(url, data) {
        var fd = new FormData();
        Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k] == null ? '' : data[k]); });
        fd.append('csrf_token', F.csrf || '');
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (res) {
                if (r.ok && res && res.success) { return res; }
                var err = new Error((res && (res.error || res.message)) || (t('error') + ' ' + r.status));
                err.status = r.status;
                throw err;
            });
        });
    }

    var nameToId = {};
    (F.people || []).forEach(function (p) { nameToId[p.name] = p.id; });

    var assignModal = document.getElementById('faceAssignModal');
    var assignInput = document.getElementById('faceAssignInput');
    var assignClusterInput = document.getElementById('faceAssignCluster');
    var currentCluster = 0;

    function openAssign(clusterId) {
        currentCluster = parseInt(clusterId, 10) || 0;
        if (assignClusterInput) { assignClusterInput.value = String(currentCluster); }
        if (assignInput) { assignInput.value = ''; }
        if (assignModal) { assignModal.style.display = 'flex'; }
        if (assignInput) { setTimeout(function () { assignInput.focus(); }, 0); }
    }
    function closeAssign() {
        if (assignModal) { assignModal.style.display = 'none'; }
    }
    function saveAssign() {
        var name = assignInput ? assignInput.value.trim() : '';
        var pid = nameToId[name];
        if (!pid) { toast(t('person_not_found')); return; }
        post('/api/face/cluster/assign', { cluster_id: currentCluster, person_id: pid }).then(function () {
            toast(t('saved'));
            closeAssign();
            setTimeout(function () { location.reload(); }, 400);
        }).catch(function (e) { toast(e.message, 'error'); });
    }

    function reloadSoon() { setTimeout(function () { location.reload(); }, 400); }

    function bind() {
        document.querySelectorAll('.faces-card').forEach(function (card) {
            card.querySelectorAll('button[data-act]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var act = btn.getAttribute('data-act');
                    var clusterId = card.getAttribute('data-cluster');
                    var faceId = card.getAttribute('data-face');
                    var personId = card.getAttribute('data-person');
                    if (act === 'assign') { openAssign(clusterId); return; }
                    if (act === 'remove') {
                        post('/api/face/remove', { face_id: faceId }).then(function () { toast(t('removed')); reloadSoon(); }).catch(function (e) { toast(e.message, 'error'); });
                    } else if (act === 'unassign') {
                        post('/api/face/cluster/unassign', { cluster_id: clusterId }).then(function () { reloadSoon(); }).catch(function (e) { toast(e.message, 'error'); });
                    } else if (act === 'delete-person') {
                        appConfirm(t('confirm_delete'), { danger: true }).then(function (yes) {
                            if (!yes) { return; }
                            post('/api/face/delete-person', { person_id: personId }).then(function () { toast(t('removed')); reloadSoon(); }).catch(function (e) { toast(e.message, 'error'); });
                        });
                    }
                });
            });
        });

        var save = document.getElementById('faceAssignSave');
        if (save) { save.addEventListener('click', saveAssign); }
        if (assignInput) {
            assignInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); saveAssign(); } });
        }
        if (assignModal) {
            assignModal.querySelectorAll('[data-close-modal]').forEach(function (b) { b.addEventListener('click', closeAssign); });
            assignModal.addEventListener('click', function (e) { if (e.target === assignModal) { closeAssign(); } });
        }

        var toggle = document.getElementById('faceEnabled');
        if (toggle) {
            toggle.addEventListener('change', function () {
                post('/api/face/settings', { enabled: toggle.checked ? '1' : '0' }).then(function () {
                    toast(t('saved'));
                }).catch(function (e) { toast(e.message, 'error'); toggle.checked = !toggle.checked; });
            });
        }
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', bind); }
    else { bind(); }
})();
