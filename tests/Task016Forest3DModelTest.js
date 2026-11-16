/**
 * Task016Forest3DModelTest.js
 * Модель 3D Шежіре-Орман (TASK-016): дерево = род по отцу, линии = только браки,
 * подписи линий, келін без отца — на дереве мужа, «мой род» в центре.
 * Запуск: node tests/Task016Forest3DModelTest.js
 */
const path = require('path');
const F = require(path.join(__dirname, '..', 'public', 'assets', 'js', 'shezhire-forest-3d.js'));

let passed = 0, total = 0;
function check(cond, label) {
    total++;
    if (cond) { passed++; console.log('  [OK] ' + label); } else { console.log('  [FAIL] ' + label); }
}

console.log('=======================================================');
console.log('  TASK-016: модель леса 3D Шежіре-Орман');
console.log('=======================================================\n');

check(typeof F.buildForestModel === 'function', 'buildForestModel экспортирован');
const H = F.helpers;
check(H.kkPlural('Алтыбаев') === 'Алтыбаевтар', 'kkPlural: Алтыбаев → Алтыбаевтар');
check(H.kkPlural('Қасым') === 'Қасымдар', 'kkPlural: Қасым → Қасымдар');
check(H.kkPlural('Әбілхайыр') === 'Әбілхайырлар', 'kkPlural: Әбілхайыр → Әбілхайырлар');
check(H.kkPlural('Тілеуберді') === 'Тілеуберділер', 'kkPlural: Тілеуберді → Тілеуберділер (жіңішке)');
check(H.kkDative('Алтыбаевтар') === 'Алтыбаевтарға', 'kkDative: Алтыбаевтарға');
check(H.ruPlural('Алтыбаев').gen === 'Алтыбаевых', 'ruPlural: Алтыбаевых');
check(H.surnameBase('Кубенова') === 'Кубенов', 'surnameBase: Кубенова → Кубенов');
check(H.TXT && H.TXT.ru && H.TXT.ru.introTree && H.TXT.ru.introTree.includes('дуб') && H.TXT.ru.introLeaf.includes('медальон'), 'Легенда ru содержит дуб, желуди и медальоны');
check(H.TXT && H.TXT.kk && H.TXT.kk.introTree && H.TXT.kk.introTree.includes('емен') && H.TXT.kk.introLeaf.includes('медальон'), 'Легенда kk содержит дубовую рощу (емен)');

// Синтетическое шежіре: два рода (А… и К…), дочь рода К замужем за сыном рода А,
// келін без отца в базе, одиночка-муж без родителей, брак внутри рода.
const persons = [
    { id: 1, first_name: 'Ата', last_name: 'Ақбаев', gender: 'M', father_id: null, is_deceased: 1, clan_id: 1 },
    { id: 2, first_name: 'Ұлы1', last_name: 'Ақбаев', gender: 'M', father_id: 1, is_deceased: 1, clan_id: 1, birth_year: '1950' },
    { id: 3, first_name: 'Ұлы2', last_name: 'Ақбаев', gender: 'M', father_id: 1, is_deceased: 0, clan_id: 1, birth_year: '1955' },
    { id: 4, first_name: 'Немере', last_name: 'Ақбаев', gender: 'M', father_id: 2, is_deceased: 0, clan_id: 1, birth_year: '1980' },
    { id: 5, first_name: 'Қызы', last_name: 'Ақбаева', gender: 'F', father_id: 2, is_deceased: 0, clan_id: 1, birth_year: '1982' },
    { id: 10, first_name: 'Бек', last_name: 'Кубенов', gender: 'M', father_id: null, is_deceased: 1 },
    { id: 11, first_name: 'Асила', last_name: 'Ақбаева', maiden_name: 'Кубенова', gender: 'F', father_id: 10, is_deceased: 0 },
    { id: 12, first_name: 'Келін', last_name: 'Ақбаева', gender: 'F', father_id: null, is_deceased: 0 },
    { id: 20, first_name: 'Күйеу', last_name: 'Сериков', gender: 'M', father_id: null, is_deceased: 0 }
];
const marriages = [
    { id: 1, husband_id: 4, wife_id: 11, is_current: 1 },   // дочь Кубеновых → келін Ақбаевых: ЛИНИЯ
    { id: 2, husband_id: 3, wife_id: 12, is_current: 1 },   // келін без отца → на дерево мужа, без линии
    { id: 3, husband_id: 20, wife_id: 5, is_current: 1 }    // дочь Ақбаевых → Сериковы: ЛИНИЯ
];
const clans = [{ id: 1, name: 'Керей', tamga: 'Ашамай (X)', tree_color: '#2d6a4f' }];

