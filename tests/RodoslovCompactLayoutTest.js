/*
 * TASK-006 · замечание Жандоса 13.11 17:18: раскладка «Шежіре-Ағаш» должна быть компактной.
 * Дети — рядом над своими родителями, родители супругов — поблизости, без наложения лент.
 * Запуск:  node tests/RodoslovCompactLayoutTest.js [--debug]
 * Данные: import/Altubaiev - 2010-05-27.ged (только имя, пол, даты, связи).
 */
'use strict';

const fs = require('fs');
const path = require('path');
const Gen = require(path.join(__dirname, '..', 'public', 'assets', 'js', 'rodoslov-generator.js'));
const { parseGedcom, xmlBalanced } = require(path.join(__dirname, 'RodoslovGeneratorTest.js'));

const DEBUG = process.argv.indexOf('--debug') >= 0;
// ширина всего древа на тех же данных до переделки раскладки (13.11, отчёт 14:50)
const WIDTH_BEFORE = 40876;

let passed = 0;
let failed = 0;
function ok(cond, msg) {
    if (cond) { passed++; console.log('  OK   ' + msg); } else { failed++; console.log('  FAIL ' + msg); }
}

console.log('RodoslovCompactLayoutTest — компактность раскладки «Шежіре-Ағаш»');

const ged = path.join(__dirname, '..', 'import', 'Altubaiev - 2010-05-27.ged');
if (!fs.existsSync(ged)) {
    console.log('  SKIP GEDCOM не найден: ' + ged);
    process.exit(0);
}
const data = parseGedcom(ged);
const focal = data.idOf.get('@I6@');
const res = Gen.build({ persons: data.persons, marriages: data.marriages, user_person_id: focal }, { title: 'TEST' });
const pos = res.positions;
const byId = new Map(data.persons.map(p => [p.id, p]));

console.log('  .... холст ' + res.width + '×' + res.height + ' (до переделки ' + WIDTH_BEFORE + '), стволов ' + res.stats.trees);
ok(res.stats.placed === res.stats.persons, 'каждая персона размещена');
ok(xmlBalanced(res.svg) && res.svg.indexOf('NaN') < 0 && res.svg.indexOf('Infinity') < 0, 'SVG корректный, без NaN/Infinity');

// 1. нет наложений лент на ярусе
const byY = new Map();
Object.keys(pos).forEach(id => {
    const p = pos[id];
    if (!byY.has(p.y)) byY.set(p.y, []);
    byY.get(p.y).push(p.x);
});
let overlaps = 0, widest = 0;
byY.forEach(xs => {
    xs.sort((a, b) => a - b);
    widest = Math.max(widest, xs.length);
    // лента с хвостами — 248 px
    for (let i = 1; i < xs.length; i++) if (xs[i] - xs[i - 1] < 252) overlaps++;
});
ok(overlaps === 0, 'ленты одного яруса не накладываются (шаг ≥ 252): ' + overlaps + ' наложений');

// 2. ширина
const minPossible = widest * 262;
console.log('  .... самый широкий ярус: ' + widest + ' лент, нижняя граница ' + minPossible + ' px; запас ' +
    Math.round((res.width / minPossible - 1) * 100) + ' %');
ok(res.width <= WIDTH_BEFORE * 0.8, 'древо уже минимум на 20 % (' + res.width + ' ≤ ' + Math.round(WIDTH_BEFORE * 0.8) + ')');
ok(res.width <= minPossible * 1.35, 'ширина не более чем на 35 % больше самого широкого яруса');

// 3. дети над родителями
let sum = 0, n = 0, far = 0, worst = 0;
data.persons.forEach(p => {
    const c = pos[p.id];
    const f = p.father_id && pos[p.father_id];
    const m = p.mother_id && pos[p.mother_id];
    if (!c || (!f && !m)) return;
    const ax = f && m ? (f.x + m.x) / 2 : (f || m).x;
    const d = Math.abs(c.x - ax);
    sum += d; n++;
    worst = Math.max(worst, d);
    if (d > 2500) far++;
});
const mean = Math.round(sum / Math.max(1, n));
console.log('  .... ребёнок ↔ родители по горизонтали: среднее ' + mean + ' px, худшее ' + Math.round(worst) + ' px, дальше 2500 px: ' + far + ' из ' + n);
ok(mean <= 1700, 'в среднем ребёнок не дальше 1700 px от родителей (сейчас ' + mean + ')');
ok(far <= Math.ceil(n * 0.2), 'не более 20 % детей дальше 2500 px от родителей (' + far + ' из ' + n + ')');

