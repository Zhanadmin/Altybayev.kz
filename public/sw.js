/* ALT-016: service worker. Кэшируется ТОЛЬКО публичная статика /assets/ (css, js, svg, шрифты).
   НЕ кэшируются: HTML-страницы, /api/, /uploads/ (личные фото), вход/выход, аудио (range-запросы), не-GET.
   Страницы — всегда из сети; при отсутствии связи показывается /offline.html. */
var VERSION = 'v1';
var STATIC = 'shezhire-static-' + VERSION;
var OFFLINE_URL = '/offline.html';
var PRECACHE = [OFFLINE_URL, '/assets/img/pwa/icon-192.svg', '/assets/img/pwa/icon-512.svg'];

self.addEventListener('install', function (e) {
    e.waitUntil(caches.open(STATIC).then(function (c) { return c.addAll(PRECACHE); }).then(function () { return self.skipWaiting(); }));
});

self.addEventListener('activate', function (e) {
    e.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (k) { return k.indexOf('shezhire-') === 0 && k !== STATIC; }).map(function (k) { return caches.delete(k); }));
        }).then(function () { return self.clients.claim(); })
    );
});

function isStaticAsset(url) {
    if (url.origin !== self.location.origin) return false;
    var p = url.pathname;
    if (p.indexOf('/assets/') !== 0) return false;
    if (p.indexOf('/assets/audio/') === 0) return false;
    return /\.(css|js|svg|png|jpg|jpeg|webp|woff2?|json|bin)$/i.test(p);
}

self.addEventListener('fetch', function (e) {
    var req = e.request;
    if (req.method !== 'GET') return;
    var url = new URL(req.url);

    if (req.mode === 'navigate') {
        e.respondWith(fetch(req).catch(function () { return caches.match(OFFLINE_URL); }));
        return;
    }
    if (!isStaticAsset(url)) return;

    // кэш с обновлением в фоне; версия в ?v= даёт новый ключ
    e.respondWith(
        caches.open(STATIC).then(function (cache) {
            return cache.match(req).then(function (hit) {
                var net = fetch(req).then(function (res) {
                    if (res && res.status === 200 && res.type === 'basic') cache.put(req, res.clone());
                    return res;
                }).catch(function () { return hit; });
                return hit || net;
            });
        })
    );
});
