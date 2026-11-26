#!/usr/bin/env node
'use strict';

/**
 * ALT-120: карточка «Жеті ата» в утверждённом стиле ALT-117.
 *
 * Проверяет единый SVG-рендерер: экран, PNG и PDF используют одну разметку,
 * старого бордового герба-звезды нет, семь поколений и данные сохраняются,
 * длинные и пустые значения не ломают вёрстку, PDF — настоящий файл A4.
 */

const path = require('node:path');
const fs = require('node:fs');
const { buildPlaque, _buildPdfBytes } = require('../public/assets/js/zheti-ata.js');

let fails = 0;
function chk(ok, message) {
    console.log((ok ? 'OK   ' : 'FAIL ') + message);
    if (!ok) fails++;
}

const levels = ['Өзі', 'Әке', 'Ата', 'Арғы ата', 'Баба', 'Түп ата', 'Дүние ата', 'Қаймана ата'];
const rows = [
    { level: 0, person: { id: 6, first_name: 'Жандос', last_name: 'Алтыбаев', birth_year: '1985', is_deceased: 0 } },
    { level: 1, person: { id: 5, first_name: 'Ғабит', last_name: '', birth_year: '1956', is_deceased: 0 } },
    { level: 2, person: { id: 4, first_name: 'Ғани', last_name: '', birth_year: '1929', death_year: '1991', is_deceased: 1 } },
    { level: 3, person: { id: 3, first_name: 'Шамсиден', last_name: '', birth_year: '1902', death_year: '1942', is_deceased: 1 } },
    { level: 4, person: { id: 2, first_name: 'Алтыбай', last_name: '', birth_year: '1864', death_year: '1934', is_deceased: 1 } },
    { level: 5, person: { id: 1, first_name: 'Кенен', last_name: '', birth_year: '1846', is_deceased: 1 } },
    { level: 6, person: { id: 10, first_name: 'Косак', last_name: '', birth_year: '', is_deceased: 1 } },
    { level: 7, person: null }
];

const base = {
    rows: rows,
    levels: levels,
    unknown: 'белгісіз',
    personName: 'Жандос Алтыбаев',
    personYears: '1985–',
    subtitle: 'Жағалбайлы',
    emblemInner: '<g data-test-emblem="1"><text>КЕРЕЙ</text></g>',
    fontData: '',
    ariaLabel: 'Жеті ата — Жандос Алтыбаев'
};

const svg = buildPlaque(base);

chk(svg.startsWith('<svg id="zhetiAtaPlaque"'), 'карточка — единый SVG #zhetiAtaPlaque');
chk(svg.includes('viewBox="0 0 720 '), 'портретная система координат 720 по ширине');
chk(svg.includes('data-za-style="alt117-v1"'), 'версия фирменного рендерера отмечена');
chk(svg.includes('data-test-emblem="1"'), 'новый герб рода из ALT-117 в верхней части');
chk(svg.includes('ЖЕТІ АТА'), 'заголовок карточки');
chk(svg.includes('Жандос Алтыбаев · 1985–'), 'имя и годы человека');
chk(svg.includes('ЖАҒАЛБАЙЛЫ'), 'подразделение рода');
rows.forEach((r) => {
    if (r.person) {
        chk(svg.includes('>' + r.person.first_name + '<'), 'поколение: ' + r.person.first_name);
    }
});
chk(levels.every((l) => svg.includes(l.toUpperCase())), 'все восемь подписей поколений');
chk(svg.includes('1846–…'), 'незакрытая дата смерти показана честно');
chk(svg.includes('>белгісіз<'), 'пустое поколение подписано «белгісіз»');
chk((svg.match(/class="za-name"/g) || []).length === 7, 'кликабельные имена только у известных людей');
chk(svg.includes('data-person-id="6"'), 'id человека сохранён для перехода по клику');
chk(!svg.includes('textPath'), 'нет старых круговых надписей textPath');
chk(!/5b2018|3f1510|8a2b22/.test(svg), 'нет старых бордовых заливок');
chk(!svg.includes('polygon points="130,130'), 'нет старого герба-звезды');
chk(svg.includes('#c9a86a') || svg.includes('#c9a86a'.toUpperCase()) || svg.includes('zaCardGold'), 'золотая рамка утверждённого стиля');
chk(svg.includes('#f4e3c0'), 'кремовые плашки поколений');
chk(svg.includes('ALTYBAYEV.KZ'), 'фирменный подвал карточки');
chk(svg.includes('&#039;') || !svg.includes("'"), 'апострофы экранируются');

