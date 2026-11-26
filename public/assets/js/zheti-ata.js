/* ALT-092: общий код «Жеті ата» — табличка, PNG, PDF.
   Используется страницей /zheti-ata и окном в шежіре (views/tree.php).
   API: ZhetiAta.labels(lang), ZhetiAta.render(box, personId, lang, onPerson), ZhetiAta.downloadPng(), ZhetiAta.printPdf() */
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
            kk: { uran: 'Ұран', pdf: 'PDF жасау', print: 'Басып шығару', download: 'Картинкамен жүктеу (PNG)', png: 'PNG жүктеу', emblemPdf: 'PDF жүктеу', loading: 'Елтаңба дайындалуда…', noSign: 'Таңба жүктелмеген — елтаңба таңбасыз көрсетілді.', zhuz: { senior: 'Ұлы жүз', middle: 'Орта жүз', junior: 'Кіші жүз', other: '' } },
            ru: { uran: 'Ұран', pdf: 'Сохранить в PDF', print: 'Печать', download: 'Скачать картинкой (PNG)', png: 'Скачать PNG', emblemPdf: 'Скачать PDF', loading: 'Готовим герб…', noSign: 'Таңба не загружена — герб показан без знака.', zhuz: { senior: 'Старший жуз', middle: 'Средний жуз', junior: 'Младший жуз', other: '' } },
            en: { uran: 'Battle cry', pdf: 'Save as PDF', print: 'Print', download: 'Download as image (PNG)', png: 'Download PNG', emblemPdf: 'Download PDF', loading: 'Preparing the emblem…', noSign: 'The tamga is not loaded — the emblem is shown without it.', zhuz: { senior: 'Senior Zhuz', middle: 'Middle Zhuz', junior: 'Junior Zhuz', other: '' } },
            es: { uran: 'Grito de guerra', pdf: 'Guardar como PDF', print: 'Imprimir', download: 'Descargar como imagen (PNG)', png: 'Descargar PNG', emblemPdf: 'Descargar PDF', loading: 'Preparando el emblema…', noSign: 'La tamga no está cargada — el emblema se muestra sin ella.', zhuz: { senior: 'Zhuz Mayor', middle: 'Zhuz Medio', junior: 'Zhuz Menor', other: '' } }
        };
        const l = T[currentLang] || T.kk;
        Object.assign(l, EXTRA[currentLang] || EXTRA.kk);
        if (currentLang === 'ru') l.unknown = 'неизвестен (белгісіз)';
        if (currentLang === 'en') l.unknown = 'unknown (белгісіз)';
        if (currentLang === 'es') l.unknown = 'desconocido (белгісіз)';
        return l;
    }

    const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    /* ALT-101: декоративная миниатюра для таблички «Жеті ата».
       ALT-117: окно data-clan-coat и экспорт герба используют clan-emblem.js,
       а не этот старый герб-звезду. o: {pre, zhuz, clan, uran, tamga (data-URI или '')}.
       Возвращает содержимое в системе координат 260×260 (центр 130,130). */
    function coatInner(o) {
        const C = 130, G = '#c9a24b', GL = '#e8c97a';
        const polar = (a, r) => [C + Math.cos(a * Math.PI / 180) * r, C + Math.sin(a * Math.PI / 180) * r];
        const sq = (off) => [0, 1, 2, 3].map((k) => polar(off + k * 90, 124).map((v) => v.toFixed(1)).join(',')).join(' ');
        const fit = (txt, base, maxLen) => Math.max(8, Math.min(base, maxLen / (Math.max(1, txt.length) * 0.78)));
        const top = [o.zhuz, o.clan].filter(Boolean).join(' • ').toUpperCase();
        const bottom = String(o.uran || '').toUpperCase();
        const spear = (a) => {
            const p1 = polar(a, 100), p2 = polar(a, 170);
            return `<line x1="${p1[0].toFixed(1)}" y1="${p1[1].toFixed(1)}" x2="${p2[0].toFixed(1)}" y2="${p2[1].toFixed(1)}" stroke="${G}" stroke-width="3" stroke-linecap="round"/>`
                + `<path transform="translate(${p2[0].toFixed(1)} ${p2[1].toFixed(1)}) rotate(${a + 90})" d="M0 -14 L5 0 L0 8 L-5 0 z" fill="${G}" stroke="#2f0e0a" stroke-width="1"/>`;
        };
        const tips = [0, 45, 90, 135, 180, 225, 270, 315].map((a) => {
            const p = polar(a, 118);
            return `<path transform="translate(${p[0].toFixed(1)} ${p[1].toFixed(1)}) rotate(${a + 45})" d="M0 -4 L4 0 L0 4 L-4 0 z" fill="${G}"/>`;
        }).join('');
        const tA = o.pre + 'T', bA = o.pre + 'B';
        return `<defs>
                <path id="${tA}" d="M ${C - 60} ${C} A 60 60 0 0 1 ${C + 60} ${C}"/>
                <path id="${bA}" d="M ${C - 80} ${C} A 80 80 0 0 0 ${C + 80} ${C}"/>
            </defs>
            ${spear(-45)}${spear(-135)}${spear(45)}${spear(135)}
            <polygon points="${sq(45)}" fill="#5b2018" stroke="${G}" stroke-width="2.5" stroke-linejoin="round"/>
            <polygon points="${sq(0)}" fill="#5b2018" stroke="${G}" stroke-width="2.5" stroke-linejoin="round"/>
            <polygon points="${sq(45)}" fill="none" stroke="${GL}" stroke-width="0.8" stroke-linejoin="round" transform="translate(130 130) scale(0.95) translate(-130 -130)"/>
            <polygon points="${sq(0)}" fill="none" stroke="${GL}" stroke-width="0.8" stroke-linejoin="round" transform="translate(130 130) scale(0.95) translate(-130 -130)"/>
            ${tips}
            <circle cx="${C}" cy="${C}" r="88" fill="#3f1510" stroke="${G}" stroke-width="2.5"/>
            <circle cx="${C}" cy="${C}" r="54" fill="#f3e6c8" stroke="#b28a3c" stroke-width="2"/>
            ${top ? `<text font-size="${fit(top, 14, 150).toFixed(1)}" font-weight="700" letter-spacing="1" fill="${GL}" text-anchor="middle"><textPath href="#${tA}" startOffset="50%">${esc(top)}</textPath></text>` : ''}
            ${bottom ? `<text font-size="${fit(bottom, 15, 190).toFixed(1)}" font-weight="700" letter-spacing="1.5" fill="${GL}" text-anchor="middle"><textPath href="#${bA}" startOffset="50%">${esc(bottom)}</textPath></text>` : ''}
            ${o.tamga ? `<image href="${o.tamga}" x="${C - 31}" y="${C - 31}" width="62" height="62" preserveAspectRatio="xMidYMid meet"/>` : ''}
            <path d="M${C - 46} ${C} l4 -5 l4 5 l-4 5 z M${C + 38} ${C} l4 -5 l4 5 l-4 5 z" fill="#b28a3c"/>
            <path d="M${C - 46} ${C - 14} v-6 M${C - 46} ${C + 14} v6 M${C + 46} ${C - 14} v-6 M${C + 46} ${C + 14} v6" stroke="#b28a3c" stroke-width="1.5" stroke-linecap="round"/>`;
    }

    function coatSvgFull(o, id) {
        return `<svg id="${id}" xmlns="http://www.w3.org/2000/svg" viewBox="-40 -40 340 340" width="100%" style="max-width:420px; display:block; margin:0 auto;" font-family="Georgia, 'Times New Roman', serif">${coatInner(o)}</svg>`;
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
       ALT-117: вместо старого бордового герба-звезды — новый единый герб со степью;
       PNG и PDF экспортируют ровно эту же композицию. */
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
                noSign: L.noSign
            }
        });
    }

    // клик по любому элементу с data-clan-coat="<название ру>" открывает герб
    document.addEventListener('click', (ev) => {
        const t = ev.target.closest ? ev.target.closest('[data-clan-coat]') : null;
        if (!t) return;
        const n = t.getAttribute('data-clan-coat');
        if (!n) return;
        ev.preventDefault();
        ev.stopPropagation();
        openClan(n, document.documentElement.getAttribute('data-lang') || window.__zaLang || 'kk');
    }, true);

    /* Рисует табличку в box для человека personId. onPerson(id) — клик по имени в цепочке (необязательно). */
    async function render(resBox, id, currentLang, onPerson, opts) {
        const L = labels(currentLang);
        if (!id) return;
        resBox.style.display = 'block';
        resBox.innerHTML = `<div style="text-align:center; padding:16px; color:#94a3b8;">${esc(L.loading)}</div>`;

        try {
            const res = await fetch(`/api/zheti-ata/seven?id=${encodeURIComponent(id)}`);
            const data = await res.json();
            if (!data.success || !Array.isArray(data.fathers)) {
                resBox.innerHTML = `<div style="color:#ef4444; padding:10px;">${esc(data.error || L.error)}</div>`;
                return;
            }

            // колено 0 — сам человек, 1–7 — его отцы
            const planks = data.fathers.slice().reverse();
            const W = 400, PLANK_Y = 300, PLANK_H = 66;
            const H = PLANK_Y + planks.length * PLANK_H + 150;
            const self = data.fathers[0].person || {};
            const clanInfo = data.clan || {};
            const clanName = (currentLang === 'kk' ? (clanInfo.name || self.clan_ru) : (clanInfo.name_ru || self.clan_ru)) || '';
            const zhuzLabel = L.zhuz[clanInfo.zhuz] || '';
            const subName = self.clan_subru || '';
            // таңба ру встраиваем data-URI, чтобы работало в PNG/PDF
            const tamgaData = await tamgaDataUri(clanInfo.tamga_url);
            const uranText = (clanInfo.uran || '').trim();
            // ALT-101: герб-звезда вместо круглого медальона
            const coat = coatInner({ pre: 'zaPl', zhuz: zhuzLabel, clan: clanName, uran: uranText, tamga: tamgaData });
            const side = (x) => Array.from({ length: Math.floor((planks.length * PLANK_H) / 18) }, (_, i) =>
                `<path d="M${x} ${PLANK_Y + 6 + i * 18} l4 5 l-4 5 l-4 -5 z" fill="#c9a24b"/>`).join('');
            const fitSize = (txt, base, maxCh) => (txt.length > maxCh ? Math.max(11, Math.floor(base * maxCh / txt.length)) : base);
            const plankSvg = planks.map((f, i) => {
                const y = PLANK_Y + i * PLANK_H;
                const p = f.person;
                const title = L.levels[f.level] || '';
                let inner;
                if (!p) {
                    inner = `<text x="200" y="${y + 40}" text-anchor="middle" font-size="17" font-style="italic" fill="#9a8466">${esc(L.unknown)}</text>`;
                } else {
                    const full = `${p.first_name || ''}`.trim();
                    const yOnly = (v) => { const m = String(v == null ? '' : v).match(/\d{4}/); return m ? m[0] : ''; };
                    const by = yOnly(p.birth_year), dy = yOnly(p.death_year);
                    const years = (by || dy)
                        ? `${by || '…'}–${(p.is_deceased || dy) ? (dy || '…') : ''}`
                        : '';
                    inner = `<text class="za-name" data-person-id="${parseInt(p.id, 10)}" x="200" y="${y + 38}" text-anchor="middle" font-size="${fitSize(full, 20, 22)}" font-weight="700" fill="#3b1a12"${onPerson ? ' style="cursor:pointer"' : ''}>${esc(full)}</text>`
                        + (years ? `<text x="200" y="${y + 55}" text-anchor="middle" font-size="12" fill="#6b4a2e">${esc(years)}</text>` : '');
                }
                return `<rect x="52" y="${y}" width="296" height="${PLANK_H - 8}" rx="5" fill="${p ? '#f3e6c8' : '#e4d6b6'}" stroke="#b28a3c" stroke-width="1.5"/>`
                    + `<text x="200" y="${y + 15}" text-anchor="middle" font-size="10" fill="#8a6a40">${esc(title)}</text>` + inner;
            }).join('');
            const bottomY = PLANK_Y + planks.length * PLANK_H;
            const svg = `<svg id="zhetiAtaPlaque" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" width="100%" style="max-width:${W}px; display:block; margin:0 auto;" font-family="Georgia, 'Times New Roman', serif">
                <rect x="20" y="0" width="360" height="${bottomY + 70}" rx="14" fill="#5b2018" stroke="#2f0e0a" stroke-width="3"/>
                <rect x="30" y="10" width="340" height="${bottomY + 50}" rx="10" fill="none" stroke="#c9a24b" stroke-width="1.5"/>
                <g transform="translate(70 12) scale(0.98)">${coat}</g>
                <text x="200" y="${PLANK_Y - 16}" text-anchor="middle" font-size="${fitSize(subName.toUpperCase(), 22, 18)}" font-weight="700" font-style="normal" letter-spacing="2" fill="#f3e6c8">${esc(subName.toUpperCase() || L.title.toUpperCase())}</text>
                ${side(38)}${side(362)}
                ${plankSvg}
                <rect x="52" y="${bottomY + 2}" width="296" height="30" rx="4" fill="#3f1510" stroke="#c9a24b" stroke-width="1.5"/>
                <path d="M170 ${bottomY + 28} v-8 a30 14 0 0 1 60 0 v8 z M193 ${bottomY + 28} v-9 h14 v9" fill="#f3e6c8" stroke="#c9a24b" stroke-width="1"/>
                <path d="M62 ${bottomY + 17} h92 M246 ${bottomY + 17} h92" stroke="#c9a24b" stroke-width="2" stroke-dasharray="4 4"/>
                <path d="M170 ${bottomY + 70} v40 M230 ${bottomY + 70} v40" stroke="#c9a24b" stroke-width="2.5"/>
                <path d="M160 ${bottomY + 110} h20 l-3 34 h-14 z M220 ${bottomY + 110} h20 l-3 34 h-14 z" fill="#8a2b22" stroke="#c9a24b" stroke-width="1.5"/>
            </svg>`;

            window.zhetiAtaFileName = 'Жеті ата — ' + (String(self.first_name || '').trim() || 'person');
            const btnsHtml = `
                    <button type="button" class="btn-modal-submit btn btn-primary" data-za-act="png">⬇ ${esc(L.download)}</button>
                    <button type="button" class="btn-modal-cancel btn" data-za-act="pdf">📄 ${esc(L.pdf)}</button>
                    <button type="button" class="btn-modal-cancel btn" data-za-act="print">🖨 ${esc(L.print)}</button>`;
            const ext = opts || {};
            if (ext.actionsBox) {
                // кнопки и сведения о человеке — в боковой панели, справа только табличка
                resBox.innerHTML = svg;
                ext.actionsBox.innerHTML = btnsHtml;
                ext.actionsBox.onclick = (ev) => {
                    const b = ev.target.closest ? ev.target.closest('[data-za-act]') : null;
                    if (!b) return;
                    if (b.getAttribute('data-za-act') === 'png') downloadPng(); else printPdf();
                };
                if (ext.infoBox) {
                    const nm = esc(`${self.first_name || ''} ${self.last_name || ''}`.trim());
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
                else printPdf(); // «PDF» и «Печать» — один диалог печати (в нём можно выбрать «Сохранить как PDF»)
            };
        } catch (err) {
            resBox.innerHTML = `<div style="color:#ef4444; padding:10px;">${esc(L.error)}: ${esc(err.message)}</div>`;
        }
    }

    function downloadPng(svgId) {
        const svgEl = document.getElementById(svgId || 'zhetiAtaPlaque');
        if (!svgEl) return;
        const vb = svgEl.viewBox.baseVal;
        const clone = svgEl.cloneNode(true);
        clone.setAttribute('width', vb.width * 2);
        clone.setAttribute('height', vb.height * 2);
        clone.removeAttribute('style');
        const xml = new XMLSerializer().serializeToString(clone);
        const img = new Image();
        img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = vb.width * 2;
            canvas.height = vb.height * 2;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            canvas.toBlob((blob) => {
                if (!blob) return;
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = (window.zhetiAtaFileName || 'Жеті ата') + '.png';
                document.body.appendChild(a);
                a.click();
                setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
            }, 'image/png');
        };
        img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(xml);
    }

    function printPdf(svgId) {
        const svgEl = document.getElementById(svgId || 'zhetiAtaPlaque');
        if (!svgEl) return;
        const clone = svgEl.cloneNode(true);
        clone.setAttribute('style', 'max-width:420px;display:block;margin:0 auto;');
        clone.setAttribute('width', '100%');
        const frame = document.createElement('iframe');
        frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
        document.body.appendChild(frame);
        const d = frame.contentWindow.document;
        d.open();
        d.write('<!doctype html><html><head><meta charset="utf-8"><title>' + (window.zhetiAtaFileName || 'Жеті ата').replace(/[<&]/g, '') + '</title><style>@page{margin:10mm}body{margin:0}svg{-webkit-print-color-adjust:exact;print-color-adjust:exact}</style></head><body>' + new XMLSerializer().serializeToString(clone) + '</body></html>');
        d.close();
        setTimeout(() => {
            frame.contentWindow.focus();
            frame.contentWindow.print();
            setTimeout(() => frame.remove(), 3000);
        }, 300);
    }

    window.ZhetiAta = { labels, render, downloadPng, printPdf, openClan };
})();
