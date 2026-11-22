/* ALT-014: фоновая музыка по всему сайту. Кнопка ♪ создаётся здесь (один код для всех страниц).
   Состояние: localStorage shezhire_music = '1'/'0', позиция shezhire_music_pos (секунды).
   Одна вкладка: BroadcastChannel 'shezhire_bgm' — при старте в одной вкладке остальные ставят паузу. */
(function () {
    'use strict';
    if (window.__shezhireBgm) return;
    window.__shezhireBgm = true;

    var SRC = '/assets/audio/welcome.mp3';
    var VOL = 0.18;
    var KEY = 'shezhire_music', POS = 'shezhire_music_pos';
    var T = {
        kk: ['Музыканы қосу', 'Музыканы өшіру'],
        ru: ['Включить музыку', 'Выключить музыку'],
        en: ['Turn music on', 'Turn music off'],
        es: ['Activar la música', 'Desactivar la música']
    };
    var lang = (document.documentElement.lang || 'kk').slice(0, 2);
    var L = T[lang] || T.kk;

    function ls(k, v) {
        try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) {}
        return null;
    }

    function init() {
        // на странице приветствия кнопка могла быть в разметке — убираем дубль
        var old = document.getElementById('wlMusic');
        if (old) old.remove();

        var a = null, fade = null, pausedByMedia = false, pausedByTab = false;
        var chan = ('BroadcastChannel' in window) ? new BroadcastChannel('shezhire_bgm') : null;
        var tabId = Math.random().toString(36).slice(2);

        var b = document.createElement('button');
        b.type = 'button';
        b.id = 'bgmToggle';
        b.className = 'off';
        // значок: ноты + «звуковые полоски» (эквалайзер); когда выключено — полоски стоят, значок перечёркнут
        b.innerHTML = '<span class="bgm-note">♫</span><span class="bgm-bars" aria-hidden="true"><i></i><i></i><i></i><i></i></span>';
        var st = document.createElement('style');
        st.textContent = '#bgmToggle{position:fixed;right:10px;bottom:22vh;z-index:900;width:46px;height:58px;border-radius:23px;' +
            'border:1px solid var(--c-border-strong,#bbb);background:var(--c-island,#fff);color:inherit;' +
            'cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.25);padding:4px 0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px}' +
            '#bgmToggle:hover,#bgmToggle:focus-visible{box-shadow:0 4px 18px rgba(0,0,0,.4)}' +
            '#bgmToggle .bgm-note{font-size:20px;line-height:1}' +
            '#bgmToggle .bgm-bars{display:flex;align-items:flex-end;gap:2px;height:14px}' +
            '#bgmToggle .bgm-bars i{display:block;width:3px;height:4px;border-radius:2px;background:currentColor}' +
            '#bgmToggle[aria-pressed="true"]{background:var(--c-accent-soft,#e8f0ff);border-color:var(--c-accent,#4a7)}' +
            '#bgmToggle[aria-pressed="true"] .bgm-bars i{animation:bgmBar .9s ease-in-out infinite}' +
            '#bgmToggle[aria-pressed="true"] .bgm-bars i:nth-child(2){animation-delay:.2s}' +
            '#bgmToggle[aria-pressed="true"] .bgm-bars i:nth-child(3){animation-delay:.45s}' +
            '#bgmToggle[aria-pressed="true"] .bgm-bars i:nth-child(4){animation-delay:.1s}' +
            '@keyframes bgmBar{0%,100%{height:3px}50%{height:14px}}' +
            '#bgmToggle.off{opacity:.8}' +
            '#bgmToggle.off::after{content:"";position:absolute;left:8px;right:8px;top:50%;border-top:2px solid currentColor;transform:rotate(-45deg)}' +
            '@media (prefers-reduced-motion:reduce){#bgmToggle .bgm-bars i{animation:none!important}#bgmToggle[aria-pressed="true"] .bgm-bars i{height:9px}}' +
            '@media print{#bgmToggle{display:none}}';
        document.head.appendChild(st);
        document.body.appendChild(b);

        function ui(on) {
            b.classList.toggle('off', !on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
            var t = on ? L[1] : L[0];
            b.setAttribute('aria-label', t);
            b.title = t;
        }
        ui(false);

        function ramp(to, done) {
            clearInterval(fade);
            fade = setInterval(function () {
                var d = to - a.volume;
                if (Math.abs(d) < 0.02) { a.volume = to; clearInterval(fade); if (done) done(); return; }
                a.volume = Math.max(0, Math.min(1, a.volume + (d > 0 ? 0.02 : -0.02)));
            }, 100);
        }

        function savePos() { if (a && !a.paused) ls(POS, String(a.currentTime)); }

        function ensure() {
            if (a) return;
            a = new Audio(SRC);
            a.loop = true;
            a.volume = 0;
            a.preload = 'auto';
            var p = parseFloat(ls(POS));
            if (p > 0) {
                var setPos = function () { try { if (a.duration && p < a.duration) a.currentTime = p; } catch (e) {} };
                if (a.readyState >= 1) setPos(); else a.addEventListener('loadedmetadata', setPos, { once: true });
            }
        }

        function play() {
            ensure();
            var pr = a.play();
            var ok = function () {
                ui(true); ramp(VOL);
                if (chan) chan.postMessage({ t: 'playing', id: tabId });
            };
            if (pr && pr.then) pr.then(ok).catch(function () {
                // браузер заблокировал автозапуск — ждём первого действия пользователя
                ui(false);
                var retry = function () {
                    document.removeEventListener('pointerdown', retry, true);
                    document.removeEventListener('keydown', retry, true);
                    if (ls(KEY) === '1' && !pausedByTab) play();
                };
                document.addEventListener('pointerdown', retry, true);
                document.addEventListener('keydown', retry, true);
            }); else ok();
        }

        function stop() {
            ui(false);
            if (a) { savePos(); ramp(0, function () { a.pause(); }); }
        }

        b.addEventListener('click', function () {
            var on = b.getAttribute('aria-pressed') !== 'true';
            pausedByMedia = false; pausedByTab = false;
            ls(KEY, on ? '1' : '0');
            if (chan) chan.postMessage({ t: 'pref', v: on ? '1' : '0', id: tabId });
            on ? play() : stop();
        });

        // Другая вкладка включила музыку — здесь пауза (предпочтение не меняем)
        if (chan) chan.onmessage = function (e) {
            var m = e.data || {};
            if (m.id === tabId) return;
            if (m.t === 'playing' && a && !a.paused) { pausedByTab = true; stop(); }
            if (m.t === 'pref' && m.v === '0') { pausedByTab = false; if (a && !a.paused) stop(); }
        };

        // Видео/аудио в альбомах: пауза фона на время их воспроизведения
        document.addEventListener('play', function (e) {
            var t = e.target;
            if (t === a || !t || (t.tagName !== 'VIDEO' && t.tagName !== 'AUDIO')) return;
            if (a && !a.paused) { pausedByMedia = true; savePos(); a.pause(); ui(false); }
        }, true);
        function mediaEnded(e) {
            var t = e.target;
            if (t === a || !t || (t.tagName !== 'VIDEO' && t.tagName !== 'AUDIO')) return;
            if (!pausedByMedia) return;
            var any = Array.prototype.some.call(document.querySelectorAll('video,audio'), function (m) { return m !== a && !m.paused && !m.ended; });
            if (any) return;
            pausedByMedia = false;
            if (ls(KEY) === '1' && !pausedByTab) play();
        }
        document.addEventListener('pause', mediaEnded, true);
        document.addEventListener('ended', mediaEnded, true);

        setInterval(savePos, 1000);
        window.addEventListener('pagehide', savePos);

        // выбор «выкл» из другой вкладки/страницы
        window.addEventListener('storage', function (e) {
            if (e.key === KEY && e.newValue === '0' && a && !a.paused) stop();
        });

        if (ls(KEY) === '1') play();
    }

    if (document.body) init(); else document.addEventListener('DOMContentLoaded', init);
})();