// Экранирование данных, а не вставка HTML.
const xss = buildPlaque(Object.assign({}, base, {
    personName: '<script>alert(1)</script>',
    rows: [{ level: 0, person: { id: 6, first_name: '<b>X</b>', last_name: '', birth_year: '1985' } }]
}));
chk(!xss.includes('<script>'), 'имя человека экранируется');
chk(!xss.includes('<b>X</b>'), 'поле имени экранируется');

// Длинное имя уменьшается, но не обрезается.
const long = buildPlaque(Object.assign({}, base, {
    rows: [{ level: 0, person: { id: 6, first_name: 'АШАМАЙЛЫ-КЕРЕЙ-ҰЗЫН-АТАУ-СЫНАҚ', last_name: '', birth_year: '1985' } }]
}));
const m = long.match(/class="za-name"[\s\S]*?font-size="(\d+(?:\.\d+)?)"/);
chk(!!m && Number(m[1]) < 30, 'длинное имя уменьшено (кегль ' + (m ? m[1] : '?') + ')');

// Без герба остаётся нейтральная золотая рамка, без выдуманных данных.
const noClan = buildPlaque(Object.assign({}, base, { emblemInner: '' }));
chk(!noClan.includes('data-test-emblem'), 'без рода герб не подставляется');
chk(noClan.includes('stroke-dasharray="3 8"'), 'честный нейтральный плейсхолдер');

// Настоящий PDF: A4 portrait, JPEG всей карточки.
const jpeg = new Uint8Array([0xFF, 0xD8, 0xFF, 0xD9]);
const pdf = _buildPdfBytes(jpeg, 1440, 3160, 595, 842);
const pdfText = Buffer.from(pdf).toString('latin1');
chk(pdfText.startsWith('%PDF-1.4'), 'PDF начинается с %PDF-1.4');
chk(pdfText.includes('/MediaBox [0 0 595 842]'), 'PDF — страница A4 portrait 595×842');
chk(pdfText.includes('/Filter /DCTDecode'), 'PDF встраивает JPEG (кириллица не зависит от шрифтов)');
chk(pdfText.includes('/Width 1440 /Height 3160'), 'размер изображения в PDF совпадает с PNG 2×');
chk(pdfText.trimEnd().endsWith('%%EOF'), 'PDF корректно завершён');

// Сама страница подключает единый генератор герба и новый CSS.
const view = fs.readFileSync(path.join(__dirname, '..', 'views', 'zheti_ata.php'), 'utf8');
const master = fs.readFileSync(path.join(__dirname, '..', 'views', 'layout', 'master_start.php'), 'utf8');
const css = fs.readFileSync(path.join(__dirname, '..', 'public', 'assets', 'css', 'brand-system.css'), 'utf8');
chk(view.includes('/assets/js/clan-emblem.js'), 'страница подключает ClanEmblem ALT-117');
chk(view.includes('/assets/js/zheti-ata.js'), 'страница подключает общий рендерер');
chk(master.includes('/assets/css/brand-system.css'), 'фирменная система стилей подключена');
chk(master.includes('ds-zheti-ui'), 'страница получает класс фирменного окружения');
chk(!master.includes('#o-branch'), 'старая ветвь ою-өрнек убрана из подвала панели');
chk(css.includes('.ds-sidebar') && css.includes('--brand-gold'), 'боковая панель переведена на золото/камень');
chk(!/\.ds-sidebar[\s\S]{0,200}#5b2018/.test(css), 'в панели нет старого бордового');

if (fails) {
    console.error('\n' + fails + ' проверок не прошло');
    process.exit(1);
}
console.log('\nВсе проверки ALT-120 пройдены');
