/*
 * TASK-006 · тест генератора «Шежіре-Ағаш» (public/assets/js/rodoslov-generator.js).
 * Запуск:  node tests/RodoslovGeneratorTest.js [путь-для-превью.svg]
 * Данные: import/Altubaiev - 2010-05-27.ged → разбираются в формат таблиц persons/marriages
 * (берутся только имя, пол, даты, связи; адреса, телефоны и заметки не читаются).
 */
'use strict';

const fs = require('fs');
const path = require('path');
const Gen = require(path.join(__dirname, '..', 'public', 'assets', 'js', 'rodoslov-generator.js'));

let passed = 0;
let failed = 0;
function ok(cond, msg) {
    if (cond) { passed++; console.log('  OK   ' + msg); } else { failed++; console.log('  FAIL ' + msg); }
}

const MONTHS = { JAN: '01', FEB: '02', MAR: '03', APR: '04', MAY: '05', JUN: '06', JUL: '07', AUG: '08', SEP: '09', OCT: '10', NOV: '11', DEC: '12' };
function gedDate(s) {
    const t = String(s || '').trim().replace(/^(ABT|EST|CAL|BEF|AFT)\s+/i, '');
    let m = t.match(/^(\d{1,2}) ([A-Z]{3}) (\d{4})$/);
    if (m && MONTHS[m[2]]) return { date: m[3] + '-' + MONTHS[m[2]] + '-' + String(m[1]).padStart(2, '0'), year: +m[3] };
    m = t.match(/(\d{4})$/);
    if (m) return { date: null, year: +m[1] };
    return { date: null, year: null };
}

function parseGedcom(file) {
    const lines = fs.readFileSync(file, 'utf8').split(/\r?\n/);
    const indi = new Map();
    const fams = new Map();
    let cur = null, curType = null, sub = null;
    for (const raw of lines) {
        const m = raw.match(/^(\d+)\s+(@[^@]+@\s+)?(\S+)\s?(.*)$/);
        if (!m) continue;
        const level = +m[1];
        const xref = m[2] ? m[2].trim() : null;
        const tag = m[3];
        const val = m[4] || '';
        if (level === 0) {
            sub = null;
            if (xref && tag === 'INDI') { cur = { gid: xref, given: '', surn: '', marnm: '', sex: 'U', birt: {}, deat: null, famc: null }; curType = 'I'; indi.set(xref, cur); }
            else if (xref && tag === 'FAM') { cur = { gid: xref, husb: null, wife: null, chil: [], marr: null }; curType = 'F'; fams.set(xref, cur); }
            else { cur = null; curType = null; }
            continue;
        }
        if (!cur) continue;
        if (level === 1) sub = tag;
        if (curType === 'I') {
            if (level === 1 && tag === 'NAME' && !cur.given) {
                const nm = val.match(/^([^/]*)\/?([^/]*)\/?/);
                cur.given = (nm[1] || '').trim(); cur.surn = (nm[2] || '').trim();
            } else if (level === 2 && sub === 'NAME' && tag === 'GIVN' && !cur._g) { cur.given = val.trim(); cur._g = 1; }
            else if (level === 2 && sub === 'NAME' && tag === 'SURN' && !cur._s) { cur.surn = val.trim(); cur._s = 1; }
            else if (level === 2 && sub === 'NAME' && tag === '_MARNM' && !cur.marnm) { cur.marnm = val.trim(); }
            else if (level === 1 && tag === 'SEX') cur.sex = val.trim();
            else if (level === 1 && tag === 'DEAT') cur.deat = {};
            else if (level === 2 && tag === 'DATE' && sub === 'BIRT') cur.birt = gedDate(val);
            else if (level === 2 && tag === 'DATE' && sub === 'DEAT') cur.deat = gedDate(val);
            else if (level === 1 && tag === 'FAMC' && !cur.famc) cur.famc = val.trim();
        } else {
            if (level === 1 && tag === 'HUSB') cur.husb = val.trim();
            else if (level === 1 && tag === 'WIFE') cur.wife = val.trim();
            else if (level === 1 && tag === 'CHIL') cur.chil.push(val.trim());
            else if (level === 2 && tag === 'DATE' && sub === 'MARR') cur.marr = gedDate(val).date || (gedDate(val).year ? gedDate(val).year + '-00-00' : null);
        }
    }
    const idOf = new Map();
    let n = 0;
    indi.forEach((p, gid) => idOf.set(gid, ++n));
    const persons = [];
    indi.forEach((p, gid) => {
        const fam = p.famc ? fams.get(p.famc) : null;
        const clean = s => String(s || '').replace(/[,;]+\s*$/, '').trim();
        const last = clean(p.marnm || p.surn);
        persons.push({
            id: idOf.get(gid), gedcom_id: gid,
            first_name: p.given, last_name: last,
            maiden_name: p.marnm ? clean(p.surn) : null,
            gender: p.sex === 'F' ? 'F' : (p.sex === 'M' ? 'M' : 'unknown'),
            birth_date: p.birt.date || null, birth_year: p.birt.year || null,
            is_deceased: p.deat ? 1 : 0,
            death_date: p.deat ? p.deat.date || null : null, death_year: p.deat ? p.deat.year || null : null,
            father_id: fam && fam.husb ? idOf.get(fam.husb) || null : null,
            mother_id: fam && fam.wife ? idOf.get(fam.wife) || null : null,
            clan_ru: null, is_deleted: 0
        });
    });
    const marriages = [];
    let mid = 0;
    fams.forEach(f => {
        if (f.husb && f.wife && idOf.get(f.husb) && idOf.get(f.wife)) {
            marriages.push({ id: ++mid, husband_id: idOf.get(f.husb), wife_id: idOf.get(f.wife), marriage_date: f.marr });
        }
    });
    return { persons, marriages, idOf };
}

