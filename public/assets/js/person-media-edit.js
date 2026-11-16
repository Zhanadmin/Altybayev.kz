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
    var CS = 300;    // размер холста обрезки

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
    function renderAvatar(url) {
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
        $('pmeCover').style.backgroundPosition = '50% ' + st.cover.pos_y + '%';
        $('pmeCoverDim').style.opacity = String(st.cover.dim / 100);
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
            var min = Math.min(img.naturalWidth, img.naturalHeight);
            crop = { img: img, w: img.naturalWidth, h: img.naturalHeight, cx: img.naturalWidth / 2, cy: img.naturalHeight / 2, zoom: 1, min: min, source: source };
            $('pmeZoom').value = 100;
            showPane('crop');
            drawCrop();
        };
        img.onerror = function () { toast(T.imgError, 'error'); window.pmeClose(); };
        img.src = src;
    }

    function cropGeom() {
        var side = crop.min / crop.zoom;
        var sx = Math.max(0, Math.min(crop.cx - side / 2, crop.w - side));
        var sy = Math.max(0, Math.min(crop.cy - side / 2, crop.h - side));
        crop.cx = sx + side / 2; crop.cy = sy + side / 2;
        return { side: side, sx: sx, sy: sy };
    }

    function drawCrop() {
        var cv = $('pmeCropCanvas'), g = cropGeom();
        var ctx = cv.getContext('2d');
        ctx.clearRect(0, 0, CS, CS);
        ctx.drawImage(crop.img, g.sx, g.sy, g.side, g.side, 0, 0, CS, CS);
    }

    window.pmeZoom = function () {
        if (!crop) return;
        crop.zoom = Math.max(100, Math.min(400, parseInt($('pmeZoom').value, 10) || 100)) / 100;
        drawCrop();
    };

    function initDrag() {
        var cv = $('pmeCropCanvas');
        if (!cv || cv.dataset.pmeBound) return;
        cv.dataset.pmeBound = '1';
        var last = null;
        cv.addEventListener('pointerdown', function (e) { last = { x: e.clientX, y: e.clientY }; cv.setPointerCapture(e.pointerId); });
        cv.addEventListener('pointermove', function (e) {
            if (!last || !crop) return;
            var k = cropGeom().side / cv.getBoundingClientRect().width;
            crop.cx -= (e.clientX - last.x) * k;
            crop.cy -= (e.clientY - last.y) * k;
            last = { x: e.clientX, y: e.clientY };
            drawCrop();
        });
        var end = function () { last = null; };
        cv.addEventListener('pointerup', end);
        cv.addEventListener('pointercancel', end);
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
            fd.append('crop_s', (g.side / crop.min).toFixed(5));
            postForm('/api/album/media/avatar', fd).then(function (d) { ok(d.avatar_url); })
                .catch(function (e) { toast(e.message, 'error'); }).then(done);
        } else {
            var out = Math.min(600, Math.round(g.side)), c = document.createElement('canvas');
            c.width = out; c.height = out;
            c.getContext('2d').drawImage(crop.img, g.sx, g.sy, g.side, g.side, 0, 0, out, out);
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
