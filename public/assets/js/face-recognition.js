/* TASK-019: распознавание лиц в браузере (face-api.js + локальные модели).
   - модели загружаются лениво из /assets/vendor/face-api (без внешних API/облаков);
   - лица ищутся порциями с прогрессом, страница не виснет;
   - на сервер уходят только координаты рамки и вектор-отпечаток;
   - авто-подсказки «Это X?» и рамки с именами в полноэкранном просмотре. */
(function () {
    'use strict';
    var AL = window.AL || {};
    var MODEL_URL = '/assets/vendor/face-api';
    var LANG = AL.lang === 'ru' ? 'ru' : 'kk';
    var STR = {
        kk: {
            loading: 'Модельдер жүктелуде…', scan: 'Беттерді іздеу', done: 'Дайын',
            no_photos: 'Альбомда фото жоқ', found: 'бет табылды',
            is: 'Бұл', confirm: '— бет байланды; «Лица» бөлімінде алып тастауға болады', error: 'Қате', err_models: 'Модельдер жүктелмеді'
        },
        ru: {
            loading: 'Загружаем модели…', scan: 'Ищем лица', done: 'Готово',
            no_photos: 'В альбоме нет фото', found: 'лиц найдено',
            is: 'Это', confirm: '— лицо привязано; убрать можно в разделе «Лица»', error: 'Ошибка', err_models: 'Не удалось загрузить модели'
        }
    };
    function S(k) { return (STR[LANG] && STR[LANG][k]) || k; }

    function toast(msg, kind) {
        if (typeof window.showAppToast === 'function') { window.showAppToast(msg, kind || 'info'); }
        else if (typeof console !== 'undefined') { console.log(msg); }
    }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* POST JSON (для массивов лиц) с csrf_token */
    function postJson(url, data) {
        data = data || {};
        data.csrf_token = AL.csrf || '';
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data)
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (res) {
                if (r.ok && res && res.success) { return res; }
                var err = new Error((res && (res.error || res.message)) || (S('error') + ' ' + r.status));
                err.status = r.status;
                throw err;
            });
        });
    }

    var libPromise = null;
    function ensureLib() {
        if (window.faceapi) { return Promise.resolve(window.faceapi); }
        if (libPromise) { return libPromise; }
        libPromise = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = MODEL_URL + '/face-api.js';
            s.async = true;
            s.onload = function () { resolve(window.faceapi); };
            s.onerror = function () { reject(new Error(S('err_models'))); };
            document.head.appendChild(s);
        });
        return libPromise;
    }

    var modelsPromise = null;
    function ensureModels() {
        if (modelsPromise) { return modelsPromise; }
        modelsPromise = ensureLib().then(function (fa) {
            return Promise.all([
                fa.nets.tinyFaceDetector.loadFromUri(MODEL_URL + '/'),
                fa.nets.faceLandmark68TinyNet.loadFromUri(MODEL_URL + '/'),
                fa.nets.faceRecognitionNet.loadFromUri(MODEL_URL + '/')
            ]).then(function () { return fa; });
        });
        return modelsPromise;
    }

    /* Найти лица на одном фото (URL) → [{x,y,w,h,descriptor}] в долях 0..1 */
    function detectUrl(url) {
        return ensureModels().then(function (fa) {
            return new Promise(function (resolve, reject) {
                var img = new Image();
                img.src = url;
                img.onload = function () {
                    try {
                        var opts = new fa.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.4 });
                        fa.detectAllFaces(img, opts).withFaceLandmarks().withFaceDescriptors().then(function (dets) {
                            resolve((dets || []).map(function (d) {
                                var b = d.detection.box;
                                var nw = img.naturalWidth || 1, nh = img.naturalHeight || 1;
                                return {
                                    x: b.x / nw, y: b.y / nh,
                                    w: b.width / nw, h: b.height / nh,
                                    descriptor: Array.from(d.descriptor)
                                };
                            }));
                        }).catch(reject);
                    } catch (e) { reject(e); }
                };
                img.onerror = function () { reject(new Error('img')); };
            });
        });
    }

    /* --- прогресс --- */
    function progressEl() {
        var el = document.getElementById('frProgress');
        if (el) { return el; }
        el = document.createElement('div');
        el.id = 'frProgress';
        el.className = 'fr-progress';
        el.innerHTML = '<span class="fr-progress-txt"></span><span class="fr-bar"><i></i></span>';
        document.body.appendChild(el);
        return el;
    }
    function setProgress(msg, pct) {
        var el = progressEl();
        el.classList.add('is-on');
        el.querySelector('.fr-progress-txt').textContent = msg || '';
        el.querySelector('.fr-bar > i').style.width = Math.max(0, Math.min(100, pct || 0)) + '%';
    }
    function hideProgress() {
        var el = document.getElementById('frProgress');
        if (el) { el.classList.remove('is-on'); }
    }

    function photoItems() {
        return (AL.items || []).filter(function (m) { return m.type === 'photo'; });
    }

    function showSuggestions(res) {
        var data = res && res.data ? res.data : {};
        var sugg = data.suggestions || [];
        if (!sugg.length) { return 0; }
        var names = {};
        sugg.forEach(function (s) { if (s.name) { names[s.name] = 1; } });
        var list = Object.keys(names);
        if (list.length) {
            toast(S('is') + ' ' + list.slice(0, 3).join(', ') + '? ' + S('confirm'));
        }
        return sugg.length;
    }

    /* «Найти лица» по всем фото альбома — порциями, с прогрессом */
    function findAll(onDone) {
        var photos = photoItems();
        if (!photos.length) { toast(S('no_photos')); if (onDone) { onDone(); } return; }
        setProgress(S('loading'), 1);
        var i = 0, total = photos.length, found = 0, sugg = 0;
        function step() {
            if (i >= total) {
                hideProgress();
                toast(S('done') + ': ' + found + ' ' + S('found'));
                if (onDone) { onDone(); }
                return;
            }
            var m = photos[i];
            setProgress(S('scan') + ' ' + (i + 1) + ' / ' + total + ' · ' + esc(m.caption || ('#' + m.id)), Math.round(i / total * 100));
            detectUrl(m.full).then(function (faces) {
                found += faces.length;
                return saveOne(m, faces);
            }).then(function (res) {
                sugg += showSuggestions(res);
            }).catch(function () {
                /* одно фото не должно останавливать остальные */
            }).then(function () {
                i++;
                setTimeout(step, 0); // следующий кадр — страница не виснет
            });
        }
        step();
    }

    function saveOne(media, faces) {
        return postJson('/api/face/save', { media_id: media.id, faces: faces });
    }

    /* после загрузки нового фото — сразу предложить найти лица (авто-подсказка «Это X?») */
    function onUploaded(item) {
        if (!item || !item.id || item.type !== 'photo') { return; }
        setProgress(S('scan') + ' 1 / 1', 10);
        detectUrl(item.full).then(function (faces) {
            if (!faces.length) { hideProgress(); return null; }
            return saveOne(item, faces);
        }).then(function (res) {
            hideProgress();
            if (res) { showSuggestions(res); }
        }).catch(function () { hideProgress(); });
    }

    /* рамки лиц в полноэкранном просмотре (вызывается из album.js) */
    function renderLightbox() {
        var stage = document.querySelector('#alLb .al-lb-stage');
        if (!stage) { return; }
        stage.querySelectorAll('.fr-boxes').forEach(function (n) { n.remove(); });
        var media = stage.querySelector('img[data-media-id]');
        if (!media || !media.dataset.mediaId) { return; }
        var mediaId = media.dataset.mediaId;
        fetch('/api/face/media/' + mediaId, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(function (res) {
            var faces = (res && res.data) || [];
            if (faces.length) { renderBoxes(stage, media, faces); }
        }).catch(function () {});
    }

    function renderBoxes(stage, media, faces) {
        if (!media.naturalWidth) {
            media.addEventListener('load', function () { renderBoxes(stage, media, faces); }, { once: true });
            return;
        }
        var rect = media.getBoundingClientRect();
        var srect = stage.getBoundingClientRect();
        var ox = rect.left - srect.left;
        var oy = rect.top - srect.top;
        faces.forEach(function (f) {
            var b = f.box || [0, 0, 0, 0];
            var d = document.createElement('div');
            d.className = 'fr-boxes' + (f.name ? ' is-named' : '');
            d.style.left = (ox + b[0] * rect.width) + 'px';
            d.style.top = (oy + b[1] * rect.height) + 'px';
            d.style.width = (b[2] * rect.width) + 'px';
            d.style.height = (b[3] * rect.height) + 'px';
            if (f.name) {
                var s = document.createElement('span');
                s.className = 'fr-box-name';
                s.textContent = f.name;
                d.appendChild(s);
            }
            stage.appendChild(d);
        });
    }

    function init() {
        var btn = document.getElementById('alFindFaces');
        if (btn) { btn.addEventListener('click', function () { findAll(); }); }
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
    else { init(); }

    window.FaceRecognition = {
        findAll: findAll,
        onUploaded: onUploaded,
        renderLightbox: renderLightbox,
        ensureModels: ensureModels
    };
})();
