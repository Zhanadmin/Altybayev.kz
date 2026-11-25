/* ALT-107: «Что не заполнено» — пустые поля заполняются прямо в таблице, без перехода и перезагрузки.
 * Даты, ру, пол, национальность, девичья фамилия — POST /api/admin/missing/save;
 * отец / мать — поиск /api/person/parent-search и /api/person/add-parent;
 * фото — обрезка овалом из person-media-edit.js (pmeAvatarFile) и /api/person/avatar/upload.
 * После каждого сохранения сервер присылает свежую строку (или removed=true), счётчики и подпись.
 */
(function () {
    'use strict';

    var wrap = document.getElementById('ms-wrap');
    if (!wrap) { return; }
    var csrf = wrap.dataset.csrf || '';
    var gapFilter = wrap.dataset.gapFilter || '';
    var genFilter = wrap.dataset.genFilter || '';
    var tbody = wrap.querySelector('.ms-table tbody');
    var photoPerson = 0;
    var searchTimer = 0;

    function personOf(el) {
        var tr = el.closest('tr[data-person]');
        return tr ? parseInt(tr.dataset.person, 10) : 0;
    }
    function toast(msg, type) {
        if (typeof window.showAppToast === 'function') { window.showAppToast(msg, type); }
    }
    function say(cell, text, kind) {
        var m = cell && cell.querySelector('.ms-msg');
        if (!m) { return; }
        m.textContent = text || '';
        m.className = 'ms-msg' + (kind ? ' ' + kind : '');
    }
    function fail(el, text) {
        el.classList.add('err');
        say(el.closest('.ms-cell'), text || wrap.dataset.msgError, 'err');
    }
    function readJson(res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
            if (!res.ok || data.success === false || data.error) {
                throw new Error(data.error || ('HTTP ' + res.status));
            }
            return data;
        });
    }
    function post(url, params) {
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        Object.keys(params).forEach(function (k) { fd.append(k, params[k]); });
        return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(readJson);
    }
    function filterQuery() {
        return 'gap_filter=' + encodeURIComponent(gapFilter) + '&gen_filter=' + encodeURIComponent(genFilter);
    }

    // ---- ответ сервера: строка, счётчики, подпись
    function apply(d, keepFocus) {
        var counts = d.counts || {};
        wrap.querySelectorAll('.ms-counter[data-gap]').forEach(function (a) {
            var k = a.dataset.gap, b = a.querySelector('b');
            if (b && k && counts[k] !== undefined) { b.textContent = String(counts[k]); }
        });
        var total = document.getElementById('ms-total');
        if (total) { total.textContent = String(d.totalGaps); }
        var sub = document.getElementById('ms-sub');
        if (sub && d.sub) { sub.textContent = d.sub; }

        var row = tbody.querySelector('tr[data-person="' + d.person_id + '"]');
        if (row) {
            if (d.removed || !d.html) {
                row.parentNode.removeChild(row);
            } else {
                var tmp = document.createElement('tbody');
                tmp.innerHTML = d.html;
                var fresh = tmp.firstElementChild;
                if (fresh) {
                    tbody.replaceChild(fresh, row);
                    if (keepFocus) {
                        var f = fresh.querySelector('.ms-in');
                        if (f) { f.focus(); }
                    }
                }
            }
        }
        var left = tbody.querySelectorAll('tr[data-person]').length;
        document.getElementById('ms-empty').hidden = left > 0;
        document.getElementById('ms-table-box').hidden = left === 0;
    }

    function refresh(personId, keepFocus) {
        return fetch('/api/admin/missing/state?person_id=' + personId + '&' + filterQuery(), { credentials: 'same-origin' })
            .then(readJson)
            .then(function (d) { apply(d, keepFocus); });
    }

    // ---- простые поля
    function save(el, fromEnter) {
        var value = el.value.trim();
        if (value === '' || el.dataset.busy === '1') { return; }
        var personId = personOf(el);
        var params = { person_id: personId, gap: el.dataset.save, value: value, gap_filter: gapFilter, gen_filter: genFilter };
        if (el.dataset.marriage) { params.marriage_id = el.dataset.marriage; }
        el.dataset.busy = '1';
        el.classList.remove('err');
        el.classList.add('busy');
        say(el.closest('.ms-cell'), '', '');
        post('/api/admin/missing/save', params).then(function (d) {
            apply(d, !!fromEnter);
            toast(wrap.dataset.msgSaved, 'success');
        }).catch(function (err) {
            el.classList.remove('busy');
            fail(el, err.message);
        }).then(function () { el.dataset.busy = ''; });
    }

    wrap.addEventListener('change', function (ev) {
        var el = ev.target;
        if (el.matches && el.matches('[data-save]')) { save(el); }
        if (el.matches && el.matches('input[data-photo-file]')) { pickPhoto(el); }
    });
    wrap.addEventListener('keydown', function (ev) {
        var el = ev.target;
        if (ev.key === 'Enter' && el.matches && el.matches('input[data-save]')) {
            ev.preventDefault();
            save(el, true);
        }
        if (el.matches && el.matches('input[data-parent]')) {
            if (ev.key === 'Escape') { closeList(el); }
            if (ev.key === 'ArrowDown') {
                var first = listOf(el).querySelector('li[role="option"]');
                if (first) { ev.preventDefault(); first.focus(); }
            }
        } else if (el.matches && el.matches('li[role="option"]')) {
            var nxt = ev.key === 'ArrowDown' ? el.nextElementSibling : (ev.key === 'ArrowUp' ? el.previousElementSibling : null);
            if (nxt) { ev.preventDefault(); nxt.focus(); }
        }
    });

    // ---- отец / мать: поиск по вводу
    function listOf(input) { return document.getElementById(input.id + '-list'); }
    function closeList(input) {
        var ul = listOf(input);
        if (ul) { ul.hidden = true; ul.innerHTML = ''; }
        input.setAttribute('aria-expanded', 'false');
    }
    function showResults(input, results) {
        var ul = listOf(input);
        ul.innerHTML = '';
        if (!results.length) {
            var li0 = document.createElement('li');
            li0.className = 'none';
            li0.textContent = wrap.dataset.msgNobody;
            ul.appendChild(li0);
        }
        results.forEach(function (r) {
            var li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.tabIndex = 0;
            li.textContent = r.name || ('#' + r.id);
            var small = document.createElement('small');
            small.textContent = r.years || '';
            li.appendChild(small);
            var choose = function () { addParent(input, r.id); };
            li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(); });
            li.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); choose(); } });
            ul.appendChild(li);
        });
        ul.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }
    function search(input) {
        var url = '/api/person/parent-search?target_id=' + personOf(input) + '&kind=' + encodeURIComponent(input.dataset.parent)
            + '&q=' + encodeURIComponent(input.value.trim());
        var seq = (input._seq = (input._seq || 0) + 1);
        fetch(url, { credentials: 'same-origin' }).then(readJson).then(function (d) {
            // устаревший ответ, строка уже перерисована или поле потеряло фокус — список не открываем
            if (seq !== input._seq || !input.isConnected || !(document.activeElement === input || listOf(input).contains(document.activeElement))) { return; }
            showResults(input, d.results || []);
        }).catch(function (err) { fail(input, err.message); });
    }
    function addParent(input, parentId) {
        var personId = personOf(input);
        closeList(input);
        input.classList.add('busy');
        post('/api/person/add-parent', { target_id: personId, kind: input.dataset.parent, mode: 'existing', parent_id: parentId })
            .then(function () { return refresh(personId, false); })
            .then(function () { toast(wrap.dataset.msgSaved, 'success'); })
            .catch(function (err) { input.classList.remove('busy'); fail(input, err.message); });
    }
    wrap.addEventListener('input', function (ev) {
        var el = ev.target;
        if (el.matches && el.matches('input[data-parent]')) {
            el.classList.remove('err');
            say(el.closest('.ms-cell'), '', '');
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { search(el); }, 250);
        } else if (el.classList && el.classList.contains('err')) {
            el.classList.remove('err');
            say(el.closest('.ms-cell'), '', '');
        }
    });
    wrap.addEventListener('focusin', function (ev) {
        var el = ev.target;
        if (el.matches && el.matches('input[data-parent]')) { search(el); }
    });
    wrap.addEventListener('focusout', function (ev) {
        var el = ev.target, inp = null;
        if (el.matches && el.matches('input[data-parent]')) { inp = el; }
        else if (el.closest && el.closest('ul.ms-results')) {
            var box = el.closest('.ms-find');
            inp = box ? box.querySelector('input[data-parent]') : null;
        }
        if (!inp) { return; }
        setTimeout(function () {
            var ul = listOf(inp);
            if (document.activeElement !== inp && !(ul && ul.contains(document.activeElement))) { closeList(inp); }
        }, 150);
    });

    // ---- фото: выбор файла → обрезка овалом (person-media-edit.js) → загрузка
    wrap.addEventListener('click', function (ev) {
        var btn = ev.target.closest ? ev.target.closest('button[data-photo]') : null;
        if (!btn) { return; }
        var file = btn.parentNode.querySelector('input[data-photo-file]');
        if (file) { file.click(); }
    });
    function pickPhoto(input) {
        if (typeof window.pmeAvatarFile !== 'function' || typeof window.pmeSetTarget !== 'function') {
            fail(input, wrap.dataset.msgError);
            return;
        }
        photoPerson = personOf(input);
        window.pmeSetTarget(photoPerson, csrf);
        window.pmeAvatarFile(input);
    }
    // person-media-edit.js вызывает это после успешной загрузки
    window.pmeOnAvatarSaved = function () {
        if (photoPerson) { refresh(photoPerson, false).catch(function () {}); }
    };
})();
