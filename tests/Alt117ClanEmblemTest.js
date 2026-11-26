// ALT-117 (2026-11-26): единый герб рода — SVG, PNG и настоящий PDF.
// Проверяем динамические слои, казахские буквы, длинные названия, экранирование
// и структуру PDF без запуска браузера.
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

const base = { zhuz: 'ОРТА ЖҮЗ', name: 'КЕРЕЙ', uran: 'ОШЫБАЙ!', tamga: tamga, tamgaIsRaster: false };
const svg = E.buildSvg(base);

chk(svg.includes('viewBox="0 0 1024 1024"'), 'SVG 1024×1024');
chk(svg.includes('width="1024" height="1024"'), 'явные размеры 1024');
chk(svg.includes('ОРТА ЖҮЗ'), 'жуз выведен динамически');
chk(svg.includes('КЕРЕЙ'), 'название рода выведено');
chk(svg.includes('ОШЫБАЙ!'), 'ұран выведен');
chk(svg.includes('ҰРАН'), 'подпись поля ұрана');
chk(svg.includes('preserveAspectRatio="xMidYMid meet"'), 'таңба не искажается');
chk(svg.includes('filter="url(#ce') && svg.includes('-ink"'), 'таңба приводится к чёрному');
chk(!svg.includes('<textPath'), 'нет кругового текста: надписи горизонтальные');
chk(!svg.includes('#5b2018'), 'в новом гербе нет старого бордового фона');
chk(svg.includes('linearGradient') && svg.includes('radialGradient'), 'есть градиенты металла/неба');
chk(svg.includes('M778 622') || svg.includes('юрта') || svg.includes('yurt'), 'в сцене есть юрта');
chk(svg.includes('translate(372 820)'), 'в сцене есть тазы');
chk(svg.includes('translate(690 0)') && svg.includes('translate(760 0)') && svg.includes('translate(828 0)'), 'в сцене есть семья');

// Long name: font-size должен уменьшиться, а не обрезаться.
const longName = 'АШАМАЙЛЫ-КЕРЕЙ-ҰЗЫН-АТАУ-СЫНАҚ-ТЕКСЕРУ';
const longSvg = E.buildSvg(Object.assign({}, base, { name: longName, uran: 'АРУАҚ! ҚАРАБУРА! БАҚТИЯР!' }));
chk(longSvg.includes(longName), 'длинное казахское название не потеряно');
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

// Нет зашитых демо-данных.
const src = fs.readFileSync(path.join(__dirname, '../public/assets/js/clan-emblem.js'), 'utf8');
chk(!src.includes('ОШЫБАЙ'), 'демо-ұран «ОШЫБАЙ» не зашит в генератор');
chk(!src.includes('ҚАРА ҚОЖА'), 'демо-ұран «ҚАРА ҚОЖА» не зашит в генератор');
chk(!src.includes('КЕРЕЙ'), 'демо-название «КЕРЕЙ» не зашито в генератор');

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

chk(typeof E.open === 'function' && typeof E.downloadPng === 'function' && typeof E.downloadPdf === 'function', 'экспорт и окно доступны через API');

console.log('\nALT-117 emblem: ' + (n - bad) + '/' + n + ' проверок');
process.exit(bad === 0 ? 0 : 1);
