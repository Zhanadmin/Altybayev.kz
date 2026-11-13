/**
 * TASK-006, шаг 7: печатный плакат /tree/print в стиле «Шежіре-Ағаш» (Rodoslov).
 * Проверяет без браузера и без БД:
 *  - встроенный JS views/print_poster.php синтаксически корректен (PHP-вставки заменены заглушками);
 *  - генератор подключён до встроенного скрипта, есть оба вида (древо / список), печать и SVG;
 *  - на плакате нет эмодзи;
 *  - выборка как в PrintPosterService::getRodoslovData (потомки по отцу + супруги,
 *    родители вне выборки обнулены) строится генератором без ошибок.
 * Запуск: node tests\PrintPosterRodoslovTest.js [путь-для-превью.svg]
 */
'use strict';
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const html = fs.readFileSync(path.join(ROOT, 'views', 'print_poster.php'), 'utf8');
const service = fs.readFileSync(path.join(ROOT, 'app', 'Services', 'PrintPosterService.php'), 'utf8');
const controller = fs.readFileSync(path.join(ROOT, 'app', 'Controllers', 'PrintPosterController.php'), 'utf8');
const Gen = require(path.join(ROOT, 'public', 'assets', 'js', 'rodoslov-generator.js'));

let ok = 0, fail = 0;
function check(name, cond, info) {
    if (cond) { ok++; console.log('  OK   ' + name); }
    else { fail++; console.log('  FAIL ' + name + (info ? ' — ' + info : '')); }
}
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

console.log('PrintPosterRodoslovTest — плакат /tree/print в стиле Шежіре-Ағаш');

