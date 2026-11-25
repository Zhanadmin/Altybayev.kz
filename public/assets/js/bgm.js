/* ALT-014: фоновая музыка по всему сайту. Кнопка ♪ создаётся здесь (один код для всех страниц).
   Состояние: localStorage shezhire_music = '1'/'0', позиция shezhire_music_pos (секунды).
   Одна вкладка: BroadcastChannel 'shezhire_bgm' — при старте в одной вкладке остальные ставят паузу.
   ALT-046: непрерывная музыка. Сайт открывается внутри «оболочки» (views/shell.php: страница-обёртка с iframe),
   которая не перезагружается при переходах. Три режима этого скрипта:
     • оболочка (window.__bgmShell) — владеет <audio>, кнопки нет, отдаёт API window.__shezhireBgmApi;
     • внутри iframe оболочки — только кнопка, всё делегируется API оболочки;
     • отдельная страница (вход, браузер без Sec-Fetch-Dest) — как раньше: свой <audio>, позиция в localStorage. */
(function () {
    'use strict';
    if (window.__shezhireBgm) return;
    window.__shezhireBgm = true;

    var SHELL = !!window.__bgmShell;
    var P = null;      // API оболочки (если мы внутри её iframe)
    var inFrame = false;
    try { inFrame = window.parent && window.parent !== window; } catch (e) {}
    if (!SHELL && inFrame) {
        try { if (window.parent.__bgmShell && window.parent.__shezhireBgmApi) P = window.parent.__shezhireBgmApi; } catch (e) { P = null; }
        // вход и OAuth должны быть на верхнем уровне (Google не открывается во фрейме)
        try {
            if (window.parent.__bgmShell && /^\/(login|auth\/)/.test(location.pathname)) {
                window.parent.location.replace(location.href);
                return;
            }
        } catch (e) {}
    }

    var SRC = '/assets/audio/welcome.mp3';
    var VOL = 0.18;
    var KEY = 'shezhire_music', POS = 'shezhire_music_pos', POSAT = 'shezhire_music_pos_at';
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

    function isMedia(t, a) {
        return !!t && t !== a && (t.tagName === 'VIDEO' || t.tagName === 'AUDIO');
    }

    function init() {
        // на странице приветствия кнопка могла быть в разметке — убираем дубль
        var old = document.getElementById('wlMusic');
        if (old) old.remove();

        var headless = SHELL;   // у оболочки кнопки на экране нет
        var a = null, fade = null, pausedByMedia = false, pausedByTab = false, subs = [];
        var chan = (!P && 'BroadcastChannel' in window) ? new BroadcastChannel('shezhire_bgm') : null;
        var tabId = Math.random().toString(36).slice(2);

        var b = document.createElement('button');
        b.type = 'button';
        b.id = 'bgmToggle';
        b.className = 'off';
        // ALT-115: выключено — плашка play, включено — эквалайзер; перечёркивания нет
        b.innerHTML = '<span class="bgm-play" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M8 5v14l11-7z"/></svg></span>' +
            '<span class="bgm-bars" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>';
        if (!headless) {
            var st = document.createElement('style');
            // ALT-014: компактная круглая кнопка в верхней панели рядом с переключателем языка
            st.textContent = '#bgmToggle{position:relative;flex:0 0 auto;box-sizing:border-box;width:36px;height:36px;min-width:36px;border-radius:50%;margin:0 8px 0 0;padding:0;' +
                'border:1px solid var(--c-border-strong,#475569);background:var(--c-island,rgba(15,23,42,.85));color:var(--c-text,inherit);' +
                'cursor:pointer;display:inline-flex;align-items:center;justify-content:center;line-height:1}' +
                '#bgmToggle:hover,#bgmToggle:focus-visible{border-color:var(--c-accent,#d4af37)}' +
                '#bgmToggle .bgm-play{display:inline-flex;align-items:center;justify-content:center;line-height:1}' +
                '#bgmToggle .bgm-play svg{display:block;width:15px;height:15px;fill:currentColor}' +
                '#bgmToggle .bgm-bars{display:none;align-items:center;gap:2px;height:11px}' +
                '#bgmToggle .bgm-bars i{display:block;width:2px;height:2px;border-radius:1px;background:currentColor}' +
                '#bgmToggle[aria-pressed="true"]{border-color:var(--c-accent,#d4af37);color:var(--c-accent,#d4af37)}' +
                '#bgmToggle[aria-pressed="true"] .bgm-play{display:none}' +
                '#bgmToggle[aria-pressed="true"] .bgm-bars{display:flex}' +
                '#bgmToggle[aria-pressed="true"] .bgm-bars i{animation:bgmBar .9s ease-in-out infinite}' +
                '#bgmToggle[aria-pressed="true"] .bgm-bars i:nth-child(2){animation-delay:.2s}' +
                '#bgmToggle[aria-pressed="true"] .bgm-bars i:nth-child(3){animation-delay:.45s}' +
                '#bgmToggle[aria-pressed="true"] .bgm-bars i:nth-child(4){animation-delay:.1s}' +
                '@keyframes bgmBar{0%,100%{height:2px}50%{height:11px}}' +
                '#bgmToggle.floating{position:fixed;right:10px;top:10px;z-index:900;margin:0}' +
                '@media (prefers-reduced-motion:reduce){#bgmToggle .bgm-bars i{animation:none!important}#bgmToggle[aria-pressed="true"] .bgm-bars i{height:7px}}' +
                '@media print{#bgmToggle{display:none}}';
            document.head.appendChild(st);
            // ALT-086: в каркасе сайта кнопка живёт в боковой панели (#bgmSlot), на остальных страницах — рядом с переключателем языка
            var slot = document.getElementById('bgmSlot');
            var sw = document.querySelector('.lang-switcher, .ds-topbar .seg, .au-top .seg');
            if (slot) {
                slot.appendChild(b);
            } else if (sw && sw.parentNode) {
                sw.parentNode.insertBefore(b, sw);
            } else {
                b.classList.add('floating');
                document.body.appendChild(b);
            }
        }

        function ui(on) {
            b.classList.toggle('off', !on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
            var t = on ? L[1] : L[0];
            b.setAttribute('aria-label', t);
            b.title = t;
            // оболочка сообщает состояние кнопке в iframe (она могла уже уйти со страницы — тогда отписываем)
            subs = subs.filter(function (fn) { try { fn(on); return true; } catch (e) { return false; } });
        }
        ui(false);

        // ---- режим «внутри iframe»: кнопка + делегирование в оболочку ----
        if (P) {
            ui(!!P.isOn());
            var unsub = null;
            try { unsub = P.subscribe(ui); } catch (e) {}
            b.addEventListener('click', function () { try { P.toggle(); } catch (e) {} });
            // автозапуск мог быть заблокирован браузером — первое действие на странице его разрешает
            var kick = function () { try { P.kick(); } catch (e) {} };
            document.addEventListener('pointerdown', kick, true);
            document.addEventListener('keydown', kick, true);
            // видео/аудио в альбомах: пауза фона на время их воспроизведения
            var innerMedia = false;
            document.addEventListener('play', function (e) {
                if (!isMedia(e.target, null)) return;
                innerMedia = true;
                try { P.mediaPlay(); } catch (x) {}
            }, true);
            var innerEnd = function (e) {
                if (!innerMedia || !isMedia(e.target, null)) return;
                var any = Array.prototype.some.call(document.querySelectorAll('video,audio'), function (m) { return !m.paused && !m.ended; });
                if (any) return;
                innerMedia = false;
                try { P.mediaDone(); } catch (x) {}
            };
            document.addEventListener('pause', innerEnd, true);
            document.addEventListener('ended', innerEnd, true);
            // внешние ссылки не должны открываться во фрейме (сайты запрещают встраивание)
            document.addEventListener('click', function (e) {
                var el = e.target && e.target.closest ? e.target.closest('a[href]') : null;
                if (!el || el.target || el.hasAttribute('download')) return;
                if (/^https?:$/.test(el.protocol) && el.origin !== location.origin) el.target = '_top';
            }, true);
            // адрес и заголовок в оболочке — после возврата из кэша (кнопки «назад/вперёд»)
            window.addEventListener('pageshow', function () {
                try { window.parent.__shezhireShellSync(window); } catch (e) {}
            });
            window.addEventListener('pagehide', function () {
                if (unsub) { try { unsub(); } catch (e) {} }
                if (innerMedia) { innerMedia = false; try { P.mediaDone(); } catch (e) {} }
            });
            return;
        }

        // ---- режим «оболочка» / «отдельная страница»: владеем <audio> ----
        function ramp(to, done) {
            clearInterval(fade);
            fade = setInterval(function () {
                var d = to - a.volume;
                if (Math.abs(d) < 0.02) { a.volume = to; clearInterval(fade); if (done) done(); return; }
                a.volume = Math.max(0, Math.min(1, a.volume + (d > 0 ? 0.02 : -0.02)));
            }, 100);
        }

        // ALT-046: сохраняем позицию + время сохранения; после полной перезагрузки компенсируем паузу
        function savePos() {
            if (a && !a.paused) { ls(POS, String(a.currentTime)); ls(POSAT, String(Date.now())); }
        }

        function ensure() {
            if (a) return;
            a = new Audio(SRC);
            a.loop = true;
            a.volume = 0;
            a.preload = 'auto';
            var p = parseFloat(ls(POS));
            var at = parseFloat(ls(POSAT));
            if (p >= 0) {
                var gap = (at > 0) ? (Date.now() - at) / 1000 : 0;
                // недавно играло (переход между страницами) — продолжаем без разрыва, сразу с рабочей громкостью
                if (gap >= 0 && gap < 15) { p += gap; a.volume = VOL; }
                try { a.currentTime = p; } catch (e) {}
                a.addEventListener('loadedmetadata', function () {
                    try { if (a.duration && p >= a.duration) a.currentTime = p % a.duration; } catch (e) {}
                }, { once: true });
            }
        }

        var waiting = false;
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
                if (waiting) return;
                waiting = true;
                var retry = function () {
                    document.removeEventListener('pointerdown', retry, true);
                    document.removeEventListener('keydown', retry, true);
                    waiting = false;
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

        function mediaPlay() {
            if (a && !a.paused) { pausedByMedia = true; savePos(); a.pause(); ui(false); }
        }
        function mediaDone() {
            if (!pausedByMedia) return;
            pausedByMedia = false;
            if (ls(KEY) === '1' && !pausedByTab) play();
        }

        if (SHELL) {
            // API для страниц внутри iframe
            window.__shezhireBgmApi = {
                isOn: function () { return b.getAttribute('aria-pressed') === 'true'; },
                toggle: function () { b.click(); },
                subscribe: function (fn) {
                    subs.push(fn);
                    return function () { subs = subs.filter(function (f) { return f !== fn; }); };
                },
                kick: function () { if (ls(KEY) === '1' && !pausedByTab && !pausedByMedia && (!a || a.paused)) play(); },
                mediaPlay: mediaPlay,
                mediaDone: mediaDone
            };
        } else {
            // Видео/аудио в альбомах: пауза фона на время их воспроизведения
            document.addEventListener('play', function (e) {
                if (isMedia(e.target, a)) mediaPlay();
            }, true);
            var mediaEnded = function (e) {
                if (!isMedia(e.target, a) || !pausedByMedia) return;
                var any = Array.prototype.some.call(document.querySelectorAll('video,audio'), function (m) { return m !== a && !m.paused && !m.ended; });
                if (any) return;
                mediaDone();
            };
            document.addEventListener('pause', mediaEnded, true);
            document.addEventListener('ended', mediaEnded, true);
        }

        setInterval(savePos, 1000);
        window.addEventListener('pagehide', savePos);
        window.addEventListener('beforeunload', savePos);
        document.addEventListener('visibilitychange', function () { if (document.hidden) savePos(); });

        // выбор «выкл» из другой вкладки/страницы
        window.addEventListener('storage', function (e) {
            if (e.key === KEY && e.newValue === '0' && a && !a.paused) stop();
        });

        if (ls(KEY) === '1') play();
    }

    if (document.body) init(); else document.addEventListener('DOMContentLoaded', init);
})();
