/* ALT-018: удаление человека (мягкое). Использование: altDeletePerson(id, name, {lang, csrf, onDone})
   ALT-061: подтверждение — окно в стиле сайта (вместо системного confirm)
   ALT-119: после успешного удаления показываем понятное сообщение о результате */
(function () {
    'use strict';

    function ru(lang) { return lang === 'ru' || lang === 'en' || lang === 'es'; }

    // Короткое сообщение об успехе: общий тост сайта → тост древа → свой тост
    function showSuccessToast(text) {
        try {
            if (typeof window.showAppToast === 'function') { window.showAppToast(text, 'success'); return; }
            if (typeof window.altSavedToast === 'function') { window.altSavedToast(text); return; }
        } catch (e) { /* показываем свой тост */ }

        const t = document.createElement('div');
        t.setAttribute('role', 'status');
        t.textContent = '✓ ' + text;
        t.style.cssText = 'position:fixed;right:20px;bottom:20px;z-index:100001;max-width:min(420px,calc(100vw - 32px));padding:10px 16px;border-radius:10px;background:var(--c-surface,#fff);color:var(--c-text,#1c1b19);border:1px solid var(--c-border,#d9d2c3);box-shadow:0 6px 24px rgba(0,0,0,.18);font-weight:600;font-size:14px;line-height:1.4;';
        document.body.appendChild(t);
        setTimeout(function () { t.remove(); }, 3500);
    }

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

    // Окно подтверждения: resolve(true) — «Удалить», resolve(false) — «Отмена» / Esc / клик мимо
    function askConfirm(text, okLabel, cancelLabel) {
        return new Promise(function (resolve) {
            const prevFocus = document.activeElement;
            const back = document.createElement('div');
            back.style.cssText = 'position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(20,18,14,.55);';
            const box = document.createElement('div');
            box.setAttribute('role', 'alertdialog');
            box.setAttribute('aria-modal', 'true');
            box.style.cssText = 'background:var(--c-surface,#fff);color:var(--c-text,#2a2620);border:1px solid var(--c-border,#ddd6c8);border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.28);max-width:440px;width:100%;padding:22px 22px 18px;font:inherit;';
            const p = document.createElement('p');
            p.style.cssText = 'margin:0 0 18px;line-height:1.5;white-space:pre-line;font-size:16px;';
            p.textContent = text;
            const row = document.createElement('div');
            row.style.cssText = 'display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;';
            const bCancel = document.createElement('button');
            bCancel.type = 'button';
            bCancel.textContent = cancelLabel;
            bCancel.style.cssText = 'min-height:42px;padding:8px 18px;border-radius:10px;border:1px solid var(--c-border,#ddd6c8);background:transparent;color:inherit;font:inherit;font-weight:600;cursor:pointer;';
            const bOk = document.createElement('button');
            bOk.type = 'button';
            bOk.textContent = okLabel;
            bOk.style.cssText = 'min-height:42px;padding:8px 18px;border-radius:10px;border:1px solid var(--c-danger,#b4493f);background:var(--c-danger,#b4493f);color:#fff;font:inherit;font-weight:700;cursor:pointer;';
            row.appendChild(bCancel);
            row.appendChild(bOk);
            box.appendChild(p);
            box.appendChild(row);
            back.appendChild(box);

            function done(v) {
                document.removeEventListener('keydown', onKey, true);
                back.remove();
                if (prevFocus && typeof prevFocus.focus === 'function') { try { prevFocus.focus(); } catch (e) { /* нет фокуса */ } }
                resolve(v);
            }
            function onKey(e) {
                if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); done(false); }
            }
            bCancel.addEventListener('click', function () { done(false); });
            bOk.addEventListener('click', function () { done(true); });
            back.addEventListener('mousedown', function (e) { if (e.target === back) { done(false); } });
            document.addEventListener('keydown', onKey, true);
            document.body.appendChild(back);
            bCancel.focus(); // по умолчанию — безопасная кнопка
        });
    }

    window.altDeletePerson = async function (id, name, opts) {
        opts = opts || {};
        const r = ru(opts.lang);
        const csrf = opts.csrf || '';
        const label = name || ('#' + id);
        const okL = r ? 'Удалить' : 'Жою';
        const noL = r ? 'Отмена' : 'Бас тарту';

        const q = r
            ? 'Удалить ' + label + '? Это действие нельзя отменить.\nСвязи с родителями, супругом и детьми будут убраны.'
            : label + ' жойылсын ба? Бұл әрекетті қайтаруға болмайды.\nАта-анасымен, жұбайымен және балаларымен байланыстар алынады.';
        if (!(await askConfirm(q, okL, noL))) { return false; }

        try {
            let res = await post(id, csrf, false);
            if (res.status === 409 && res.data && res.data.has_children) {
                const names = (res.data.children || []).join(', ');
                const q2 = r
                    ? 'У ' + label + ' есть потомки в древе (' + res.data.children_count + '): ' + names +
                      '.\n\nУдалить только этого человека? Дети останутся, связь с ним порвётся. Это действие нельзя отменить.'
                    : label + ' адамының ұрпақтары бар (' + res.data.children_count + '): ' + names +
                      '.\n\nТек осы адам жойылсын ба? Балалары қалады, байланыс үзіледі. Бұл әрекетті қайтаруға болмайды.';
                if (!(await askConfirm(q2, okL, noL))) { return false; }
                res = await post(id, csrf, true);
            }
            if (res.data && res.data.status === 'success') {
                showSuccessToast(r ? 'Человек удалён' : 'Адам жойылды');
                if (typeof opts.onDone === 'function') {
                    // Даём увидеть сообщение до перезагрузки страницы/древа
                    setTimeout(opts.onDone, 800);
                }
                return true;
            }
            window.alert((r ? 'Ошибка: ' : 'Қате: ') + ((res.data && res.data.error) || res.status));
        } catch (err) {
            window.alert((r ? 'Ошибка: ' : 'Қате: ') + err.message);
        }
        return false;
    };
})();
