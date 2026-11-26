/* ALT-117 (2026-11-26, редакция 2): единый герб рода для превью, окна и экспорта.
 *
 * Один рендерер на все представления:
 *   - превью в галерее «Ру таңбалары»;
 *   - живое превью в редакторе рода;
 *   - модальное окно по клику;
 *   - PNG и PDF.
 *
 * Фон — статический растровый слой, утверждённый владельцем. Пока файла нет,
 * рисуется НЕЙТРАЛЬНАЯ каменная подложка (без пейзажа, животных, юрты и людей):
 * это честный плейсхолдер, а не замена утверждённой иллюстрации. Слот:
 * /assets/img/clan-emblem/approved-background.{jpg,jpeg,png,webp}.
 *
 * Динамические слои: жуз, название рода, ұран и исходная таңба (геометрия не
 * меняется). Надписи горизонтальные, шрифт Lora с казахскими буквами.
 *
 * API (window.ClanEmblem):
 *   buildSvg(o)                 — SVG-строка 1024×1024;
 *   mount(el, o)                — превью в контейнере (тот же buildSvg);
 *   mountAll(root)              — все [data-clan-emblem] на странице;
 *   open(o)                     — модальное окно с тем же гербом;
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
    const EMBED_FONT = "ALT117Emblem, Lora, Georgia, 'Times New Roman', serif";
    const DEFAULT_BACKGROUNDS = [
        '/assets/brand/altybayev-brand/backgrounds/clan-emblem.png',
        '/assets/img/clan-emblem/approved-background.jpg',
        '/assets/img/clan-emblem/approved-background.jpeg',
        '/assets/img/clan-emblem/approved-background.png',
        '/assets/img/clan-emblem/approved-background.webp'
    ];
    let seq = 0;
    let measureCtx = null;
    let fontPromise = null;
    let backgroundPromise = null;
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

    function renderTextAt(ctx, text, x, y, max, size, ls, weight, fill, stroke, strokeW) {
        if (!text) return '';
        const width = measureText(text, size, ls, weight, ctx.fontFamily);
        const tl = width > max ? ' textLength="' + max + '" lengthAdjust="spacingAndGlyphs"' : '';
        const st = stroke
            ? ' stroke="' + stroke + '" stroke-width="' + (strokeW || 4) + '" stroke-opacity=".55" paint-order="stroke"'
            : '';
        return '<text x="' + x + '" y="' + y + '" text-anchor="middle" font-family="' + esc(ctx.fontFamily) + '"'
            + ' font-size="' + size.toFixed(1) + '" font-weight="' + weight + '" letter-spacing="' + ls + '"'
            + ' fill="' + fill + '"' + st + tl + '>' + esc(text) + '</text>';
    }

    // Длинное название: одна строка, а если не помещается — две по границе
    // слова/дефиса. Шрифт при этом остаётся читаемым, а не сжимается в точку.
    function wrapName(text) {
        const s = clean(text);
        if (!s) return [''];
        const seps = [];
        for (let i = 0; i < s.length; i++) if (s[i] === '-' || s[i] === ' ') seps.push(i);
        if (!seps.length) return [s];
        const mid = s.length / 2;
        let best = seps[0];
        for (const i of seps) if (Math.abs(i - mid) < Math.abs(best - mid)) best = i;
        if (best < 3 || best > s.length - 4) return [s];
        const keep = s[best] === '-' ? 1 : 0;
        const first = s.slice(0, best + keep).trim();
        const second = s.slice(best + keep).trim();
        return [first, second].filter(Boolean);
    }

    function layoutName(name, ctx) {
        if (!name) return { lines: [], size: 68, ys: [], signTop: 360 };
        const one = fitText(name, 820, 68, 48, 3, 700, ctx.fontFamily);
        if (!one.textLength) return { lines: [name], size: one.size, ys: [265], signTop: 360 };
        const lines = wrapName(name);
        if (lines.length < 2) return { lines: [name], size: one.size, ys: [265], signTop: 360 };
        let size = 54;
        while (size > 30 && lines.some((l) => measureText(l, size, 3, 700, ctx.fontFamily) > 820)) size--;
        return { lines: lines, size: size, ys: [230, 285], signTop: 360 };
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

    function normalizeTamga(url, isRaster) {
        const data = clean(url);
        return { data: data, isRaster: !!isRaster, missing: !data };
    }

    async function loadTamga(url) {
        const src = clean(url);
        if (!src) return { data: '', isRaster: false, missing: true };
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

    /* ---------- фон ---------- */

    function backgroundCandidates() {
        if (typeof window !== 'undefined' && Object.prototype.hasOwnProperty.call(window, 'CLAN_EMBLEM_BG')) {
            const v = clean(window.CLAN_EMBLEM_BG);
            return v ? [v] : [];
        }
        return DEFAULT_BACKGROUNDS.slice();
    }

    async function loadBackground() {
        if (backgroundPromise) return backgroundPromise;
        backgroundPromise = (async () => {
            const candidates = backgroundCandidates();
            if (!candidates.length || typeof fetch !== 'function') {
                return { href: '', data: '', isRaster: false, missing: true };
            }
            for (const url of candidates) {
                try {
                    const r = await fetch(url, { credentials: 'same-origin' });
                    if (!r.ok) continue;
                    const blob = await r.blob();
                    if (!blob || !blob.size || !/^image\//i.test(blob.type)) continue;
                    const data = await blobToDataUrl(blob);
                    if (!data) continue;
                    return { href: url, data: data, isRaster: true, missing: false };
                } catch (e) { /* пробуем следующий кандидат */ }
            }
            return { href: '', data: '', isRaster: false, missing: true };
        })();
        return backgroundPromise;
    }

    /* ---------- декоративные части ---------- */
    /* Рамку, орнамент и нижнюю плашку даёт утверждённый растровый фон из
       комплекта бренда. Поверх него не рисуем вторую рамку/плашку. */

    function defs(uid, fontData) {
        const fontCss = fontData
            ? '<style type="text/css"><![CDATA[@font-face{font-family:\'ALT117Emblem\';src:url(data:font/ttf;base64,'
                + fontData
                + ') format(\'truetype\');font-weight:700;font-style:normal;}]]></style>'
            : '';
        return '<defs>'
            + fontCss
            + '<linearGradient id="' + uid + '-nameGold" x1="0" y1="0" x2="0" y2="1">'
            + '<stop offset="0" stop-color="#7a4a0c"/><stop offset=".45" stop-color="#a86a12"/>'
            + '<stop offset="1" stop-color="#5e3707"/>'
            + '</linearGradient>'
            + '<radialGradient id="' + uid + '-signGlow" cx="50%" cy="50%" r="50%">'
            + '<stop offset="0" stop-color="#fffdf4" stop-opacity=".34"/>'
            + '<stop offset=".56" stop-color="#fff8e6" stop-opacity=".12"/>'
            + '<stop offset="1" stop-color="#fff8e6" stop-opacity="0"/>'
            + '</radialGradient>'
            + '<linearGradient id="' + uid + '-stoneBase" x1="0" y1="0" x2="1" y2="1">'
            + '<stop offset="0" stop-color="#2b261f"/><stop offset=".34" stop-color="#191612"/>'
            + '<stop offset=".68" stop-color="#232019"/><stop offset="1" stop-color="#100e0b"/>'
            + '</linearGradient>'
            + '<radialGradient id="' + uid + '-stoneGlow" cx="50%" cy="42%" r="64%">'
            + '<stop offset="0" stop-color="#6d5940" stop-opacity=".42"/>'
            + '<stop offset=".44" stop-color="#3a3026" stop-opacity=".22"/>'
            + '<stop offset="1" stop-color="#0b0907" stop-opacity="0"/>'
            + '</radialGradient>'
            + '<radialGradient id="' + uid + '-vignette" cx="50%" cy="50%" r="72%">'
            + '<stop offset="0" stop-color="#000" stop-opacity="0"/>'
            + '<stop offset=".72" stop-color="#000" stop-opacity="0"/>'
            + '<stop offset="1" stop-color="#000" stop-opacity=".5"/>'
            + '</radialGradient>'
            + '<pattern id="' + uid + '-grain" width="26" height="26" patternUnits="userSpaceOnUse">'
            + '<circle cx="3" cy="5" r=".8" fill="#e9d6ad" opacity=".10"/>'
            + '<circle cx="17" cy="9" r=".6" fill="#fff6dd" opacity=".07"/>'
            + '<circle cx="9" cy="21" r=".7" fill="#b9975a" opacity=".09"/>'
            + '<circle cx="23" cy="19" r=".5" fill="#fff1cf" opacity=".06"/>'
            + '</pattern>'
            + '<filter id="' + uid + '-ink" x="-5%" y="-5%" width="110%" height="110%" color-interpolation-filters="sRGB">'
            + '<feColorMatrix type="matrix" values="0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 1 0"/>'
            + '</filter>'
            + '</defs>';
    }

    /* Нейтральная каменная подложка: без пейзажа, животных, юрты и людей.
       Это аварийный плейсхолдер, если утверждённый файл фона не найден. */
    function neutralScene(uid) {
        return '<rect x="0" y="0" width="1024" height="1024" fill="url(#' + uid + '-stoneBase)"/>'
            + '<rect x="0" y="0" width="1024" height="1024" fill="url(#' + uid + '-stoneGlow)"/>'
            + '<rect x="0" y="0" width="1024" height="1024" fill="url(#' + uid + '-grain)" opacity=".55"/>'
            + '<rect x="8" y="8" width="1008" height="1008" rx="48" fill="none" stroke="#c9a86a" stroke-width="6"/>'
            + '<rect x="20" y="20" width="984" height="984" rx="40" fill="none" stroke="#e6c98f" stroke-width="1.2" opacity=".7"/>'
            + '<rect x="0" y="0" width="1024" height="1024" fill="url(#' + uid + '-vignette)"/>';
    }

    function approvedScene(uid, href) {
        // Утверждённый растр уже содержит рамку, орнамент и нижнюю плашку.
        return '<image x="0" y="0" width="1024" height="1024" preserveAspectRatio="xMidYMid slice"'
            + ' href="' + esc(href) + '" xlink:href="' + esc(href) + '"/>';
    }

    function scene(uid, background) {
        const approved = clean(background);
        return approved ? approvedScene(uid, approved) : neutralScene(uid);
    }

    function topText(ctx, zhuz, layout) {
        let out = '';
        const two = layout.lines.length > 1;
        if (layout.lines.length) {
            if (zhuz) {
                out += renderText(ctx, zhuz, 512, two ? 160 : 190, 760, 34, 22, 5, 700, '#10263f', '#fff6e0', 4);
            }
            layout.lines.forEach((line, i) => {
                out += renderTextAt(ctx, line, 512, layout.ys[i], 820, layout.size, 3, 700, 'url(#' + ctx.uid + '-nameGold)', '#fff6e0', 5);
            });
        } else if (zhuz) {
            out += renderText(ctx, zhuz, 512, 200, 760, 40, 26, 5, 700, '#10263f', '#fff6e0', 4);
        }
        return out;
    }

    function sign(ctx, tamga, isRaster, top) {
        const y = Number(top) || 360;
        const cy = y + 155;
        const glow = '<ellipse cx="512" cy="' + cy + '" rx="190" ry="178" fill="url(#' + ctx.uid + '-signGlow)"/>';
        if (!tamga) {
            return glow
                + '<circle cx="512" cy="' + cy + '" r="150" fill="none" stroke="#f3e6c8" stroke-width="1.6"'
                + ' stroke-dasharray="3 8" opacity=".7"/>';
        }
        const filter = isRaster ? '' : ' filter="url(#' + ctx.uid + '-ink)"';
        return glow
            + '<image x="357" y="' + y + '" width="310" height="310" preserveAspectRatio="xMidYMid meet"'
            + ' href="' + esc(tamga) + '" xlink:href="' + esc(tamga) + '"' + filter + '/>';
    }

    function plaque(ctx, uran, hasBackground) {
        if (hasBackground) {
            // Нижняя плашка уже встроена в утверждённый растр. Не накладываем
            // вторую плашку и не добавляем подпись «ҰРАН» — только значение.
            return uran ? renderText(ctx, uran, 512, 900, 780, 58, 24, 3, 700, '#2a1a0a', '', 0) : '';
        }
        // Аварийный вариант без растра: рисуем читаемую плашку сами.
        const points = '170,838 206,824 818,824 854,838 854,932 818,946 206,946 170,932';
        const value = uran
            ? renderText(ctx, uran, 512, 900, 600, 54, 24, 3, 700, '#2a1a0a', '', 0)
            : '<path d="M468 898 H556" stroke="#b8862e" stroke-width="3" opacity=".45" stroke-linecap="round"/>';
        return '<g>'
            + '<polygon points="' + points + '" fill="#f4e3c0" stroke="#c9973f" stroke-width="5" stroke-linejoin="round"/>'
            + '<polygon points="202,840 230,830 794,830 822,840 822,934 794,944 230,944 202,934" fill="none" stroke="#f0d79c" stroke-width="1.4"/>'
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
        const background = clean(opts.background);
        const fontData = opts.fontData || '';
        const fontFamily = fontData ? EMBED_FONT : DEFAULT_FONT;
        const ctx = { uid: uid, fontFamily: fontFamily };
        const nameLayout = layoutName(name, ctx);
        const hidden = opts.ariaHidden ? ' aria-hidden="true"' : '';
        const role = opts.ariaHidden ? 'presentation' : 'img';
        return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"'
            + ' viewBox="0 0 ' + W + ' ' + W + '" width="' + W + '" height="' + W + '"'
            + ' role="' + role + '"' + hidden
            + ' data-ce-background="' + (background ? 'approved' : 'neutral') + '"'
            + ' aria-label="' + esc(opts.ariaLabel || ('Герб рода ' + clean(opts.name))) + '"'
            + ' font-family="' + esc(fontFamily) + '">'
            + defs(uid, fontData)
            + scene(uid, background)
            + topText(ctx, zhuz, nameLayout)
            + sign(ctx, opts.tamga || '', !!opts.tamgaIsRaster, nameLayout.signTop)
            + plaque(ctx, uran, !!background)
            + '</svg>';
    }

    /* ---------- превью: тот же buildSvg, другой масштаб ---------- */

    function parseOpts(el) {
        try {
            const raw = el.getAttribute('data-clan-emblem') || '{}';
            const data = JSON.parse(raw);
            return data && typeof data === 'object' ? data : {};
        } catch (e) { return {}; }
    }

    async function mount(el, o) {
        if (!el) return null;
        const opts = o || {};
        const tamga = opts.tamga !== undefined
            ? normalizeTamga(opts.tamga, opts.tamgaIsRaster)
            : await loadTamga(opts.tamgaUrl);
        let background = clean(opts.background);
        if (!background) {
            const bg = await loadBackground();
            background = bg.href || '';
        }
        const svg = buildSvg({
            zhuz: opts.zhuz,
            name: opts.name,
            uran: opts.uran,
            tamga: tamga.data,
            tamgaIsRaster: tamga.isRaster,
            background: background,
            ariaLabel: opts.ariaLabel,
            ariaHidden: true,
            fontData: ''
        });
        el.innerHTML = svg;
        el.classList.add('is-mounted');
        return svg;
    }

    async function mountAll(rootEl) {
        if (typeof document === 'undefined') return 0;
        const scope = rootEl || document;
        const nodes = Array.from(scope.querySelectorAll('[data-clan-emblem]'));
        let mounted = 0;
        let i = 0;
        const workers = Math.max(1, Math.min(6, nodes.length));
        async function worker() {
            while (i < nodes.length) {
                const el = nodes[i++];
                if (el.getAttribute('data-ce-mounted') === '1') continue;
                el.setAttribute('data-ce-mounted', '1');
                const opts = parseOpts(el);
                try {
                    await mount(el, opts);
                    mounted++;
                } catch (e) {
                    el.removeAttribute('data-ce-mounted');
                }
            }
        }
        await Promise.all(Array.from({ length: workers }, worker));
        return mounted;
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
        + '#ceOverlay .ce-note.ce-note-warn{color:#e0c48e;}'
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
            noBackground: 'Утверждённый художественный фон ещё не загружен — показан нейтральный макет. Пришлите файл фона, чтобы включить его без перерисовки.',
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

        const [fontData, tamga, bg] = await Promise.all([ensureFont(), loadTamga(opts.tamgaUrl), loadBackground()]);
        if (token !== openSeq) return null;

        const svg = buildSvg({
            zhuz: opts.zhuz,
            name: opts.name,
            uran: opts.uran,
            tamga: tamga.data,
            tamgaIsRaster: tamga.isRaster,
            background: bg.data,
            fontData: fontData,
            ariaLabel: opts.ariaLabel || ('Герб рода ' + clean(opts.name))
        });
        const notes = [];
        if (bg.missing) notes.push('<div class="ce-note ce-note-warn">' + esc(labels.noBackground) + '</div>');
        if (tamga.missing || !tamga.data) notes.push('<div class="ce-note">' + esc(labels.noSign) + '</div>');
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
            + notes.join('');
        return ov;
    }

    function autoMount() {
        if (typeof document === 'undefined') return;
        const run = () => { mountAll(document).catch(() => {}); };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run, { once: true });
        } else {
            setTimeout(run, 0);
        }
    }
    autoMount();

    return {
        buildSvg: buildSvg,
        mount: mount,
        mountAll: mountAll,
        open: open,
        close: function () { openSeq++; removeOverlay(); },
        ensureFont: ensureFont,
        loadTamga: loadTamga,
        loadBackground: loadBackground,
        backgroundCandidates: backgroundCandidates,
        toPngDataUrl: toPngDataUrl,
        toPdfBytes: toPdfBytes,
        toJpegBytes: toJpegBytes,
        buildPdf: buildPdf,
        downloadPng: downloadPng,
        downloadPdf: downloadPdf
    };
});