// Простая проверка правильной вложенности тегов XML.
function xmlBalanced(svg) {
    const stack = [];
    const re = /<(\/?)([a-zA-Z][\w:-]*)([^>]*?)(\/?)>/g;
    let m;
    while ((m = re.exec(svg))) {
        if (m[1]) { if (stack.pop() !== m[2]) return false; }
        else if (!m[4]) stack.push(m[2]);
    }
    return stack.length === 0;
}

// при подключении из другого теста — только разбор GEDCOM, без запуска проверок
module.exports = { parseGedcom, xmlBalanced };
if (require.main !== module) return;

console.log('RodoslovGeneratorTest — генератор SVG «Шежіре-Ағаш»');

// --- 1. утилиты ---
const I = Gen._internal;
ok(I.escapeXml('<a href="x">&\'') === '&lt;a href=&quot;x&quot;&gt;&amp;&#39;', 'escapeXml экранирует & < > " \'');
ok(I.genderSurname('Айткалиева', 'M') === 'Айткалиев', 'мужская форма фамилии: Айткалиева → Айткалиев');
ok(I.genderSurname('Алтыбаев', 'F') === 'Алтыбаева', 'женская форма фамилии: Алтыбаев → Алтыбаева');
ok(I.genderSurname('Алтыбаева', 'F') === 'Алтыбаева', 'правильная женская фамилия не меняется');
ok(I.clanPlural('Алтыбаев') === 'Алтыбаевтар', 'множественное число: Алтыбаевтар');
ok(I.clanPlural('Айткалиев') === 'Айткалиевтер', 'множественное число: Айткалиевтер');
ok(I.fmtDate('1956-02-03', 1956) === '3.02.1956', 'дата 1956-02-03 → 3.02.1956');
ok(I.fmtDate(null, 1985) === '1985', 'только год → 1985');
ok(I.fmtDate('1979-00-00', 0) === '1979', 'неполная дата → год');

