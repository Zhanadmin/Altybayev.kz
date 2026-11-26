/* ALT-092: общий код «Жеті ата» — табличка, PNG, PDF.
   ALT-120: карточка полностью переведена на утверждённый стиль ALT-117:
   тёмный камень, золотая рамка, кремовые плашки, герб рода из ClanEmblem.
   API: ZhetiAta.labels(lang), ZhetiAta.render(box, personId, lang, onPerson), ZhetiAta.downloadPng(), ZhetiAta.downloadPdf(), ZhetiAta.printPdf() */
(function () {
    'use strict';

    function labels(currentLang) {
        const T = {
            kk: {
                title: 'Жеті ата', subtitle: 'Адамның әке желісі бойынша жеті атасы', person: 'Адам:', run: 'Жеті ата', close: 'Жабу',
                unknown: 'белгісіз', clan: 'Ру', loading: 'Жүктелуде...', error: 'Қате',
                known: (n) => `Жеті атадан белгілісі: ${n}`,
                levels: ['Өзі', 'Әке', 'Ата', 'Арғы ата', 'Баба', 'Түп ата', 'Дүние ата', 'Қаймана ата']
            },
            ru: {
                title: 'Жеті ата', subtitle: 'Семь отцов человека по отцовской линии', person: 'Человек:', run: 'Жеті ата', close: 'Закрыть',
                unknown: 'белгісіз', clan: 'Ру', loading: 'Загрузка...', error: 'Ошибка',
                known: (n) => `Известно предков: ${n} из 7`,
                levels: ['Он сам', 'Отец (әке)', 'Дед (ата)', 'Прадед (арғы ата)', 'Прапрадед (баба)', '5-е колено (түп ата)', '6-е колено (дүние ата)', '7-е колено (қаймана ата)']
            },
            en: {
                title: 'Zheti ata', subtitle: 'A person’s seven fathers along the paternal line', person: 'Person:', run: 'Zheti ata', close: 'Close',
                unknown: 'белгісіз', clan: 'Clan', loading: 'Loading...', error: 'Error',
                known: (n) => `Known ancestors: ${n} of 7`,
                levels: ['Himself', 'Father (əke)', 'Grandfather (ata)', 'Great-grandfather (argı ata)', '4th generation (baba)', '5th generation (tüp ata)', '6th generation (dünie ata)', '7th generation (qaymana ata)']
            },
            es: {
                title: 'Zheti ata', subtitle: 'Los siete padres de una persona por línea paterna', person: 'Persona:', run: 'Zheti ata', close: 'Cerrar',
                unknown: 'белгісіз', clan: 'Clan', loading: 'Cargando...', error: 'Error',
                known: (n) => `Antepasados conocidos: ${n} de 7`,
                levels: ['Él mismo', 'Padre (əke)', 'Abuelo (ata)', 'Bisabuelo (argı ata)', '4.ª generación (baba)', '5.ª generación (tüp ata)', '6.ª generación (dünie ata)', '7.ª generación (qaymana ata)']
            }
        };
        const EXTRA = {
            kk: { uran: 'Ұран', pdf: 'PDF жасау', print: 'Басып шығару', download: 'Картинкамен жүктеу (PNG)', png: 'PNG жүктеу', emblemPdf: 'PDF жүктеу', loading: 'Елтаңба дайындалуда…', noSign: 'Таңба жүктелмеген — елтаңба таңбасыз көрсетілді.', noBackground: 'Бекітілген көркем фон әлі жүктелмеген — бейтарап тас макет көрсетілді. Фон файлын жіберіңіз.', zhuz: { senior: 'Ұлы жүз', middle: 'Орта жүз', junior: 'Кіші жүз', other: 'Жүзден тыс' } },
            ru: { uran: 'Ұран', pdf: 'Сохранить в PDF', print: 'Печать', download: 'Скачать картинкой (PNG)', png: 'Скачать PNG', emblemPdf: 'Скачать PDF', loading: 'Готовим герб…', noSign: 'Таңба не загружена — герб показан без знака.', noBackground: 'Утверждённый художественный фон ещё не загружен — показан нейтральный макет. Пришлите файл фона.', zhuz: { senior: 'Старший жуз', middle: 'Средний жуз', junior: 'Младший жуз', other: 'Вне жузов' } },
            en: { uran: 'Battle cry', pdf: 'Save as PDF', print: 'Print', download: 'Download as image (PNG)', png: 'Download PNG', emblemPdf: 'Download PDF', loading: 'Preparing the emblem…', noSign: 'The tamga is not loaded — the emblem is shown without it.', noBackground: 'The approved artwork background is not uploaded yet — a neutral layout is shown. Please send the background file.', zhuz: { senior: 'Senior Zhuz', middle: 'Middle Zhuz', junior: 'Junior Zhuz', other: 'Outside the zhuzes' } },
            es: { uran: 'Grito de guerra', pdf: 'Guardar como PDF', print: 'Imprimir', download: 'Descargar como imagen (PNG)', png: 'Descargar PNG', emblemPdf: 'Descargar PDF', loading: 'Preparando el emblema…', noSign: 'La tamga no está cargada — el emblema se muestra sin ella.', noBackground: 'El fondo artístico aprobado aún no se ha subido — se muestra un diseño neutro. Envíe el archivo del fondo.', zhuz: { senior: 'Zhuz Mayor', middle: 'Zhuz Medio', junior: 'Zhuz Menor', other: 'Fuera de los zhuz' } }
        };
        const l = T[currentLang] || T.kk;
        Object.assign(l, EXTRA[currentLang] || EXTRA.kk);
        if (currentLang === 'ru') l.unknown = 'неизвестен (белгісіз)';
        if (currentLang === 'en') l.unknown = 'unknown (белгісіз)';
        if (currentLang === 'es') l.unknown = 'desconocido (белгісіз)';
        return l;
    }

    const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;'
    }[c]));

    const CARD_W = 720;
    const INK = '#17120c';
    const GOLD = '#c9a86a';
    const GOLD_LIGHT = '#e6c98f';
    const GOLD_DARK = '#a8782e';
    const CREAM = '#f3e6c8';
    const CREAM_PLAQUE = '#f4e3c0';
    const PLAQUE_INK = '#2a1a0a';

    function bevelPoints(x, y, w, h, b) {
        return [x + b, y, x + w - b, y, x + w, y + b, x + w, y + h - b,
            x + w - b, y + h, x + b, y + h, x, y + h - b, x, y + b]
            .join(' ');
    }

    function yearOnly(v) {
        const m = String(v == null ? '' : v).match(/\d{4}/);
        return m ? m[0] : '';
    }

    function lifeYears(p) {
        if (!p) return '';
        const by = yearOnly(p.birth_year), dy = yearOnly(p.death_year);
        if (!by && !dy) return '';
        return `${by || '…'}–${(p.is_deceased || dy) ? (dy || '…') : ''}`;
    }

    function fitSize(text, base, max, min) {
        const len = Array.from(String(text || '')).length;
        return Math.max(min, Math.min(base, Math.floor(max / (Math.max(1, len) * 0.58))));
    }

    function diamond(cx, cy, r, fill) {
        return `<path d="M${cx} ${cy - r} L${cx + r} ${cy} L${cx} ${cy + r} L${cx - r} ${cy} Z" fill="${fill}"/>`;
    }

    /* Единый SVG вертикальной карточки: экран, PNG, PDF и печать используют один и тот же разметочный код.
       o: { rows, levels, unknown, personName, personYears, subtitle, emblemInner, fontData, ariaLabel } */
    function buildPlaque(o) {
        const opts = o || {};
        const rows = Array.isArray(opts.rows) ? opts.rows : [];
        const levels = Array.isArray(opts.levels) ? opts.levels : [];
        const ROW_TOP = 736;
        const ROW_H = 84;
        const ROW_GAP = 8;
        const STEP = ROW_H + ROW_GAP;
        const rowsBottom = ROW_TOP + rows.length * STEP - ROW_GAP;
        const footerY = rowsBottom + 44;
        const H = footerY + 72;
        const fontData = String(opts.fontData || '');
        const fontFamily = fontData
            ? "ALT120Card, Lora, Georgia, 'Times New Roman', serif"
            : "Lora, Georgia, 'Times New Roman', serif";
        const fontCss = fontData
            ? '<style type="text/css"><![CDATA[@font-face{font-family:\'ALT120Card\';src:url(data:font/ttf;base64,'
                + fontData + ') format(\'truetype\');font-weight:400 700;font-style:normal;}]]></style>'
            : '';
        const emblem = String(opts.emblemInner || '').trim();
        const neutral = !emblem;

        let rowSvg = '';
        rows.forEach((row, i) => {
            const y = ROW_TOP + i * STEP;
            const p = row && row.person ? row.person : null;
            const isSelf = Number(row && row.level) === 0;
            const label = String(levels[Number(row && row.level)] || '').toUpperCase();
            const fill = isSelf ? '#f8ecd4' : CREAM_PLAQUE;
            const name = p ? String(p.first_name || '').trim() : '';
            const years = lifeYears(p);
            const nameSize = fitSize(name, 30, 520, 15);
            rowSvg += `<polygon points="${bevelPoints(62, y, 596, ROW_H, 14)}" fill="${fill}" stroke="${GOLD_DARK}" stroke-width="2.6" stroke-linejoin="round"/>`
                + `<polygon points="${bevelPoints(69, y + 7, 582, ROW_H - 14, 10)}" fill="none" stroke="${GOLD_LIGHT}" stroke-width="1.1" opacity=".9" stroke-linejoin="round"/>`
                + diamond(46, y + ROW_H / 2, 5, GOLD)
                + diamond(674, y + ROW_H / 2, 5, GOLD)
                + (label ? `<text x="360" y="${y + 21}" text-anchor="middle" font-family="${esc(fontFamily)}" font-size="12.5" font-weight="700" letter-spacing="2.6" fill="${GOLD_DARK}">${esc(label)}</text>` : '')
                + (p
                    ? `<text class="za-name" data-person-id="${parseInt(p.id, 10)}" x="360" y="${y + 51}" text-anchor="middle" font-family="${esc(fontFamily)}" font-size="${nameSize}" font-weight="700" fill="${PLAQUE_INK}"${opts.onPerson ? ' style="cursor:pointer"' : ''}>${esc(name)}</text>`
                        + (years ? `<text x="360" y="${y + 71}" text-anchor="middle" font-family="${esc(fontFamily)}" font-size="16" fill="#6b4a2e">${esc(years)}</text>` : '')
                    : `<text x="360" y="${y + 53}" text-anchor="middle" font-family="${esc(fontFamily)}" font-size="22" font-style="italic" fill="#8a6a40">${esc(opts.unknown || 'белгісіз')}</text>`);
        });

        const personLine = [opts.personName, opts.personYears].filter(Boolean).join(' · ');
        const subtitle = String(opts.subtitle || '').trim().toUpperCase();

        return `<svg id="zhetiAtaPlaque" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${CARD_W} ${H}" width="100%" style="max-width:${CARD_W}px; display:block; margin:0 auto;" font-family="${esc(fontFamily)}" role="img" aria-label="${esc(opts.ariaLabel || 'Жеті ата')}" data-za-style="alt117-v1">
            <defs>
                ${fontCss}
                <linearGradient id="zaCardGold" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#f0d79c"/><stop offset=".5" stop-color="#c9973f"/><stop offset="1" stop-color="#8a5f12"/>
                </linearGradient>
                <linearGradient id="zaCardStone" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#2b261f"/><stop offset=".34" stop-color="#191612"/>
                    <stop offset=".68" stop-color="#232019"/><stop offset="1" stop-color="#100e0b"/>
                </linearGradient>
                <radialGradient id="zaCardGlow" cx="50%" cy="41%" r="64%">
                    <stop offset="0" stop-color="#6d5940" stop-opacity=".44"/>
                    <stop offset=".44" stop-color="#3a3026" stop-opacity=".22"/>
                    <stop offset="1" stop-color="#0b0907" stop-opacity="0"/>
                </radialGradient>
                <radialGradient id="zaCardVignette" cx="50%" cy="50%" r="72%">
                    <stop offset="0" stop-color="#000" stop-opacity="0"/>
                    <stop offset=".72" stop-color="#000" stop-opacity="0"/>
                    <stop offset="1" stop-color="#000" stop-opacity=".5"/>
                </radialGradient>
                <pattern id="zaCardGrain" width="26" height="26" patternUnits="userSpaceOnUse">
                    <circle cx="3" cy="5" r=".8" fill="#e9d6ad" opacity=".10"/>
                    <circle cx="17" cy="9" r=".6" fill="#fff6dd" opacity=".07"/>
                    <circle cx="9" cy="21" r=".7" fill="#b9975a" opacity=".09"/>
                    <circle cx="23" cy="19" r=".5" fill="#fff1cf" opacity=".06"/>
                </pattern>
                <clipPath id="zaCardClip"><rect x="0" y="0" width="${CARD_W}" height="${H}" rx="28"/></clipPath>
            </defs>
            <g clip-path="url(#zaCardClip)">
                <rect x="0" y="0" width="${CARD_W}" height="${H}" fill="url(#zaCardStone)"/>
                <rect x="0" y="0" width="${CARD_W}" height="${H}" fill="url(#zaCardGlow)"/>
                <rect x="0" y="0" width="${CARD_W}" height="${H}" fill="url(#zaCardGrain)" opacity=".55"/>
                <rect x="0" y="0" width="${CARD_W}" height="${H}" fill="url(#zaCardVignette)"/>
            </g>
            <rect x="7" y="7" width="${CARD_W - 14}" height="${H - 14}" rx="26" fill="none" stroke="url(#zaCardGold)" stroke-width="4"/>
            <rect x="21" y="21" width="${CARD_W - 42}" height="${H - 42}" rx="18" fill="none" stroke="${GOLD_LIGHT}" stroke-width="1.2" opacity=".82"/>
            ${emblem
                ? `<ellipse cx="360" cy="316" rx="286" ry="270" fill="url(#zaCardGlow)" opacity=".55"/>
                   <g transform="translate(100 56) scale(${(520 / 1024).toFixed(7)})">${emblem}</g>`
                : `<rect x="130" y="80" width="460" height="460" rx="24" fill="#211a12" stroke="${GOLD}" stroke-width="2.5"/>
                   <rect x="146" y="96" width="428" height="428" rx="18" fill="none" stroke="${GOLD_LIGHT}" stroke-width="1.2" opacity=".55"/>
                   <circle cx="360" cy="310" r="86" fill="none" stroke="${CREAM}" stroke-width="1.6" stroke-dasharray="3 8" opacity=".7"/>
                   <path d="M360 266 l10 34 l34 10 l-34 10 l-10 34 l-10 -34 l-34 -10 l34 -10 Z" fill="${GOLD}" opacity=".85"/>`}
            ${diamond(360, 616, 7, GOLD)}
            <text x="360" y="654" text-anchor="middle" font-family="${esc(fontFamily)}" font-size="34" font-weight="700" letter-spacing="8" fill="url(#zaCardGold)">ЖЕТІ АТА</text>
            ${personLine ? `<text x="360" y="690" text-anchor="middle" font-family="${esc(fontFamily)}" font-size="22" font-weight="700" fill="${CREAM}">${esc(personLine)}</text>` : ''}
            ${subtitle ? `<text x="360" y="714" text-anchor="middle" font-family="${esc(fontFamily)}" font-size="13" font-weight="700" letter-spacing="3" fill="#b7a17a">${esc(subtitle)}</text>` : ''}
            <path d="M150 723 H300 M420 723 H570" stroke="${GOLD_DARK}" stroke-width="1.2" opacity=".7"/>
            ${rowSvg}
            <path d="M82 ${footerY} H322 M398 ${footerY} H638" stroke="${GOLD_DARK}" stroke-width="1.3" opacity=".8"/>
            ${diamond(360, footerY, 6, GOLD)}
            <text x="360" y="${footerY + 38}" text-anchor="middle" font-family="${esc(fontFamily)}" font-size="12.5" font-weight="700" letter-spacing="4" fill="#a8782e">ALTYBAYEV.KZ</text>
        </svg>`;
    }

    async function tamgaDataUri(url) {
        if (!url) return '';
        try {
            const tr = await fetch(url);
            if (!tr.ok) return '';
            const blob = await tr.blob();
            return await new Promise((ok) => { const fr = new FileReader(); fr.onload = () => ok(String(fr.result || '')); fr.onerror = () => ok(''); fr.readAsDataURL(blob); });
        } catch (e) { return ''; }
    }

    /* ALT-117: подгружаем единый генератор герба, если он ещё не подключён на странице. */
    let emblemLibPromise = null;
    function ensureEmblemLib() {
        if (window.ClanEmblem) return Promise.resolve(window.ClanEmblem);
        if (emblemLibPromise) return emblemLibPromise;
        emblemLibPromise = new Promise((resolve, reject) => {
            if (typeof document === 'undefined') { reject(new Error('no document')); return; }
            const s = document.createElement('script');
            s.src = '/assets/js/clan-emblem.js';
            s.onload = () => window.ClanEmblem ? resolve(window.ClanEmblem) : reject(new Error('ClanEmblem is not available'));
            s.onerror = () => reject(new Error('ClanEmblem load failed'));
            document.head.appendChild(s);
        });
        return emblemLibPromise;
    }

    /* Окно с готовым гербом ру: по названию ру (клик в «Ру таңбалары» и в карточке человека).
       ALT-117: вместо старого бордового герба-звезды — единый герб на утверждённой
       растровой подложке; PNG и PDF экспортируют ровно эту же композицию. */
    async function openClan(name, currentLang) {
        const lang = currentLang || document.documentElement.lang || 'kk';
        const L = labels(lang);
        name = String(name || '').trim();
        if (!name) return;
        const stale = document.getElementById('zaCoatOverlay');
        if (stale) stale.remove();
        let data = null;
        try {
            const r = await fetch('/api/zheti-ata/clan?name=' + encodeURIComponent(name));
            const j = await r.json();
            if (j && j.success) data = j.clan;
        } catch (e) { data = null; }

        let lib;
        try {
            lib = await ensureEmblemLib();
        } catch (e) {
            lib = null;
        }
        if (!lib) {
            const ov = document.createElement('div');
            ov.id = 'zaCoatOverlay';
            ov.style.cssText = 'position:fixed;inset:0;z-index:100000;background:rgba(10,8,5,.86);display:flex;align-items:center;justify-content:center;padding:12px;';
            ov.innerHTML = '<div style="max-width:420px;padding:20px;border:1px solid #c9a86a;border-radius:14px;background:#17120c;color:#f3e6c8;text-align:center;font-family:Georgia,serif">'
                + esc(name) + '<br><br><button type="button" class="btn" data-close="1">' + esc(L.close) + '</button></div>';
            ov.addEventListener('click', (ev) => {
                if (ev.target === ov || (ev.target.closest && ev.target.closest('[data-close]'))) ov.remove();
            });
            document.body.appendChild(ov);
            return;
        }

        if (!data) {
            const ov = document.createElement('div');
            ov.id = 'zaCoatOverlay';
            ov.style.cssText = 'position:fixed;inset:0;z-index:100000;background:rgba(10,8,5,.86);display:flex;align-items:center;justify-content:center;padding:12px;';
            ov.innerHTML = '<div style="max-width:420px;padding:20px;border:1px solid #c9a86a;border-radius:14px;background:#17120c;color:#f3e6c8;text-align:center;font-family:Georgia,serif">'
                + esc(name) + '<br><br><button type="button" class="btn" data-close="1">' + esc(L.close) + '</button></div>';
            ov.addEventListener('click', (ev) => {
                if (ev.target === ov || (ev.target.closest && ev.target.closest('[data-close]'))) ov.remove();
            });
            document.body.appendChild(ov);
            return;
        }

        const clanName = (lang === 'kk' ? (data.name || data.name_ru) : (data.name_ru || data.name)) || name;
        window.zhetiAtaFileName = 'Ру — ' + clanName;
        await lib.open({
            zhuz: L.zhuz[data.zhuz] || '',
            name: clanName,
            uran: data.uran || '',
            tamgaUrl: data.tamga_url || '',
            filename: 'Ру — ' + clanName,
            ariaLabel: (lang === 'kk' ? 'Елтаңба: ' : 'Герб рода ') + clanName,
            labels: {
                loading: L.loading,
                png: L.png,
                pdf: L.emblemPdf,
                close: L.close,
                noSign: L.noSign,
                noBackground: L.noBackground
            }
        });
    }

    // клик по любому элементу с data-clan-coat="<название ру>" открывает герб
    if (typeof document !== 'undefined') {
        document.addEventListener('click', (ev) => {
            const t = ev.target.closest ? ev.target.closest('[data-clan-coat]') : null;
            if (!t) return;
            const n = t.getAttribute('data-clan-coat');
            if (!n) return;
            ev.preventDefault();
            ev.stopPropagation();
            openClan(n, document.documentElement.getAttribute('data-lang') || window.__zaLang || 'kk');
        }, true);
    }

    /* Рисует табличку в box для человека personId. onPerson(id) — клик по имени в цепочке (необязательно). */
    async function render(resBox, id, currentLang, onPerson, opts) {
        const L = labels(currentLang);
        if (!id) return;
        resBox.style.display = 'block';
        resBox.innerHTML = `<div style="text-align:center; padding:16px; color:#b7a17a;">${esc(L.loading)}</div>`;

        try {
            const res = await fetch(`/api/zheti-ata/seven?id=${encodeURIComponent(id)}`);
            const data = await res.json();
            if (!data.success || !Array.isArray(data.fathers)) {
                resBox.innerHTML = `<div style="color:#ef4444; padding:10px;">${esc(data.error || L.error)}</div>`;
                return;
            }

            // колено 0 — сам человек, 1–7 — его отцы
            const planks = data.fathers.slice().reverse();
            const self = data.fathers[0].person || {};
            const clanInfo = data.clan || {};
            const clanName = (currentLang === 'kk' ? (clanInfo.name || self.clan_ru) : (clanInfo.name_ru || self.clan_ru)) || '';
            const zhuzLabel = L.zhuz[clanInfo.zhuz] || '';
            const subName = self.clan_subru || '';
            const uranText = (clanInfo.uran || '').trim();
            const personName = `${self.first_name || ''} ${self.last_name || ''}`.trim();
            const personYears = lifeYears(self);

            let lib = null;
            try { lib = await ensureEmblemLib(); } catch (e) { lib = null; }

            let emblemInner = '';
            let fontData = '';
            if (lib) {
                let tamga = { data: '', isRaster: false };
                try { tamga = await lib.loadTamga(clanInfo.tamga_url || ''); } catch (e) { tamga = { data: '', isRaster: false }; }
                const [fonts, bg] = await Promise.all([
                    lib.ensureFont().catch(() => ''),
                    lib.loadBackground().catch(() => ({ data: '', href: '' }))
                ]);
                fontData = fonts || '';
                if (clanName || tamga.data) {
                    try {
                        const full = lib.buildSvg({
                            zhuz: zhuzLabel,
                            name: clanName,
                            uran: uranText,
                            tamga: tamga.data,
                            tamgaIsRaster: !!tamga.isRaster,
                            background: bg && bg.data ? bg.data : '',
                            fontData: fontData,
                            ariaLabel: `Герб рода ${clanName || ''}`.trim()
                        });
                        emblemInner = String(full || '')
                            .replace(/^<svg[^>]*>/, '')
                            .replace(/<\/svg>\s*$/, '');
                    } catch (e) { emblemInner = ''; }
                }
            }

            const svg = buildPlaque({
                rows: planks,
                levels: L.levels,
                unknown: L.unknown,
                personName: personName,
                personYears: personYears,
                subtitle: subName,
                emblemInner: emblemInner,
                fontData: fontData,
                onPerson: !!onPerson,
                ariaLabel: `Жеті ата — ${personName}`.trim()
            });

            window.zhetiAtaFileName = 'Жеті ата — ' + (String(self.first_name || '').trim() || 'person');
            const btnsHtml = `
                    <button type="button" class="btn btn-primary" data-za-act="png">⬇ ${esc(L.download)}</button>
                    <button type="button" class="btn" data-za-act="pdf">📄 ${esc(L.pdf)}</button>
                    <button type="button" class="btn" data-za-act="print">🖨 ${esc(L.print)}</button>`;
            const ext = opts || {};
            if (ext.actionsBox) {
                // кнопки и сведения о человеке — в боковой панели, справа только табличка
                resBox.innerHTML = svg;
                ext.actionsBox.innerHTML = btnsHtml;
                ext.actionsBox.onclick = (ev) => {
                    const b = ev.target.closest ? ev.target.closest('[data-za-act]') : null;
                    if (!b) return;
                    const act = b.getAttribute('data-za-act');
                    if (act === 'png') downloadPng();
                    else if (act === 'pdf') downloadPdf();
                    else printPdf();
                };
                if (ext.infoBox) {
                    const nm = esc(personName);
                    ext.infoBox.innerHTML = `<div class="za-info-name">${nm}</div>`
                        + (clanName ? `<div class="za-info-row">${esc(L.clan)}: <b>${esc(clanName)}</b></div>` : '')
                        + (zhuzLabel ? `<div class="za-info-row">${esc(zhuzLabel)}</div>` : '');
                }
            } else {
                resBox.innerHTML = `
                ${svg}
                <div style="text-align:center; margin-top:12px;">${btnsHtml}</div>`;
            }
            resBox.onclick = (ev) => {
                const t = ev.target.closest ? ev.target.closest('[data-za-act],.za-name') : null;
                if (!t) return;
                if (t.classList.contains('za-name')) {
                    if (onPerson) onPerson(parseInt(t.getAttribute('data-person-id'), 10));
                    return;
                }
                const act = t.getAttribute('data-za-act');
                if (act === 'png') downloadPng();
                else if (act === 'pdf') downloadPdf();
                else printPdf();
            };
        } catch (err) {
            resBox.innerHTML = `<div style="color:#ef4444; padding:10px;">${esc(L.error)}: ${esc(err.message)}</div>`;
        }
    }

    function cloneForExport(svgEl, scale) {
        const vb = svgEl.viewBox.baseVal;
        const clone = svgEl.cloneNode(true);
        clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        clone.setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
        clone.setAttribute('width', Math.round(vb.width * scale));
        clone.setAttribute('height', Math.round(vb.height * scale));
        clone.removeAttribute('style');
        clone.removeAttribute('class');
        return { clone, width: vb.width, height: vb.height };
    }

    function svgToCanvas(svgEl, scale) {
        const parts = cloneForExport(svgEl, scale || 2);
        const xml = new XMLSerializer().serializeToString(parts.clone);
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(parts.width * (scale || 2));
                canvas.height = Math.round(parts.height * (scale || 2));
                const ctx = canvas.getContext('2d');
                if (!ctx) { reject(new Error('Canvas 2D недоступен')); return; }
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                resolve(canvas);
            };
            img.onerror = () => reject(new Error('Не удалось отрисовать карточку'));
            img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(xml);
        });
    }

    async function downloadPng(svgId) {
        const svgEl = document.getElementById(svgId || 'zhetiAtaPlaque');
        if (!svgEl) return;
        const canvas = await svgToCanvas(svgEl, 2);
        const blob = await new Promise((ok) => canvas.toBlob(ok, 'image/png'));
        if (!blob) return;
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = (window.zhetiAtaFileName || 'Жеті ата') + '.png';
        document.body.appendChild(a);
        a.click();
        setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1200);
    }

    /* Настоящий PDF (не диалог печати): A4 portrait, встроенный JPEG всей карточки. */
    function buildPdfBytes(jpegBytes, imgW, imgH, pageW, pageH) {
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
        const margin = 18;
        const s = Math.min((pageW - margin * 2) / imgW, (pageH - margin * 2) / imgH);
        const w = Math.round(imgW * s * 100) / 100;
        const h = Math.round(imgH * s * 100) / 100;
        const x = Math.round((pageW - w) / 2 * 100) / 100;
        const y = Math.round((pageH - h) / 2 * 100) / 100;
        obj(1, '<< /Type /Catalog /Pages 2 0 R >>');
        obj(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
        obj(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + pageW + ' ' + pageH + ']'
            + ' /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>');
        obj(4, '<< /Type /XObject /Subtype /Image /Width ' + imgW + ' /Height ' + imgH
            + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + jpegBytes.length + ' >>', jpegBytes);
        const content = 'q\n' + w + ' 0 0 ' + h + ' ' + x + ' ' + y + ' cm\n/Im0 Do\nQ\n';
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

    async function downloadPdf(svgId) {
        const svgEl = document.getElementById(svgId || 'zhetiAtaPlaque');
        if (!svgEl) return;
        const canvas = await svgToCanvas(svgEl, 2);
        const jpegBlob = await new Promise((ok) => canvas.toBlob(ok, 'image/jpeg', 0.95));
        if (!jpegBlob) return;
        const bytes = buildPdfBytes(new Uint8Array(await jpegBlob.arrayBuffer()), canvas.width, canvas.height, 595, 842);
        const url = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
        const a = document.createElement('a');
        a.href = url;
        a.download = (window.zhetiAtaFileName || 'Жеті ата') + '.pdf';
        document.body.appendChild(a);
        a.click();
        setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 1500);
    }

    function printPdf(svgId) {
        const svgEl = document.getElementById(svgId || 'zhetiAtaPlaque');
        if (!svgEl) return;
        const clone = svgEl.cloneNode(true);
        clone.removeAttribute('width');
        clone.removeAttribute('height');
        clone.setAttribute('style', 'width:auto;height:auto;max-width:100%;max-height:277mm;display:block;margin:0 auto;');
        const frame = document.createElement('iframe');
        frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
        document.body.appendChild(frame);
        const d = frame.contentWindow.document;
        d.open();
        d.write('<!doctype html><html><head><meta charset="utf-8"><title>' + (window.zhetiAtaFileName || 'Жеті ата').replace(/[<&]/g, '') + '</title><style>@page{size:A4 portrait;margin:8mm}html,body{height:100%}body{margin:0;display:flex;align-items:center;justify-content:center;background:#fff}svg{width:auto;height:auto;max-width:100%;max-height:277mm;display:block;-webkit-print-color-adjust:exact;print-color-adjust:exact}</style></head><body>' + new XMLSerializer().serializeToString(clone) + '</body></html>');
        d.close();
        setTimeout(() => {
            frame.contentWindow.focus();
            frame.contentWindow.print();
            setTimeout(() => frame.remove(), 3000);
        }, 300);
    }

    const API = { labels, render, buildPlaque, downloadPng, downloadPdf, printPdf, openClan, _buildPdfBytes: buildPdfBytes };
    if (typeof window !== 'undefined') window.ZhetiAta = API;
    if (typeof module !== 'undefined' && module.exports) module.exports = API;
})();