// родители стоят под группой своих детей: точка ветви — в пределах группы (± одна лента)
const groups = new Map();
data.persons.forEach(p => {
    if (!pos[p.id] || !p.father_id || !pos[p.father_id]) return;
    const key = p.father_id + ':' + (p.mother_id || 0);
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push(pos[p.id].x);
});
let notUnder = 0, excessSum = 0;
groups.forEach((xs, key) => {
    const [f, m] = key.split(':').map(Number);
    const ax = pos[m] ? (pos[f].x + pos[m].x) / 2 : pos[f].x;
    const ex = Math.max(0, Math.min(...xs) - 262 - ax, ax - Math.max(...xs) - 262);
    excessSum += ex;
    if (ex > 0) {
        notUnder++;
        if (DEBUG) console.log('  dbg не под детьми: отец ' + byId.get(f).gedcom_id + ' y=' + pos[f].y + ' точка ' + Math.round(ax) +
            ', дети ' + xs.length + ' шт. ' + Math.round(Math.min(...xs)) + '…' + Math.round(Math.max(...xs)));
    }
});
const excessMean = Math.round(excessSum / Math.max(1, groups.size));
console.log('  .... семей с детьми: ' + groups.size + ', точка ветви вне группы детей: ' + notUnder + ', средний выход за группу ' + excessMean + ' px');
ok(excessMean <= 800, 'родители стоят под группой своих детей: в среднем выход за край группы ≤ 800 px (' + excessMean + ')');

// 4. ветка Жандоса и родители жён — рядом
const gid = g => data.idOf.get(g);
const z = pos[focal];
const gabit = pos[gid('@I1@')];
const asila = pos[gid('@I24@')];
const zKids = data.persons.filter(p => p.father_id === gid('@I1@')).map(p => pos[p.id].x);
ok(z && gabit && gabit.x >= Math.min(...zKids) - 262 && gabit.x <= Math.max(...zKids) + 262,
    'Габит стоит под группой своих детей (Жандос и братья/сестра)');
ok(asila && Math.abs(asila.x - z.x) < 300, 'Асила рядом с Жандосом');
function parentsDist(id) {
    const p = byId.get(id);
    if (!p) return null;
    const f = p.father_id && pos[p.father_id];
    const m = p.mother_id && pos[p.mother_id];
    if (!f && !m) return null;
    const ax = f && m ? (f.x + m.x) / 2 : (f || m).x;
    return Math.abs(pos[id].x - ax);
}
const menslu = byId.get(gid('@I1@')) && data.marriages.find(mm => mm.husband_id === gid('@I1@'));
if (menslu) {
    const d = parentsDist(menslu.wife_id);
    // у Менслу большой род (64 чел.), его ветви не могут встать вплотную, не раздвинув семью Габита
    if (d !== null) ok(d <= 6500, 'род матери (Менслу) вставлен рядом, родители не дальше 6500 px (' + Math.round(d) + ' px)');
}
const dA = parentsDist(gid('@I24@'));
if (dA !== null) ok(dA <= 600, 'родители жены (Асилы) прямо под ней (' + Math.round(dA) + ' px)');

// 5. все дети выше родителей, супруги на одном ярусе
let badKids = 0, badSp = 0;
data.persons.forEach(p => {
    [p.father_id, p.mother_id].forEach(pid => { if (pid && pos[pid] && pos[p.id] && pos[p.id].y >= pos[pid].y) badKids++; });
});
data.marriages.forEach(mm => { if (pos[mm.husband_id] && pos[mm.wife_id] && pos[mm.husband_id].y !== pos[mm.wife_id].y) badSp++; });
ok(badKids === 0 && badSp === 0, 'дети выше родителей, супруги на одном ярусе');