// --- 2. маленькая семья, экранирование, клик-атрибуты ---
const small = Gen.build({
    persons: [
        { id: 1, first_name: 'Ата', last_name: 'Тестов', gender: 'M', is_deceased: 1, birth_year: 1900, death_year: 1970 },
        { id: 2, first_name: 'Әже', last_name: 'Тестова', maiden_name: 'Басқа', gender: 'F', birth_year: 1905 },
        { id: 3, first_name: '<script>alert(1)</script>', last_name: 'Тестов', gender: 'M', father_id: 1, mother_id: 2, birth_date: '1930-05-01' },
        { id: 4, first_name: 'Қыз', last_name: 'Тестов', gender: 'F', father_id: 1, mother_id: 2, birth_year: 1933 }
    ],
    marriages: [{ id: 1, husband_id: 1, wife_id: 2, marriage_date: '1928-03-08' }]
}, { focalId: 3, title: 'Тест & <шежіре>' });
ok(small.svg.indexOf('<script>') < 0 && small.svg.indexOf('&lt;script&gt;') > 0, 'имя с <script> экранировано');
ok(small.svg.indexOf('Тест &amp; &lt;шежіре&gt;') > 0, 'заголовок экранирован');
ok(xmlBalanced(small.svg), 'малое древо: теги сбалансированы');
ok((small.svg.match(/class="rs-p"/g) || []).length === 4, 'малое древо: 4 ленты');
ok(small.positions[3].y < small.positions[1].y, 'ребёнок выше родителя (крона вверху)');
ok(small.positions[1].y === small.positions[2].y, 'супруги на одном ярусе');
ok(small.svg.indexOf('неке · 8.03.1928') > 0, 'лента с датой брака');
ok(small.svg.indexOf('қыз кезінде — Басқа') > 0, 'у жены — «қыз кезінде — …»');
ok(small.svg.indexOf('Қыз Тестова') > 0, 'дочери выведена женская форма фамилии');
ok(/href="#rs\d+-rbG"/.test(small.svg), 'ушедший — золотая лента');
ok(/href="#rs\d+-rings"/.test(small.svg), 'кольца у пары');
ok(/href="#rs\d+-tulip"/.test(small.svg), 'тюльпан у дочери');
ok(!/(href="#|url\(#|id=")rs-/.test(small.svg), 'все id в <defs> с уникальным префиксом');
const small2 = Gen.build({ persons: [{ id: 1, first_name: 'А', gender: 'M' }] }, { labels: { father: 'X' } });
ok(small2.svg.indexOf('undefined') < 0, 'частичная замена подписей не даёт «undefined»');
const idA = (small.svg.match(/id="(rs\d+-)rb"/) || [])[1], idB = (small2.svg.match(/id="(rs\d+-)rb"/) || [])[1];
ok(idA && idB && idA !== idB, 'два построения — разные префиксы id');
const cyc = Gen.build({ persons: [
    { id: 1, first_name: 'Цикл', gender: 'M', father_id: 2 },
    { id: 2, first_name: 'Цикл2', gender: 'M', father_id: 1 },
    { id: 3, first_name: 'Сам', gender: 'M', father_id: 3 }, null
], marriages: [null, { husband_id: 1, wife_id: 1 }] });
ok(cyc.stats.placed === 3 && xmlBalanced(cyc.svg) && cyc.svg.indexOf('NaN') < 0, 'циклы в данных и пустые записи не ломают построение');
ok(!/[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}]/u.test(small.svg), 'нет эмодзи');

// --- 3. пустые данные ---
const empty = Gen.build({ persons: [], marriages: [] });
ok(empty.width > 0 && xmlBalanced(empty.svg) && empty.stats.persons === 0, 'пустая база: заглушка без ошибок');

