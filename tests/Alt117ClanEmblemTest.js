// ALT-117 (2026-11-26, редакция 2): один рендерер эмблемы рода.
// Проверяем: статическая растровая подложка (или нейтральный плейсхолдер),
// динамические слои, казахские буквы, длинные названия, XSS и структуру PDF.
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const E = require('../public/assets/js/clan-emblem.js');

let bad = 0, n = 0;
function chk(ok, name, extra) {
    n++;
    if (!ok) { bad++; console.log('FAIL ' + name + (extra ? ' :: ' + extra : '')); }
    else console.log('OK   ' + name);
}

const tamga = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2NCA2NCI+PHBhdGggZD0iTTQgMzJoNTZNMzIgNHY1NiIgc3Ryb2tlPSIjMmIyYjJiIi8+PC9zdmc+';
const bg = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lw1cAAAAAElFTkSuQmCC';

const base = { zhuz: 'ОРТА ЖҮЗ', name: 'КЕРЕЙ', uran: 'ОШЫБАЙ!', tamga: tamga, tamgaIsRaster: false };
const svg = E.buildSvg(base);

chk(svg.includes('viewBox="0 0 1024 1024"'), 'SVG 1024×1024');
chk(svg.includes('width="1024" height="1024"'), 'явные размеры 1024');
chk(svg.includes('data-ce-background="neutral"'), 'без файла фона — нейтральная подложка');
chk(svg.includes('ОРТА ЖҮЗ'), 'жуз выведен динамически');
chk(svg.includes('КЕРЕЙ'), 'название рода выведено');
chk(svg.includes('ОШЫБАЙ!'), 'ұран выведен');
chk(svg.includes('ҰРАН'), 'подпись поля ұрана');
chk(svg.includes('preserveAspectRatio="xMidYMid meet"'), 'таңба не искажается');
chk(svg.includes('filter="url(#ce') && svg.includes('-ink"'), 'таңба приводится к чёрному');
chk(!svg.includes('<textPath'), 'нет кругового текста: надписи горизонтальные');
chk(!svg.includes('#5b2018'), 'нет старого бордового фона');
chk(svg.includes('linearGradient') && svg.includes('radialGradient'), 'есть градиенты металла/камня');
chk(!svg.includes('M778 622') && !svg.includes('translate(372 820)') && !svg.includes('translate(690 0)'), 'нет мультяшного пейзажа: юрты, тазы и семьи');
chk(!svg.includes('mountains') && !svg.includes('steppe') && !svg.includes('horses'), 'нет программно нарисованного пейзажа');

// Утверждённый растр: тот же рендерер, но фон — статическая картинка.
const withBg = E.buildSvg(Object.assign({}, base, { background: bg }));
chk(withBg.includes('data-ce-background="approved"'), 'с растром включается approved-подложка');
chk(withBg.includes('href="' + bg + '"'), 'растровый фон встроен в SVG');
chk(withBg.includes('preserveAspectRatio="xMidYMid slice"'), 'растр заполняет квадрат без искажений');
chk(!withBg.includes('M778 622'), 'поверх растра не рисуется пейзаж');

// Длинное название: font-size должен уменьшиться, а не обрезаться.
const longName = 'АШАМАЙЛЫ-КЕРЕЙ-ҰЗЫН-АТАУ-СЫНАҚ-ТЕКСЕРУ';
const longSvg = E.buildSvg(Object.assign({}, base, { name: longName, uran: 'АРУАҚ! ҚАРАБУРА! БАҚТИЯР!' }));
chk(longSvg.includes(longName), 'длинное казахское название не потеряно');
const visibleLong = (longSvg.match(/<text[^>]*>[^<]*<\/text>/g) || [])
    .map((s) => s.replace(/<[^>]+>/g, ''))
    .join('');
chk(visibleLong.replace(/\s+/g, '').includes(longName), 'длинное название видно в двух строках, а не сжато в точку');
chk((longSvg.match(/fill="url\(#[^)]+-nameGold\)"/g) || []).length === 2, 'длинное название разбито ровно на две строки');
const nameSize = (longSvg.match(/font-size="([\d.]+)" font-weight="700" letter-spacing="3" fill="url\(#[^)]+-nameGold\)"/) || [])[1];
chk(!!nameSize && Number(nameSize) < 68, 'длинное название уменьшено', nameSize);
chk(longSvg.includes('АРУАҚ! ҚАРАБУРА! БАҚТИЯР!'), 'длинный ұран не потерян');

