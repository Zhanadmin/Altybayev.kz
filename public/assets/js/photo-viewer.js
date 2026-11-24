/* ALT-039: просмотр фото карточки человека крупно (аватарка, обложка). Доступен всем. */
(function () {
    'use strict';
    var book = document.querySelector('.pc-book');
    if (!book) { return; }
    var items = [], idx = 0, box = null, img = null, prevB = null, nextB = null, lastFocus = null;

    function collect() {
        var list = [];
        var av = document.getElementById('personAvatarImg');
        if (av && av.tagName === 'IMG' && av.getAttribute('src')) { list.push({ src: av.getAttribute('src'), alt: av.getAttribute('alt') || '', kind: 'avatar' }); }
        var cov = book.querySelector('.pc-cover');
        if (cov) {
            var m = /url\(["']?([^"')]+)["']?\)/.exec(cov.style.backgroundImage || '');
            if (m) { list.push({ src: m[1], alt: '', kind: 'cover' }); }
        }
        return list;
    }

    function build() {
        if (box) { return; }
        box = document.createElement('div');
        box.className = 'pv-overlay';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.hidden = true;
        box.innerHTML = '<button type="button" class="pv-btn pv-close" aria-label="Закрыть / Close">&times;</button>' +
            '<button type="button" class="pv-btn pv-prev" aria-label="&larr;">&#8249;</button>' +
            '<img class="pv-img" alt="">' +
            '<button type="button" class="pv-btn pv-next" aria-label="&rarr;">&#8250;</button>';
        var st = document.createElement('style');
        st.textContent = '.pv-overlay{position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.92);display:flex;align-items:center;justify-content:center;touch-action:pan-y}' +
            '.pv-overlay[hidden]{display:none}' +
            '.pv-img{max-width:100vw;max-height:100vh;width:auto;height:auto;object-fit:contain;user-select:none;-webkit-user-drag:none}' +
            '.pv-btn{position:absolute;background:rgba(255,255,255,.18);color:#fff;border:0;border-radius:50%;width:48px;height:48px;font-size:32px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center}' +
            '.pv-btn:hover,.pv-btn:focus-visible{background:rgba(255,255,255,.35)}' +
            '.pv-close{top:12px;right:12px}.pv-prev{left:12px;top:50%;margin-top:-24px}.pv-next{right:12px;top:50%;margin-top:-24px}' +
            '.pv-single .pv-prev,.pv-single .pv-next{display:none}' +
            '.pc-book .photo-frame .oval img,.pc-book .photo-frame{cursor:zoom-in}';
        document.head.appendChild(st);
        document.body.appendChild(box);
        img = box.querySelector('.pv-img');
        prevB = box.querySelector('.pv-prev');
        nextB = box.querySelector('.pv-next');
        box.addEventListener('click', function (e) { if (e.target === box || e.target === img) { close(); } });
        box.querySelector('.pv-close').addEventListener('click', close);
        prevB.addEventListener('click', function (e) { e.stopPropagation(); step(-1); });
        nextB.addEventListener('click', function (e) { e.stopPropagation(); step(1); });
        var sx = null, sy = null;
        box.addEventListener('touchstart', function (e) { var t = e.touches[0]; sx = t.clientX; sy = t.clientY; }, { passive: true });
        box.addEventListener('touchend', function (e) {
            if (sx === null) { return; }
            var t = e.changedTouches[0], dx = t.clientX - sx, dy = t.clientY - sy;
            sx = null;
            if (Math.abs(dy) > 60 && Math.abs(dy) > Math.abs(dx)) { close(); }
            else if (Math.abs(dx) > 60 && items.length > 1) { step(dx < 0 ? 1 : -1); }
        }, { passive: true });
    }

    function show() {
        var it = items[idx];
        img.src = it.src;
        img.alt = it.alt;
        box.classList.toggle('pv-single', items.length < 2);
    }
    function step(d) { idx = (idx + d + items.length) % items.length; show(); }
    function open(kind) {
        items = collect();
        if (!items.length) { return; }
        idx = 0;
        for (var i = 0; i < items.length; i++) { if (items[i].kind === kind) { idx = i; } }
        build();
        lastFocus = document.activeElement;
        show();
        box.hidden = false;
        document.documentElement.style.overflow = 'hidden';
        box.querySelector('.pv-close').focus();
    }
    function close() {
        if (!box || box.hidden) { return; }
        box.hidden = true;
        img.removeAttribute('src');
        document.documentElement.style.overflow = '';
        if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) {} }
    }

    document.addEventListener('keydown', function (e) {
        if (!box || box.hidden) { return; }
        if (e.key === 'Escape') { close(); }
        else if (e.key === 'ArrowLeft' && items.length > 1) { step(-1); }
        else if (e.key === 'ArrowRight' && items.length > 1) { step(1); }
    });

    book.addEventListener('click', function (e) {
        var t = e.target;
        if (t.closest('.pme-fab, label, button, a, input, .pme-menu')) { return; }
        var frame = t.closest('.photo-frame');
        if (frame) {
            if (document.querySelector('#personAvatarImg[src]')) { e.preventDefault(); open('avatar'); }
            return;
        }
        if (t === book && book.querySelector('.pc-cover')) { open('cover'); }
    });
})();
