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
            kk: { uran: 'Ұран', pdf: 'PDF жасау', print: 'Басып шығару', download: 'Картинкамен жүктеу (PNG)', zhuz: { senior: 'Ұлы жүз', middle: 'Орта жүз', junior: 'Кіші жүз', other: '' } },
            ru: { uran: 'Ұран', pdf: 'Сохранить в PDF', print: 'Печать', download: 'Скачать картинкой (PNG)', zhuz: { senior: 'Старший жуз', middle: 'Средний жуз', junior: 'Младший жуз', other: '' } },
            en: { uran: 'Battle cry', pdf: 'Save as PDF', print: 'Print', download: 'Download as image (PNG)', zhuz: { senior: 'Senior Zhuz', middle: 'Middle Zhuz', junior: 'Junior Zhuz', other: '' } },
            es: { uran: 'Grito de guerra', pdf: 'Guardar como PDF', print: 'Imprimir', download: 'Descargar como imagen (PNG)', zhuz: { senior: 'Zhuz Mayor', middle: 'Zhuz Medio', junior: 'Zhuz Menor', other: '' } }
        };
        const l = T[currentLang] || T.kk;
        Object.assign(l, EXTRA[currentLang] || EXTRA.kk);
        if (currentLang === 'ru') l.unknown = 'неизвестен (белгісіз)';
        if (currentLang === 'en') l.unknown = 'unknown (белгісіз)';
        if (currentLang === 'es') l.unknown = 'desconocido (белгісіз)';
        return l;
    }

    const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    /* Рисует табличку в box для человека personId. onPerson(id) — клик по имени в цепочке (необязательно). */
    async function render(resBox, id, currentLang, onPerson) {
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
            const W = 400, PLANK_Y = 266, PLANK_H = 66;
            const H = PLANK_Y + planks.length * PLANK_H + 150;
            const self = data.fathers[0].person || {};
            const clanInfo = data.clan || {};
            const clanName = (currentLang === 'kk' ? (clanInfo.name || self.clan_ru) : (clanInfo.name_ru || self.clan_ru)) || '';
            const zhuzLabel = L.zhuz[clanInfo.zhuz] || '';
            const subName = self.clan_subru || '';
            // таңба ру встраиваем data-URI, чтобы работало в PNG/PDF
            let tamgaData = '';
            if (clanInfo.tamga_url) {
                try {
                    const tr = await fetch(clanInfo.tamga_url);
                    if (tr.ok) {
                        const blob = await tr.blob();
                        tamgaData = await new Promise((ok) => { const fr = new FileReader(); fr.onload = () => ok(String(fr.result || '')); fr.onerror = () => ok(''); fr.readAsDataURL(blob); });
                    }
                } catch (e) { tamgaData = ''; }
            }
            const uranText = (clanInfo.uran || '').trim();
            const ring = Array.from({ length: 28 }, (_, i) => {
                const a = (i / 28) * Math.PI * 2;
                return `<path d="M${(200 + Math.cos(a) * 82).toFixed(1)} ${(115 + Math.sin(a) * 82).toFixed(1)} l3 3 l-3 3 l-3 -3 z" fill="#c9a24b" transform="rotate(${(a * 180 / Math.PI + 45).toFixed(0)} ${(200 + Math.cos(a) * 82).toFixed(1)} ${(115 + Math.sin(a) * 82).toFixed(1)})"/>`;
            }).join('');
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
                <circle cx="200" cy="115" r="92" fill="#3f1510" stroke="#c9a24b" stroke-width="3"/>
                ${ring}
                <circle cx="200" cy="115" r="70" fill="#f3e6c8" stroke="#b28a3c" stroke-width="2"/>
                ${zhuzLabel ? `<text x="200" y="${tamgaData ? 78 : 92}" text-anchor="middle" font-size="11" fill="#6b4a2e">${esc(zhuzLabel)}</text>` : ''}
                ${tamgaData ? `<image href="${tamgaData}" x="178" y="88" width="44" height="44" preserveAspectRatio="xMidYMid meet"/>` : ''}
                <text x="200" y="${tamgaData ? 160 : 122}" text-anchor="middle" font-size="${Math.max(10, Math.min(tamgaData ? 18 : 22, Math.floor(92 / (0.72 * Math.max(1, clanName.length)))))}" font-weight="700" font-style="normal" fill="#3b1a12">${esc(clanName.toUpperCase())}</text>
                ${uranText ? `<text x="200" y="${PLANK_Y - 42}" text-anchor="middle" font-size="${fitSize(uranText, 14, 28)}" font-style="normal" fill="#e8c97a">${esc(L.uran + ': ' + uranText)}</text>` : ''}
                <text x="200" y="${PLANK_Y - 18}" text-anchor="middle" font-size="${fitSize(subName.toUpperCase(), 22, 18)}" font-weight="700" font-style="normal" letter-spacing="2" fill="#f3e6c8">${esc(subName.toUpperCase() || L.title.toUpperCase())}</text>
                ${side(38)}${side(362)}
                ${plankSvg}
                <rect x="52" y="${bottomY + 2}" width="296" height="30" rx="4" fill="#3f1510" stroke="#c9a24b" stroke-width="1.5"/>
                <path d="M170 ${bottomY + 28} v-8 a30 14 0 0 1 60 0 v8 z M193 ${bottomY + 28} v-9 h14 v9" fill="#f3e6c8" stroke="#c9a24b" stroke-width="1"/>
                <path d="M62 ${bottomY + 17} h92 M246 ${bottomY + 17} h92" stroke="#c9a24b" stroke-width="2" stroke-dasharray="4 4"/>
                <path d="M170 ${bottomY + 70} v40 M230 ${bottomY + 70} v40" stroke="#c9a24b" stroke-width="2.5"/>
                <path d="M160 ${bottomY + 110} h20 l-3 34 h-14 z M220 ${bottomY + 110} h20 l-3 34 h-14 z" fill="#8a2b22" stroke="#c9a24b" stroke-width="1.5"/>
            </svg>`;

            window.zhetiAtaFileName = 'Жеті ата — ' + (String(self.first_name || '').trim() || 'person');
            resBox.innerHTML = `
                ${svg}
                <div style="text-align:center; margin-top:12px;">
                    <button type="button" class="btn-modal-submit btn btn-primary" data-za-act="png" style="padding:8px 22px; font-size:13px;">⬇ ${esc(L.download)}</button>
                    <button type="button" class="btn-modal-cancel btn" data-za-act="pdf" style="padding:8px 22px; font-size:13px; margin-left:8px;">📄 ${esc(L.pdf)}</button>
                    <button type="button" class="btn-modal-cancel btn" data-za-act="print" style="padding:8px 22px; font-size:13px; margin-left:8px;">🖨 ${esc(L.print)}</button>
                </div>`;
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

    function downloadPng() {
        const svgEl = document.getElementById('zhetiAtaPlaque');
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

    function printPdf() {
        const svgEl = document.getElementById('zhetiAtaPlaque');
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

    window.ZhetiAta = { labels, render, downloadPng, printPdf };
})();