// 1. Встроенные скрипты
const scripts = [];
html.replace(/<script(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script>/g, function (m, body) { scripts.push(body); return m; });
check('найдены встроенные скрипты', scripts.length > 0);
scripts.forEach(function (body, i) {
    const js = body.replace(/<\?=[\s\S]*?\?>/g, '0').replace(/<\?php[\s\S]*?\?>/g, '');
    let err = null;
    try { new Function(js); } catch (e) { err = e; }
    check('скрипт #' + (i + 1) + ' без синтаксических ошибок', !err, err && err.message);
});

// 2. Разметка
const genTag = html.indexOf('<script src="/assets/js/rodoslov-generator.js"></script>');
check('rodoslov-generator.js подключён', genTag > 0);
check('генератор подключён до встроенного скрипта', genTag > 0 && genTag < html.indexOf('function renderRodoslovPoster'));
check('контейнер #rodoslovSheet есть', html.indexOf('id="rodoslovSheet"') > 0);
check('старый вид-список #posterSheet сохранён', html.indexOf('id="posterSheet"') > 0);
check('переключатель вида древо/список', html.indexOf('id="btnPosterView"') > 0 && html.indexOf('function togglePosterView') > 0);
check('по умолчанию — древо (body.view-rodoslov)', html.indexOf('<body class="view-rodoslov">') > 0);
check('печать (window.print) осталась', html.indexOf('window.print()') > 0);
check('SVG рисунка скачивается с клиента (Blob)', /function downloadPosterSvg[\s\S]{0,800}new Blob\(/.test(html));
check('серверный SVG остался запасным', html.indexOf('/tree/print/svg?root=') > 0);
check('ориентация листа по пропорциям рисунка', html.indexOf("res.width >= res.height ? 'landscape' : 'portrait'") > 0);
check('данные в JS — json_encode с JSON_HEX_TAG', /POSTER_DATA = <\?= json_encode\(\$rodoslovData[^;]*JSON_HEX_TAG/.test(html));
check('нет эмодзи на плакате', !/[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}]/u.test(html));
check('сервис: есть getRodoslovData', service.indexOf('public function getRodoslovData(') > 0);
check('контроллер передаёт rodoslovData в вид', controller.indexOf("'rodoslovData' => $this->service->getRodoslovData(") > 0);

// 3. Построение по выборке «как в сервисе» из GEDCOM проекта
const ged = path.join(ROOT, 'import', 'Altubaiev - 2010-05-27.ged');
if (!fs.existsSync(ged)) {
    console.log('  SKIP GEDCOM не найден: ' + ged);
} else {
    // Минимальный разбор: только имя, пол, даты, связи (как в RodoslovGeneratorTest).
    const MONTHS = { JAN: '01', FEB: '02', MAR: '03', APR: '04', MAY: '05', JUN: '06', JUL: '07', AUG: '08', SEP: '09', OCT: '10', NOV: '11', DEC: '12' };
    const gd = function (s) {
        const t = String(s || '').trim().replace(/^(ABT|EST|CAL|BEF|AFT)\s+/i, '');
        let m = t.match(/^(\d{1,2}) ([A-Z]{3}) (\d{4})$/);
        if (m && MONTHS[m[2]]) return { date: m[3] + '-' + MONTHS[m[2]] + '-' + String(m[1]).padStart(2, '0'), year: +m[3] };
        m = t.match(/(\d{4})$/);
        return m ? { date: null, year: +m[1] } : { date: null, year: null };
    };
    const indi = new Map(), fams = new Map();
    let cur = null, type = null, sub = null;
    fs.readFileSync(ged, 'utf8').split(/\r?\n/).forEach(function (raw) {
        const m = raw.match(/^(\d+)\s+(@[^@]+@\s+)?(\S+)\s?(.*)$/);
        if (!m) return;
        const lv = +m[1], x = m[2] ? m[2].trim() : null, tag = m[3], val = m[4] || '';
        if (lv === 0) {
            sub = null;
            if (x && tag === 'INDI') { cur = { gid: x, given: '', surn: '', sex: 'U', birt: {}, deat: null, famc: null }; type = 'I'; indi.set(x, cur); }
            else if (x && tag === 'FAM') { cur = { husb: null, wife: null, marr: null }; type = 'F'; fams.set(x, cur); }
            else { cur = null; }
            return;
        }
        if (!cur) return;
        if (lv === 1) sub = tag;
        if (type === 'I') {
            if (lv === 1 && tag === 'NAME' && !cur.given) { const nm = val.match(/^([^/]*)\/?([^/]*)\/?/); cur.given = nm[1].trim(); cur.surn = nm[2].trim(); }
            else if (lv === 1 && tag === 'SEX') cur.sex = val.trim();
            else if (lv === 1 && tag === 'DEAT') cur.deat = {};
            else if (lv === 2 && tag === 'DATE' && sub === 'BIRT') cur.birt = gd(val);
            else if (lv === 2 && tag === 'DATE' && sub === 'DEAT') cur.deat = gd(val);
            else if (lv === 1 && tag === 'FAMC' && !cur.famc) cur.famc = val.trim();
        } else {
            if (lv === 1 && tag === 'HUSB') cur.husb = val.trim();
            else if (lv === 1 && tag === 'WIFE') cur.wife = val.trim();
            else if (lv === 2 && tag === 'DATE' && sub === 'MARR') cur.marr = gd(val).date;
        }
    });
    const idOf = new Map();
    let n = 0;
    indi.forEach(function (p, gid) { idOf.set(gid, ++n); });
    const all = new Map();
    indi.forEach(function (p, gid) {
        const f = p.famc ? fams.get(p.famc) : null;
        all.set(idOf.get(gid), {
            id: idOf.get(gid), gedcom_id: gid, first_name: p.given, last_name: p.surn.replace(/[,;]+\s*$/, ''),
            gender: p.sex === 'F' ? 'F' : 'M', birth_date: p.birt.date || null, birth_year: p.birt.year || null,
            is_deceased: p.deat ? 1 : 0, death_date: p.deat ? p.deat.date || null : null, death_year: p.deat ? p.deat.year || null : null,
            father_id: f && f.husb ? idOf.get(f.husb) : null, mother_id: f && f.wife ? idOf.get(f.wife) : null
        });
    });
    const marriagesAll = [];
    fams.forEach(function (f) { if (f.husb && f.wife) marriagesAll.push({ husband_id: idOf.get(f.husb), wife_id: idOf.get(f.wife), marriage_date: f.marr }); });

    // корень — Алтыбай, предок Жандоса (@I6@) по отцу (как родоначальник по умолчанию в контроллере);
    // если имени нет — 4 колена вверх от Жандоса
    let rootId = idOf.get('@I6@'), alt = 0, up = 0, fallback = rootId;
    const seen = new Set();
    while (all.get(rootId) && all.get(rootId).father_id && !seen.has(rootId)) {
        seen.add(rootId);
        rootId = all.get(rootId).father_id;
        if (++up === 4) fallback = rootId;
        if (!alt && /^Алтыбай/.test(all.get(rootId).first_name)) alt = rootId;
    }
    rootId = alt || fallback;

    // getRodoslovData: потомки по отцу (7 буын) + супруги
    const GENS = 7;
    const ids = new Set();
    const childrenByFather = new Map();
    all.forEach(function (p) { if (p.father_id) { if (!childrenByFather.has(p.father_id)) childrenByFather.set(p.father_id, []); childrenByFather.get(p.father_id).push(p.id); } });
    const spousesOf = function (id) { return marriagesAll.filter(m => m.husband_id === id || m.wife_id === id).map(m => m.husband_id === id ? m.wife_id : m.husband_id); };
    (function walk(id, g) {
        if (g > GENS || !all.has(id) || ids.has(id)) return;
        ids.add(id);
        spousesOf(id).forEach(s => ids.add(s));
        (childrenByFather.get(id) || []).forEach(c => walk(c, g + 1));
    })(rootId, 1);
    const persons = [];
    ids.forEach(function (id) {
        const p = Object.assign({}, all.get(id));
        if (p.father_id && !ids.has(p.father_id)) p.father_id = null;
        if (p.mother_id && !ids.has(p.mother_id)) p.mother_id = null;
        persons.push(p);
    });
    const marriages = marriagesAll.filter(m => ids.has(m.husband_id) && ids.has(m.wife_id));

    const res = Gen.build({ persons: persons, marriages: marriages, focal_id: rootId }, {
        focalId: rootId, title: 'АЛТЫБАЕВТАР ӘУЛЕТІНІҢ ШЕЖІРЕСІ', subtitle: 'Плакат · тексеру нұсқасы',
        mainClan: 'КЕРЕЙ', labels: { father: 'АТА ЖҰРТ' }, footnote: 'altybayev.kz · TASK-006, 7-қадам'
    });
    console.log('  .... корень #' + rootId + ' (' + all.get(rootId).first_name + '), персон ' + res.stats.persons +
        ', стволов ' + res.stats.trees + ', ярусов ' + res.stats.levels + ', холст ' + res.width + '×' + res.height);
    check('выборка не пустая', persons.length > 10);
    check('каждая персона размещена', res.stats.placed === res.stats.persons);
    const pid = (res.svg.match(/data-person-id="(\d+)"/g) || []).map(s => s.replace(/\D/g, ''));
    check('каждая персона нарисована один раз', pid.length === persons.length && new Set(pid).size === pid.length);
    check('Жандос на плакате', pid.indexOf(String(idOf.get('@I6@'))) >= 0);
    check('корень стоит ниже всех (у корней)', Object.keys(res.positions).every(k => res.positions[k].y <= res.positions[rootId].y));
    check('теги сбалансированы', xmlBalanced(res.svg));
    check('нет NaN / undefined', res.svg.indexOf('NaN') < 0 && res.svg.indexOf('undefined') < 0);
    check('подпись ствола «АТА ЖҰРТ»', res.svg.indexOf('АТА ЖҰРТ') > 0);
    check('нет эмодзи в рисунке', !/[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}]/u.test(res.svg));
    if (process.argv[2]) {
        fs.writeFileSync(process.argv[2], '<?xml version="1.0" encoding="UTF-8"?>\n' + res.svg, 'utf8');
        console.log('  .... превью: ' + process.argv[2]);
    }
}

console.log('\nИтого: ' + ok + ' OK, ' + fail + ' FAIL');
process.exit(fail ? 1 : 0);
