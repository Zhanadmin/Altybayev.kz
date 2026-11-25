/*
 * ALT-114: общий «исправитель раскладки» для всех поисков сайта.
 *   «;fyljc» (русская раскладка на английской клавиатуре) → «Жандос»; кириллица → латиница обратно.
 *   Казахская раскладка: ә ғ қ ң ө ұ ү һ і.
 *
 * API (window.LayoutFix):
 *   convert(str, layout)       — перевод строки; layout: 'ru' | 'kk' | 'en' (кириллица → латиница)
 *   candidates(str)            — список вариантов перевода (без исходной строки, без повторов)
 *   smartSearch(q, fetcher)    — fetcher(q) → Promise<массив результатов>; если по q пусто,
 *                                пробует варианты; → Promise<{results, used, hint}>
 *   pickCandidate(q, items, test) — то же для синхронного поиска по готовому списку
 *   hintText(fixed, lang)      — «Может, вы имели в виду: X?»
 *   showHint(anchor, fixed, onPick, lang) — рисует подсказку над результатами (anchor = контейнер результатов)
 *   clearHint(anchor)
 */
(function (root) {
    'use strict';

    // Клавиши английской раскладки → русская ЙЦУКЕН (нижний регистр)
    var RU = {
        'q': 'й', 'w': 'ц', 'e': 'у', 'r': 'к', 't': 'е', 'y': 'н', 'u': 'г', 'i': 'ш', 'o': 'щ', 'p': 'з',
        '[': 'х', ']': 'ъ', 'a': 'ф', 's': 'ы', 'd': 'в', 'f': 'а', 'g': 'п', 'h': 'р', 'j': 'о', 'k': 'л',
        'l': 'д', ';': 'ж', "'": 'э', 'z': 'я', 'x': 'ч', 'c': 'с', 'v': 'м', 'b': 'и', 'n': 'т', 'm': 'ь',
        ',': 'б', '.': 'ю', '`': 'ё'
    };
    // Казахская раскладка (ЙЦУКЕН + казахские буквы на местах редких русских клавиш)
    var KK = {};
    Object.keys(RU).forEach(function (k) { KK[k] = RU[k]; });
    KK['`'] = 'ә';  // ё → ә
    KK[']'] = 'і';  // ъ → і
    KK['['] = 'х';
    KK['\\'] = 'ұ';
    // Казахские буквы приводим к базовым при сравнении (қайрат = кайрат), см. fold()
    var KK_FOLD = { 'ә': 'а', 'ғ': 'г', 'қ': 'к', 'ң': 'н', 'ө': 'о', 'ұ': 'у', 'ү': 'у', 'һ': 'х', 'і': 'и' };
    function fold(s) {
        return String(s == null ? '' : s).toLowerCase().replace(/ё/g, 'е').replace(/[әғқңөұүһі]/g, function (c) { return KK_FOLD[c]; });
    }

    var SHIFTED = { '{': '[', '}': ']', ':': ';', '"': "'", '<': ',', '>': '.', '~': '`', '|': '\\' };

    function isLatinLetter(ch) { return /[a-z]/i.test(ch); }
    function isCyrLetter(ch) { return /[Ѐ-ӿ]/.test(ch); }

    function reverseMap(map) {
        var r = {};
        Object.keys(map).forEach(function (k) { if (map[k]) { r[map[k]] = k; } });
        return r;
    }
    var RU_REV = reverseMap(RU);
    var KK_REV = reverseMap(KK);

    function convertChar(ch, map) {
        var low = ch.toLowerCase();
        var upper = ch !== low;
        var key = low;
        if (SHIFTED[ch]) { key = SHIFTED[ch]; upper = true; }
        var out = map[key];
        if (out === undefined || out === null) { return ch; }
        return upper ? out.toUpperCase() : out;
    }

    function convert(str, layout) {
        str = String(str == null ? '' : str);
        var out = '';
        var i;
        if (layout === 'en') {
            for (i = 0; i < str.length; i++) {
                var c = str.charAt(i), lc = c.toLowerCase();
                var lat = RU_REV[lc] || KK_REV[lc];
                if (!lat) { out += c; continue; }
                out += (c !== lc) ? (lat.length === 1 && isLatinLetter(lat) ? lat.toUpperCase() : upperPunct(lat)) : lat;
            }
            return out;
        }
        var map = layout === 'kk' ? KK : RU;
        for (i = 0; i < str.length; i++) { out += convertChar(str.charAt(i), map); }
        return out;
    }
    function upperPunct(lat) {
        for (var k in SHIFTED) { if (SHIFTED[k] === lat) { return k; } }
        return lat;
    }

    // Есть ли в строке латинские буквы и нет кириллицы (тогда имеет смысл перевод в кириллицу)
    function hasLatinOnly(str) {
        var lat = false, i;
        for (i = 0; i < str.length; i++) {
            var ch = str.charAt(i);
            if (isCyrLetter(ch)) { return false; }
            if (isLatinLetter(ch)) { lat = true; }
        }
        return lat;
    }
    function hasCyrOnly(str) {
        var cyr = false, i;
        for (i = 0; i < str.length; i++) {
            var ch = str.charAt(i);
            if (isLatinLetter(ch)) { return false; }
            if (isCyrLetter(ch)) { cyr = true; }
        }
        return cyr;
    }

    function candidates(str) {
        str = String(str == null ? '' : str).trim();
        var res = [];
        if (!str) { return res; }
        function add(v) { if (v && v !== str && res.indexOf(v) < 0) { res.push(v); } }
        if (hasLatinOnly(str)) {
            add(convert(str, 'ru'));
            add(convert(str, 'kk'));
        } else if (hasCyrOnly(str)) {
            add(convert(str, 'en'));
        }
        return res;
    }

    function titleCase(s) {
        return String(s).split(/(\s+|-)/).map(function (w) {
            return w ? w.charAt(0).toUpperCase() + w.slice(1).toLowerCase() : w;
        }).join('');
    }

    function smartSearch(q, fetcher) {
        q = String(q == null ? '' : q).trim();
        return Promise.resolve(fetcher(q)).then(function (first) {
            first = first || [];
            if (first.length || !q) { return { results: first, used: q, hint: '' }; }
            var cands = candidates(q);
            var chain = Promise.resolve(null);
            cands.forEach(function (c) {
                chain = chain.then(function (found) {
                    if (found) { return found; }
                    return Promise.resolve(fetcher(c)).then(function (r) {
                        return (r && r.length) ? { results: r, used: c, hint: titleCase(c) } : null;
                    });
                });
            });
            return chain.then(function (found) { return found || { results: first, used: q, hint: '' }; });
        });
    }

    function pickCandidate(q, items, test) {
        q = String(q == null ? '' : q).trim();
        var first = items.filter(function (it) { return test(it, q); });
        if (first.length || !q) { return { results: first, used: q, hint: '' }; }
        var cands = candidates(q);
        for (var i = 0; i < cands.length; i++) {
            var r = items.filter(function (it) { return test(it, cands[i]); });
            if (r.length) { return { results: r, used: cands[i], hint: titleCase(cands[i]) }; }
        }
        return { results: first, used: q, hint: '' };
    }

    function pageLang() {
        var l = (root.document && root.document.documentElement && root.document.documentElement.lang) || 'ru';
        return /^kk|^kz/i.test(l) ? 'kk' : 'ru';
    }
    function hintText(fixed, lang) {
        return (lang || pageLang()) === 'kk' ? 'Сіз мынаны айтқыңыз келді ме: ' + fixed + '?' : 'Может, вы имели в виду: ' + fixed + '?';
    }

    function hintNode(anchor) {
        return anchor && anchor.parentNode ? anchor.parentNode.querySelector('.lf-hint[data-for="' + (anchor.id || '') + '"]') : null;
    }
    function clearHint(anchor) {
        var n = hintNode(anchor);
        if (n && n.parentNode) { n.parentNode.removeChild(n); }
    }
    function showHint(anchor, fixed, onPick, lang) {
        clearHint(anchor);
        if (!anchor || !fixed || !anchor.parentNode) { return null; }
        var d = root.document.createElement('div');
        d.className = 'lf-hint';
        d.setAttribute('role', 'note');
        d.setAttribute('data-for', anchor.id || '');
        d.style.cssText = 'font-size:0.85em; margin:4px 0; opacity:0.9;';
        var text = hintText('\u0000', lang).split('\u0000');
        d.appendChild(root.document.createTextNode(text[0]));
        var a = root.document.createElement('a');
        a.href = '#';
        a.textContent = fixed;
        a.addEventListener('mousedown', function (e) { e.preventDefault(); });
        a.addEventListener('click', function (e) { e.preventDefault(); if (onPick) { onPick(fixed); } });
        d.appendChild(a);
        d.appendChild(root.document.createTextNode(text[1] || ''));
        anchor.parentNode.insertBefore(d, anchor);
        return d;
    }

    var api = {
        convert: convert, candidates: candidates, smartSearch: smartSearch, pickCandidate: pickCandidate,
        hintText: hintText, showHint: showHint, clearHint: clearHint, titleCase: titleCase, fold: fold, KK_FOLD: KK_FOLD
    };
    root.LayoutFix = api;
    if (typeof module !== 'undefined' && module.exports) { module.exports = api; }
})(typeof window !== 'undefined' ? window : globalThis);
