/* ALT-018: удаление человека (мягкое). Использование: altDeletePerson(id, name, {lang, csrf, onDone}) */
(function () {
    'use strict';

    function ru(lang) { return lang === 'ru' || lang === 'en' || lang === 'es'; }

    async function post(id, csrf, confirmed) {
        const body = new URLSearchParams({ id: String(id), csrf_token: csrf });
        if (confirmed) { body.append('children_confirmed', '1'); }
        const res = await fetch('/api/person/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        });
        let data = {};
        try { data = await res.json(); } catch (e) { /* пустой ответ */ }
        return { status: res.status, data: data };
    }

    window.altDeletePerson = async function (id, name, opts) {
        opts = opts || {};
        const r = ru(opts.lang);
        const csrf = opts.csrf || '';
        const label = name || ('#' + id);

        const q = r
            ? 'Удалить ' + label + '? Связи с родителями, супругом и детьми будут убраны.'
            : label + ' жойылсын ба? Ата-анасымен, жұбайымен және балаларымен байланыстар алынады.';
        if (!window.confirm(q)) { return false; }

        try {
            let res = await post(id, csrf, false);
            if (res.status === 409 && res.data && res.data.has_children) {
                const names = (res.data.children || []).join(', ');
                const q2 = r
                    ? 'У ' + label + ' есть потомки в древе (' + res.data.children_count + '): ' + names +
                      '.\n\nОК — удалить только этого человека (дети останутся, связь с ним порвётся).\nОтмена — ничего не удалять.'
                    : label + ' адамының ұрпақтары бар (' + res.data.children_count + '): ' + names +
                      '.\n\nОК — тек осы адам жойылады (балалары қалады, байланыс үзіледі).\nБас тарту — ештеңе жойылмайды.';
                if (!window.confirm(q2)) { return false; }
                res = await post(id, csrf, true);
            }
            if (res.data && res.data.status === 'success') {
                if (typeof opts.onDone === 'function') { opts.onDone(); }
                return true;
            }
            window.alert((r ? 'Ошибка: ' : 'Қате: ') + ((res.data && res.data.error) || res.status));
        } catch (err) {
            window.alert((r ? 'Ошибка: ' : 'Қате: ') + err.message);
        }
        return false;
    };
})();
