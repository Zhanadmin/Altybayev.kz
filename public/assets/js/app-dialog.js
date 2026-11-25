/* ALT-066: единые окна подтверждения, ввода и всплывающие сообщения в стиле сайта
   вместо браузерных confirm / alert / prompt.
   API:
     appConfirm(text, {danger, okText, cancelText, title}) -> Promise<boolean>
     appPrompt(text, defaultValue)                         -> Promise<string|null>
     appAlert(text, kind)                                  -> всплывающее сообщение (само исчезает)
   window.alert заменён на appAlert (сообщения без блокировки страницы). */
(function () {
    'use strict';
    if (window.appConfirm) { return; }

    function getCookie(name) {
        var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        return m ? decodeURIComponent(m[1]) : '';
    }
    function isRu() {
        var l = (getCookie('shezhire_lang') || document.documentElement.lang || '').toLowerCase();
        return l === 'ru' || l === 'en' || l === 'es';
    }
    var DANGER_RE = /удал|жою|жойыл|өшір|delete|remove/i;
    var activeCount = 0;

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text != null) { n.textContent = text; }
        return n;
    }

    function openDialog(text, opts) {
        opts = opts || {};
        return new Promise(function (resolve) {
            var ru = isRu();
            var danger = opts.danger != null ? !!opts.danger : DANGER_RE.test(String(text));
            var isPrompt = !!opts.prompt;
            var prevFocus = document.activeElement;

            var overlay = el('div', 'ad-overlay');
            var box = el('div', 'ad-box' + (danger ? ' ad-danger' : ''));
            box.setAttribute('role', 'alertdialog');
            box.setAttribute('aria-modal', 'true');
            var msg = el('div', 'ad-msg', String(text));
            msg.id = 'adMsg' + (++activeCount);
            box.setAttribute('aria-labelledby', msg.id);
            box.appendChild(msg);

            var input = null;
            if (isPrompt) {
                input = el('input', 'ad-input');
                input.type = 'text';
                input.value = opts.defaultValue != null ? String(opts.defaultValue) : '';
                box.appendChild(input);
            }

            var actions = el('div', 'ad-actions');
            var cancel = el('button', 'ad-btn', opts.cancelText || (ru ? 'Отмена' : 'Болдырмау'));
            cancel.type = 'button';
            var okLabel = opts.okText || (danger ? (ru ? 'Удалить' : 'Жою') : (ru ? 'Подтвердить' : 'Растау'));
            var ok = el('button', 'ad-btn ad-ok' + (danger ? ' ad-btn-danger' : ' ad-btn-primary'), okLabel);
            ok.type = 'button';
            actions.appendChild(cancel);
            actions.appendChild(ok);
            box.appendChild(actions);
            overlay.appendChild(box);

            var done = false;
            function close(result) {
                if (done) { return; }
                done = true;
                document.removeEventListener('keydown', onKey, true);
                overlay.classList.remove('ad-show');
                setTimeout(function () { overlay.remove(); }, 160);
                if (prevFocus && prevFocus.focus) { try { prevFocus.focus(); } catch (e) {} }
                resolve(result);
            }
            function accept() { close(isPrompt ? input.value : true); }
            function reject() { close(isPrompt ? null : false); }

            function onKey(e) {
                if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); reject(); return; }
                if (e.key === 'Enter') {
                    if (document.activeElement === cancel) { return; }
                    e.preventDefault(); e.stopPropagation(); accept(); return;
                }
                if (e.key === 'Tab') {
                    var f = [input, cancel, ok].filter(Boolean);
                    var i = f.indexOf(document.activeElement);
                    e.preventDefault();
                    var next = e.shiftKey ? (i <= 0 ? f.length - 1 : i - 1) : (i + 1) % f.length;
                    f[next].focus();
                }
            }
            document.addEventListener('keydown', onKey, true);
            ok.addEventListener('click', accept);
            cancel.addEventListener('click', reject);
            overlay.addEventListener('mousedown', function (e) { if (e.target === overlay) { reject(); } });

            document.body.appendChild(overlay);
            requestAnimationFrame(function () { overlay.classList.add('ad-show'); });
            (input || ok).focus();
            if (input) { input.select(); }
        });
    }

    window.appConfirm = function (text, opts) { return openDialog(text, opts); };
    window.appPrompt = function (text, def) { return openDialog(text, { prompt: true, defaultValue: def, danger: false }); };

    // Всплывающее сообщение; на страницах с showAppToast используется оно, иначе — своё
    var ownStack = null;
    function ownToast(message, kind) {
        if (!ownStack || !ownStack.isConnected) {
            ownStack = document.getElementById('appToastStack') || el('div', 'toast-stack ad-toast-stack');
            if (!ownStack.isConnected) { ownStack.setAttribute('aria-live', 'polite'); document.body.appendChild(ownStack); }
        }
        var t = el('div', 'toast ad-toast ' + kind);
        t.setAttribute('role', kind === 'error' ? 'alert' : 'status');
        t.appendChild(el('div', null, String(message)));
        ownStack.appendChild(t);
        setTimeout(function () { t.remove(); }, kind === 'error' ? 7000 : 3500);
    }
    var ERR_RE = /қате|ошибка|error|табылмады|не найден|жазыңыз|таңдаңыз|failed|нельзя|болмайды|тек қарауға/i;
    window.appAlert = function (message, kind) {
        var k = kind || (ERR_RE.test(String(message)) ? 'error' : 'success');
        if (typeof window.showAppToast === 'function') { window.showAppToast(message, k); }
        else { ownToast(message, k); }
    };
    if (typeof window.showAppToast !== 'function') {
        window.showAppToast = function (m, k) { ownToast(m, k === 'success' ? 'success' : 'error'); };
    }
    // Любой оставшийся alert() — тоже всплывающее сообщение, а не окно браузера
    window.alert = function (m) { window.appAlert(m); };
    // Формы/ссылки с data-confirm="текст": подтверждение окном сайта
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f || !f.getAttribute || !f.hasAttribute('data-app-confirm') || f.__adOk) { return; }
        e.preventDefault();
        window.appConfirm(f.getAttribute('data-app-confirm')).then(function (yes) {
            if (yes) { f.__adOk = true; if (f.requestSubmit) { f.requestSubmit(); } else { f.submit(); } }
        });
    }, true);
})();