// --- 4. всё древо из GEDCOM ---
const ged = path.join(__dirname, '..', 'import', 'Altubaiev - 2010-05-27.ged');
if (!fs.existsSync(ged)) {
    console.log('  SKIP GEDCOM не найден: ' + ged);
} else {
    const data = parseGedcom(ged);
    const focal = data.idOf.get('@I6@');
    const t0 = Date.now();
    const res = Gen.build({ persons: data.persons, marriages: data.marriages, user_person_id: focal }, {
        title: 'АЛТЫБАЕВТАР ШЕЖІРЕСІ',
        subtitle: 'Бүкіл шежіре — генератор, тексеру нұсқасы',
        mainClan: 'Керей',
        footnote: 'Дерек көзі: MyHeritage GEDCOM (12.11.2026) · TASK-006, 4-қадам'
    });
    const ms = Date.now() - t0;
    console.log('  .... персон ' + res.stats.persons + ', размещено ' + res.stats.placed + ', стволов ' + res.stats.trees +
        ', ярусов ' + res.stats.levels + ', нитей ' + res.stats.threads + ', холст ' + res.width + '×' + res.height + ', ' + ms + ' мс');
    res.stats.treeInfo.forEach(t => console.log('  .... ствол ' + t.kind + ' корень #' + t.rootId + ' ярус ' + t.rootLevel + ': ' + t.persons + ' чел., ширина ' + t.width));
    ok(res.stats.placed === res.stats.persons, 'каждая персона размещена');
    const ids = (res.svg.match(/data-person-id="(\d+)"/g) || []).map(s => s.replace(/\D/g, ''));
    ok(ids.length === res.stats.persons && new Set(ids).size === ids.length, 'каждая персона нарисована ровно один раз');
    ok(xmlBalanced(res.svg), 'всё древо: теги сбалансированы');
    ok(res.svg.indexOf('NaN') < 0 && res.svg.indexOf('Infinity') < 0 && res.svg.indexOf('undefined') < 0, 'всё древо: нет NaN / Infinity / undefined');
    ok(ms < 3000, 'построение быстрее 3 с');

    // нет наложений лент на одном ярусе
    const byY = new Map();
    Object.keys(res.positions).forEach(id => {
        const p = res.positions[id];
        if (!byY.has(p.y)) byY.set(p.y, []);
        byY.get(p.y).push(p.x);
    });
    let widest = 0;
    byY.forEach(xs => { widest = Math.max(widest, xs.length); });
    console.log('  .... самый широкий ярус: ' + widest + ' лент (минимум ' + widest * 262 + ' px)');
    let overlaps = 0;
    // лента с хвостами — 248 px
    byY.forEach(xs => { xs.sort((a, b) => a - b); for (let i = 1; i < xs.length; i++) if (xs[i] - xs[i - 1] < 252) overlaps++; });
    ok(overlaps === 0, 'ленты одного яруса не накладываются (шаг ≥ 252)');

    // дети выше родителей; супруги на одном ярусе
    const pos = res.positions;
    let badKids = 0, badSp = 0;
    data.persons.forEach(p => {
        [p.father_id, p.mother_id].forEach(pid => { if (pid && pos[pid] && pos[p.id] && pos[p.id].y >= pos[pid].y) badKids++; });
    });
    data.marriages.forEach(m => { if (pos[m.husband_id] && pos[m.wife_id] && pos[m.husband_id].y !== pos[m.wife_id].y) badSp++; });
    ok(badKids === 0, 'все дети выше родителей (' + badKids + ' нарушений)');
    ok(badSp === 0, 'все супруги на одном ярусе (' + badSp + ' нарушений)');

    // ветка Жандоса как в эскизе
    const z = pos[focal], g = pos[data.idOf.get('@I1@')], ak = pos[data.idOf.get('@I25@')], as = pos[data.idOf.get('@I24@')];
    ok(z && g && z.y < g.y, 'Жандос выше отца (Габит)');
    ok(ak && ak.y < z.y, 'Акнур выше Жандоса');
    ok(as && as.y === z.y && Math.abs(as.x - z.x) < 300, 'Асила рядом с Жандосом');
    ok(res.svg.indexOf('Жандос Алтыбаев</text>') > 0 && /rs-nm rs-nmB[^>]*>Жандос/.test(res.svg), 'Жандос выделен жирным');
    ok(res.svg.indexOf('Сатыбалды Айткалиев<') > 0, 'исправлена мужская фамилия Сатыбалды');
    ok(res.svg.indexOf('ӘКЕ ЖАҒЫ · АЛТЫБАЕВТАР') > 0, 'подпись главного ствола');
    ok(res.svg.indexOf('АНА ЖАҒЫ') > 0, 'есть ствол рода матери');
    ok(res.svg.indexOf('>КЕРЕЙ<') > 0, 'тамга Керей на главном стволе');

    const out = process.argv[2];
    if (out) {
        fs.writeFileSync(out, '<?xml version="1.0" encoding="UTF-8"?>\n' + res.svg, 'utf8');
        console.log('  .... превью записано: ' + out);
    }
}

console.log('\nИтог: ' + passed + ' OK, ' + failed + ' FAIL');
process.exit(failed ? 1 : 0);
