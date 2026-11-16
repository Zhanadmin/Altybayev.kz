/* TASK-019: распознавание лиц в браузере (face-api.js + локальные модели /assets/vendor/face-api).
   Замечание владельца 16.11 20:49: отдельного экрана «Лица» нет, эталон лица — аватарка человека.
   - в альбоме: считаем отпечатки аватарок, у которых их ещё нет (или аватар сменили),
     и лица на ещё не проверенных фото — по одному, с прогрессом и кнопкой «Стоп», страница не виснет;
   - на сервер уходят только рамка (доли 0..1) и вектор из 128 чисел — фото никуда не отправляются;
   - в просмотре фото: рамки с именами (при наведении), вопрос «Это X?» с «Да / Нет», «Не он» на рамке;
   - в карточке человека: после смены аватара отпечаток пересчитывается сразу. */
(function () {
    'use strict';
    var MODEL_URL = '/assets/vendor/face-api';
    var AL = window.AL || null;
    var PC = window.FR_PERSON || null;
    var CFG = AL || PC || {};
    var LANG = CFG.lang === 'ru' ? 'ru' : 'kk';
    var STR = {
        kk: {
            loading: 'Бет тану модельдері жүктелуде…', refs: 'Аватарлардан беттер есептелуде', scan: 'Фотодан беттер ізделуде',
            stop: 'Тоқтату', done: 'Беттер тексерілді', questions: 'фотода сұрақ бар: «Бұл кім?»', show: 'Көрсету',
            ask: 'Бұл {name} ме?', yes: 'Иә', no: 'Жоқ', not_him: 'Бұл {name} емес', err_models: 'Бет тану модельдері жүктелмеді',
            no_photos: 'Альбомда фото жоқ', marked: 'Белгіленді: {name}', error: 'Қате'
        },
        ru: {
            loading: 'Загружаем модели распознавания…', refs: 'Считаем лица с аватарок', scan: 'Ищем лица на фото',
            stop: 'Стоп', done: 'Лица проверены', questions: 'фото с вопросом «Кто это?»', show: 'Показать',
            ask: 'Это {name}?', yes: 'Да', no: 'Нет', not_him: 'Это не {name}', err_models: 'Не удалось загрузить модели распознавания',
            no_photos: 'В альбоме нет фото', marked: 'Отмечен(а): {name}', error: 'Ошибка'
        }
    };
    function S(k, name) { var s = (STR[LANG] && STR[LANG][k]) || k; return name != null ? s.replace('{name}', name) : s; }
    function toast(msg, ok) { if (typeof window.showAppToast === 'function') window.showAppToast(msg, ok === false ? 'error' : 'success'); }
    function wait(ms) { return new Promise(function (r) { setTimeout(r, ms || 0); }); }

    function api(url, data) {
        var opts = { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
        if (data) {
            data.csrf_token = CFG.csrf || '';
            opts.method = 'POST';
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(data);
        }
        return fetch(url, opts).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (res) {
                if (r.ok && res && res.success) return res.data || {};
                var err = new Error((res && (res.error || res.message)) || (S('error') + ' ' + r.status));
                err.status = r.status;
                throw err;
            });
        });
    }

    /* ================================================= модели (лениво, один раз) */
    var modelsPromise = null;
    function ensureModels() {
        if (modelsPromise) return modelsPromise;
        modelsPromise = new Promise(function (resolve, reject) {
            if (window.faceapi) { resolve(window.faceapi); return; }
            var s = document.createElement('script');
            s.src = MODEL_URL + '/face-api.js?v=1.7.15';
            s.async = true;
            s.onload = function () { window.faceapi ? resolve(window.faceapi) : reject(new Error(S('err_models'))); };
            s.onerror = function () { reject(new Error(S('err_models'))); };
            document.head.appendChild(s);
        }).then(function (fa) {
            return Promise.all([
                fa.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                fa.nets.faceLandmark68TinyNet.loadFromUri(MODEL_URL),
                fa.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
            ]).then(function () { return fa; });
        });
        modelsPromise.catch(function () { modelsPromise = null; });
        return modelsPromise;
    }

    function loadImage(url) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            img.decoding = 'async';
            img.onload = function () { resolve(img); };
            img.onerror = function () { reject(new Error('img')); };
            img.src = url;
        });
    }

    /* все лица на картинке → [{x,y,w,h,descriptor}] в долях 0..1 */
    function detect(fa, img, inputSize, threshold) {
        var opts = new fa.TinyFaceDetectorOptions({ inputSize: inputSize, scoreThreshold: threshold });
        return fa.detectAllFaces(img, opts).withFaceLandmarks(true).withFaceDescriptors().then(function (dets) {
            var nw = img.naturalWidth || 1, nh = img.naturalHeight || 1;
            return (dets || []).map(function (d) {
                var b = d.detection.box;
                var x = Math.max(0, b.x / nw), y = Math.max(0, b.y / nh);
                return {
                    x: +x.toFixed(5), y: +y.toFixed(5),
                    w: +Math.min(b.width / nw, 1 - x).toFixed(5), h: +Math.min(b.height / nh, 1 - y).toFixed(5),
                    descriptor: Array.prototype.map.call(d.descriptor, function (v) { return +v.toFixed(6); })
                };
            }).filter(function (f) { return f.w > 0.002 && f.h > 0.002; });
        });
    }

    function facesOfPhoto(url) {
        return ensureModels().then(function (fa) {
            return loadImage(url).then(function (img) { return detect(fa, img, 416, 0.45); });
        });
    }

    /* отпечаток с аватарки: самое крупное лицо; не нашли — ещё раз с большим разрешением */
    function referenceOf(url) {
        return ensureModels().then(function (fa) {
            return loadImage(url).then(function (img) {
                return detect(fa, img, 320, 0.4).then(function (faces) {
                    return faces.length ? faces : detect(fa, img, 608, 0.3);
                });
            });
        }).then(function (faces) {
            if (!faces.length) return null;
            faces.sort(function (a, b) { return b.w * b.h - a.w * a.h; });
            return faces[0].descriptor;
        });
    }

    /* ================================================= прогресс */
    var stopRequested = false;
    function progressEl() {
        var el = document.getElementById('frProgress');
        if (el) return el;
        el = document.createElement('div');
        el.id = 'frProgress';
        el.className = 'fr-progress';
        el.setAttribute('role', 'status');
        el.innerHTML = '<div class="fr-progress-row"><span class="fr-progress-txt"></span>' +
            '<button type="button" class="fr-progress-btn" data-fr="stop"></button></div><span class="fr-bar"><i></i></span>';
        el.querySelector('[data-fr="stop"]').textContent = S('stop');
        el.addEventListener('click', function (e) {
            var b = e.target.closest('[data-fr]');
            if (!b) return;
            if (b.getAttribute('data-fr') === 'stop') { stopRequested = true; hideProgress(); }
            if (b.getAttribute('data-fr') === 'show') { hideProgress(); openQuestion(); }
        });
        document.body.appendChild(el);
        return el;
    }
    function setProgress(msg, pct) {
        var el = progressEl();
        el.classList.add('is-on');
        el.classList.remove('is-note');
        el.querySelector('.fr-progress-txt').textContent = msg || '';
        var b = el.querySelector('.fr-progress-btn');
        b.setAttribute('data-fr', 'stop');
        b.textContent = S('stop');
        el.querySelector('.fr-bar > i').style.width = Math.max(2, Math.min(100, pct || 0)) + '%';
    }
    function hideProgress() {
        var el = document.getElementById('frProgress');
        if (el) el.classList.remove('is-on');
    }

    /* ================================================= очередь работы (строго по одному) */
    var running = false;
    var pendingRefs = [];
    var pendingPhotos = [];  // id фото
    var questionIds = [];
    var total = 0, doneCount = 0;

    function itemById(id) {
        var list = (AL && AL.items) || [];
        for (var i = 0; i < list.length; i++) if (list[i].id === id) return list[i];
        return null;
    }

    function enqueuePhotos(ids) {
        ids.forEach(function (id) {
            if (pendingPhotos.indexOf(id) < 0) { pendingPhotos.push(id); total++; }
        });
        run();
    }

    function run() {
        if (running) return;
        if (!pendingRefs.length && !pendingPhotos.length) return;
        running = true;
        stopRequested = false;
        setProgress(S('loading'), 2);
        ensureModels().then(loop, function (e) {
            running = false;
            hideProgress();
            pendingRefs = []; pendingPhotos = []; total = doneCount = 0;
            toast(e.message || S('err_models'), false);
        });
    }

    function loop() {
        if (stopRequested) { finish(true); return; }
        if (pendingRefs.length) {
            var batch = pendingRefs.splice(0, 8);
            var out = [];
            var chain = Promise.resolve();
            batch.forEach(function (r) {
                chain = chain.then(function () {
                    if (stopRequested) return;
                    doneCount++;
                    setProgress(S('refs') + ' · ' + doneCount + ' / ' + total, doneCount / total * 100);
                    return referenceOf(r.avatar).then(function (d) {
                        out.push({ person_id: r.person_id, avatar: r.avatar, descriptor: d });
                    }, function () { /* аватар не открылся — попробуем в другой раз */ }).then(function () { return wait(30); });
                });
            });
            chain.then(function () {
                return out.length ? api('/api/face/references', { refs: out }).catch(function () {}) : null;
            }).then(function () { setTimeout(loop, 0); });
            return;
        }
        if (pendingPhotos.length) {
            var id = pendingPhotos.shift();
            var it = itemById(id);
            doneCount++;
            setProgress(S('scan') + ' · ' + doneCount + ' / ' + total, doneCount / total * 100);
            if (!it || it.type !== 'photo') { setTimeout(loop, 0); return; }
            facesOfPhoto(it.full).then(function (faces) {
                return api('/api/face/save', { media_id: id, faces: faces });
            }).then(function (data) {
                applyMedia(data);
            }, function () { /* одно фото не останавливает остальные */ }).then(function () { setTimeout(loop, 40); });
            return;
        }
        finish(false);
    }

    function finish(stopped) {
        running = false;
        pendingRefs = []; pendingPhotos = [];
        total = doneCount = 0;
        if (stopped) { hideProgress(); return; }
        refreshQuestions(true);
    }

    /* состояние альбома: что посчитать и есть ли вопросы */
    function refreshQuestions(showNote) {
        if (!AL || !AL.face) return Promise.resolve();
        return api('/api/face/album/' + AL.albumId).then(function (st) {
            questionIds = st.questions || [];
            if (!showNote) return st;
            if (questionIds.length) {
                var el = progressEl();
                el.classList.add('is-on', 'is-note');
                el.querySelector('.fr-progress-txt').textContent = questionIds.length + ' ' + S('questions');
                var b = el.querySelector('.fr-progress-btn');
                b.setAttribute('data-fr', 'show');
                b.textContent = S('show');
            } else {
                hideProgress();
            }
            return st;
        }).catch(function () { hideProgress(); });
    }

    function openQuestion() {
        var id = questionIds[0];
        if (!id || !AL) return;
        var idx = -1;
        (AL.items || []).forEach(function (m, i) { if (m.id === id) idx = i; });
        if (idx >= 0 && window.AlbumUI && window.AlbumUI.open) window.AlbumUI.open(idx);
    }

    /* ответ сервера по фото → обновить отметки под фото и рамки, если это фото открыто */
    function applyMedia(data) {
        if (!data || !data.media_id) return;
        var it = itemById(data.media_id);
        if (it && data.people) it.people = data.people;
        var q = questionIds.indexOf(data.media_id);
        var hasAsk = (data.faces || []).some(function (f) { return f.ask; });
        if (hasAsk && q < 0) questionIds.push(data.media_id);
        if (!hasAsk && q >= 0) questionIds.splice(q, 1);
        var media = currentMedia();
        if (media && +media.dataset.mediaId === data.media_id) {
            if (window.AlbumUI && window.AlbumUI.refreshInfo) window.AlbumUI.refreshInfo();
            drawBoxes(data.faces || []);
        }
    }

    /* ================================================= рамки в просмотре фото */
    function stageEl() { return document.querySelector('#alLb .al-lb-stage'); }
    function currentMedia() {
        var st = stageEl();
        return st ? st.querySelector('img[data-media-id]') : null;
    }
    var lastFaces = [];
    var reqSeq = 0;

    function renderLightbox() {
        var st = stageEl();
        if (!st) return;
        st.querySelectorAll('.fr-box').forEach(function (n) { n.remove(); });
        lastFaces = [];
        if (!AL || !AL.face) return;
        var media = currentMedia();
        if (!media) return;
        var id = +media.dataset.mediaId;
        var seq = ++reqSeq;
        api('/api/face/media/' + id).then(function (data) {
            if (seq !== reqSeq) return; // уже листнули дальше
            var m = currentMedia();
            if (m && +m.dataset.mediaId === id) drawBoxes(data.faces || []);
        }).catch(function () {});
    }

    function drawBoxes(faces) {
        var st = stageEl();
        var media = currentMedia();
        if (!st || !media) return;
        lastFaces = faces;
        st.querySelectorAll('.fr-box').forEach(function (n) { n.remove(); });
        if (!faces.length) return;
        if (!media.complete || !media.naturalWidth) {
            media.addEventListener('load', function () { drawBoxes(lastFaces); }, { once: true });
            return;
        }
        var r = media.getBoundingClientRect(), sr = st.getBoundingClientRect();
        var ox = r.left - sr.left, oy = r.top - sr.top;
        faces.forEach(function (f) {
            var b = f.box || [0, 0, 0, 0];
            var d = document.createElement('div');
            d.className = 'fr-box' + (f.ask ? ' is-ask' : '');
            d.style.left = (ox + b[0] * r.width) + 'px';
            d.style.top = (oy + b[1] * r.height) + 'px';
            d.style.width = (b[2] * r.width) + 'px';
            d.style.height = (b[3] * r.height) + 'px';
            var tag = document.createElement('div');
            tag.className = 'fr-tag';
            if (f.ask) {
                var q = document.createElement('span');
                q.textContent = S('ask', f.name);
                tag.appendChild(q);
                tag.appendChild(btn(S('yes'), 'yes', f.id));
                tag.appendChild(btn(S('no'), 'no', f.id));
            } else {
                var a = document.createElement('a');
                a.href = '/person/' + f.person_id;
                a.textContent = f.name;
                tag.appendChild(a);
                if (AL.face.canContribute) {
                    var x = btn('×', 'no', f.id);
                    x.className += ' fr-x';
                    x.setAttribute('aria-label', S('not_him', f.name));
                    x.title = S('not_him', f.name);
                    tag.appendChild(x);
                }
            }
            d.appendChild(tag);
            st.appendChild(d);
        });
    }
    function btn(text, act, faceId) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'fr-btn';
        b.textContent = text;
        b.setAttribute('data-fr-act', act);
        b.setAttribute('data-face', String(faceId));
        return b;
    }

    function onStageClick(e) {
        var b = e.target.closest('[data-fr-act]');
        if (!b) return;
        e.preventDefault();
        e.stopPropagation();
        var yes = b.getAttribute('data-fr-act') === 'yes';
        var faceId = +b.getAttribute('data-face');
        var f = null;
        lastFaces.forEach(function (x) { if (x.id === faceId) f = x; });
        b.disabled = true;
        api(yes ? '/api/face/confirm' : '/api/face/reject', { face_id: faceId }).then(function (data) {
            if (yes && f) toast(S('marked', f.name));
            applyMedia(data);
        }).catch(function (err) { b.disabled = false; toast(err.message, false); });
    }

    /* ================================================= запуск */
    function initAlbum() {
        if (!AL || !AL.face || !AL.face.enabled) return;
        var st = stageEl();
        if (st) {
            st.addEventListener('click', onStageClick, true);
            ['pointerdown', 'touchstart', 'mousedown'].forEach(function (t) {
                st.addEventListener(t, function (e) { if (e.target.closest('.fr-tag')) e.stopPropagation(); }, true);
            });
        }
        window.addEventListener('resize', function () { if (lastFaces.length) drawBoxes(lastFaces); });
        if (!AL.face.canContribute) return;

        var findBtn = document.getElementById('alFindFaces');
        if (findBtn) {
            findBtn.addEventListener('click', function () {
                var ids = (AL.items || []).filter(function (m) { return m.type === 'photo'; }).map(function (m) { return m.id; });
                if (!ids.length) { toast(S('no_photos'), false); return; }
                enqueuePhotos(ids);
            });
        }
        // небольшая пауза: сначала показываем альбом, потом тяжёлая работа
        setTimeout(function () {
            refreshQuestions(false).then(function (st) {
                if (!st) return;
                (st.refs || []).forEach(function (r) { pendingRefs.push(r); total++; });
                if ((st.unscanned || []).length) { enqueuePhotos(st.unscanned); }
                else if (pendingRefs.length) { run(); }
                else if (questionIds.length) { refreshQuestions(true); }
            });
        }, 1200);
    }

    /* карточка человека: аватар новый — пересчитать отпечаток сразу */
    function initPerson() {
        if (!PC || !PC.refs || !PC.refs.length) return;
        setTimeout(function () {
            var out = [];
            var chain = Promise.resolve();
            PC.refs.forEach(function (r) {
                chain = chain.then(function () {
                    return referenceOf(r.avatar).then(function (d) {
                        out.push({ person_id: r.person_id, avatar: r.avatar, descriptor: d });
                    }, function () {});
                });
            });
            chain.then(function () {
                if (out.length) return api('/api/face/references', { refs: out });
            }).catch(function () {});
        }, 2500);
    }

    function init() { initAlbum(); initPerson(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();

    window.FaceRecognition = {
        /* после загрузки нового фото — сразу найти на нём лица */
        onUploaded: function (item) {
            if (!AL || !AL.face || !AL.face.enabled || !AL.face.canContribute) return;
            if (item && item.id && item.type === 'photo') enqueuePhotos([item.id]);
        },
        renderLightbox: renderLightbox
    };
})();