function metrics(r) {
    let s = 0, k = 0, fr = 0;
    data.persons.forEach(p => {
        const c = r.positions[p.id];
        const f = p.father_id && r.positions[p.father_id];
        const m = p.mother_id && r.positions[p.mother_id];
        if (!c || (!f && !m)) return;
        const d = Math.abs(c.x - (f && m ? (f.x + m.x) / 2 : (f || m).x));
        s += d; k++; if (d > 2500) fr++;
    });
    const pd = id => {
        const p = byId.get(id), c = r.positions[id];
        const f = p && p.father_id && r.positions[p.father_id], m = p && p.mother_id && r.positions[p.mother_id];
        if (!c || (!f && !m)) return '-';
        return Math.round(Math.abs(c.x - (f && m ? (f.x + m.x) / 2 : (f || m).x)));
    };
    const ms = data.marriages.find(mm => mm.husband_id === data.idOf.get('@I1@'));
    return 'ширина ' + r.width + ', среднее ' + Math.round(s / k) + ', дальше 2500: ' + fr +
        ', Менслу↔родители ' + (ms ? pd(ms.wife_id) : '-') + ', Асила↔родители ' + pd(data.idOf.get('@I24@')) +
        ', Жандос↔отец ' + Math.round(Math.abs(r.positions[focal].x - r.positions[data.idOf.get('@I1@')].x));
}
if (DEBUG) {
    const gb = gid('@I1@');
    const kids = data.persons.filter(p => p.father_id === gb).map(p => p.gedcom_id + '@' + Math.round(pos[p.id].x));
    console.log('  dbg Габит x=' + Math.round(pos[gb].x) + ', дети: ' + kids.join(', ') + '; Жандос x=' + Math.round(z.x));
    const sameY = Object.keys(pos).filter(id => pos[id].y === z.y).map(id => [Math.round(pos[id].x), byId.get(+id).gedcom_id]).sort((a, b) => a[0] - b[0]);
    const zi = sameY.findIndex(a => a[1] === '@I6@');
    console.log('  dbg ярус Жандоса около него: ' + sameY.slice(Math.max(0, zi - 6), zi + 7).map(a => a[1] + '@' + a[0]).join(' '));
    const gY = Object.keys(pos).filter(id => pos[id].y === pos[gb].y).map(id => [Math.round(pos[id].x), byId.get(+id).gedcom_id]).sort((a, b) => a[0] - b[0]);
    const gi = gY.findIndex(a => a[1] === '@I1@');
    console.log('  dbg ярус Габита около него: ' + gY.slice(Math.max(0, gi - 6), gi + 7).map(a => a[1] + '@' + a[0]).join(' '));
    const worstList = [];
    data.persons.forEach(p => {
        const c = pos[p.id];
        const f = p.father_id && pos[p.father_id];
        const m = p.mother_id && pos[p.mother_id];
        if (!c || (!f && !m)) return;
        const ax = f && m ? (f.x + m.x) / 2 : (f || m).x;
        worstList.push([Math.round(c.x - ax), p.id, p.gedcom_id, c.y, Math.round(c.x), f ? Math.round(f.x) : '-', m ? Math.round(m.x) : '-']);
    });
    worstList.sort((a, b) => Math.abs(b[0]) - Math.abs(a[0]));
    worstList.slice(0, 25).forEach(w => console.log('  dbg далеко: d=' + w[0] + ' #' + w[1] + ' ' + w[2] + ' y=' + w[3] + ' x=' + w[4] + ' отец x=' + w[5] + ' мать x=' + w[6]));
    for (let d = 0; d <= 5; d++) {
        const r = Gen.build({ persons: data.persons, marriages: data.marriages, user_person_id: focal }, { title: 'T', spliceDepth: d });
        console.log('  dbg spliceDepth=' + d + ': ' + metrics(r));
    }
    [...byY.keys()].sort((a, b) => b - a).forEach(y => {
        const xs = byY.get(y);
        console.log('  dbg y=' + y + ': ' + xs.length + ' лент, x ' + Math.round(xs[0]) + '…' + Math.round(xs[xs.length - 1]));
    });
    res.stats.treeInfo.forEach(t => console.log('  dbg ствол ' + t.kind + ' #' + t.rootId + ': ' + t.persons + ' чел., ширина ' + t.width));
}

console.log('\nИтог: ' + passed + ' OK, ' + failed + ' FAIL');
process.exit(failed ? 1 : 0);