// XSS / спецсимволы.
const xssSvg = E.buildSvg(Object.assign({}, base, { name: '<script>alert(1)</script>', uran: '<b>Ұран</b>' }));
chk(!xssSvg.includes('<script>alert(1)</script>'), 'HTML в названии экранирован');
chk(xssSvg.includes('&lt;script&gt;alert(1)&lt;/script&gt;'), 'экранированный текст присутствует');
chk(!xssSvg.includes('<b>Ұран</b>'), 'HTML в ұране экранирован');

// Пустой ұран: не выдумываем значение, оставляем нейтральную линию.
const emptyUran = E.buildSvg(Object.assign({}, base, { uran: '' }));
chk(!emptyUran.includes('ОШЫБАЙ!'), 'пустой ұран не подставляет чужой текст');
chk(emptyUran.includes('M468 898 H556'), 'для пустого ұрана нейтральная линия');

// Нет таңба: не рисуем чужой знак.
const noSign = E.buildSvg(Object.assign({}, base, { tamga: '' }));
chk(!noSign.includes('<image'), 'без таңба нет подставного изображения');
chk(noSign.includes('stroke-dasharray="3 8"'), 'без таңба — нейтральный пунктир, не чужой знак');

// Один рендерер: превью, окно и экспорт.
chk(typeof E.mount === 'function', 'есть mount() для превью');
chk(typeof E.mountAll === 'function', 'есть mountAll() для галереи');
chk(typeof E.loadBackground === 'function', 'есть загрузчик растрового фона');
chk(typeof E.backgroundCandidates === 'function', 'есть список кандидатов фона');
chk(E.backgroundCandidates().some((u) => /approved-background\.(jpg|jpeg|png|webp)$/.test(u)), 'слот замены фона задан');
chk(E.backgroundCandidates().some((u) => /assets\/brand\/altybayev-brand\/backgrounds\/clan-emblem\.png$/.test(u)), 'утверждённый растр бренда — кандидат');
chk(/assets\/brand\/altybayev-brand\/backgrounds\/clan-emblem\.png$/.test(E.backgroundCandidates()[0] || ''), 'утверждённый растр бренда имеет приоритет');
chk(typeof E.open === 'function' && typeof E.downloadPng === 'function' && typeof E.downloadPdf === 'function', 'окно и экспорт доступны через API');

// Нет зашитых демо-данных и старого круглого медальона.
const src = fs.readFileSync(path.join(__dirname, '../public/assets/js/clan-emblem.js'), 'utf8');
chk(!src.includes('ОШЫБАЙ'), 'демо-ұран «ОШЫБАЙ» не зашит в генератор');
chk(!src.includes('ҚАРА ҚОЖА'), 'демо-ұран «ҚАРА ҚОЖА» не зашит в генератор');
chk(!src.includes('КЕРЕЙ'), 'демо-название «КЕРЕЙ» не зашито в генератор');
chk(!src.includes('viewBox="0 0 320 320"'), 'нет старого круглого медальона 320×320');
chk(src.includes("querySelectorAll('[data-clan-emblem]')"), 'превью монтируются из общих data-атрибутов');

// PDF: настоящий файл, страница 1024×1024, изображение 2048×2048, DCTDecode.
const jpeg = new Uint8Array([0xFF, 0xD8, 0xFF, 0xE0, 0x00, 0x10, 0x4A, 0x46, 0x49, 0x46, 0x00, 0x01, 0xFF, 0xD9]);
const pdf = E.buildPdf(jpeg, 2048, 2048, 1024);
const txt = Buffer.from(pdf).toString('latin1');
chk(txt.startsWith('%PDF-1.4'), 'PDF начинается с %PDF-1.4');
chk(txt.includes('/MediaBox [0 0 1024 1024]'), 'PDF: квадратная страница 1024×1024');
chk(txt.includes('/Width 2048 /Height 2048'), 'PDF: изображение 2048×2048');
chk(txt.includes('/Filter /DCTDecode'), 'PDF: JPEG-поток');
chk(txt.includes('xref') && txt.includes('trailer') && txt.trimEnd().endsWith('%%EOF'), 'PDF: xref/trailer/EOF');
chk(pdf.length > jpeg.length, 'PDF содержит поток изображения');

console.log('\nALT-117 emblem: ' + (n - bad) + '/' + n + ' проверок');
process.exit(bad === 0 ? 0 : 1);
