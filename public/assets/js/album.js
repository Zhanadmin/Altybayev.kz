/* TASK-018: фотоальбомы — загрузка по частям с прогрессом, превью видео, просмотр со свайпом, отметки людей.
   Данные страницы — в window.AL (csrf, albumId, chunkSize, items, people, t — строки на выбранном языке). */
(function () {
    'use strict';
    var AL = window.AL || {};
    var T = AL.t || {};
    function t(k) { return T[k] || k; }

    function toast(msg, kind) {
        if (typeof window.showAppToast === 'function') { window.showAppToast(msg, kind || 'error'); } else { alert(msg); }
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function fmtBytes(n) {
        if (n < 1024 * 1024) return Math.max(1, Math.round(n / 1024)) + ' KB';
        if (n < 1024 * 1024 * 1024) return (n / 1048576).toFixed(1) + ' MB';
        return (n / 1073741824).toFixed(2) + ' GB';
    }

    function fmtDur(s) {
        s = Math.round(s || 0);
        if (!s) return '';
        var m = Math.floor(s / 60), r = s % 60;
        return m + ':' + (r < 10 ? '0' : '') + r;
    }

    /* POST: FormData или объект → JSON-ответ. XHR — ради прогресса загрузки. */
    function post(url, data, onProgress) {
        return new Promise(function (resolve, reject) {
            var fd;
            if (data instanceof FormData) { fd = data; } else {
                fd = new FormData();
                Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k] == null ? '' : data[k]); });
            }
            if (!fd.has('csrf_token')) fd.append('csrf_token', AL.csrf || '');
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');
            if (onProgress && xhr.upload) {
                xhr.upload.onprogress = function (e) { if (e.lengthComputable) onProgress(e.loaded, e.total); };
            }
            xhr.onload = function () {
                var res = null;
                try { res = JSON.parse(xhr.responseText); } catch (e) { res = null; }
                if (xhr.status >= 200 && xhr.status < 300 && res && res.success) { resolve(res); return; }
                var err = new Error((res && (res.error || res.message)) || (t('err_server') + ' (' + xhr.status + ')'));
                err.status = xhr.status;
                err.data = res;
                reject(err);
            };
            xhr.onerror = function () { var e = new Error(t('err_network')); e.status = 0; reject(e); };
            xhr.send(fd);
        });
    }
    AL.post = post;

    /* ===================================================== выбор людей (чипы) */
    var peopleById = {};
    var peopleByName = {};
    (AL.people || []).forEach(function (p) { peopleById[p.id] = p.name; peopleByName[p.name.toLowerCase()] = p.id; });

    function Picker(root) {
        this.root = root;
        this.input = root.querySelector('input[type="text"]');
        this.hidden = root.querySelector('input[type="hidden"]');
        this.box = root.querySelector('.al-picked');
        this.ids = [];
        var self = this;
        (this.hidden.value || '').split(',').forEach(function (v) { v = parseInt(v, 10); if (v > 0) self.add(v, true); });
        this.render();
        var tryAdd = function () {
            var v = self.input.value.trim().toLowerCase();
            if (!v) return;
            var id = peopleByName[v];
            if (id) { self.add(id); self.input.value = ''; }
        };
        this.input.addEventListener('change', tryAdd);
        this.input.addEventListener('input', function (e) { if (!e.inputType || e.inputType === 'insertReplacementText') tryAdd(); });
        this.input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); tryAdd(); } });
    }
    Picker.prototype.add = function (id, silent) {
        if (!peopleById[id] || this.ids.indexOf(id) >= 0) return;
        this.ids.push(id);
        if (!silent) this.render();
    };
    Picker.prototype.set = function (ids) { this.ids = []; var s = this; (ids || []).forEach(function (i) { s.add(i, true); }); this.render(); };
    Picker.prototype.render = function () {
        var self = this;
        this.hidden.value = this.ids.join(',');
        this.box.innerHTML = this.ids.map(function (id) {
            return '<span class="badge">' + esc(peopleById[id]) + '<button type="button" data-rm="' + id + '" aria-label="' + esc(t('remove')) + '">×</button></span>';
        }).join('');
        this.box.querySelectorAll('[data-rm]').forEach(function (b) {
            b.addEventListener('click', function () {
                var id = parseInt(b.getAttribute('data-rm'), 10);
                self.ids = self.ids.filter(function (x) { return x !== id; });
                self.render();
            });
        });
    };
    var pickers = {};
    document.querySelectorAll('.al-picker').forEach(function (el) { pickers[el.id] = new Picker(el); });
    AL.pickers = pickers;

    /* ===================================================== простые формы (альбом, мероприятие) */
    document.querySelectorAll('form[data-api]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = form.querySelector('[type="submit"]');
            if (btn) btn.disabled = true;
            post(form.getAttribute('data-api'), new FormData(form)).then(function (res) {
                if (res.url) { window.location.href = res.url; } else { window.location.reload(); }
            }).catch(function (err) {
                toast(err.message);
                if (btn) btn.disabled = false;
            });
        });
    });
    document.querySelectorAll('[data-delete-api]').forEach(function (b) {
        b.addEventListener('click', function () {
            if (!confirm(b.getAttribute('data-confirm') || t('confirm_delete'))) return;
            post(b.getAttribute('data-delete-api'), { id: b.getAttribute('data-id') }).then(function (res) {
                window.location.href = res.url || '/';
            }).catch(function (err) { toast(err.message); });
        });
    });
    document.querySelectorAll('[data-open-modal]').forEach(function (b) {
        b.addEventListener('click', function () { openModal(b.getAttribute('data-open-modal')); });
    });
    document.querySelectorAll('[data-close-modal]').forEach(function (b) {
        b.addEventListener('click', function () { closeModal(b.closest('.modal-backdrop')); });
    });
    function openModal(id) {
        var m = document.getElementById(id);
        if (!m) return;
        m.style.display = 'flex';
        var f = m.querySelector('input:not([type=hidden]), textarea, select');
        if (f) setTimeout(function () { f.focus(); }, 30);
    }
    function closeModal(m) { if (m) m.style.display = 'none'; }
    AL.openModal = openModal;
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') document.querySelectorAll('.modal-backdrop').forEach(function (m) { if (m.style.display === 'flex') closeModal(m); });
    });

    /* ===================================================== сетка */
    var items = AL.items || [];
    var grid = document.getElementById('alGrid');
    var emptyBox = document.getElementById('alEmpty');

    var PLAY = '<svg viewBox="0 0 24 24"><path d="M7 4.5v15l13-7.5z"/></svg>';
    function tileHtml(it, i) {
        var inner;
        if (it.thumb) {
            inner = '<img src="' + esc(it.thumb) + '" alt="' + esc(it.caption || '') + '" loading="lazy" decoding="async">';
        } else {
            inner = '<span class="al-noposter"><svg class="ic"><use href="#i-camera"/></svg></span>';
        }
        if (it.type === 'video') {
            inner += '<span class="al-play"><span>' + PLAY + '</span></span>';
            if (it.duration) inner += '<span class="al-dur">' + fmtDur(it.duration) + '</span>';
        }
        var label = (it.type === 'video' ? t('video') : t('photo')) + (it.caption ? ': ' + it.caption : '');
        return '<button type="button" class="al-tile" data-i="' + i + '" aria-label="' + esc(label) + '">' + inner + '</button>';
    }
    function renderGrid() {
        if (!grid) return;
        grid.innerHTML = items.map(tileHtml).join('');
        if (emptyBox) emptyBox.style.display = items.length ? 'none' : '';
        var cnt = document.getElementById('alCount');
        if (cnt) cnt.textContent = items.length;
    }
    if (grid) {
        grid.addEventListener('click', function (e) {
            var b = e.target.closest('.al-tile');
            if (b) openLb(parseInt(b.getAttribute('data-i'), 10));
        });
        renderGrid();
    }

    /* ===================================================== загрузка */
    var fileInput = document.getElementById('alFiles');
    var drop = document.getElementById('alDrop');
    var queueBox = document.getElementById('alQueue');
    var queue = [];
    var running = 0;
    var MAX_PARALLEL = 2;

    if (fileInput) {
        fileInput.addEventListener('change', function () { addFiles(fileInput.files); fileInput.value = ''; });
    }
    if (drop) {
        ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
        ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); }); });
        drop.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files) addFiles(e.dataTransfer.files); });
        // файл, брошенный мимо зоны, не должен открываться браузером вместо страницы
        ['dragover', 'drop'].forEach(function (ev) {
            window.addEventListener(ev, function (e) { if (!drop.contains(e.target)) e.preventDefault(); });
        });
    }

    function kindOf(f) {
        var n = (f.name || '').toLowerCase();
        if (/\.(jpe?g|png|webp)$/.test(n)) return 'photo';
        if (/\.(mp4|m4v|mov|webm)$/.test(n)) return 'video';
        return null;
    }

    function addFiles(list) {
        Array.prototype.forEach.call(list || [], function (f) {
            var row = document.createElement('div');
            row.className = 'al-q';
            row.innerHTML = '<span class="nm"></span><span class="st"></span><span class="al-bar"><i></i></span>';
            row.querySelector('.nm').textContent = f.name + ' · ' + fmtBytes(f.size);
            queueBox.appendChild(row);
            var job = { file: f, row: row, kind: kindOf(f) };
            if (!job.kind) { fail(job, t('err_type')); return; }
            var max = (job.kind === 'video' ? AL.videoMaxMb : AL.photoMaxMb) * 1048576;
            if (f.size > max) { fail(job, t('err_big') + ' ' + (job.kind === 'video' ? AL.videoMaxMb : AL.photoMaxMb) + ' MB'); return; }
            status(job, t('waiting'));
            queue.push(job);
        });
        pump();
    }
    function status(job, s, pct) {
        job.row.querySelector('.st').textContent = s;
        if (pct != null) job.row.querySelector('.al-bar > i').style.width = Math.max(0, Math.min(100, pct)) + '%';
    }
    function fail(job, msg) {
        job.row.classList.add('is-err');
        status(job, msg, 100);
    }
    function pump() {
        while (running < MAX_PARALLEL && queue.length) {
            var job = queue.shift();
            running++;
            uploadOne(job).catch(function () {}).then(function () { running--; pump(); });
        }
    }

    window.addEventListener('beforeunload', function (e) {
        if (running > 0 || queue.length > 0) { e.preventDefault(); e.returnValue = ''; }
    });

    function uploadOne(job) {
        var f = job.file;
        var uploadId, chunk;
        status(job, t('starting'), 0);
        return post('/api/album/upload/init', { album_id: AL.albumId, name: f.name, size: f.size }).then(function (res) {
            uploadId = res.upload_id;
            chunk = res.chunk_size || AL.chunkSize || 1048576;
            return sendChunks(job, uploadId, chunk, 0);
        }).then(function () {
            status(job, t('processing'), 100);
            return job.kind === 'video' ? videoPoster(f) : Promise.resolve(null);
        }).then(function (poster) {
            var fd = new FormData();
            fd.append('upload_id', uploadId);
            fd.append('caption', valueOf('alCaption'));
            fd.append('taken_date', valueOf('alDate'));
            fd.append('people', valueOf('alUploadPeopleIds'));
            if (poster && poster.blob) fd.append('poster', poster.blob, 'poster.jpg');
            if (poster && poster.duration) fd.append('duration', String(Math.round(poster.duration)));
            return post('/api/album/upload/complete', fd);
        }).then(function (res) {
            job.row.classList.add('is-ok');
            status(job, t('done'), 100);
            if (res.item) { items.push(res.item); renderGrid(); }
            setTimeout(function () { if (job.row.parentNode) job.row.parentNode.removeChild(job.row); }, 4000);
        }).catch(function (err) {
            fail(job, err.message || t('err_server'));
            if (uploadId) post('/api/album/upload/cancel', { upload_id: uploadId }).catch(function () {});
            throw err;
        });
    }
    function valueOf(id) { var el = document.getElementById(id); return el ? el.value : ''; }

    function sendChunks(job, uploadId, chunk, offset, tries) {
        var f = job.file;
        tries = tries || 0;
        if (offset >= f.size) return Promise.resolve();
        var end = Math.min(f.size, offset + chunk);
        var fd = new FormData();
        fd.append('upload_id', uploadId);
        fd.append('offset', String(offset));
        fd.append('chunk', f.slice(offset, end), 'chunk.bin');
        return post('/api/album/upload/chunk', fd, function (loaded) {
            var pct = ((offset + loaded) / f.size) * 100;
            status(job, Math.floor(pct) + '% · ' + fmtBytes(offset + loaded) + ' / ' + fmtBytes(f.size), pct);
        }).then(function (res) {
            return sendChunks(job, uploadId, chunk, res.received, 0);
        }).catch(function (err) {
            if (err.status === 409 && err.data && typeof err.data.received === 'number') {
                return sendChunks(job, uploadId, chunk, err.data.received, tries + 1);
            }
            if ((err.status === 0 || err.status >= 500) && tries < 4) {
                status(job, t('retry'));
                return new Promise(function (r) { setTimeout(r, 1500 * (tries + 1)); }).then(function () {
                    return sendChunks(job, uploadId, chunk, offset, tries + 1);
                });
            }
            throw err;
        });
    }

    /* Кадр из видео для превью (в браузере; сервер пересохраняет его через GD) */
    function videoPoster(file) {
        return new Promise(function (resolve) {
            var done = false;
            var url = URL.createObjectURL(file);
            var v = document.createElement('video');
            v.muted = true; v.playsInline = true; v.preload = 'metadata';
            var finish = function (val) { if (done) return; done = true; URL.revokeObjectURL(url); resolve(val); };
            var timer = setTimeout(function () {
                finish(isFinite(v.duration) && v.duration > 0 ? { duration: v.duration } : null);
            }, 8000);
            v.addEventListener('loadedmetadata', function () {
                var d = isFinite(v.duration) ? v.duration : 0;
                try { v.currentTime = Math.min(1, d > 0 ? d / 3 : 0.1); } catch (e) { clearTimeout(timer); finish({ duration: d }); }
            });
            v.addEventListener('seeked', function () {
                try {
                    var w = v.videoWidth, h = v.videoHeight;
                    if (!w || !h) { clearTimeout(timer); finish({ duration: v.duration }); return; }
                    var k = Math.min(1, 720 / Math.max(w, h));
                    var c = document.createElement('canvas');
                    c.width = Math.round(w * k); c.height = Math.round(h * k);
                    c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
                    c.toBlob(function (b) { clearTimeout(timer); finish({ blob: b, duration: v.duration }); }, 'image/jpeg', 0.82);
                } catch (e) { clearTimeout(timer); finish({ duration: v.duration }); }
            });
            v.addEventListener('error', function () { clearTimeout(timer); finish(null); });
            v.src = url;
        });
    }

    /* ===================================================== полноэкранный просмотр */
    var lb = document.getElementById('alLb');
    var stage = lb ? lb.querySelector('.al-lb-stage') : null;
    var cur = -1;

    function openLb(i) {
        if (!lb || !items[i]) return;
        cur = i;
        lb.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        showCur();
        lb.querySelector('[data-lb="close"]').focus();
    }
    function closeLb() {
        if (!lb) return;
        var v = stage.querySelector('video');
        if (v) v.pause();
        lb.classList.remove('is-open');
        document.body.style.overflow = '';
        stage.querySelectorAll('img,video').forEach(function (n) { n.remove(); });
        var tile = grid && grid.querySelector('[data-i="' + cur + '"]');
        if (tile) tile.focus();
    }
    function go(d) {
        if (!items.length) return;
        cur = (cur + d + items.length) % items.length;
        showCur();
    }
    function showCur() {
        var it = items[cur];
        stage.querySelectorAll('img,video,.al-lb-vidmsg').forEach(function (n) { if (n.pause) n.pause(); n.remove(); });
        var el;
        if (it.type === 'video') {
            el = document.createElement('video');
            el.controls = true; el.playsInline = true; el.preload = 'metadata';
            if (it.thumb) el.poster = it.thumb;
            el.addEventListener('error', function () {
                // например, HEVC-видео с iPhone в Chrome — предлагаем скачать
                if (stage.querySelector('.al-lb-vidmsg')) return;
                var msg = document.createElement('div');
                msg.className = 'al-lb-vidmsg';
                msg.innerHTML = esc(t('video_unsupported')) + ' <a href="' + esc(it.download) + '">' + esc(t('download')) + '</a>';
                stage.appendChild(msg);
            });
            el.src = it.full;
        } else {
            el = document.createElement('img');
            el.src = it.full;
            el.alt = it.caption || '';
            el.draggable = false;
        }
        stage.insertBefore(el, stage.firstChild);
        lb.querySelector('.al-lb-count').textContent = (cur + 1) + ' / ' + items.length;
        var dl = lb.querySelector('[data-lb="download"]');
        dl.href = it.download;
        lb.querySelector('[data-lb="delete"]').style.display = it.canDelete ? '' : 'none';
        lb.querySelector('[data-lb="edit"]').style.display = it.canEdit ? '' : 'none';
        var av = lb.querySelector('[data-lb="avatar"]');
        av.style.display = (it.type === 'photo' && it.people.length && (AL.canEditAll || it.people.some(function (p) { return p.id === AL.myPersonId; }))) ? '' : 'none';

        var info = lb.querySelector('.al-lb-info');
        var sub = [];
        if (it.date) sub.push(it.date.split('-').reverse().join('.'));
        if (it.uploader) sub.push(t('added_by') + ' ' + it.uploader);
        info.innerHTML = (it.caption ? '<div class="cap">' + esc(it.caption) + '</div>' : '') +
            (sub.length ? '<div class="sub">' + esc(sub.join(' · ')) + '</div>' : '') +
            (it.people.length ? '<div class="ppl">' + it.people.map(function (p) {
                return '<a href="/person/' + p.id + '">' + esc(p.name) + '</a>';
            }).join('') + '</div>' : '');
        // соседние фото — заранее
        [cur + 1, cur - 1].forEach(function (j) {
            var n = items[(j + items.length) % items.length];
            if (n && n.type === 'photo') { var im = new Image(); im.src = n.full; }
        });
    }

    if (lb) {
        lb.addEventListener('click', function (e) {
            var a = e.target.closest('[data-lb]');
            if (!a) { if (e.target === stage) closeLb(); return; }
            var act = a.getAttribute('data-lb');
            if (act === 'close') closeLb();
            else if (act === 'prev') go(-1);
            else if (act === 'next') go(1);
            else if (act === 'delete') delCur();
            else if (act === 'edit') editCur();
            else if (act === 'avatar') avatarCur();
        });
        document.addEventListener('keydown', function (e) {
            if (!lb.classList.contains('is-open')) return;
            if (document.querySelector('.modal-backdrop[style*="flex"]')) return;
            if (e.key === 'Escape') closeLb();
            else if (e.key === 'ArrowLeft') go(-1);
            else if (e.key === 'ArrowRight') go(1);
        });
        // свайп: влево/вправо — листать, вниз — закрыть
        var sx = 0, sy = 0, tracking = false;
        stage.addEventListener('touchstart', function (e) {
            if (e.touches.length !== 1) { tracking = false; return; }
            // нижняя треть видео — панель плеера (перемотка), там не листаем
            var vid = e.target.closest && e.target.closest('video');
            if (vid) {
                var r = vid.getBoundingClientRect();
                if (e.touches[0].clientY > r.top + r.height * 0.66) { tracking = false; return; }
            }
            tracking = true; sx = e.touches[0].clientX; sy = e.touches[0].clientY;
        }, { passive: true });
        stage.addEventListener('touchcancel', function () {
            tracking = false;
            var media = stage.firstChild;
            if (media && media.style) media.style.transform = '';
        });
        stage.addEventListener('touchmove', function (e) {
            if (!tracking) return;
            var dx = e.touches[0].clientX - sx;
            var media = stage.firstChild;
            if (media && media.style) media.style.transform = 'translateX(' + dx + 'px)';
        }, { passive: true });
        stage.addEventListener('touchend', function (e) {
            if (!tracking) return;
            tracking = false;
            var t0 = e.changedTouches[0];
            var dx = t0.clientX - sx, dy = t0.clientY - sy;
            var media = stage.firstChild;
            if (media && media.style) media.style.transform = '';
            if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) { go(dx < 0 ? 1 : -1); }
            else if (dy > 90 && Math.abs(dy) > Math.abs(dx) * 1.5) { closeLb(); }
        });
    }

    function delCur() {
        var it = items[cur];
        if (!it || !confirm(t('confirm_delete_media'))) return;
        post('/api/album/media/delete', { id: it.id }).then(function () {
            items.splice(cur, 1);
            renderGrid();
            if (!items.length) { closeLb(); return; }
            if (cur >= items.length) cur = items.length - 1;
            showCur();
            toast(t('deleted'), 'success');
        }).catch(function (err) { toast(err.message); });
    }

    var editForm = document.getElementById('alEditForm');
    function editCur() {
        var it = items[cur];
        if (!it || !editForm) return;
        editForm.querySelector('[name="id"]').value = it.id;
        editForm.querySelector('[name="caption"]').value = it.caption || '';
        editForm.querySelector('[name="taken_date"]').value = it.date || '';
        if (pickers.alEditPeople) pickers.alEditPeople.set(it.people.map(function (p) { return p.id; }));
        openModal('alEditModal');
    }
    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            e.preventDefault();
            post('/api/album/media/update', new FormData(editForm)).then(function (res) {
                var id = res.item.id;
                for (var i = 0; i < items.length; i++) { if (items[i].id === id) { items[i] = res.item; } }
                renderGrid();
                closeModal(document.getElementById('alEditModal'));
                if (lb.classList.contains('is-open')) showCur();
                toast(t('saved'), 'success');
            }).catch(function (err) { toast(err.message); });
        });
    }

    function avatarCur() {
        var it = items[cur];
        if (!it) return;
        var opts = it.people.filter(function (p) { return AL.canEditAll || p.id === AL.myPersonId; });
        if (!opts.length) return;
        var p = opts[0];
        if (opts.length > 1) {
            var list = opts.map(function (o, i) { return (i + 1) + ') ' + o.name; }).join('\n');
            var ans = prompt(t('avatar_choose') + '\n' + list, '1');
            if (ans === null) return;
            p = opts[parseInt(ans, 10) - 1];
            if (!p) return;
        } else if (!confirm(t('avatar_confirm') + ' ' + p.name + '?')) {
            return;
        }
        post('/api/album/media/avatar', { id: it.id, person_id: p.id }).then(function () {
            toast(t('avatar_done') + ' ' + p.name, 'success');
        }).catch(function (err) { toast(err.message); });
    }
})();
