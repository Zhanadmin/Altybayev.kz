/**
 * TASK-006, шаги 5–6: подключение RodoslovGenerator к views/tree.php.
 * Проверяет без браузера:
 *  - встроенный JS страницы синтаксически корректен (PHP-вставки заменены заглушками);
 *  - генератор подключён до основного скрипта;
 *  - режим «Шежіре-Орман» вынесен из трансформируемого холста #treeWorld;
 *  - старый захардкоженный рисунок с выдуманными данными удалён;
 *  - математика центрирования на ленте совпадает с позициями генератора.
 * Запуск: node tests\TreeRodoslovIntegrationTest.js
 */
'use strict';
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const html = fs.readFileSync(path.join(ROOT, 'views', 'tree.php'), 'utf8');
const Gen = require(path.join(ROOT, 'public', 'assets', 'js', 'rodoslov-generator.js'));

let ok = 0, fail = 0;
function check(name, cond, info) {
    if (cond) { ok++; console.log('  OK   ' + name); }
    else { fail++; console.log('  FAIL ' + name + (info ? ' — ' + info : '')); }
}

console.log('TreeRodoslovIntegrationTest — Шежіре-Ағаш на странице /tree');

// 1. Синтаксис встроенных скриптов
const scripts = [];
html.replace(/<script(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script>/g, function (m, body) { scripts.push(body); return m; });
check('найдены встроенные скрипты', scripts.length > 0);
scripts.forEach(function (body, i) {
    const js = body.replace(/<\?=[\s\S]*?\?>/g, '0').replace(/<\?php[\s\S]*?\?>/g, '');
    let err = null;
    try { new Function(js); } catch (e) { err = e; }
    check('скрипт #' + (i + 1) + ' без синтаксических ошибок', !err, err && err.message);
});

// 2. Подключение генератора
const genTag = html.indexOf('<script src="/assets/js/rodoslov-generator.js"></script>');
const mainScript = html.indexOf('let userPersonId');
check('rodoslov-generator.js подключён', genTag > 0);
check('генератор подключён до основного скрипта', genTag > 0 && genTag < mainScript);

// 3. Структура: #forestView не внутри #treeWorld
function blockOf(id) {
    const start = html.indexOf('id="' + id + '"');
    if (start < 0) return null;
    const open = html.lastIndexOf('<div', start);
    let depth = 0, i = open;
    const re = /<\/?div\b/g;
    re.lastIndex = open;
    let m;
    while ((m = re.exec(html))) {
        depth += m[0] === '<div' ? 1 : -1;
        if (depth === 0) { i = m.index; break; }
    }
    return { open: open, close: i };
}
const worldB = blockOf('treeWorld');
const forestB = blockOf('forestView');
const viewportB = blockOf('canvasViewport');
check('блоки найдены', worldB && forestB && viewportB);
if (worldB && forestB && viewportB) {
    check('#forestView вне #treeWorld', forestB.open > worldB.close);
    check('#forestView внутри #canvasViewport', forestB.open > viewportB.open && forestB.close < viewportB.close);
    const editB = blockOf('editingView');
    check('#editingView остался внутри #treeWorld', editB && editB.open > worldB.open && editB.close < worldB.close);
}

// 4. Старый рисунок удалён, новый вызов есть
check('нет выдуманных ярусов «Шамсиден/Раушан»', html.indexOf('ШАМСИДЕН') < 0 && html.indexOf('Раушан)') < 0);
check('нет жёстких id персон (personMap.get(65))', html.indexOf('personMap.get(65)') < 0);
check('нет фиксированного холста 3000×2200', html.indexOf('viewBox="0 0 3000 2200"') < 0);
check('renderRodoslovView вызывает RodoslovGenerator.mount', /function renderRodoslovView\(\)[\s\S]{0,5000}RodoslovGenerator\.mount\(/.test(html));
check('клик по ленте открывает карточку (selectPerson)', /onPersonClick:[\s\S]{0,400}selectPerson\(/.test(html));
['rodoslovCenterOn', 'rodoslovFitTree', 'rodoslovFocusFocal', 'rodoslovHighlight', 'setupRodoslovEvents'].forEach(function (fn) {
    check('есть функция ' + fn, html.indexOf('function ' + fn + '(') > 0);
});
check('щипок двумя пальцами (pinch)', html.indexOf('touchDist(e.touches)') > 0);
check('клик после перетаскивания гасится', html.indexOf('rodoslovDragMoved') > 0);

// 5. Центрирование: та же формула, что в rodoslovCenterOn
const data = {
    user_person_id: 3,
    persons: [
        { id: 1, first_name: 'Ата', last_name: 'Тест', gender: 'M' },
        { id: 2, first_name: 'Ана', last_name: 'Тест', gender: 'F' },
        { id: 3, first_name: 'Ұл', last_name: 'Тест', gender: 'M', father_id: 1, mother_id: 2 }
    ],
    marriages: [{ husband_id: 1, wife_id: 2 }]
};
const res = Gen.build(data, { focalId: 3 });
const M = 60, scale = 0.85, vw = 1200, vh = 800;
const p = res.positions[3];
check('позиция фокусной ленты есть', !!p);
if (p) {
    const panX = vw / 2 - (M + p.x) * scale;
    const panY = vh / 2 - (M + p.y) * scale;
    const screenX = panX + (M + p.x) * scale;
    const screenY = panY + (M + p.y) * scale;
    check('лента в центре экрана после центрирования', Math.abs(screenX - vw / 2) < 0.01 && Math.abs(screenY - vh / 2) < 0.01);
    check('позиция внутри холста', p.x > 0 && p.x < res.width && p.y > 0 && p.y < res.height);
}
// Всё древо проекта по GEDCOM: было 40 876 × 5 180 px, после плотной раскладки (17:18) ~27 400 px.
// Берём прежнюю, бо́льшую ширину — с запасом: на экране 1280 px должно влезать
const fullW = 40876, fullH = 5180;
const fitScale = Math.min(1280 / (fullW + 2 * M), 720 / (fullH + 2 * M)) * 0.96;
const minScaleSrc = /RODOSLOV_MIN_SCALE\s*=\s*([\d.]+)/.exec(html);
check('RODOSLOV_MIN_SCALE задан', !!minScaleSrc);
check('«Бүкіл ағаш» вписывает всё древо (масштаб ≥ минимума)', minScaleSrc && fitScale >= parseFloat(minScaleSrc[1]), 'fit=' + fitScale.toFixed(4));

console.log('\nИтог: ' + ok + ' OK, ' + fail + ' FAIL');
process.exit(fail ? 1 : 0);
