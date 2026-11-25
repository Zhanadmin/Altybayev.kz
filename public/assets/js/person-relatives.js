/* ALT-067: блок «Туыстар / Родственники» в окне «Өзгерту» + общий диалог выбора/создания.
   Настройки задаёт страница: window.__relCfg = { ru: bool, csrf: string, onDone: function(personId) }. */
(function () {
    'use strict';
    var st = { id: 0, kind: '', mode: 'existing', picked: 0, gender: '', marriages: [], timer: null };

    function cfg() { return window.__relCfg || {}; }
    function T(kk, ru) { return cfg().ru ? ru : kk; }
    function $(id) { return document.getElementById(id); }

    var TITLES = {
        father: ['Әкесін қосу', 'Добавить отца'],
        mother: ['Анасын қосу', 'Добавить мать'],
        child: ['Баласын қосу', 'Добавить ребёнка']
    };

    function spouseLabel(g) {
        if (g === 'M') { return T('Әйелін қосу', 'Добавить жену'); }
        if (g === 'F') { return T('Күйеуін қосу', 'Добавить мужа'); }
        return T('Жұбайын қосу', 'Добавить мужа (жену)');
    }

    function show(id, on) { var el = $(id); if (el) { el.style.display = on ? '' : 'none'; } }

    /** Заполнить блок для человека: кнопки по правилам ALT-060. */
    window.relLoad = async function (id) {
        var block = $('relBlock');
        if (!block) { return; }
        block.style.display = 'none';
        st.id = parseInt(id, 10) || 0;
        if (!st.id) { return; }
        try {
            var res = await fetch('/api/person/relatives-state?id=' + st.id);
            var d = await res.json();
            if (!d || d.error || !d.can_edit) { return; }
            st.gender = d.gender || '';
            st.marriages = d.marriages || [];
            var hasG = st.gender === 'M' || st.gender === 'F';
            show('relBtnFather', !d.has_father);
            show('relBtnMother', !d.has_mother);
            show('relBtnSpouse', hasG && !st.marriages.length);
            show('relBtnChild', hasG);
            var sp = $('relBtnSpouse');
            if (sp) { sp.textContent = '+ ' + spouseLabel(st.gender); }
            block.style.display = '';
        } catch (e) { /* блок просто не показываем */ }
    };

    /** Открыть диалог без окна правки (кнопка «Добавить ребёнка» в карточке). */
    window.relOpenFor = async function (id, kind) {
        st.id = parseInt(id, 10) || 0;
        try {
            var res = await fetch('/api/person/relatives-state?id=' + st.id);
            var d = await res.json();
            if (!d || d.error || !d.can_edit) { return; }
            st.gender = d.gender || '';
            st.marriages = d.marriages || [];
        } catch (e) { return; }
        window.relOpen(kind);
    };

    window.relOpen = function (kind) {
        st.kind = kind; st.picked = 0;
        var title = kind === 'spouse' ? spouseLabel(st.gender) : T(TITLES[kind][0], TITLES[kind][1]);
        $('relTitle').textContent = title;
        $('relError').textContent = '';
        ['relFirst', 'relLast', 'relMaiden', 'relYear', 'relSearch'].forEach(function (i) { $(i).value = ''; });
        // пол нужен только новому ребёнку; у супруга он определяется, у родителей задан видом
        show('relGenderWrap', kind === 'child');
        // ALT-078: дата заключения брака — только при добавлении супруга
        show('relMarriageDateWrap', kind === 'spouse');
        if ($('relMarriageDate')) { $('relMarriageDate').value = ''; }
        // девичья фамилия — только у женщины: мать или супруга мужчины
        show('relMaidenWrap', kind === 'mother' || (kind === 'spouse' && st.gender === 'M'));
        var g = document.querySelector('input[name="relGender"]:checked');
        if (g) { g.checked = false; }
        // выбор брака — только если действующих браков больше одного
        var mw = $('relMarriageWrap');
        if (kind === 'child' && st.marriages.length > 1) {
            var sel = $('relMarriage');
            sel.textContent = '';
            st.marriages.forEach(function (m) {
                var o = document.createElement('option');
                o.value = m.id;
                o.textContent = m.name || ('№' + m.id);
                sel.appendChild(o);
            });
            mw.style.display = '';
        } else {
            mw.style.display = 'none';
        }
        $('relDialog').style.display = 'flex';
        relSetMode('existing');
        relSearch('');
    };

    window.relClose = function () { $('relDialog').style.display = 'none'; };

    window.relSetMode = function (m) {
        st.mode = m;
        show('relPaneExisting', m === 'existing');
        show('relPaneNew', m === 'new');
        mark($('relTabExisting'), m === 'existing');
        mark($('relTabNew'), m === 'new');
    };

    // Выделение не зависит от CSS страницы (в древе нет классов .btn)
    function mark(el, on) {
        el.style.boxShadow = on ? 'inset 0 0 0 2px var(--c-accent, #d4af37)' : '';
        el.style.fontWeight = on ? '700' : '';
    }

    var SEARCH_URL = {
        father: '/api/person/parent-search?kind=father&target_id=',
        mother: '/api/person/parent-search?kind=mother&target_id=',
        spouse: '/api/person/spouse-search?target_id=',
        child: '/api/person/child-search?target_id='
    };

    async function relSearch(q) {
        var box = $('relResults');
        try {
            var res = await fetch(SEARCH_URL[st.kind] + st.id + '&q=' + encodeURIComponent(q));
            var data = await res.json();
            box.textContent = '';
            (data.results || []).forEach(function (p) {
                var row = document.createElement('button');
                row.type = 'button';
                row.className = cfg().btnGhost || 'btn btn-ghost';
                row.style.cssText = 'display:block; width:100%; text-align:left;';
                row.setAttribute('role', 'option');
                row.appendChild(document.createTextNode(p.name));
                if (p.maiden_name) {
                    var em = document.createElement('em');
                    em.className = 'maiden-it';
                    em.textContent = ' (' + p.maiden_name + ')';
                    row.appendChild(em);
                }
                row.appendChild(document.createTextNode(' · ' + p.years));
                row.onclick = function () {
                    st.picked = p.id;
                    Array.prototype.forEach.call(box.children, function (c) { if (c.style) { mark(c, false); } });
                    mark(row, true);
                };
                box.appendChild(row);
            });
            if (!box.children.length) { box.textContent = 'Табылмады / Не найдено'; }
        } catch (e) { box.textContent = 'Қате / Ошибка'; }
    }
    window.relSearch = relSearch;

    document.addEventListener('DOMContentLoaded', function () {
        var s = $('relSearch');
        if (s) { s.addEventListener('input', function () { clearTimeout(st.timer); st.timer = setTimeout(function () { relSearch(s.value.trim()); }, 250); }); }
    });

    window.relSubmit = async function () {
        var err = $('relError');
        var btn = $('relSave');
        if (btn.disabled) { return; }
        err.textContent = '';
        var f = new URLSearchParams();
        f.append('csrf_token', cfg().csrf || '');
        f.append('target_id', st.id);
        f.append('mode', st.mode);
        var url;
        if (st.kind === 'father' || st.kind === 'mother') {
            url = '/api/person/add-parent'; f.append('kind', st.kind);
            if (st.mode === 'existing') { f.append('parent_id', st.picked); }
        } else if (st.kind === 'spouse') {
            url = '/api/person/add-spouse';
            if (st.mode === 'existing') { f.append('spouse_id', st.picked); }
            f.append('marriage_date', $('relMarriageDate') ? $('relMarriageDate').value : '');
        } else {
            url = '/api/person/add-child';
            if (st.mode === 'existing') { f.append('child_id', st.picked); }
            if (st.marriages.length > 1) { f.append('marriage_id', $('relMarriage').value); }
            else if (st.marriages.length === 1) { f.append('marriage_id', st.marriages[0].id); }
        }
        if (st.mode === 'existing') {
            if (!st.picked) { err.textContent = 'Тізімнен таңдаңыз / Выберите человека из списка'; return; }
        } else {
            var first = $('relFirst').value.trim();
            if (!first) { err.textContent = 'Аты / Имя обязательно'; return; }
            f.append('first_name', first);
            f.append('last_name', $('relLast').value.trim());
            f.append('maiden_name', $('relMaiden').value.trim());
            f.append('birth_year', $('relYear').value.trim());
            if (st.kind === 'child') {
                var g = document.querySelector('input[name="relGender"]:checked');
                if (!g) { err.textContent = 'Жынысын таңдаңыз / Укажите пол ребёнка'; return; }
                f.append('gender', g.value);
            }
        }
        btn.disabled = true;
        try {
            var res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: f.toString() });
            var data = await res.json();
            if (data && data.status === 'success') {
                relClose();
                window.pcfDirty = true; // страница под окном устарела: при закрытии окна правки она перезагрузится
                var id = st.id;
                if (typeof cfg().onDone === 'function') { await cfg().onDone(id); }
                await window.relLoad(id);
            } else {
                err.textContent = (data && data.error) || 'Қате / Ошибка';
            }
        } catch (e) { err.textContent = 'Сұраныс қатесі: ' + e.message; }
        btn.disabled = false;
    };
})();
