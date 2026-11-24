/* ALT-049: единые подсказки в стиле сайта вместо браузерных (title).
   Источник текста — data-tip; существующий title переносится в data-tip, чтобы браузерная подсказка не появлялась.
   Тексты не меняются. Работает и внутри iframe оболочки (каждая страница подключает скрипт сама). */
(function () {
    'use strict';
    if (window.__altTip) return;
    window.__altTip = true;

    var css = '' +
        '.alt-tip{position:fixed;left:0;top:0;z-index:2147483000;max-width:min(280px,calc(100vw - 16px));' +
        'padding:7px 11px;border-radius:10px;background:var(--c-text,#3b2914);color:var(--c-bg,#fdf6e4);' +
        'font:500 13px/1.35 var(--font-sans,"Inter","Segoe UI",system-ui,sans-serif);letter-spacing:.01em;' +
        'box-shadow:var(--shadow-pop,0 10px 30px rgba(59,41,20,.18));pointer-events:none;overflow-wrap:anywhere;' +
        'opacity:0;transform:translateY(4px);transition:opacity .16s ease,transform .16s ease;visibility:hidden}' +
        '.alt-tip.alt-tip-below{transform:translateY(-4px)}' +
        '.alt-tip.alt-tip-on{opacity:1;transform:none;visibility:visible}' +
        '.alt-tip::after{content:"";position:absolute;left:var(--alt-tip-ax,50%);width:10px;height:10px;' +
        'background:inherit;transform:translateX(-50%) rotate(45deg);border-radius:2px;bottom:-4px}' +
        '.alt-tip.alt-tip-below::after{bottom:auto;top:-4px}' +
        '@media (prefers-reduced-motion:reduce){.alt-tip{transition:none}}';
    var st = document.createElement('style');
    st.textContent = css;
    (document.head || document.documentElement).appendChild(st);

    var tipEl = null, current = null, showTimer = null, pressTimer = null, touchShown = false;

    function convert(el) {
        if (!el || el.nodeType !== 1) return;
        if (el.tagName === 'IFRAME' || el.tagName === 'svg' || el.namespaceURI === 'http://www.w3.org/2000/svg') return;
        var t = el.getAttribute('title');
        if (t === null) return;
        el.removeAttribute('title');
        t = t.trim();
        if (!t) return;
        // бестолковая подсказка: повторяет видимый текст элемента — не показываем
        if (isRepeat(el, t)) return;
        if (!el.hasAttribute('data-tip')) el.setAttribute('data-tip', t);
        ensureA11y(el, el.getAttribute('data-tip'));
    }

    function norm(s) { return (s || '').replace(/\s+/g, ' ').trim().toLowerCase(); }

    // подсказка повторяет видимый текст (имя на карточке, строка списка) — ничего не объясняет
    function isRepeat(el, t) {
        var tip = norm(t), vis = norm(el.textContent);
        if (!tip || !vis) return false;
        if (vis === tip || vis.indexOf(tip) !== -1) return true;
        if (vis.length >= 5 && vis.indexOf(' ') !== -1 && tip.indexOf(vis) === 0) return true;
        return false;
    }

    function ensureA11y(el, text) {
        if (!text) return;
        var hasName = el.hasAttribute('aria-label') || el.hasAttribute('aria-labelledby') ||
            (el.textContent || '').trim() !== '';
        if (!hasName) el.setAttribute('aria-label', text);
        else if (!el.hasAttribute('aria-description')) el.setAttribute('aria-description', text);
    }

    function scan(root) {
        if (!root || !root.querySelectorAll) return;
        if (root.nodeType === 1) convert(root);
        var list = root.querySelectorAll('[title]');
        for (var i = 0; i < list.length; i++) convert(list[i]);
        var tl = root.querySelectorAll('[data-tip]');
        for (var j = 0; j < tl.length; j++) ensureA11y(tl[j], tl[j].getAttribute('data-tip'));
    }

    function ensureTip() {
        if (tipEl) return tipEl;
        tipEl = document.createElement('div');
        tipEl.className = 'alt-tip';
        tipEl.setAttribute('role', 'tooltip');
        tipEl.setAttribute('aria-hidden', 'true');
        document.body.appendChild(tipEl);
        return tipEl;
    }

    function hide() {
        clearTimeout(showTimer);
        current = null;
        if (tipEl) tipEl.classList.remove('alt-tip-on');
    }

    function place(el) {
        var tip = ensureTip();
        var r = el.getBoundingClientRect();
        var vw = document.documentElement.clientWidth || window.innerWidth;
        var vh = document.documentElement.clientHeight || window.innerHeight;
        var m = 8, gap = 10;
        var tw = tip.offsetWidth, th = tip.offsetHeight;
        var cx = r.left + r.width / 2;
        var left = Math.max(m, Math.min(cx - tw / 2, vw - tw - m));
        // по умолчанию подсказка снизу (как нравится владельцу); сверху — только если снизу нет места
        var below = !((r.bottom + th + gap) > vh - m && (r.top - th - gap) >= m);
        var top = below ? r.bottom + gap : r.top - th - gap;
        top = Math.max(m, Math.min(top, vh - th - m));
        tip.style.left = Math.round(left) + 'px';
        tip.style.top = Math.round(top) + 'px';
        tip.style.setProperty('--alt-tip-ax', Math.round(Math.max(12, Math.min(cx - left, tw - 12))) + 'px');
        tip.classList.toggle('alt-tip-below', below);
    }

    function show(el) {
        var text = el.getAttribute('data-tip');
        if (!text || !el.isConnected || isRepeat(el, text)) return;
        var tip = ensureTip();
        current = el;
        tip.textContent = text;
        tip.classList.remove('alt-tip-on');
        place(el);
        // принудительный пересчёт, чтобы появление было плавным
        void tip.offsetWidth;
        tip.classList.add('alt-tip-on');
    }

    function target(e) {
        var n = e.target;
        if (!n || !n.closest) return null;
        return n.closest('[data-tip]');
    }

    document.addEventListener('pointerover', function (e) {
        if (e.pointerType === 'touch') return;
        var el = target(e);
        if (!el || el === current) return;
        hide();
        showTimer = setTimeout(function () { show(el); }, 220);
    }, true);
    document.addEventListener('pointerout', function (e) {
        if (e.pointerType === 'touch') return;
        var el = target(e);
        if (el && (!e.relatedTarget || !el.contains(e.relatedTarget))) hide();
    }, true);
    document.addEventListener('pointerdown', function (e) {
        if (e.pointerType === 'touch') {
            // телефон: долгое нажатие показывает подсказку, обычное нажатие не затрагивается
            var el = target(e);
            clearTimeout(pressTimer);
            if (!el) return;
            pressTimer = setTimeout(function () { touchShown = true; show(el); }, 550);
        } else {
            hide();
        }
    }, true);
    function endPress() {
        clearTimeout(pressTimer);
        if (touchShown) { touchShown = false; setTimeout(hide, 1600); }
    }
    document.addEventListener('pointerup', endPress, true);
    document.addEventListener('pointercancel', function () { clearTimeout(pressTimer); hide(); }, true);
    document.addEventListener('contextmenu', function (e) {
        if (touchShown) e.preventDefault(); // долгое нажатие: без системного меню поверх подсказки
    }, true);
    document.addEventListener('focusin', function (e) {
        var el = target(e);
        if (el && el.matches && el.matches(':focus-visible')) show(el);
    }, true);
    document.addEventListener('focusout', hide, true);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hide(); }, true);
    window.addEventListener('scroll', hide, true);
    window.addEventListener('resize', hide);

    function start() {
        scan(document);
        var mo = new MutationObserver(function (list) {
            for (var i = 0; i < list.length; i++) {
                var m = list[i];
                if (m.type === 'attributes') {
                    if (m.attributeName === 'title') convert(m.target);
                    else if (m.attributeName === 'data-tip') {
                        ensureA11y(m.target, m.target.getAttribute('data-tip'));
                        if (m.target === current) show(m.target);
                    }
                } else {
                    for (var k = 0; k < m.addedNodes.length; k++) scan(m.addedNodes[k]);
                }
            }
        });
        mo.observe(document.documentElement, {
            subtree: true, childList: true, attributes: true, attributeFilter: ['title', 'data-tip']
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
