/* TASK-022: фото (портрет) и обложка человека внутри окна «Өңдеу».
 * Подключается вместе с views/layout/person_media_fields.php (разметка #pmeBox, #pmeOverlay, строки window.PME_T).
 * Глобальные функции: pmeLoad(personId, data), pmeSave().
 * Портрет: загрузка файла → обрезка квадратом в браузере → /api/person/avatar/upload;
 *          фото из альбома → обрезка → /api/album/media/avatar (обрезает сервер).
 * Обложка: загрузка / фото из альбома / готовый фон / удаление — /api/person/cover/save;
 *          положение и затемнение — ползунки, сохраняются кнопкой «Сақтау» окна (pmeSave).
 */
(function () {
    'use strict';

    var T = window.PME_T || {};
    var st = { personId: 0, csrf: '', coverReady: false, cover: { url: null, pos_y: 50, dim: 30 }, saved: { pos_y: 50, dim: 30 } };
    var picker = { mode: 'avatar', offset: 0 };
    var crop = null; // {img, w, h, cx, cy, zoom, source:{file|mediaId}}
    var AR = 120 / 152;      // пропорции овала аватарки на карточке (ширина/высота)
    var CW = 240, CH = Math.round(240 / AR); // холст обрезки

    function $(id) { return document.getElementById(id); }
    function toast(msg, type) {
        if (typeof showAppToast === 'function') { showAppToast(msg, type); } else { alert(msg); }
    }
    function markDirty() { window.pcfDirty = true; } // после закрытия окна страница перезагрузится

    function postForm(url, fd) {
        fd.append('csrf_token', st.csrf);
        fd.append('person_id', String(st.personId));
        return fetch(url, { method: 'POST', body: fd }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok || !data.success) { throw new Error(data.error || ('HTTP ' + res.status)); }
                return data;
            });
        });
    }

    // ---------------------------------------------------------------- предпросмотр
    // TASK-037: смена прямо на карточке человека (views/layout/person_media_card.php)
    function renderCardAvatar(url) {
        var el = $('personAvatarImg');
        if (!el || !url) return;
        var src = url + (url.indexOf('?') < 0 ? '?t=' + Date.now() : '');
        if (el.tagName === 'IMG') { el.src = src; return; }
        var im = document.createElement('img');
        im.id = 'personAvatarImg'; im.alt = ''; im.src = src;
        el.parentNode.replaceChild(im, el);
    }

    function renderCardCover() {
        var book = document.querySelector('.pc-book');
        if (!book) return;
        var c = book.querySelector('.pc-cover'), url = st.cover.url;
        if (!url) {
            if (c) { c.parentNode.removeChild(c); }
            book.classList.remove('has-cover');
            return;
        }
        if (!c) {
            c = document.createElement('div');
            c.className = 'pc-cover';
            c.setAttribute('aria-hidden', 'true');
            book.insertBefore(c, book.firstChild);
        }
        book.classList.add('has-cover');
        c.style.backgroundImage = 'url("' + url.replace(/"/g, '%22') + '")';
        c.style.backgroundPosition = '50% ' + st.cover.pos_y + '%';
        c.style.setProperty('--cover-dim', String(st.cover.dim / 100));
        if (window.pcCoverTone) { window.pcCoverTone(); }
        var rm = $('pmeCoverRemove'); if (rm) { rm.style.display = ''; }
        var tn = $('pmeCoverTuning'); if (tn) { tn.style.display = ''; }
        var p = $('pmePos'); if (p) { p.value = st.cover.pos_y; }
        var d = $('pmeDim'); if (d) { d.value = st.cover.dim; }
    }

    // cfg: {personId, csrf, cover:{url,pos_y,dim}}
    window.pmeCardInit = function (cfg) {
        st.personId = cfg.personId;
        st.csrf = cfg.csrf || '';
        st.coverReady = true;
        st.cover = { url: cfg.cover.url || null, pos_y: cfg.cover.pos_y, dim: cfg.cover.dim };
        st.saved = { pos_y: st.cover.pos_y, dim: st.cover.dim };
        var rm = $('pmeCoverRemove'); if (rm) { rm.style.display = st.cover.url ? '' : 'none'; }
        var tn = $('pmeCoverTuning'); if (tn) { tn.style.display = st.cover.url ? '' : 'none'; }
        var p = $('pmePos'); if (p) { p.value = st.cover.pos_y; }
        var d = $('pmeDim'); if (d) { d.value = st.cover.dim; }
    };
    window.pmeMenuToggle = function (ev) {
        if (ev) { ev.stopPropagation(); }
        var m = $('pmeCoverMenu');
        if (m) { m.hidden = !m.hidden; }
    };
    document.addEventListener('click', function (e) {
        var m = $('pmeCoverMenu');
        if (m && !m.hidden && !m.contains(e.target)) { m.hidden = true; }
    });
    // ползунки меню: сохранить по отпусканию
    window.pmeTuneSave = function () {
        window.pmeTune();
        window.pmeSave();
    };

    function renderAvatar(url) {
        renderCardAvatar(url);
        var box = $('pmeAvatar');
        if (!box) return;
        box.innerHTML = '';
        if (url) {
            var im = document.createElement('img');
            im.alt = '';
            im.src = url + (url.indexOf('?') < 0 ? '?t=' + Date.now() : '');
            box.appendChild(im);
        } else {
            box.textContent = '—';
        }
    }

    function renderCover() {
        renderCardCover();
        var c = $('pmeCover');
        if (!c) return;
        var url = st.cover.url;
        c.style.backgroundImage = url ? 'url("' + url.replace(/"/g, '%22') + '")' : '';
        c.style.backgroundPosition = '50% ' + st.cover.pos_y + '%';
        c.classList.toggle('pme-cover-empty', !url);
        $('pmeCoverDim').style.opacity = String(st.cover.dim / 100);
        $('pmeCoverEmptyText').style.display = url ? 'none' : 'flex';
        $('pmePos').value = st.cover.pos_y;
        $('pmeDim').value = st.cover.dim;
        $('pmeCoverTuning').style.display = url ? 'block' : 'none';
        $('pmeCoverRemove').style.display = url ? 'inline-block' : 'none';
    }

    window.pmeLoad = function (personId, data) {
        var box = $('pmeBox');
        if (!box) return;
        if (!data || !personId) { box.style.display = 'none'; st.personId = 0; return; }
        st.personId = personId;
        st.csrf = data.csrf_token || '';
        st.coverReady = !!data.cover_ready;
        st.cover = { url: data.cover.url || null, pos_y: data.cover.pos_y, dim: data.cover.dim };
        st.saved = { pos_y: data.cover.pos_y, dim: data.cover.dim };
        renderAvatar(data.avatar || '');
        renderCover();
        $('pmeNoMigration').style.display = st.coverReady ? 'none' : 'block';
        $('pmeCoverBlock').classList.toggle('pme-disabled', !st.coverReady);
        box.style.display = 'block';
    };

    // Сохранить положение/затемнение (вызывается кнопкой «Сақтау»). Promise<boolean>
    window.pmeSave = function () {
        if (!st.personId || !st.coverReady || !st.cover.url) { return Promise.resolve(true); }
        if (st.cover.pos_y === st.saved.pos_y && st.cover.dim === st.saved.dim) { return Promise.resolve(true); }
        var fd = new FormData();
        fd.append('action', 'adjust');
        fd.append('pos_y', String(st.cover.pos_y));
        fd.append('dim', String(st.cover.dim));
        return postForm('/api/person/cover/save', fd).then(function (d) {
            st.saved = { pos_y: d.cover.pos_y, dim: d.cover.dim };
            markDirty();
            return true;
        }).catch(function (e) { toast(e.message, 'error'); return false; });
    };

    // ---------------------------------------------------------------- обложка
    function coverAction(fd) {
        var mn = $('pmeCoverMenu'); if (mn) { mn.hidden = true; }
        var btns = document.querySelectorAll('#pmeBox button, #pmeBox label.pme-btn');
        btns.forEach(function (b) { b.classList.add('pme-busy'); });
        fd.append('pos_y', String(st.cover.pos_y));
        fd.append('dim', String(st.cover.dim));
        return postForm('/api/person/cover/save', fd).then(function (d) {
            st.cover = d.cover;
            st.saved = { pos_y: d.cover.pos_y, dim: d.cover.dim };
            renderCover();
            markDirty();
            toast(T.saved, 'success');
        }).catch(function (e) { toast(e.message, 'error'); })
          .then(function () { btns.forEach(function (b) { b.classList.remove('pme-busy'); }); });
    }

    window.pmeCoverFile = function (input) {
        var f = input.files && input.files[0];
        input.value = '';
        if (!f) return;
        var fd = new FormData();
        fd.append('action', 'upload');
        fd.append('photo', f);
        coverAction(fd);
    };
    window.pmeCoverPreset = function (key) {
        var fd = new FormData();
        fd.append('action', 'preset');
        fd.append('preset', key);
        coverAction(fd);
    };
    window.pmeCoverRemove = function () {
        var fd = new FormData();
        fd.append('action', 'remove');
        coverAction(fd);
    };
    window.pmeTune = function () {
        st.cover.pos_y = parseInt($('pmePos').value, 10) || 0;
        st.cover.dim = parseInt($('pmeDim').value, 10) || 0;
        var pc = $('pmeCover');
        if (pc) {
            pc.style.backgroundPosition = '50% ' + st.cover.pos_y + '%';
            $('pmeCoverDim').style.opacity = String(st.cover.dim / 100);
        }
        var cc = document.querySelector('.pc-book .pc-cover');
        if (cc) {
            cc.style.backgroundPosition = '50% ' + st.cover.pos_y + '%';
            cc.style.setProperty('--cover-dim', String(st.cover.dim / 100));
            if (window.pcCoverTone) { window.pcCoverTone(); }
        }
    };

    // ---------------------------------------------------------------- окно выбора / обрезки
    function overlay() {
        var o = $('pmeOverlay');
        if (o && o.parentNode !== document.body) { document.body.appendChild(o); } // не зависеть от стилей модалки
        return o;
    }
    function showPane(name) { // grid | crop
        $('pmeGridPane').style.display = name === 'grid' ? 'block' : 'none';
        $('pmeCropPane').style.display = name === 'crop' ? 'block' : 'none';
    }
    window.pmeClose = function () {
        var o = $('pmeOverlay');
        if (o) o.style.display = 'none';
        crop = null;
    };

    window.pmeOpenPicker = function (mode) {
        picker = { mode: mode, offset: 0 };
        $('pmeGrid').innerHTML = '';
        $('pmeGridTitle').textContent = mode === 'avatar' ? T.pickAvatar : T.pickCover;
        overlay().style.display = 'flex';
        showPane('grid');
        loadPhotos();
    };

    function loadPhotos() {
        var more = $('pmeMore');
        more.style.display = 'none';
        fetch('/api/album/photos?offset=' + picker.offset, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) { throw new Error(d.error || 'Error'); }
                var grid = $('pmeGrid');
                d.items.forEach(function (it) {
                    var b = document.createElement('button');
                    b.type = 'button'; b.className = 'pme-thumb';
                    b.title = it.caption || '';
                    var im = document.createElement('img');
                    im.loading = 'lazy'; im.alt = it.caption || ''; im.src = it.thumb;
                    b.appendChild(im);
                    b.addEventListener('click', function () { onPickPhoto(it); });
                    grid.appendChild(b);
                });
                $('pmeGridEmpty').style.display = grid.children.length ? 'none' : 'block';
                picker.offset = d.next;
                more.style.display = d.more ? 'inline-block' : 'none';
            })
            .catch(function (e) { toast(e.message, 'error'); });
    }
    window.pmePickerMore = loadPhotos;

    function onPickPhoto(it) {
        if (picker.mode === 'cover') {
            var fd = new FormData();
            fd.append('action', 'media');
            fd.append('media_id', String(it.id));
            window.pmeClose();
            coverAction(fd);
        } else {
            startCrop(it.full, { mediaId: it.id });
        }
    }

    window.pmeAvatarFile = function (input) {
        var f = input.files && input.files[0];
        input.value = '';
        if (!f) return;
        var url = URL.createObjectURL(f);
        overlay().style.display = 'flex';
        startCrop(url, { file: f, objectUrl: url });
    };

    function startCrop(src, source) {
        var img = new Image();
        img.onload = function () {
            // рамка = овал аватарки на карточке (.photo-frame .oval 120×152), поэтому пропорции AR
            var fit = Math.min(img.naturalWidth, img.naturalHeight * AR); // ширина области при масштабе 1
            crop = { img: img, w: img.naturalWidth, h: img.naturalHeight, cx: img.naturalWidth / 2, cy: img.naturalHeight / 2, zoom: 1, fit: fit, source: source };
            var cv = $('pmeCropCanvas'); cv.width = CW; cv.height = CH;
            var pv = $('pmeCropPreview'); if (pv) { pv.width = 120; pv.height = 152; }
            $('pmeZoom').value = 100;
            showPane('crop');
            drawCrop();
        };
        img.onerror = function () { toast(T.imgError, 'error'); window.pmeClose(); };
        img.src = src;
    }

    function cropGeom() {
        var rw = crop.fit / crop.zoom, rh = rw / AR;
        var sx = Math.max(0, Math.min(crop.cx - rw / 2, crop.w - rw));
        var sy = Math.max(0, Math.min(crop.cy - rh / 2, crop.h - rh));
        crop.cx = sx + rw / 2; crop.cy = sy + rh / 2;
        return { rw: rw, rh: rh, sx: sx, sy: sy };
    }

    function drawCrop() {
        var cv = $('pmeCropCanvas'), g = cropGeom();
        var ctx = cv.getContext('2d');
        ctx.clearRect(0, 0, cv.width, cv.height);
        ctx.drawImage(crop.img, g.sx, g.sy, g.rw, g.rh, 0, 0, cv.width, cv.height);
        var pv = $('pmeCropPreview');
        if (pv) {
            var pc = pv.getContext('2d');
            pc.clearRect(0, 0, pv.width, pv.height);
            pc.drawImage(crop.img, g.sx, g.sy, g.rw, g.rh, 0, 0, pv.width, pv.height);
        }
    }

    // ALT-043: точный сдвиг кнопками-стрелками (шаг — 6 % области кадра)
    window.pmeMove = function (dx, dy) {
        if (!crop) return;
        var g = cropGeom();
        crop.cx += dx * g.rw * 0.06;
        crop.cy += dy * g.rh * 0.06;
        drawCrop();
    };

    window.pmeZoomStep = function (dir) {
        if (!crop) return;
        crop.zoom = Math.max(1, Math.min(4, crop.zoom + dir * 0.2));
        $('pmeZoom').value = Math.round(crop.zoom * 100);
        drawCrop();
    };

    window.pmeZoom = function () {
        if (!crop) return;
        crop.zoom = Math.max(100, Math.min(400, parseInt($('pmeZoom').value, 10) || 100)) / 100;
        drawCrop();
    };

    function initDrag() {
        var cv = $('pmeCropCanvas');
        if (!cv || cv.dataset.pmeBound) return;
        cv.dataset.pmeBound = '1';
        var pts = {}, pinch = 0; // ALT-043: активные касания, расстояние между двумя пальцами
        function setZoom(z) {
            crop.zoom = Math.max(1, Math.min(4, z));
            $('pmeZoom').value = Math.round(crop.zoom * 100);
            drawCrop();
        }
        function dist() {
            var k = Object.keys(pts);
            if (k.length < 2) return 0;
            return Math.hypot(pts[k[0]].x - pts[k[1]].x, pts[k[0]].y - pts[k[1]].y);
        }
        cv.addEventListener('pointerdown', function (e) {
            pts[e.pointerId] = { x: e.clientX, y: e.clientY };
            cv.setPointerCapture(e.pointerId);
            pinch = dist();
        });
        cv.addEventListener('pointermove', function (e) {
            if (!crop || !pts[e.pointerId]) return;
            var prev = pts[e.pointerId];
            pts[e.pointerId] = { x: e.clientX, y: e.clientY };
            if (Object.keys(pts).length >= 2) {
                var d = dist();
                if (pinch > 0 && d > 0) { setZoom(crop.zoom * d / pinch); }
                pinch = d;
                return;
            }
            var k = cropGeom().rw / cv.getBoundingClientRect().width;
            crop.cx -= (e.clientX - prev.x) * k;
            crop.cy -= (e.clientY - prev.y) * k;
            drawCrop();
        });
        var end = function (e) { delete pts[e.pointerId]; pinch = dist(); };
        cv.addEventListener('pointerup', end);
        cv.addEventListener('pointercancel', end);
        cv.addEventListener('wheel', function (e) {
            if (!crop) return;
            e.preventDefault();
            setZoom(crop.zoom * (e.deltaY < 0 ? 1.1 : 1 / 1.1));
        }, { passive: false });
    }

    window.pmeCropApply = function () {
        if (!crop) return;
        var g = cropGeom(), src = crop.source, btn = $('pmeCropApply');
        btn.disabled = true;
        var done = function () { btn.disabled = false; };
        var ok = function (url) {
            renderAvatar(url);
            markDirty();
            toast(T.saved, 'success');
            window.pmeClose();
            if (src.objectUrl) { URL.revokeObjectURL(src.objectUrl); }
        };
        if (src.mediaId) {
            var fd = new FormData();
            fd.append('id', String(src.mediaId));
            fd.append('crop_x', (g.sx / crop.w).toFixed(5));
            fd.append('crop_y', (g.sy / crop.h).toFixed(5));
            fd.append('crop_w', (g.rw / crop.w).toFixed(5));
            fd.append('crop_h', (g.rh / crop.h).toFixed(5));
            postForm('/api/album/media/avatar', fd).then(function (d) { ok(d.avatar_url); })
                .catch(function (e) { toast(e.message, 'error'); }).then(done);
        } else {
            var outW = Math.max(16, Math.min(480, Math.round(g.rw))), outH = Math.round(outW / AR), c = document.createElement('canvas');
            c.width = outW; c.height = outH;
            c.getContext('2d').drawImage(crop.img, g.sx, g.sy, g.rw, g.rh, 0, 0, outW, outH);
            c.toBlob(function (blob) {
                if (!blob) { toast(T.imgError, 'error'); done(); return; }
                var fd2 = new FormData();
                fd2.append('photo', blob, 'avatar.jpg');
                postForm('/api/person/avatar/upload', fd2).then(function (d) { ok(d.avatar_url); })
                    .catch(function (e) { toast(e.message, 'error'); }).then(done);
            }, 'image/jpeg', 0.9);
        }
    };

    document.addEventListener('DOMContentLoaded', initDrag);
    if (document.readyState !== 'loading') { initDrag(); }
})();
