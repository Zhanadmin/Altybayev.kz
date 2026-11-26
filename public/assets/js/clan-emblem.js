/* ALT-117 (2026-11-26): единый герб рода для окна просмотра и экспорта PNG/PDF.
 *
 * Композиция: квадрат 1:1 со скруглёнными углами, многослойная золотая рамка,
 * казахский орнамент, живописная степь (небо, закат, снежные горы, озеро,
 * лошади, юрта, семья, дети и тазы). Текст и таңба — отдельные динамические
 * слои, поэтому данные рода не зашиты в картинку.
 *
 * API (window.ClanEmblem):
 *   buildSvg(o)                 — SVG-строка 1024×1024;
 *   open(o)                     — модальное окно с готовым гербом;
 *   toPngDataUrl(svg, size)     — PNG data URL;
 *   toPdfBytes(svg, size)       — настоящий PDF (одна страница, JPEG 2048+);
 *   downloadPng(svg, name, size), downloadPdf(svg, name, size);
 *   buildPdf(jpegBytes, w, h, page) — чистый построитель PDF для тестов.
 *
 * В Node модуль экспортируется через module.exports — для автотестов.
 */
(function (root, factory) {
    'use strict';
    const api = factory();
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    if (root && root.document) root.ClanEmblem = api;
})(typeof window !== 'undefined' ? window : null, function () {
    'use strict';

    const W = 1024;
    const DEFAULT_FONT = "Lora, Georgia, 'Times New Roman', serif";
    let seq = 0;
    let measureCtx = null;
    let fontPromise = null;
    let openSeq = 0;
    let savedOverflow = '';

    const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;'
    }[c]));

    const clean = (v) => String(v == null ? '' : v).replace(/\s+/g, ' ').trim();

    function getMeasureCtx() {
        if (measureCtx !== null) return measureCtx;
        if (typeof document === 'undefined' || !document.createElement) { measureCtx = false; return false; }
        try {
            const c = document.createElement('canvas');
            measureCtx = c.getContext('2d') || false;
        } catch (e) { measureCtx = false; }
        return measureCtx;
    }

    function measureText(text, size, ls, weight, family) {
        const ctx = getMeasureCtx();
        if (ctx) {
            ctx.font = weight + ' ' + size + 'px ' + family;
            return ctx.measureText(text).width + Math.max(0, text.length - 1) * ls;
        }
        // Запасной расчёт для Node/старых движков: чуть шире реального,
        // поэтому длинные строки уменьшаются, а не обрезаются.
        return text.length * size * 0.62 + Math.max(0, text.length - 1) * ls;
    }

    function fitText(text, max, base, min, ls, weight, family) {
        if (!text) return { size: base, textLength: false };
        let size = base;
        let width = measureText(text, size, ls, weight, family);
        while (width > max && size > min) {
            size -= 1;
            width = measureText(text, size, ls, weight, family);
        }
        return { size: size, textLength: width > max };
    }

    function renderText(ctx, text, x, y, max, base, min, ls, weight, fill, stroke, strokeW) {
        if (!text) return '';
        const f = fitText(text, max, base, min, ls, weight, ctx.fontFamily);
        const tl = f.textLength ? ' textLength="' + max + '" lengthAdjust="spacingAndGlyphs"' : '';
        const st = stroke
            ? ' stroke="' + stroke + '" stroke-width="' + (strokeW || 4) + '" stroke-opacity=".55" paint-order="stroke"'
            : '';
        return '<text x="' + x + '" y="' + y + '" text-anchor="middle" font-family="' + esc(ctx.fontFamily) + '"'
            + ' font-size="' + f.size.toFixed(1) + '" font-weight="' + weight + '" letter-spacing="' + ls + '"'
            + ' fill="' + fill + '"' + st + tl + '>' + esc(text) + '</text>';
    }

    function b64FromBuffer(buf) {
        const bytes = new Uint8Array(buf);
        let bin = '';
        const chunk = 0x8000;
        for (let i = 0; i < bytes.length; i += chunk) {
            bin += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
        }
        return btoa(bin);
    }

    function ensureFont() {
        if (fontPromise) return fontPromise;
        fontPromise = (async () => {
            if (typeof fetch !== 'function' || typeof btoa !== 'function') return '';
            try {
                const r = await fetch('/assets/fonts/lora-700.ttf', { credentials: 'same-origin' });
                if (!r.ok) return '';
                return b64FromBuffer(await r.arrayBuffer());
            } catch (e) { return ''; }
        })();
        return fontPromise;
    }

    function blobToDataUrl(blob) {
        return new Promise((resolve) => {
            try {
                const fr = new FileReader();
                fr.onload = () => resolve(String(fr.result || ''));
                fr.onerror = () => resolve('');
                fr.readAsDataURL(blob);
            } catch (e) { resolve(''); }
        });
    }

    async function loadTamga(url) {
        const src = clean(url);
        if (!src) return { data: '', isRaster: false, missing: false };
        if (/^data:/i.test(src)) {
            return { data: src, isRaster: /^data:image\/(png|jpe?g|webp)/i.test(src), missing: false };
        }
        try {
            const r = await fetch(src, { credentials: 'same-origin' });
            if (!r.ok) return { data: '', isRaster: false, missing: true };
            const blob = await r.blob();
            const data = await blobToDataUrl(blob);
            return { data: data, isRaster: /^image\/(png|jpe?g|webp)/i.test(blob.type), missing: !data };
        } catch (e) {
            return { data: '', isRaster: false, missing: true };
        }
    }

    /* ---------- декоративные части ---------- */

    const HORN = 'M0 8 C-22 8 -38 -8 -38 -28 C-38 -46 -25 -58 -9 -58 C3 -58 13 -49 13 -37 '
        + 'C13 -28 6 -21 -2 -21 C-8 -21 -13 -26 -13 -32 '
        + 'M0 8 C22 8 38 -8 38 -28 C38 -46 25 -58 9 -58 C-3 -58 -13 -49 -13 -37 '
        + 'C-13 -28 -6 -21 2 -21 C8 -21 13 -26 13 -32';

    function ornamentAt(uid, x, y, scale, rotate) {
        const tr = 'translate(' + x + ' ' + y + ') rotate(' + (rotate || 0) + ') scale(' + scale + ')';
        return '<g transform="' + tr + '" filter="url(#' + uid + '-ornShadow)">'
            + '<path d="' + HORN + '" fill="none" stroke="#090603" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" opacity=".9"/>'
            + '<path d="' + HORN + '" fill="none" stroke="url(#' + uid + '-gold)" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>'
            + '<path d="M0 10 l7 -10 l-7 -10 l-7 10 z" fill="url(#' + uid + '-gold)" stroke="#090603" stroke-width="1"/>'
            + '</g>';
    }

    function defs(uid, fontData) {
        const fontCss = fontData
            ? '<style type="text/css"><![CDATA[@font-face{font-family:\'ALT117Emblem\';src:url(data:font/ttf;base64,'
                + fontData
                + ') format(\'truetype\');font-weight:700;font-style:normal;}]]></style>'
            : '';
        return '<defs>'
            + fontCss
            + '<clipPath id="' + uid + '-scene"><rect x="40" y="40" width="944" height="944" rx="36"/></clipPath>'
            + '<linearGradient id="' + uid + '-gold" x1="0" y1="0" x2="1" y2="1">'
            + '<stop offset="0" stop-color="#fff3cf"/><stop offset=".24" stop-color="#eccb86"/>'
            + '<stop offset=".48" stop-color="#b9822f"/><stop offset=".68" stop-color="#f2d79b"/>'
            + '<stop offset=".86" stop-color="#c2913f"/><stop offset="1" stop-color="#80551e"/>'
            + '</linearGradient>'
            + '<linearGradient id="' + uid + '-nameGold" x1="0" y1="0" x2="0" y2="1">'
            + '<stop offset="0" stop-color="#a86a12"/><stop offset=".45" stop-color="#c18a2e"/>'
            + '<stop offset="1" stop-color="#7c4a0d"/>'
            + '</linearGradient>'
            + '<linearGradient id="' + uid + '-sky" x1="0" y1="0" x2="0" y2="1">'
            + '<stop offset="0" stop-color="#2c6ba3"/><stop offset=".24" stop-color="#4f93c4"/>'
            + '<stop offset=".44" stop-color="#9dc9df"/><stop offset=".54" stop-color="#dce6dd"/>'
            + '<stop offset=".62" stop-color="#f5ce86"/><stop offset=".72" stop-color="#edb861"/>'
            + '<stop offset="1" stop-color="#dc9d4b"/>'
            + '</linearGradient>'
            + '<radialGradient id="' + uid + '-sunGlow" cx="50%" cy="50%" r="50%">'
            + '<stop offset="0" stop-color="#fff6cc" stop-opacity=".95"/>'
            + '<stop offset=".34" stop-color="#ffe4a0" stop-opacity=".55"/>'
            + '<stop offset="1" stop-color="#ffd98a" stop-opacity="0"/>'
            + '</radialGradient>'
            + '<radialGradient id="' + uid + '-haze" cx="50%" cy="50%" r="50%">'
            + '<stop offset="0" stop-color="#fff9e9" stop-opacity=".72"/>'
            + '<stop offset=".56" stop-color="#fff9e9" stop-opacity=".30"/>'
            + '<stop offset="1" stop-color="#fff9e9" stop-opacity="0"/>'
            + '</radialGradient>'
            + '<radialGradient id="' + uid + '-signGlow" cx="50%" cy="50%" r="50%">'
            + '<stop offset="0" stop-color="#fffdf4" stop-opacity=".92"/>'
            + '<stop offset=".56" stop-color="#fff8e6" stop-opacity=".48"/>'
            + '<stop offset="1" stop-color="#fff8e6" stop-opacity="0"/>'
            + '</radialGradient>'
            + '<linearGradient id="' + uid + '-mFar" x1="0" y1="0" x2="0" y2="1">'
            + '<stop offset="0" stop-color="#edf5fa"/><stop offset=".55" stop-color="#c3d5e2"/>'
            + '<stop offset="1" stop-color="#93abc0"/>'
            + '</linearGradient>'
            + '<linearGradient id="' + uid + '-mNear" x1="0" y1="0" x2="0" y2="1">'
            + '<stop offset="0" stop-color="#d3e1ea"/><stop offset=".62" stop-color="#a5bccd"/>'
            + '<stop offset="1" stop-color="#7893aa"/>'
            + '</linearGradient>'
            + '<linearGradient id="' + uid + '-lake" x1="0" y1="0" x2="0" y2="1">'
            + '<stop offset="0" stop-color="#a9d1e2"/><stop offset=".42" stop-color="#69a6c2"/>'
            + '<stop offset="1" stop-color="#2f6b8b"/>'
            + '</linearGradient>'
            + '<linearGradient id="' + uid + '-steppe" x1="0" y1="0" x2="0" y2="1">'
            + '<stop offset="0" stop-color="#dcb96c"/><stop offset=".42" stop-color="#c49a4c"/>'
            + '<stop offset="1" stop-color="#8d692f"/>'
            + '</linearGradient>'
            + '<linearGradient id="' + uid + '-plaque" x1="0" y1="0" x2="0" y2="1">'
            + '<stop offset="0" stop-color="#fff8e7"/><stop offset=".5" stop-color="#f4e3c0"/>'
            + '<stop offset="1" stop-color="#e4c996"/>'
            + '</linearGradient>'
            + '<linearGradient id="' + uid + '-yurtWall" x1="0" y1="0" x2="0" y2="1">'
            + '<stop offset="0" stop-color="#f7ecd6"/><stop offset="1" stop-color="#d8c39a"/>'
            + '</linearGradient>'
            + '<linearGradient id="' + uid + '-yurtRoof" x1="0" y1="0" x2="0" y2="1">'
            + '<stop offset="0" stop-color="#fdf7e9"/><stop offset="1" stop-color="#e4d1ad"/>'
            + '</linearGradient>'
            + '<filter id="' + uid + '-ink" x="-5%" y="-5%" width="110%" height="110%" color-interpolation-filters="sRGB">'
            + '<feColorMatrix type="matrix" values="0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 1 0"/>'
            + '</filter>'
            + '<filter id="' + uid + '-ornShadow" x="-30%" y="-30%" width="160%" height="160%">'
            + '<feDropShadow dx="1.5" dy="2" stdDeviation="1.2" flood-color="#000" flood-opacity=".8"/>'
            + '</filter>'
            + '</defs>';
    }

    function frame(uid) {
        return '<rect x="0" y="0" width="1024" height="1024" fill="#0d0a06"/>'
            + '<rect x="0" y="0" width="1024" height="1024" rx="64" fill="#0d0a06"/>'
            + '<rect x="6" y="6" width="1012" height="1012" rx="60" fill="none" stroke="#2b1d0d" stroke-width="16"/>'
            + '<rect x="6" y="6" width="1012" height="1012" rx="60" fill="none" stroke="url(#' + uid + '-gold)" stroke-width="8"/>'
            + '<rect x="17" y="17" width="990" height="990" rx="54" fill="none" stroke="#f7e3aa" stroke-width="1.8"/>'
            + '<rect x="23" y="23" width="978" height="978" rx="50" fill="none" stroke="#8a5e22" stroke-width="3"/>'
            + '<rect x="30" y="30" width="964" height="964" rx="46" fill="none" stroke="url(#' + uid + '-gold)" stroke-width="1.4"/>'
            + '<rect x="38" y="38" width="948" height="948" rx="38" fill="none" stroke="#3a2610" stroke-width="2"/>';
    }

    function sky(uid) {
        return '<rect x="40" y="40" width="944" height="944" fill="url(#' + uid + '-sky)"/>'
            + '<ellipse cx="720" cy="468" rx="310" ry="250" fill="url(#' + uid + '-sunGlow)"/>'
            + '<circle cx="726" cy="462" r="40" fill="#ffe7a3" opacity=".92"/>'
            + '<g fill="#fff8e8" opacity=".42">'
            + '<path d="M110 276 q38 -20 78 -2 q34 -14 68 4 q28 -8 50 6 v8 q-102 12 -196 0 z"/>'
            + '<path d="M606 318 q30 -16 62 0 q26 -10 54 2 v6 q-60 8 -116 0 z" opacity=".72"/>'
            + '<path d="M758 248 q26 -14 54 -2 q22 -8 44 4 v6 q-50 8 -98 0 z" opacity=".6"/>'
            + '</g>'
            + '<g fill="none" stroke="#39546d" stroke-width="2.2" stroke-linecap="round" opacity=".62">'
            + '<path d="M300 250 q7 -6 14 0 q7 -6 14 0"/>'
            + '<path d="M342 232 q6 -5 12 0 q6 -5 12 0"/>'
            + '<path d="M380 252 q5 -4 10 0 q5 -4 10 0"/>'
            + '</g>'
            + '<ellipse cx="512" cy="176" rx="426" ry="104" fill="url(#' + uid + '-haze)"/>';
    }

    function snowCap(x, y, w) {
        return '<path d="M' + x + ' ' + y
            + ' l' + (w * .55).toFixed(1) + ' ' + (w * .78).toFixed(1)
            + ' l-' + (w * .25).toFixed(1) + ' -' + (w * .12).toFixed(1)
            + ' l-' + (w * .18).toFixed(1) + ' ' + (w * .16).toFixed(1)
            + ' l-' + (w * .24).toFixed(1) + ' -' + (w * .18).toFixed(1)
            + ' l-' + (w * .15).toFixed(1) + ' ' + (w * .10).toFixed(1)
            + ' z" fill="#f8fcff" opacity=".94"/>';
    }

    function mountains(uid) {
        return '<path d="M40 548 L40 486 L94 452 L142 484 L198 420 L252 466 L312 390 L364 444 '
            + 'L422 404 L474 454 L530 412 L586 458 L642 408 L696 456 L752 418 L800 462 '
            + 'L854 426 L906 464 L952 436 L984 458 L984 566 L40 566 Z" fill="url(#' + uid + '-mFar)"/>'
            + snowCap(198, 420, 34) + snowCap(312, 390, 40) + snowCap(422, 404, 30)
            + snowCap(530, 412, 34) + snowCap(642, 408, 32) + snowCap(752, 418, 30)
            + snowCap(854, 426, 28) + snowCap(952, 436, 26)
            + '<path d="M40 566 L40 518 L98 492 L152 520 L212 476 L270 518 L332 484 L392 526 '
            + 'L452 494 L514 532 L578 496 L640 534 L702 500 L764 536 L824 506 L884 540 '
            + 'L942 512 L984 530 L984 600 L40 600 Z" fill="url(#' + uid + '-mNear)"/>'
            + snowCap(212, 476, 30) + snowCap(332, 484, 30) + snowCap(452, 494, 28)
            + snowCap(578, 496, 30) + snowCap(702, 500, 30) + snowCap(824, 506, 26)
            + snowCap(942, 512, 24);
    }

    function lake(uid) {
        return '<path d="M40 548 C160 540 260 554 380 548 C500 542 620 554 740 548 '
            + 'C840 543 920 548 984 544 L984 662 C880 674 760 662 640 668 '
            + 'C500 675 360 662 240 668 C160 672 90 666 40 670 Z" fill="url(#' + uid + '-lake)"/>'
            + '<g fill="#eef8ff" opacity=".11">'
            + '<path d="M180 552 L206 606 L232 552 Z"/><path d="M312 548 L342 610 L372 548 Z"/>'
            + '<path d="M442 552 L470 604 L498 552 Z"/><path d="M582 550 L610 608 L638 550 Z"/>'
            + '<path d="M700 554 L728 602 L756 554 Z"/><path d="M842 552 L868 606 L894 552 Z"/>'
            + '</g>'
            + '<g stroke="#e9f6ff" stroke-width="1.6" opacity=".34" fill="none">'
            + '<path d="M120 586 q40 6 80 0 q40 -6 80 0"/>'
            + '<path d="M320 612 q46 7 92 0 q46 -7 92 0"/>'
            + '<path d="M560 636 q40 6 80 0 q40 -6 80 0"/>'
            + '<path d="M170 646 q34 5 68 0 q34 -5 68 0"/>'
            + '</g>'
            + '<g fill="#ffe6a6">'
            + '<ellipse cx="726" cy="584" rx="70" ry="7" opacity=".58"/>'
            + '<ellipse cx="726" cy="602" rx="50" ry="5" opacity=".46"/>'
            + '<ellipse cx="726" cy="618" rx="32" ry="4" opacity=".36"/>'
            + '<ellipse cx="726" cy="632" rx="20" ry="3" opacity=".30"/>'
            + '</g>';
    }

    function grass(x, y, s, color) {
        return '<g transform="translate(' + x + ' ' + y + ') scale(' + s + ')" stroke="' + (color || '#6d5124')
            + '" stroke-width="2" stroke-linecap="round" fill="none" opacity=".55">'
            + '<path d="M0 0 q-2 -10 -8 -16 M0 0 q0 -12 2 -18 M0 0 q3 -9 9 -14"/></g>';
    }

    function flower(x, y, color) {
        return '<g transform="translate(' + x + ' ' + y + ')">'
            + '<circle r="2.2" fill="' + color + '"/>'
            + '<circle r="4.2" fill="none" stroke="' + color + '" stroke-width=".8" opacity=".7"/></g>';
    }

    function steppe(uid) {
        let out = '<path d="M40 650 C160 638 260 656 380 648 C520 638 640 656 760 646 '
            + 'C860 638 930 650 984 642 L984 984 L40 984 Z" fill="url(#' + uid + '-steppe)"/>'
            + '<path d="M40 742 C200 708 360 748 520 726 C680 704 840 742 984 718 L984 984 L40 984 Z" fill="#c99e4e" opacity=".58"/>'
            + '<path d="M40 846 C240 816 420 854 620 828 C780 808 900 838 984 824 L984 984 L40 984 Z" fill="#aa7d35" opacity=".42"/>'
            + '<path d="M40 652 C160 640 260 658 380 650 C520 640 640 658 760 648 C860 640 930 652 984 644" fill="none" stroke="#f4d58a" stroke-width="2" opacity=".5"/>';
        const tufts = [
            [100, 720, 1.2], [150, 760, 1.0], [210, 710, 1.3], [280, 690, .9], [340, 700, 1.1],
            [430, 720, 1.2], [470, 760, 1.0], [540, 700, 1.2], [600, 730, .9], [660, 690, 1.1],
            [720, 710, 1.0], [900, 720, 1.2], [940, 760, 1.1], [120, 820, 1.4], [180, 860, 1.1],
            [300, 880, 1.3], [480, 850, 1.2], [560, 890, 1.0], [640, 870, 1.2], [760, 890, 1.1],
            [880, 860, 1.2], [70, 900, 1.3], [930, 900, 1.2]
        ];
        tufts.forEach((t) => { out += grass(t[0], t[1], t[2]); });
        const flowers = [
            [130, 735, '#f3d37a'], [245, 722, '#fff3d0'], [315, 715, '#e6a34a'],
            [470, 735, '#f3d37a'], [615, 718, '#fff3d0'], [700, 735, '#e6a34a'],
            [175, 850, '#f3d37a'], [355, 865, '#fff3d0'], [520, 872, '#e6a34a'],
            [610, 850, '#f3d37a'], [900, 840, '#fff3d0'], [80, 890, '#e6a34a']
        ];
        flowers.forEach((f) => { out += flower(f[0], f[1], f[2]); });
        return out;
    }

    function horse(x, y, s, flip) {
        const k = flip ? -s : s;
        // Силуэт лошади в профиль: длинное тело, тонкая шея, голова с ушами,
        // хвост и четыре ноги с суставами. Не камел/собака — важно для сцены.
        // Один силуэт «тело — шея — голова»: длинное тело и короткая толстая шея,
        // иначе вдали лошадь читается как верблюд.
        return '<g transform="translate(' + x + ' ' + y + ') scale(' + k + ' ' + s + ')" fill="#3a2a18" opacity=".93">'
            + '<path d="M-30 -34 C-34 -40 -30 -46 -22 -46 L8 -46 C14 -46 18 -44 22 -40'
            + ' L32 -50 C36 -54 42 -54 46 -51 L50 -48 C50 -46 47 -45 44 -46 L36 -47 L30 -42'
            + ' C26 -36 20 -32 14 -32 L-22 -32 C-26 -32 -28 -32 -30 -34 Z"/>'
            + '<path d="M34 -53 l-2 -7 l6 4 z"/>'
            + '<path d="M16 -42 Q24 -47 32 -51" fill="none" stroke="#2c1f12" stroke-width="3" stroke-linecap="round"/>'
            + '<path d="M-30 -36 C-42 -38 -44 -24 -38 -14 C-36 -22 -32 -26 -26 -28 Z"/>'
            + '<path d="M10 -30 l-1 12 l3 12 M18 -30 l3 12 l-1 12 M-22 -30 l-2 12 l2 12 M-14 -30 l1 12 l-3 12"'
            + ' fill="none" stroke="#3a2a18" stroke-width="3.4" stroke-linecap="round"/>'
            + '<path d="M-21 1 h6 M16 1 h6" stroke="#241a11" stroke-width="3" stroke-linecap="round"/>'
            + '<circle cx="44" cy="-49" r="1.2" fill="#f0d7a2" opacity=".7"/>'
            + '</g>';
    }

    function horses() {
        return horse(130, 674, .90, false) + horse(186, 682, .82, false)
            + horse(240, 668, .78, true) + horse(290, 684, .55, false);
    }

    function yurt(uid) {
        return '<g>'
            + '<ellipse cx="835" cy="670" rx="70" ry="9" fill="#5f471f" opacity=".35"/>'
            + '<path d="M778 622 L892 622 L882 668 L788 668 Z" fill="url(#' + uid + '-yurtWall)" stroke="#9a6c2c" stroke-width="2"/>'
            + '<path d="M778 622 Q835 556 892 622 Z" fill="url(#' + uid + '-yurtRoof)" stroke="#9a6c2c" stroke-width="2"/>'
            + '<path d="M782 622 Q835 608 888 622" fill="none" stroke="#c9a24b" stroke-width="2"/>'
            + '<path d="M835 560 L835 622" stroke="#b8863a" stroke-width="1.5" opacity=".7"/>'
            + '<rect x="820" y="632" width="30" height="36" rx="4" fill="#3b2a18"/>'
            + '<rect x="825" y="636" width="20" height="28" rx="2" fill="#5a3b1f"/>'
            + '<path d="M788 640 q47 10 94 0" fill="none" stroke="#b04a3a" stroke-width="3" opacity=".8"/>'
            + '<path d="M790 650 q45 8 90 0" fill="none" stroke="#c9a24b" stroke-width="1.5" opacity=".8"/>'
            + '<path d="M835 556 q-8 -16 2 -30 q8 -12 2 -24" fill="none" stroke="#fff8e8" stroke-width="3" opacity=".35" stroke-linecap="round"/>'
            + '</g>';
    }

    function family() {
        return '<g>'
            // аксакал
            + '<g transform="translate(690 0)">'
            + '<ellipse cx="0" cy="820" rx="34" ry="6" fill="#5a401c" opacity=".35"/>'
            + '<rect x="-13" y="782" width="11" height="38" rx="4" fill="#241a11"/>'
            + '<rect x="2" y="782" width="11" height="38" rx="4" fill="#241a11"/>'
            + '<path d="M-26 726 Q0 706 26 726 L32 792 Q0 806 -32 792 Z" fill="#1d2c46" stroke="#c9a24b" stroke-width="2"/>'
            + '<path d="M-26 726 L-40 762 L-31 768 L-17 736 Z" fill="#1d2c46" stroke="#c9a24b" stroke-width="2"/>'
            + '<path d="M26 726 L40 762 L31 768 L17 736 Z" fill="#1d2c46" stroke="#c9a24b" stroke-width="2"/>'
            + '<rect x="-34" y="756" width="68" height="10" rx="3" fill="#8a5e22"/>'
            + '<rect x="-7" y="756" width="14" height="10" fill="#e3bf72"/>'
            + '<circle cx="0" cy="704" r="16" fill="#d8a878"/>'
            + '<path d="M-14 706 Q0 736 14 706 Q11 724 0 726 Q-11 724 -14 706" fill="#f4ead8"/>'
            + '<path d="M-16 694 Q0 678 16 694 L14 701 Q0 695 -14 701 Z" fill="#f7f3e8" stroke="#c9a24b" stroke-width="1.4"/>'
            + '<rect x="-16" y="691" width="32" height="5" fill="#2a2119"/>'
            + '<circle cx="-6" cy="703" r="1.4" fill="#3a2a18"/><circle cx="6" cy="703" r="1.4" fill="#3a2a18"/>'
            + '<path d="M-2 708 h4" stroke="#b07a52" stroke-width="1.2"/>'
            + '</g>'
            // взрослый мужчина
            + '<g transform="translate(760 0)">'
            + '<ellipse cx="0" cy="818" rx="28" ry="5" fill="#5a401c" opacity=".35"/>'
            + '<rect x="-11" y="780" width="10" height="38" rx="4" fill="#2b2117"/>'
            + '<rect x="1" y="780" width="10" height="38" rx="4" fill="#2b2117"/>'
            + '<path d="M-22 728 Q0 710 22 728 L28 790 Q0 802 -28 790 Z" fill="#2e4a3a" stroke="#c9a24b" stroke-width="2"/>'
            + '<path d="M-22 728 L-34 758 L-26 764 L-14 738 Z" fill="#2e4a3a" stroke="#c9a24b" stroke-width="2"/>'
            + '<path d="M22 728 L34 758 L26 764 L14 738 Z" fill="#2e4a3a" stroke="#c9a24b" stroke-width="2"/>'
            + '<rect x="-28" y="756" width="56" height="9" rx="3" fill="#6f4a18"/>'
            + '<rect x="-5" y="756" width="10" height="9" fill="#d8ab58"/>'
            + '<circle cx="0" cy="708" r="15" fill="#d8a878"/>'
            + '<path d="M-14 704 Q0 692 14 704 L13 711 Q0 703 -13 711 Z" fill="#3a2a18"/>'
            + '<path d="M-16 704 Q0 690 16 704 L15 710 Q0 700 -15 710 Z" fill="#4a3420" stroke="#c9a24b" stroke-width="1.2"/>'
            + '<path d="M-4 714 q4 3 8 0" stroke="#7a4a2a" stroke-width="1.4" fill="none"/>'
            + '<circle cx="-5" cy="707" r="1.3" fill="#2a1a10"/><circle cx="5" cy="707" r="1.3" fill="#2a1a10"/>'
            + '</g>'
            // женщина в кимешеке: белый капюшон обрамляет лицо, а не закрывает его
            + '<g transform="translate(828 0)">'
            + '<ellipse cx="0" cy="818" rx="28" ry="5" fill="#5a401c" opacity=".35"/>'
            + '<path d="M-24 742 Q0 724 24 742 L30 800 Q0 812 -30 800 Z" fill="#a84b3c" stroke="#c9a24b" stroke-width="2"/>'
            + '<path d="M-20 750 Q0 742 20 750 L17 766 Q0 760 -17 766 Z" fill="#f6efdf" stroke="#d8c39a" stroke-width="1.1"/>'
            + '<path d="M-18 748 L-26 780 L-20 786 L-10 754 Z" fill="#a84b3c" stroke="#c9a24b" stroke-width="1.6"/>'
            + '<path d="M18 748 L26 780 L20 786 L10 754 Z" fill="#a84b3c" stroke="#c9a24b" stroke-width="1.6"/>'
            + '<path d="M-14 762 Q0 756 14 762 L16 796 Q0 800 -16 796 Z" fill="#f2e2c2" opacity=".92"/>'
            + '<rect x="-24" y="766" width="48" height="8" rx="3" fill="#8a5e22"/>'
            + '<path d="M-18 734 Q-16 700 0 698 Q16 700 18 734 L12 750 Q0 756 -12 750 Z" fill="#f6efdf" stroke="#d8c39a" stroke-width="1.2"/>'
            + '<ellipse cx="0" cy="720" rx="10.5" ry="12.5" fill="#d8a878"/>'
            + '<path d="M-10 716 Q0 708 10 716 L9 722 Q0 714 -9 722 Z" fill="#fbf6ea"/>'
            + '<path d="M-8 730 Q0 736 8 730 L6 742 Q0 746 -6 742 Z" fill="#f6efdf" opacity=".95"/>'
            + '<circle cx="-4" cy="720" r="1.3" fill="#3a2a18"/><circle cx="4" cy="720" r="1.3" fill="#3a2a18"/>'
            + '<path d="M-2 726 q2 1.5 4 0" stroke="#a85a42" stroke-width="1.2" fill="none"/>'
            + '<circle cx="-14" cy="724" r="2.2" fill="#e3bf72"/><circle cx="14" cy="724" r="2.2" fill="#e3bf72"/>'
            + '</g>'
            + '</g>';
    }

    function children() {
        return '<g>'
            // девочка
            + '<g transform="translate(250 0)">'
            + '<ellipse cx="0" cy="810" rx="20" ry="4" fill="#5a401c" opacity=".3"/>'
            + '<path d="M-10 780 l-2 28 M8 780 l4 26" stroke="#d8a878" stroke-width="6" stroke-linecap="round"/>'
            + '<path d="M-16 746 Q0 736 16 746 L20 792 Q0 800 -20 792 Z" fill="#3f6f9a"/>'
            + '<path d="M-14 744 Q0 736 14 744 L16 768 Q0 774 -16 768 Z" fill="#a84b3c" stroke="#c9a24b" stroke-width="1.2"/>'
            + '<path d="M-14 750 L-30 736 M14 750 L30 736" stroke="#d8a878" stroke-width="5" stroke-linecap="round"/>'
            + '<circle cx="0" cy="730" r="12" fill="#d8a878"/>'
            + '<path d="M-12 730 Q-10 714 0 714 Q10 714 12 730 Q6 722 0 722 Q-6 722 -12 730" fill="#3a2a18"/>'
            + '<path d="M-10 734 q-6 8 -4 14 M10 734 q6 8 4 14" stroke="#3a2a18" stroke-width="3" stroke-linecap="round" fill="none"/>'
            + '<circle cx="-4" cy="730" r="1.1" fill="#2a1a10"/><circle cx="4" cy="730" r="1.1" fill="#2a1a10"/>'
            + '</g>'
            // мальчик
            + '<g transform="translate(310 0)">'
            + '<ellipse cx="0" cy="812" rx="20" ry="4" fill="#5a401c" opacity=".3"/>'
            + '<path d="M-6 784 l-10 26 M8 784 l12 24" stroke="#3a2a18" stroke-width="6" stroke-linecap="round"/>'
            + '<path d="M-12 750 Q0 740 12 750 L16 792 Q0 798 -16 792 Z" fill="#f2e2c2"/>'
            + '<path d="M-12 748 Q0 742 12 748 L14 770 Q0 776 -14 770 Z" fill="#4a7a4a"/>'
            + '<path d="M-12 754 L-28 742 M12 754 L28 744" stroke="#d8a878" stroke-width="5" stroke-linecap="round"/>'
            + '<circle cx="0" cy="734" r="12" fill="#d8a878"/>'
            + '<path d="M-12 734 Q-10 718 0 718 Q10 718 12 734 L10 728 Q0 722 -10 728 Z" fill="#3a2a18"/>'
            + '<path d="M-12 730 q12 -8 24 0" stroke="#c9a24b" stroke-width="2" fill="none"/>'
            + '<circle cx="-4" cy="734" r="1.1" fill="#2a1a10"/><circle cx="4" cy="734" r="1.1" fill="#2a1a10"/>'
            + '</g>'
            // казахская тазы: длинное тело, узкая морда, висячие уши, кольцом хвост
            + '<g transform="translate(372 820)">'
            + '<ellipse cx="0" cy="0" rx="32" ry="4" fill="#5a401c" opacity=".3"/>'
            + '<ellipse cx="-5" cy="-25" rx="24" ry="9" fill="#d8b47a" stroke="#7a5a2c" stroke-width="2"/>'
            + '<path d="M12 -30 Q16 -38 22 -42 L28 -40 Q24 -34 20 -27 Z" fill="#d8b47a" stroke="#7a5a2c" stroke-width="1.8"/>'
            + '<path d="M20 -42 Q26 -46 34 -44 L36 -42 Q36 -39 32 -39 L26 -38 Q22 -38 20 -40 Z" fill="#d8b47a" stroke="#7a5a2c" stroke-width="1.6"/>'
            + '<path d="M22 -44 Q16 -44 14 -36 Q14 -30 18 -28 Q21 -32 21 -38 Z" fill="#b08a52" stroke="#7a5a2c" stroke-width="1.4"/>'
            + '<circle cx="33" cy="-41" r="1.4" fill="#2a1a10"/>'
            + '<path d="M-27 -26 Q-35 -26 -37 -34 Q-37 -40 -33 -38 Q-31 -35 -35 -33"'
            + ' fill="none" stroke="#b08a52" stroke-width="4" stroke-linecap="round"/>'
            + '<path d="M-18 -18 l-8 10 l-2 8 M-8 -18 l-4 10 l-2 8 M8 -18 l5 10 l-1 8 M16 -18 l7 10 l1 8"'
            + ' fill="none" stroke="#7a5a2c" stroke-width="3" stroke-linecap="round"/>'
            + '<path d="M-28 1 h6 M-14 1 h6 M-6 1 h6 M22 1 h6" stroke="#5a401c" stroke-width="2.4" stroke-linecap="round"/>'
            + '</g>'
            + '</g>';
    }

    function topText(ctx, zhuz, name) {
        let out = '';
        if (zhuz && name) {
            out += renderText(ctx, zhuz, 512, 150, 760, 36, 24, 5, 700, '#12263f', '#fff6e0', 4);
            out += renderText(ctx, name, 512, 222, 820, 68, 30, 3, 700, 'url(#' + ctx.uid + '-nameGold)', '#fff6e0', 5);
        } else if (zhuz) {
            out += renderText(ctx, zhuz, 512, 184, 760, 40, 26, 5, 700, '#12263f', '#fff6e0', 4);
        } else if (name) {
            out += renderText(ctx, name, 512, 190, 820, 72, 30, 3, 700, 'url(#' + ctx.uid + '-nameGold)', '#fff6e0', 5);
        }
        return out;
    }

    function sign(ctx, tamga, isRaster) {
        const glow = '<ellipse cx="512" cy="378" rx="194" ry="180" fill="url(#' + ctx.uid + '-signGlow)"/>';
        if (!tamga) return glow;
        const filter = isRaster ? '' : ' filter="url(#' + ctx.uid + '-ink)"';
        return glow
            + '<image x="377" y="240" width="270" height="270" preserveAspectRatio="xMidYMid meet"'
            + ' href="' + esc(tamga) + '" xlink:href="' + esc(tamga) + '"' + filter + '/>';
    }

    function plaque(ctx, uran) {
        const points = '170,838 206,824 818,824 854,838 854,932 818,946 206,946 170,932';
        const value = uran
            ? renderText(ctx, uran, 512, 902, 600, 54, 24, 3, 700, '#2a1a0a', '', 0)
            : '<path d="M468 898 H556" stroke="#b8862e" stroke-width="3" opacity=".45" stroke-linecap="round"/>';
        return '<g>'
            + '<polygon points="' + points + '" fill="url(#' + ctx.uid + '-plaque)" stroke="#c9973f" stroke-width="5" stroke-linejoin="round"/>'
            + '<polygon points="202,840 230,830 794,830 822,840 822,934 794,944 230,944 202,934" fill="none" stroke="#f0d79c" stroke-width="1.4"/>'
            + '<path d="M198 878 q-14 0 -14 -14 q0 -10 10 -10" fill="none" stroke="#a8782e" stroke-width="3" stroke-linecap="round"/>'
            + '<path d="M826 878 q14 0 14 -14 q0 -10 -10 -10" fill="none" stroke="#a8782e" stroke-width="3" stroke-linecap="round"/>'
            + '<path d="M198 892 q-14 0 -14 14 q0 10 10 10" fill="none" stroke="#a8782e" stroke-width="3" stroke-linecap="round"/>'
            + '<path d="M826 892 q14 0 14 14 q0 10 -10 10" fill="none" stroke="#a8782e" stroke-width="3" stroke-linecap="round"/>'
            + '<text x="512" y="852" text-anchor="middle" font-family="' + esc(ctx.fontFamily) + '" font-size="15"'
            + ' font-weight="700" letter-spacing="6" fill="#a8782e">ҰРАН</text>'
            + value
            + '</g>';
    }

    function buildSvg(o) {
        const opts = o || {};
        const uid = 'ce' + (++seq);
        const zhuz = clean(opts.zhuz).toUpperCase();
        const name = clean(opts.name).toUpperCase();
        const uran = clean(opts.uran).toUpperCase();
        const fontData = opts.fontData || '';
        const fontFamily = fontData ? "ALT117Emblem, Lora, Georgia, 'Times New Roman', serif" : DEFAULT_FONT;
        const ctx = { uid: uid, fontFamily: fontFamily };
        return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"'
            + ' viewBox="0 0 ' + W + ' ' + W + '" width="' + W + '" height="' + W + '"'
            + ' role="img" aria-label="' + esc(opts.ariaLabel || ('Герб рода ' + clean(opts.name))) + '"'
            + ' font-family="' + esc(fontFamily) + '">'
            + defs(uid, fontData)
            + frame(uid)
            + '<g clip-path="url(#' + uid + '-scene)">'
            + sky(uid)
            + mountains(uid)
            + lake(uid)
            + steppe(uid)
            + horses()
            + yurt(uid)
            + children()
            + family()
            + topText(ctx, zhuz, name)
            + sign(ctx, opts.tamga || '', !!opts.tamgaIsRaster)
            + plaque(ctx, uran)
            + '</g>'
            + ornamentAt(uid, 512, 92, .85, 0)
            + ornamentAt(uid, 512, 978, .55, 180)
            + ornamentAt(uid, 60, 512, .62, 90)
            + ornamentAt(uid, 964, 512, .62, -90)
            + '<g fill="none" stroke="url(#' + uid + '-gold)" stroke-width="2.6" stroke-linecap="round" opacity=".92">'
            + '<path d="M52 96 Q52 70 78 70 Q92 70 92 84 Q92 93 83 93 Q77 93 77 87 Q77 82 82 82"/>'
            + '<path d="M972 96 Q972 70 946 70 Q932 70 932 84 Q932 93 941 93 Q947 93 947 87 Q947 82 942 82"/>'
            + '<path d="M52 928 Q52 954 78 954 Q92 954 92 940 Q92 931 83 931 Q77 931 77 937 Q77 942 82 942"/>'
            + '<path d="M972 928 Q972 954 946 954 Q932 954 932 940 Q932 931 941 931 Q947 931 947 937 Q947 942 942 942"/>'
            + '</g>'
            + '</svg>';
    }

    /* ---------- растрирование и PDF ---------- */

    function serialize(svg) {
        if (typeof svg === 'string') return svg;
        const clone = svg.cloneNode(true);
        clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        clone.setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
        clone.setAttribute('width', String(W));
        clone.setAttribute('height', String(W));
        clone.removeAttribute('style');
        clone.removeAttribute('class');
        return new XMLSerializer().serializeToString(clone);
    }

    async function svgToCanvas(svg, size) {
        const px = Math.max(1024, Math.round(Number(size) || 2048));
        const markup = serialize(svg);
        const img = new Image();
        img.decoding = 'sync';
        const url = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(markup);
        await new Promise((resolve, reject) => {
            img.onload = resolve;
            img.onerror = () => reject(new Error('Не удалось отрисовать SVG герба'));
            img.src = url;
        });
        const canvas = document.createElement('canvas');
        canvas.width = px;
        canvas.height = px;
        const ctx = canvas.getContext('2d');
        if (!ctx) throw new Error('Canvas 2D недоступен');
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(img, 0, 0, px, px);
        return canvas;
    }

    async function toPngDataUrl(svg, size) {
        const canvas = await svgToCanvas(svg, size);
        return canvas.toDataURL('image/png');
    }

    async function toJpegBytes(svg, size) {
        const canvas = await svgToCanvas(svg, size);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.95));
        if (!blob) throw new Error('Не удалось получить JPEG');
        return new Uint8Array(await blob.arrayBuffer());
    }

    function buildPdf(jpegBytes, imgW, imgH, pageSize) {
        const enc = new TextEncoder();
        const chunks = [];
        let offset = 0;
        const offsets = [];
        const add = (u8) => { chunks.push(u8); offset += u8.length; };
        const addStr = (s) => add(enc.encode(s));
        addStr('%PDF-1.4\n%\xE2\xE3\xCF\xD3\n');
        const obj = (n, body, stream) => {
            offsets[n] = offset;
            addStr(n + ' 0 obj\n' + body + '\n');
            if (stream) {
                addStr('stream\n');
                add(stream);
                addStr('\nendstream\n');
            }
            addStr('endobj\n');
        };
        obj(1, '<< /Type /Catalog /Pages 2 0 R >>');
        obj(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
        obj(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + pageSize + ' ' + pageSize + ']'
            + ' /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>');
        obj(4, '<< /Type /XObject /Subtype /Image /Width ' + imgW + ' /Height ' + imgH
            + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + jpegBytes.length + ' >>', jpegBytes);
        const content = 'q\n' + pageSize + ' 0 0 ' + pageSize + ' 0 0 cm\n/Im0 Do\nQ\n';
        const contentBytes = enc.encode(content);
        obj(5, '<< /Length ' + contentBytes.length + ' >>', contentBytes);
        const xref = offset;
        addStr('xref\n0 6\n0000000000 65535 f \n');
        for (let i = 1; i <= 5; i++) addStr(String(offsets[i]).padStart(10, '0') + ' 00000 n \n');
        addStr('trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n' + xref + '\n%%EOF\n');
        const total = chunks.reduce((a, c) => a + c.length, 0);
        const out = new Uint8Array(total);
        let p = 0;
        chunks.forEach((c) => { out.set(c, p); p += c.length; });
        return out;
    }

    async function toPdfBytes(svg, size) {
        const px = Math.max(1024, Math.round(Number(size) || 2048));
        const jpeg = await toJpegBytes(svg, px);
        return buildPdf(jpeg, px, px, 1024);
    }

    function safeName(name) {
        const s = String(name || 'Ру — герб').replace(/[\\/:*?"<>|]+/g, ' ').replace(/\s+/g, ' ').trim();
        return s || 'Ру — герб';
    }

    function triggerDownload(url, filename, revoke) {
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        setTimeout(() => {
            if (revoke) URL.revokeObjectURL(url);
            a.remove();
        }, 1500);
    }

    async function downloadPng(svg, filename, size) {
        const dataUrl = await toPngDataUrl(svg, size);
        triggerDownload(dataUrl, safeName(filename) + '.png', false);
        return dataUrl;
    }

    async function downloadPdf(svg, filename, size) {
        const bytes = await toPdfBytes(svg, size);
        const blob = new Blob([bytes], { type: 'application/pdf' });
        const url = URL.createObjectURL(blob);
        triggerDownload(url, safeName(filename) + '.pdf', true);
        return bytes;
    }

    /* ---------- модальное окно ---------- */

    const CSS = ''
        + '#ceOverlay{position:fixed;inset:0;z-index:100000;background:rgba(10,8,5,.86);'
        + 'display:flex;align-items:center;justify-content:center;padding:14px;overflow:auto;'
        + 'backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px);}'
        + '#ceOverlay .ce-panel{width:min(760px,96vw);background:#17120c;border:1px solid rgba(201,168,106,.7);'
        + 'border-radius:24px;padding:12px;box-shadow:0 30px 80px -20px #000;box-sizing:border-box;}'
        + '#ceOverlay .ce-stage{display:flex;align-items:center;justify-content:center;background:#0d0a06;'
        + 'border-radius:18px;overflow:hidden;}'
        + '#ceOverlay .ce-stage svg{display:block;width:min(100%,78vh);height:auto;aspect-ratio:1/1;}'
        + '#ceOverlay .ce-actions{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;padding:12px 4px 2px;}'
        + '#ceOverlay .ce-actions .btn{font-family:Georgia,"Times New Roman",serif;font-size:14px;height:40px;'
        + 'padding:0 16px;border-radius:10px;border:1px solid rgba(201,168,106,.55);background:rgba(255,255,255,.04);'
        + 'color:#f3e6c8;cursor:pointer;}'
        + '#ceOverlay .ce-actions .btn:hover{background:rgba(201,168,106,.16);}'
        + '#ceOverlay .ce-actions .btn.ce-primary{background:#d8b26a;border-color:transparent;color:#221a0e;font-weight:700;}'
        + '#ceOverlay .ce-actions .btn.ce-primary:hover{background:#f0d79c;}'
        + '#ceOverlay .ce-actions .btn:disabled{opacity:.55;cursor:default;}'
        + '#ceOverlay .ce-note{text-align:center;color:#b9a888;font-family:Georgia,"Times New Roman",serif;'
        + 'font-size:13px;line-height:1.4;padding:8px 12px 0;}'
        + '#ceOverlay .ce-msg{text-align:center;color:#e8b4a0;font-family:Georgia,"Times New Roman",serif;'
        + 'font-size:13px;min-height:18px;padding:6px 8px 0;}'
        + '#ceOverlay .ce-loading{display:flex;align-items:center;justify-content:center;min-height:52vh;'
        + 'color:#d8c49a;font-family:Georgia,"Times New Roman",serif;font-size:16px;letter-spacing:.04em;}'
        + '@media (max-width:560px){#ceOverlay{padding:8px;}'
        + '#ceOverlay .ce-panel{width:96vw;padding:8px;border-radius:18px;}'
        + '#ceOverlay .ce-stage{border-radius:14px;}'
        + '#ceOverlay .ce-stage svg{width:min(100%,70vh);}'
        + '#ceOverlay .ce-actions .btn{flex:1 1 42%;height:42px;font-size:14px;}}';

    function ensureStyles() {
        if (typeof document === 'undefined' || document.getElementById('ceStyles')) return;
        const s = document.createElement('style');
        s.id = 'ceStyles';
        s.textContent = CSS;
        document.head.appendChild(s);
    }

    function removeOverlay() {
        if (typeof document === 'undefined') return;
        const old = document.getElementById('ceOverlay');
        if (old) old.remove();
        document.body.style.overflow = savedOverflow;
    }

    async function open(o) {
        if (typeof document === 'undefined') return null;
        ensureStyles();
        const opts = o || {};
        const token = ++openSeq;
        removeOverlay();
        savedOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        const labels = Object.assign({
            loading: 'Загрузка герба…',
            png: 'Скачать PNG',
            pdf: 'Скачать PDF',
            close: 'Закрыть',
            noSign: 'Таңба не загружена — герб показан без знака.',
            error: 'Не удалось подготовить файл.'
        }, opts.labels || {});

        const ov = document.createElement('div');
        ov.id = 'ceOverlay';
        ov.setAttribute('role', 'presentation');
        ov.innerHTML = '<div class="ce-panel"><div class="ce-loading">' + esc(labels.loading) + '</div></div>';
        document.body.appendChild(ov);

        const close = () => {
            ov.remove();
            document.body.style.overflow = savedOverflow;
            document.removeEventListener('keydown', onKey);
        };
        const onKey = (ev) => { if (ev.key === 'Escape') close(); };
        document.addEventListener('keydown', onKey);
        ov.addEventListener('click', (ev) => {
            if (ev.target === ov) close();
            const b = ev.target.closest ? ev.target.closest('[data-ce]') : null;
            if (!b) return;
            const act = b.getAttribute('data-ce');
            if (act === 'close') { close(); return; }
            const svg = ov.querySelector('svg');
            if (!svg) return;
            const name = opts.filename || ('Ру — ' + (opts.name || 'герб'));
            const msg = ov.querySelector('.ce-msg');
            b.disabled = true;
            const oldText = b.textContent;
            b.textContent = '…';
            (async () => {
                try {
                    if (act === 'png') await downloadPng(svg, name, 2048);
                    else if (act === 'pdf') await downloadPdf(svg, name, 2048);
                } catch (e) {
                    if (msg) msg.textContent = labels.error + ' ' + (e && e.message ? e.message : '');
                } finally {
                    b.disabled = false;
                    b.textContent = oldText;
                }
            })();
        });

        const [fontData, tamga] = await Promise.all([ensureFont(), loadTamga(opts.tamgaUrl)]);
        if (token !== openSeq) return null;

        const svg = buildSvg({
            zhuz: opts.zhuz,
            name: opts.name,
            uran: opts.uran,
            tamga: tamga.data,
            tamgaIsRaster: tamga.isRaster,
            fontData: fontData,
            ariaLabel: opts.ariaLabel || ('Герб рода ' + clean(opts.name))
        });
        const note = (tamga.missing || !tamga.data)
            ? '<div class="ce-note">' + esc(labels.noSign) + '</div>'
            : '';
        const panel = ov.querySelector('.ce-panel');
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        panel.setAttribute('aria-label', opts.ariaLabel || ('Герб рода ' + clean(opts.name)));
        panel.innerHTML = '<div class="ce-stage">' + svg + '</div>'
            + '<div class="ce-actions">'
            + '<button type="button" class="btn ce-primary" data-ce="png">' + esc(labels.png) + '</button>'
            + '<button type="button" class="btn" data-ce="pdf">' + esc(labels.pdf) + '</button>'
            + '<button type="button" class="btn" data-ce="close">' + esc(labels.close) + '</button>'
            + '</div>'
            + '<div class="ce-msg" role="status"></div>'
            + note;
        return ov;
    }

    return {
        buildSvg: buildSvg,
        open: open,
        close: function () { openSeq++; removeOverlay(); },
        ensureFont: ensureFont,
        loadTamga: loadTamga,
        toPngDataUrl: toPngDataUrl,
        toPdfBytes: toPdfBytes,
        toJpegBytes: toJpegBytes,
        buildPdf: buildPdf,
        downloadPng: downloadPng,
        downloadPdf: downloadPdf
    };
});
