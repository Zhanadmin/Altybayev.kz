/* ALT-016: регистрация service worker, кнопка «Установить приложение» (Android/Chrome),
   подсказка «Поделиться → На экран Домой» (iPhone). Вход через Google и музыка не затрагиваются. */
(function () {
    'use strict';
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
        });
    }

    var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
    if (standalone) return;

    var lang = (document.documentElement.lang || 'kk').slice(0, 2);
    var T = {
        kk: { install: 'Қосымшаны орнату', ios: 'iPhone: «Бөлісу» → «Басты экранға қосу»', close: 'Жабу' },
        ru: { install: 'Установить приложение', ios: 'iPhone: «Поделиться» → «На экран Домой»', close: 'Закрыть' },
        en: { install: 'Install app', ios: 'iPhone: Share → Add to Home Screen', close: 'Close' },
        es: { install: 'Instalar la app', ios: 'iPhone: Compartir → Añadir a pantalla de inicio', close: 'Cerrar' }
    }[lang] || null;
    if (!T) T = { install: 'Install app', ios: 'iPhone: Share → Add to Home Screen', close: 'Close' };

    var KEY = 'shezhire_pwa_dismissed';
    function dismissed() { try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; } }
    function setDismissed() { try { localStorage.setItem(KEY, '1'); } catch (e) {} }

    function bar(text, withBtn, onInstall) {
        if (document.getElementById('pwaBar') || dismissed()) return;
        var st = document.createElement('style');
        st.textContent = '#pwaBar{position:fixed;left:8px;right:8px;bottom:calc(8px + env(safe-area-inset-bottom,0px));z-index:1200;display:flex;align-items:center;gap:8px;' +
            'padding:8px 8px 8px 14px;border-radius:12px;background:#14110c;color:#efe6d0;border:1px solid #d4af37;box-shadow:0 6px 24px rgba(0,0,0,.4);font:14px/1.35 system-ui,sans-serif}' +
            '#pwaBar span{flex:1;min-width:0}' +
            '#pwaBar button{min-height:44px;min-width:44px;padding:0 14px;border-radius:10px;border:1px solid #d4af37;background:transparent;color:#d4af37;font:inherit;font-weight:600;cursor:pointer}' +
            '#pwaBar button.pwa-go{background:#d4af37;color:#14110c}@media print{#pwaBar{display:none}}';
        document.head.appendChild(st);
        var d = document.createElement('div');
        d.id = 'pwaBar';
        d.setAttribute('role', 'region');
        var s = document.createElement('span'); s.textContent = text; d.appendChild(s);
        if (withBtn) {
            var g = document.createElement('button'); g.type = 'button'; g.className = 'pwa-go'; g.textContent = T.install;
            g.addEventListener('click', function () { onInstall(); d.remove(); });
            d.appendChild(g);
        }
        var x = document.createElement('button'); x.type = 'button'; x.textContent = '×'; x.setAttribute('aria-label', T.close);
        x.addEventListener('click', function () { setDismissed(); d.remove(); });
        d.appendChild(x);
        document.body.appendChild(d);
    }

    var deferred = null;
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferred = e;
        bar(T.install + '?', true, function () {
            if (!deferred) return;
            deferred.prompt();
            deferred.userChoice.then(function () { deferred = null; });
        });
    });
    window.addEventListener('appinstalled', function () {
        var b = document.getElementById('pwaBar'); if (b) b.remove();
    });

    var ua = navigator.userAgent || '';
    var isIos = /iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
    if (isIos) {
        var go = function () { setTimeout(function () { bar(T.ios, false); }, 2500); };
        if (document.readyState === 'complete') go(); else window.addEventListener('load', go);
    }
})();