const mKk = F.buildForestModel({ persons, marriages, clans }, { lang: 'kk', userPersonId: 4 });
const tA = mKk.trees.find(t => t.rootId === 1);
const tK = mKk.trees.find(t => t.rootId === 10);
const tS = mKk.trees.find(t => t.rootId === 20);
check(mKk.trees.length === 3, 'Три дерева: Ақбаевтар, Кубеновтар, Сериковтер (получено ' + mKk.trees.length + ')');
check(tA && tA.name === 'Ақбаевтар', 'Имя рода по фамилии мужчин: Ақбаевтар');
check(tA && tA.clanName === 'Керей' && tA.tamga === 'Ашамай (X)', 'Ру и тамга берутся из таблицы clans');
check(tA && tA.members.indexOf(12) !== -1 && tA.kelins.indexOf(12) !== -1, 'Келін без отца в базе — на дереве мужа');
check(tA && tA.members.indexOf(5) !== -1, 'Дочь (выданная замуж) остаётся на дереве отца');
check(tK && tK.members.indexOf(11) !== -1, 'Асила — на дереве отца (Кубеновтар)');
check(mKk.myTreeKey === tA.key && tA.x === 0 && tA.z === 0, 'Мой род — в центре леса');

check(mKk.links.length === 2, 'Линий ровно 2 — только браки между разными родами (получено ' + mKk.links.length + ')');
const lAsila = mKk.links.find(l => l.wifeId === 11);
check(lAsila && lAsila.fromTree === tK.key && lAsila.toTree === tA.key, 'Линия Асилы: дерево Кубеновых → дерево Ақбаевых');
check(lAsila && lAsila.label === 'Асила Кубенова → Ақбаевтарға келін', 'Подпись kk: «Асила Кубенова → Ақбаевтарға келін» (получено «' + (lAsila && lAsila.label) + '»)');
check(!mKk.links.some(l => l.wifeId === 12), 'Для келін без отца линии нет');

const mRu = F.buildForestModel({ persons, marriages, clans }, { lang: 'ru', userPersonId: 4 });
const lRu = mRu.links.find(l => l.wifeId === 11);
check(lRu && lRu.label === 'Асила Кубенова → келін Ақбаевых', 'Подпись ru: «Асила Кубенова → келін Ақбаевых» (получено «' + (lRu && lRu.label) + '»)');

// поколения и раскладка
const p1 = mKk.positions.get(1), p2 = mKk.positions.get(2), p4 = mKk.positions.get(4);
check(p1 && p1.gen === 0 && p2.gen === 1 && p4.gen === 2, 'Поколения считаются от родоначальника');
check(p1.onTrunk === true, 'Родоначальник — на стволе');
check(p4.y > p2.y && p2.y > p1.y, 'Потомки выше предков');
check(mKk.positions.size === persons.length, 'Каждый человек получил место в лесу');
const dist = (a, b) => Math.hypot(a.x - b.x, a.z - b.z);
check(dist(tA, tK) > tA.R + tK.R - 0.01 && dist(tA, tS) > tA.R + tS.R - 0.01, 'Деревья не пересекаются');

// люди одного яруса не слипаются
let minGap = Infinity;
mKk.trees.forEach(t => {
    const byGen = {};
    t.local.forEach((p, id) => { if (!p.onTrunk) (byGen[p.gen] = byGen[p.gen] || []).push(p); });
    Object.values(byGen).forEach(row => {
        for (let i = 0; i < row.length; i++) for (let j = i + 1; j < row.length; j++) {
            minGap = Math.min(minGap, Math.hypot(row[i].x - row[j].x, row[i].z - row[j].z));
        }
    });
});
check(minGap >= 2.0, 'Соседи на одном ярусе не ближе 2.0 (мин. ' + (isFinite(minGap) ? minGap.toFixed(2) : '—') + ')');

// циклы в данных не вешают построение
const cyc = [
    { id: 1, first_name: 'A', gender: 'M', father_id: 2 },
    { id: 2, first_name: 'B', gender: 'M', father_id: 1 }
];
let ok = true;
try { F.buildForestModel({ persons: cyc, marriages: [] }, {}); } catch (e) { ok = false; }
check(ok, 'Цикл отец↔сын в данных не ломает построение');

check(F.buildForestModel({}, {}).trees.length === 0, 'Пустые данные — пустой лес без ошибок');

// большой лес: 400 человек, проверка скорости
const big = [];
let id = 1;
for (let r = 0; r < 20; r++) {
    const root = id++;
    big.push({ id: root, first_name: 'R' + r, last_name: 'Ру' + r + 'ов', gender: 'M', father_id: null });
    let gen = [root];
    for (let g = 0; g < 4; g++) {
        const next = [];
        gen.forEach(f => {
            for (let k = 0; k < 2 && id < 420; k++) {
                const cid = id++;
                big.push({ id: cid, first_name: 'P' + cid, last_name: 'Ру' + r + 'ов', gender: k ? 'F' : 'M', father_id: f });
                if (!k) next.push(cid);
            }
        });
        gen = next;
    }
}
const t0 = Date.now();
const mb = F.buildForestModel({ persons: big, marriages: [] }, {});
const ms = Date.now() - t0;
check(mb.positions.size === big.length && ms < 1000, `Лес из ${big.length} человек строится за ${ms} мс`);

console.log('\n-------------------------------------------------------');
console.log(`Результат: ${passed} из ${total}`);
if (passed !== total) { console.log('ЕСТЬ ОШИБКИ [FAILED]'); process.exit(1); }
console.log('ВСЕ ТЕСТЫ TASK-016 ПРОЙДЕНЫ [SUCCESS]');
