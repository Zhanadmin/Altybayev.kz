/**
 * ============================================================================
 * ШЕЖІРЕ-ОРМАН 3D — понятный лес родов (TASK-016, переделка TASK-006)
 * ============================================================================
 * Правила леса (их же видит человек в подсказке при входе):
 *  - одно дерево = один род по отцу: все потомки одного верхнего предка по мужской линии;
 *    у корней — табличка с названием рода, ру и тамгой, на стволе — тамга, над кроной — имя рода;
 *  - снизу вверх — поколения: на стволе стоят предки, пока род идёт одной линией,
 *    дальше ветви расходятся «отец → дети», каждый ярус — одно поколение;
 *  - люди — плоды на ветках: голубые (мужчины) и тюльпаны (женщины), золотые — ушедшие; у каждого подпись имени;
 *  - поляна (TASK-016, замечание 16.11): небо, трава с цветами, холмы; дерево — байтерек с корой, корнями и густой кроной;
 *    линии браков в покое тонкие и бледные, яркие — при наведении/выборе; подписи не налезают друг на друга;
 *  - келін, у которой в базе нет отца, растёт на дереве мужа рядом с ним (тонкая золотая связка);
 *  - линии между деревьями — ТОЛЬКО браки: дочь рода (тюльпан на дереве отца) → муж на дереве его рода.
 *    Подпись: «Асила Кубенова → келін Алтыбаевых».
 *
 * Модель леса (buildForestModel) — чистая функция без THREE, проверяется tests/Task016Forest3DModelTest.js.
 * Сцена: instanced-листья и ветви (мало вызовов отрисовки — плавно в Safari и Chrome), без теней,
 * перерисовка только когда что-то изменилось.
 */

(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        let T = null;
        try { T = require('three'); } catch (e) { T = null; }
        module.exports = factory(T);
    } else {
        root.ShezhireForest3D = factory(root.THREE);
    }
}(typeof self !== 'undefined' ? self : this, function (THREE) {
    'use strict';

    // =========================================================================
    // 0. ТЕКСТЫ И ИМЕНА РОДОВ
    // =========================================================================
    const TXT = {
        kk: {
            introTitle: 'Шежіре-Орман: қалай қарау керек',
            introTree: 'Бір ағаш — бір ру (әке жағымен). Ағаш түбінде — ру аты мен таңбасы.',
            introBranch: 'Төменде, діңде — ата-бабалар, жоғарыда — ұрпақтар. Бұтақ әкеден балаларына тарайды, әр қабат — бір буын.',
            introLeaf: 'Адамдар — бұтақтағы жемістер: көк жеміс — ер адам, қызғылт қызғалдақ — әйел, алтын түстілер — дүниеден өткендер. Әр адамның аты жазулы, жасыл жапырақ — жай ғана ағаштың жапырағы.',
            introLine: 'Ағаштар арасындағы сызық — тек неке: ұзатылған қыз әкесінің ағашынан күйеуінің ағашына. Сызыққа апарсаңыз — кім кімге келін болғаны жазылады.',
            introMouse: 'Тышқан: сүйреу — айналдыру, дөңгелек — жақындату, оң батырма — жылжыту',
            introKeys: 'Пернетақта: W A S D / бағыттауыштар — жүру, Q / E — бұрылу, + / − — жақындату',
            introTouch: 'Сенсор: бір саусақ — айналдыру, екі саусақ — жақындату және жылжыту',
            introClick: 'Адамға басыңыз — оның карточкасы мен үш жұрты ашылады.',
            btnMine: 'Менің руымды көрсету',
            btnAll: 'Бүкіл орман',
            btnGotIt: 'Түсінікті',
            btnHelp: 'Көмек',
            pickTree: 'Руға бару…',
            legendLeaf: 'ер адам (көк жеміс)',
            legendTulip: 'әйел (қызғалдақ)',
            legendGold: 'дүниеден өткен',
            legendLine: 'неке: қыз → күйеуінің руы',
            legendKelin: 'келін (күйеуінің ағашында)',
            you: 'Сіз',
            gen: (n) => `${n}-буын`,
            people: (n) => `${n} адам`,
            gens: (n) => `${n} буын`,
            clan: 'Ру',
            tamga: 'Таңба',
            tamgaUnknown: 'таңба белгісіз',
            kelinOf: (tree) => `келін — ${tree}`,
            kelinNoTree: 'келін (әкесі шежіреде жоқ)',
            daughterOf: (tree) => `${tree} қызы`,
            marriedTo: (treeDat) => `ұзатылған → ${treeDat}`,
            linkTitle: (wife, treeDat) => `${wife} → ${treeDat} келін`,
            linkSub: (birthTree, husband) => `${birthTree} қызы · күйеуі ${husband}`,
            linkKind: 'Неке байланысы',
            ushZhurt: 'Үш жұрт',
            ushFull: 'Толық үш жұрт',
            profile: 'Профиль',
            showOnTree: 'Ағашта көрсету',
            goTree: 'Ағашқа бару',
            showWife: 'Келінді көрсету',
            showHusband: 'Күйеуін көрсету',
            loading: 'Жүктелуде…',
            noData: 'Мәлімет жоқ',
            close: 'Жабу',
            noWebgl: 'Бұл браузерде 3D көрсету мүмкін емес (WebGL өшірулі). Шежіре-Ағаш режимін пайдаланыңыз.',
            empty: 'Шежіреде әзірге адам жоқ.',
            alive: 'тірі',
            deceased: 'дүниеден өткен',
            more: (n) => `тағы ${n}`,
            treeKind: 'Ру ағашы'
        },
        ru: {
            introTitle: 'Шежіре-Орман: как смотреть',
            introTree: 'Одно дерево — один род (по отцу). У корней — название рода и тамга.',
            introBranch: 'Внизу, на стволе — предки, вверху — потомки. Ветка идёт от отца к детям, каждый ярус — одно поколение.',
            introLeaf: 'Люди — плоды на ветках: голубой плод — мужчина, розовый тюльпан — женщина, золотые — ушедшие. У каждого подписано имя; зелёная листва — просто крона дерева.',
            introLine: 'Линия между деревьями — только брак: выданная замуж дочь идёт с дерева отца к дереву мужа. Наведите на линию — будет написано, чьей келін она стала.',
            introMouse: 'Мышь: тянуть — вращать, колесо — приблизить, правая кнопка — сдвинуть',
            introKeys: 'Клавиши: W A S D / стрелки — идти, Q / E — повернуть, + / − — приблизить',
            introTouch: 'Тач: один палец — вращать, два пальца — приблизить и сдвинуть',
            introClick: 'Нажмите на человека — откроется его карточка и үш жұрт.',
            btnMine: 'Показать мой род',
            btnAll: 'Весь лес',
            btnGotIt: 'Понятно',
            btnHelp: 'Помощь',
            pickTree: 'Перейти к роду…',
            legendLeaf: 'мужчина (голубой плод)',
            legendTulip: 'женщина (тюльпан)',
            legendGold: 'ушедшие',
            legendLine: 'брак: дочь → род мужа',
            legendKelin: 'келін (на дереве мужа)',
            you: 'Вы',
            gen: (n) => `${n}-е поколение`,
            people: (n) => `${n} чел.`,
            gens: (n) => `поколений: ${n}`,
            clan: 'Ру',
            tamga: 'Тамга',
            tamgaUnknown: 'тамга не указана',
            kelinOf: (tree) => `келін — ${tree}`,
            kelinNoTree: 'келін (её отца нет в шежіре)',
            daughterOf: (tree) => `дочь: ${tree}`,
            marriedTo: (treeGen) => `выдана замуж → ${treeGen}`,
            linkTitle: (wife, treeGen) => `${wife} → келін ${treeGen}`,
            linkSub: (birthTreeGen, husband) => `дочь ${birthTreeGen} · муж — ${husband}`,
            linkKind: 'Брак',
            ushZhurt: 'Үш жұрт',
            ushFull: 'Полный үш жұрт',
            profile: 'Профиль',
            showOnTree: 'Показать на дереве',
            goTree: 'Перейти к дереву',
            showWife: 'Показать келін',
            showHusband: 'Показать мужа',
            loading: 'Загрузка…',
            noData: 'Нет данных',
            close: 'Закрыть',
            noWebgl: 'В этом браузере 3D недоступно (выключен WebGL). Используйте режим Шежіре-Ағаш.',
            empty: 'В шежіре пока нет людей.',
            alive: 'жив(а)',
            deceased: 'ушёл(ушла) из жизни',
            more: (n) => `ещё ${n}`,
            treeKind: 'Дерево рода'
        }
    };

    const KK_VOWELS = 'аәеоөұүыіиуюяэё';
    const KK_BACK = 'аоұыуяё';

    // Казахское множественное: Алтыбаев → Алтыбаевтар, Қасым → Қасымдар, Әли → Әлилер
    function kkPlural(base) {
        const s = String(base || '').trim();
        if (!s) return '';
        const low = s.toLowerCase();
        // русский суффикс фамилии (-ов/-ев/-ин) гармонию не задаёт: Алтыбаев → по «Алтыба-» → -тар
        const stem = low.replace(/(ов|ев|ёв|ин|ын)$/, '') || low;
        let back = true;
        for (let i = stem.length - 1; i >= 0; i--) {
            const ch = stem[i];
            if (KK_VOWELS.indexOf(ch) !== -1) { back = KK_BACK.indexOf(ch) !== -1; break; }
        }
        const last = low[low.length - 1];
        let cons;
        if (KK_VOWELS.indexOf(last) !== -1 || 'рй'.indexOf(last) !== -1) cons = 'л';
        else if ('лмнңзж'.indexOf(last) !== -1) cons = 'д';
        else cons = 'т';
        return s + cons + (back ? 'ар' : 'ер');
    }

    // Алтыбаевтар → Алтыбаевтарға
    function kkDative(plural) {
        const s = String(plural || '');
        if (/ар$/.test(s)) return s + 'ға';
        if (/ер$/.test(s)) return s + 'ге';
        return s + 'ға';
    }

    // Алтыбаев → { nom: 'Алтыбаевы', gen: 'Алтыбаевых' }
    function ruPlural(base) {
        const s = String(base || '').trim();
        if (!s) return { nom: '', gen: '' };
        if (/(ов|ев|ёв|ин|ын)$/i.test(s)) return { nom: s + 'ы', gen: s + 'ых' };
        return { nom: 'род ' + s, gen: 'рода ' + s };
    }

    // Женская форма фамилии → мужская основа: Кубенова → Кубенов
    function surnameBase(name) {
        const s = String(name || '').trim();
        if (/(ова|ева|ёва|ина|ына)$/i.test(s)) return s.slice(0, -1);
        return s;
    }

    function personName(p) {
        if (!p) return '';
        return [p.first_name, p.last_name].filter(Boolean).join(' ').trim();
    }

    // Имя дочери рода для подписи линии: девичья фамилия, если есть
    function birthName(p) {
        if (!p) return '';
        return [p.first_name, p.maiden_name || p.last_name].filter(Boolean).join(' ').trim();
    }

    function yearsText(p) {
        if (!p) return '';
        const b = p.birth_year || (p.birth_date ? String(p.birth_date).slice(0, 4) : '');
        const d = p.death_year || (p.death_date ? String(p.death_date).slice(0, 4) : '');
        if (Number(p.is_deceased)) return (b || '?') + '–' + (d || '?');
        return b ? String(b) : '';
    }

    function hashColor(str) {
        const palette = ['#5f8a34', '#7a6a2c', '#4f7d5b', '#8a5f12', '#6b4a2b', '#5b7a3a', '#7d5a3c', '#3f6f4a'];
        let h = 0;
        const s = String(str || '');
        for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0;
        return palette[h % palette.length];
    }

    function hash(n) {
        let x = (Math.floor(Math.abs(n) * 1000) * 2654435761) >>> 0;
        x = (x ^ (x >>> 15)) >>> 0;
        return (x % 1000) / 1000;
    }

    // =========================================================================
    // 1. МОДЕЛЬ ЛЕСА (чистая функция, без THREE)
    // =========================================================================
    const GAP = 2.5;        // минимальное расстояние между соседями на ярусе
    const LEVEL_H = 3.4;    // высота одного поколения
    const Y0 = 4.0;         // высота родоначальника над землёй (выше таблички рода у корней)
    const TREE_GAP = 7;     // зазор между деревьями

    function buildForestModel(data, opts) {
        opts = opts || {};
        const lang = opts.lang === 'ru' ? 'ru' : 'kk';
        const t = TXT[lang];
        const persons = (data && Array.isArray(data.persons)) ? data.persons : [];
        const marriages = (data && Array.isArray(data.marriages)) ? data.marriages : [];
        const clans = (data && Array.isArray(data.clans)) ? data.clans : [];
        const clanById = new Map();
        clans.forEach(c => clanById.set(Number(c.id), c));

        const byId = new Map();
        persons.forEach(p => { const id = Number(p.id); if (id) byId.set(id, p); });

        const fatherOf = (id) => {
            const p = byId.get(id);
            const f = p && p.father_id ? Number(p.father_id) : 0;
            return (f && f !== id && byId.has(f)) ? f : 0;
        };

        // браки
        const husbandsOf = new Map();
        const wivesOf = new Map();
        const marriageList = [];
        marriages.forEach(m => {
            const h = Number(m.husband_id), w = Number(m.wife_id);
            if (!byId.has(h) || !byId.has(w) || h === w) return;
            if (!husbandsOf.has(w)) husbandsOf.set(w, []);
            if (!wivesOf.has(h)) wivesOf.set(h, []);
            husbandsOf.get(w).push(h);
            wivesOf.get(h).push(w);
            marriageList.push({ h, w, current: m.is_current === undefined ? true : !!Number(m.is_current) });
        });

        // корень мужской линии
        const rootCache = new Map();
        function rootOf(id) {
            if (rootCache.has(id)) return rootCache.get(id);
            const path = [];
            const seen = new Set();
            let cur = id;
            while (true) {
                if (rootCache.has(cur)) { cur = rootCache.get(cur); break; }
                seen.add(cur);
                path.push(cur);
                const f = fatherOf(cur);
                if (!f || seen.has(f)) break;
                cur = f;
            }
            path.forEach(x => rootCache.set(x, cur));
            return cur;
        }

        // келін без отца в базе → на дерево мужа
        const attachedTo = new Map();
        persons.forEach(p => {
            const id = Number(p.id);
            if (!byId.has(id)) return;
            const hs = husbandsOf.get(id);
            if (p.gender === 'F' && !fatherOf(id) && hs && hs.length) {
                const hasOwnBranch = persons.some(x => Number(x.father_id) === id);
                if (!hasOwnBranch) attachedTo.set(id, hs[0]);
            }
        });

        const treesByRoot = new Map();
        const personInfo = new Map(); // id → { treeKey, role, gen, husband }
        function getTree(rootId) {
            if (!treesByRoot.has(rootId)) {
                treesByRoot.set(rootId, { key: 'r' + rootId, rootId, members: [], blood: [], kelins: [] });
            }
            return treesByRoot.get(rootId);
        }
        persons.forEach(p => {
            const id = Number(p.id);
            if (!byId.has(id)) return;
            if (attachedTo.has(id)) {
                const tr = getTree(rootOf(attachedTo.get(id)));
                tr.members.push(id); tr.kelins.push(id);
                personInfo.set(id, { treeKey: tr.key, role: 'kelin', husband: attachedTo.get(id) });
            } else {
                const tr = getTree(rootOf(id));
                tr.members.push(id); tr.blood.push(id);
                personInfo.set(id, { treeKey: tr.key, role: 'blood' });
            }
        });

        // дети по отцу (только кровные этого же дерева)
        const childrenOf = new Map();
        persons.forEach(p => {
            const id = Number(p.id);
            const f = fatherOf(id);
            if (!f || !personInfo.has(id) || personInfo.get(id).role !== 'blood') return;
            if (!childrenOf.has(f)) childrenOf.set(f, []);
            childrenOf.get(f).push(id);
        });
        const yearNum = (id) => {
            const p = byId.get(id);
            const y = parseInt(String((p && (p.birth_year || p.birth_date)) || '').replace(/[^0-9]/g, '').slice(0, 4), 10);
            return isNaN(y) ? 99999 : y;
        };
        childrenOf.forEach(list => list.sort((a, b) => (yearNum(a) - yearNum(b)) || (a - b)));
        const kelinsOfHusband = new Map();
        attachedTo.forEach((h, w) => {
            if (!kelinsOfHusband.has(h)) kelinsOfHusband.set(h, []);
            kelinsOfHusband.get(h).push(w);
        });

        const trees = [];
        treesByRoot.forEach(tr => {
            const rootP = byId.get(tr.rootId);
            // имя рода — самая частая фамилия у мужчин рода, иначе у любого кровного
            const count = (filterFn) => {
                const m = new Map();
                tr.blood.forEach(id => {
                    const p = byId.get(id);
                    if (!filterFn(p)) return;
                    const ln = surnameBase(p.last_name);
                    if (ln) m.set(ln, (m.get(ln) || 0) + 1);
                });
                let best = '', bc = 0;
                m.forEach((c, k) => { if (c > bc) { best = k; bc = c; } });
                return best;
            };
            let base = count(p => p.gender === 'M') || count(() => true);
            let nameKk, nameRu, nameRuGen, nameKkDat;
            if (base) {
                nameKk = kkPlural(base);
                nameKkDat = kkDative(nameKk);
                const r = ruPlural(base);
                nameRu = r.nom; nameRuGen = r.gen;
            } else {
                const fn = (rootP && rootP.first_name) || '?';
                nameKk = fn + ' ұрпақтары';
                nameKkDat = fn + ' ұрпақтарына';
                nameRu = 'потомки ' + fn;
                nameRuGen = 'потомков ' + fn;
            }
            // ру и тамга
            const clanCount = new Map();
            const clanRuCount = new Map();
            tr.blood.forEach(id => {
                const p = byId.get(id);
                const cid = Number(p.clan_id || 0);
                if (cid && clanById.has(cid)) clanCount.set(cid, (clanCount.get(cid) || 0) + 1);
                const cr = String(p.clan_ru || '').trim();
                if (cr && cr !== 'белгісіз') clanRuCount.set(cr, (clanRuCount.get(cr) || 0) + 1);
            });
            let clan = null, bestC = 0;
            clanCount.forEach((c, k) => { if (c > bestC) { clan = clanById.get(k); bestC = c; } });
            let clanName = clan ? clan.name : '';
            if (!clanName) {
                let b = 0;
                clanRuCount.forEach((c, k) => { if (c > b) { clanName = k; b = c; } });
            }
            tr.name = lang === 'ru' ? nameRu : nameKk;
            tr.nameKk = nameKk; tr.nameKkDat = nameKkDat;
            tr.nameRu = nameRu; tr.nameRuGen = nameRuGen;
            tr.clanName = clanName || '';
            tr.tamga = clan && clan.tamga ? String(clan.tamga) : '';
            tr.uran = clan && clan.uran ? String(clan.uran) : '';
            tr.color = (clan && clan.tree_color) ? String(clan.tree_color) : hashColor(base || tr.rootId);
            layoutTree(tr);
            trees.push(tr);
        });

        // --- раскладка одного дерева: ярусы-поколения, сектор по числу потомков ---
        function layoutTree(tr) {
            const slots = [];      // по поколениям
            const visited = new Set();
            const weight = new Map();
            function w(id) {
                if (weight.has(id)) return weight.get(id);
                weight.set(id, 1); // защита от циклов
                const kids = childrenOf.get(id) || [];
                let sum = 0;
                kids.forEach(c => { sum += w(c); });
                const own = 1 + (kelinsOfHusband.get(id) || []).length;
                const val = Math.max(own, sum);
                weight.set(id, val);
                return val;
            }
            w(tr.rootId);
            function place(id, g, a0, a1) {
                if (visited.has(id)) return;
                visited.add(id);
                if (!slots[g]) slots[g] = [];
                const mid = (a0 + a1) / 2;
                slots[g].push({ id, role: 'blood', a: mid, father: fatherOf(id) });
                (kelinsOfHusband.get(id) || []).forEach((k, j) => {
                    slots[g].push({ id: k, role: 'kelin', a: mid + 0.001 * (j + 1), husband: id });
                });
                const kids = (childrenOf.get(id) || []).filter(c => !visited.has(c));
                const total = kids.reduce((s, c) => s + (weight.get(c) || 1), 0) || 1;
                let cur = a0;
                kids.forEach(c => {
                    const span = (a1 - a0) * (weight.get(c) || 1) / total;
                    place(c, g + 1, cur, cur + span);
                    cur += span;
                });
            }
            place(tr.rootId, 0, 0, Math.PI * 2);
            // кто не попал (циклы в данных) — к корню
            tr.blood.concat(tr.kelins).forEach(id => {
                if (visited.has(id)) return;
                const inSlots = slots.some(row => row && row.some(s => s.id === id));
                if (inSlots) return;
                visited.add(id);
                slots[0].push({ id, role: personInfo.get(id).role, a: (id * 2.399) % (Math.PI * 2) });
            });

            // радиусы ярусов: пока род идёт одной линией — люди на стволе
            const radii = [];
            let r = 0, branched = false, trunkTopGen = 0;
            for (let g = 0; g < slots.length; g++) {
                const row = slots[g] || [];
                const bloodCount = row.filter(s => s.role === 'blood').length;
                if (!branched && bloodCount <= 1) {
                    radii[g] = 0; trunkTopGen = g;
                } else {
                    branched = true;
                    const need = row.length * GAP / (Math.PI * 2);
                    r = Math.max(r + 2.0, need, 3.0);
                    radii[g] = r;
                }
            }
            const nBlood = tr.blood.length;
            const trunkR0 = Math.min(1.6, 0.35 + 0.11 * Math.sqrt(nBlood));
            tr.generations = slots.length;
            tr.trunkTopY = Y0 + trunkTopGen * LEVEL_H + (branched ? 1.2 : 0.8);
            tr.crownR = Math.max(radii.length ? Math.max.apply(null, radii) : 0, trunkR0 + 1.5) + 1.2;
            // мощный ствол байтерека: толщина по размеру кроны, а не тонкий столб
            // и по высоте ствола: длинная линия предков — значит, старое толстое дерево
            const trunkR = Math.max(0.9, trunkR0, Math.min(4.5, Math.max(tr.crownR * 0.24, tr.trunkTopY * 0.1)));
            tr.trunkR = trunkR;
            // ярусы ветвей не ближе к оси, чем кора толстого ствола (+ место под человека)
            const minRing = trunkR * 1.3 + 1.6;
            const firstRing = radii.filter(x => x > 0).reduce((m, x) => Math.min(m, x), Infinity);
            if (firstRing < minRing) {
                const shift = minRing - firstRing;
                for (let g = 0; g < radii.length; g++) if (radii[g] > 0) radii[g] += shift;
                tr.crownR = Math.max.apply(null, radii) + 1.2;
            }
            tr.height = Y0 + (slots.length - 1) * LEVEL_H + 2.2;
            // ствол уходит в крону и там сужается — не «столб с зонтиком»
            const topRowY = Y0 + (slots.length - 1) * LEVEL_H;
            tr.trunkH = Math.max(tr.trunkTopY + 1.5, tr.trunkTopY + (topRowY - tr.trunkTopY) * 0.7);
            // расстояние от оси до коры на высоте y (по профилю ствола) + место под человека
            const barkZ = (y, pad) => trunkR * trunkFactor(Math.min(1, y / tr.trunkH)) * 1.12 + pad;
            // место дерева на поляне: крона или корни с табличкой — что шире
            tr.R = Math.max(Math.max(tr.crownR, 3.2) + 1.6, trunkR * 2.6 + 2.5);
            tr.local = new Map(); // id → {x,y,z, gen, onTrunk}
            for (let g = 0; g < slots.length; g++) {
                const row = slots[g] || [];
                const y = Y0 + g * LEVEL_H;
                if (radii[g] === 0) {
                    // на стволе: кровный — спереди ствола, келін — рядом сбоку
                    let ki = 0;
                    row.forEach(s => {
                        if (s.role === 'blood') {
                            // ствол с корой бугристый (до ×1.25) — человек висит перед корой, не в ней
                            tr.local.set(s.id, { x: 0, y, z: barkZ(y, 0.75), gen: g, onTrunk: true, role: s.role });
                        } else {
                            const ang = Math.PI / 2 - 0.75 - ki * 0.6;
                            ki++;
                            const rr = barkZ(y, 2.1);
                            tr.local.set(s.id, { x: rr * Math.cos(ang), y, z: rr * Math.sin(ang), gen: g, onTrunk: false, role: s.role, husband: s.husband });
                        }
                    });
                    continue;
                }
                // разводим соседей по кругу, порядок сохраняем
                row.sort((p, q) => p.a - q.a);
                const minA = GAP / radii[g];
                const n = row.length;
                if (n > 1) {
                    for (let it = 0; it < 60; it++) {
                        let moved = false;
                        for (let i = 0; i < n; i++) {
                            const j = (i + 1) % n;
                            let d = row[j].a - row[i].a;
                            if (j === 0) d += Math.PI * 2;
                            if (d < minA - 1e-6) {
                                const push = (minA - d) / 2;
                                row[i].a -= push; row[j].a += push;
                                moved = true;
                            }
                        }
                        if (!moved) break;
                    }
                }
                row.forEach(s => {
                    // поворот всей кроны: стык первого и последнего секторов — сзади дерева (локальная −Z)
                    const a = s.a - Math.PI / 2;
                    tr.local.set(s.id, {
                        x: radii[g] * Math.cos(a), y, z: radii[g] * Math.sin(a),
                        gen: g, onTrunk: false, role: s.role, husband: s.husband
                    });
                });
            }
        }

        // --- какой род «мой» ---
        const myId = Number(opts.userPersonId || (data && data.user_person_id) || 0);
        let myTree = null;
        if (myId && personInfo.has(myId)) {
            const key = personInfo.get(myId).treeKey;
            myTree = trees.find(x => x.key === key) || null;
        }
        if (!myTree && trees.length) {
            myTree = trees.slice().sort((a, b) => b.members.length - a.members.length)[0];
        }

        // --- линии: только браки «дочь рода → дерево мужа» ---
        const treeByKey = new Map();
        trees.forEach(x => treeByKey.set(x.key, x));
        const links = [];
        marriageList.forEach(m => {
            const wi = personInfo.get(m.w), hi = personInfo.get(m.h);
            if (!wi || !hi) return;
            if (wi.role !== 'blood') return;           // келін без отца — уже на дереве мужа
            if (wi.treeKey === hi.treeKey) return;      // брак внутри одного рода — без линии
            const wifeTree = treeByKey.get(wi.treeKey), husTree = treeByKey.get(hi.treeKey);
            const wife = byId.get(m.w), hus = byId.get(m.h);
            const toName = lang === 'ru' ? husTree.nameRuGen : husTree.nameKkDat;
            const fromName = lang === 'ru' ? wifeTree.nameRuGen : wifeTree.nameKk;
            links.push({
                key: m.w + '-' + m.h,
                wifeId: m.w, husbandId: m.h,
                fromTree: wifeTree.key, toTree: husTree.key,
                current: m.current,
                label: t.linkTitle(birthName(wife), toName),
                sub: t.linkSub(fromName, personName(hus))
            });
        });

        // --- расстановка деревьев: мой род в центре, связанные с ним — ближе ---
        const linkCount = new Map();
        if (myTree) {
            links.forEach(l => {
                if (l.fromTree === myTree.key) linkCount.set(l.toTree, (linkCount.get(l.toTree) || 0) + 1);
                if (l.toTree === myTree.key) linkCount.set(l.fromTree, (linkCount.get(l.fromTree) || 0) + 1);
            });
        }
        const others = trees.filter(x => x !== myTree).sort((a, b) =>
            ((linkCount.get(b.key) || 0) - (linkCount.get(a.key) || 0)) ||
            (b.members.length - a.members.length) ||
            String(a.name).localeCompare(String(b.name)));
        if (myTree) { myTree.x = 0; myTree.z = 0; myTree.rotY = 0; myTree.isMine = true; }
        let prevOuter = myTree ? myTree.R : 0;
        let idx = 0;
        while (idx < others.length) {
            let maxR = 0;
            for (let k = idx; k < others.length; k++) maxR = Math.max(maxR, others[k].R);
            const ringR = prevOuter + TREE_GAP + maxR;
            let cursor = 0;
            const ringTrees = [];
            while (idx < others.length) {
                const tr = others[idx];
                const need = 2 * Math.asin(Math.min(1, (tr.R + TREE_GAP / 2) / ringR));
                if (ringTrees.length && cursor + need > Math.PI * 2 - 1e-6) break;
                ringTrees.push({ tr, need, start: cursor });
                cursor += need;
                idx++;
            }
            // кольцо равномерно, начиная «за» моим деревом (камера стоит спереди, на +Z)
            const spare = Math.max(0, Math.PI * 2 - cursor) / Math.max(1, ringTrees.length);
            let acc = 0;
            ringTrees.forEach(rt => {
                const ang = -Math.PI / 2 + acc + rt.need / 2;
                acc += rt.need + spare;
                rt.tr.x = ringR * Math.cos(ang);
                rt.tr.z = ringR * Math.sin(ang);
                rt.tr.rotY = Math.atan2(-rt.tr.x, -rt.tr.z); // фронт к центру леса
            });
            prevOuter = ringR + maxR;
        }
        const worldR = Math.max(prevOuter, 20);

        // мировые координаты людей
        const positions = new Map();
        trees.forEach(tr => {
            const c = Math.cos(tr.rotY), s = Math.sin(tr.rotY);
            tr.local.forEach((p, id) => {
                positions.set(id, {
                    x: tr.x + p.x * c + p.z * s,
                    y: p.y,
                    z: tr.z - p.x * s + p.z * c,
                    gen: p.gen, onTrunk: p.onTrunk, treeKey: tr.key
                });
            });
        });

        // подписи-роли у людей
        const roleText = new Map();
        personInfo.forEach((info, id) => {
            const tr = treeByKey.get(info.treeKey);
            if (info.role === 'kelin') {
                roleText.set(id, lang === 'ru' ? 'келін' : 'келін');
            } else {
                const p = byId.get(id);
                if (p.gender === 'F') {
                    const hs = (husbandsOf.get(id) || []).map(h => personInfo.get(h)).filter(Boolean);
                    const out = hs.find(h => h.treeKey !== info.treeKey);
                    if (out) {
                        const ht = treeByKey.get(out.treeKey);
                        roleText.set(id, '→ ' + (lang === 'ru' ? ht.nameRu : ht.nameKk));
                    }
                }
            }
            if (tr && myId === id) roleText.set(id, t.you);
        });

        return {
            lang, trees, links, positions, personInfo, roleText,
            myTreeKey: myTree ? myTree.key : null,
            myPersonId: myId && personInfo.has(myId) ? myId : 0,
            worldR, byId, treeByKey, husbandsOf, wivesOf
        };
    }

    // Если THREE нет (node-тесты) — отдаём только модель
    if (!THREE) {
        const Stub = function () { throw new Error('THREE is required for ShezhireForest3D'); };
        Stub.buildForestModel = buildForestModel;
        Stub.helpers = { kkPlural, kkDative, ruPlural, surnameBase, personName, birthName, TXT };
        return Stub;
    }

    // =========================================================================
    // 2. ТЕМА, ТЕКСТУРЫ, ПОДПИСИ
    // =========================================================================
    function isDarkTheme() {
        const attr = document.documentElement.getAttribute('data-theme');
        if (attr === 'dark') return true;
        if (attr === 'light') return false;
        return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    }

    // Поляна: днём — голубое небо, тёплый горизонт, сочная трава; ночью — синее небо со звёздами, луна.
    // Люди: мужчина — голубой плод, женщина — розовый тюльпан, ушедшие — золотые (не сливаются с листвой).
    function palette(dark) {
        return dark ? {
            skyTop: '#0b1428', skyHorizon: '#2a2830', fog: '#2a2830', sunGlow: '#dfe6ff',
            grass: '#2f4b26', grassLight: '#43653a', grassDark: '#1f3419', hill: '#26402a', farTree: '#18291a',
            flowers: ['#d9d3e6', '#c9b46a', '#8d86c9', '#a9c7e0'],
            bark: '#6e4e33', barkDark: '#3a2616', barkLight: '#94704c',
            leaf: '#3f6b2e', leafDark: '#24421b', leafLight: '#5e8d45',
            man: '#74b4ec', manGold: '#e2bd62', tulip: '#f07d94', tulipGold: '#e3b27f', halo: '#fff4dc',
            link: '#e79aaa', linkHi: '#ffb3c2', kelinTie: '#ddb65e', orn: '#ddb65e', mine: '#f1cf7c',
            shadow: 'rgba(0,0,0,0.55)',
            labelBg: 'rgba(36,29,20,0.90)', labelFg: '#f6ead0', labelSub: '#d6c29b', labelBorder: 'rgba(221,182,94,0.6)',
            hemiSky: '#8ea2d6', hemiGround: '#24331d', hemiI: 0.85, sunColor: '#c9d6ff', sunI: 0.75, stars: true
        } : {
            skyTop: '#6fa8dc', skyHorizon: '#f1ead4', fog: '#f1ead4', sunGlow: '#fff3c4',
            grass: '#7cab4c', grassLight: '#9cc463', grassDark: '#5a8a36', hill: '#86b062', farTree: '#4f7d3a',
            flowers: ['#ffffff', '#f4d03f', '#a78bd8', '#f3f0c8', '#8fc3f0'],
            bark: '#6b4a2b', barkDark: '#45301b', barkLight: '#9a714a',
            leaf: '#4f8a2e', leafDark: '#2f5e1d', leafLight: '#7fb348',
            man: '#2f7fd0', manGold: '#c99a2e', tulip: '#d9435f', tulipGold: '#cf9a6c', halo: '#fffbe8',
            link: '#c96479', linkHi: '#d8264d', kelinTie: '#b8862a', orn: '#b8862a', mine: '#b8862a',
            shadow: 'rgba(30,45,15,0.45)',
            labelBg: 'rgba(255,251,242,0.94)', labelFg: '#3b2914', labelSub: '#6b4c2a', labelBorder: 'rgba(184,134,42,0.6)',
            hemiSky: '#e8f2ff', hemiGround: '#7b8f4a', hemiI: 1.15, sunColor: '#fff1d2', sunI: 1.25, stars: false
        };
    }

    function bookFont() {
        try {
            const v = getComputedStyle(document.documentElement).getPropertyValue('--font-book');
            if (v && v.trim()) return v.trim();
        } catch (e) { /* нет стилей */ }
        return 'Georgia, "Times New Roman", serif';
    }

    function canvasTexture(canvas) {
        const tex = new THREE.CanvasTexture(canvas);
        tex.generateMipmaps = false;
        tex.minFilter = THREE.LinearFilter;
        tex.magFilter = THREE.LinearFilter;
        if ('colorSpace' in tex && THREE.SRGBColorSpace) tex.colorSpace = THREE.SRGBColorSpace;
        else if ('encoding' in tex && THREE.sRGBEncoding) tex.encoding = THREE.sRGBEncoding;
        return tex;
    }

    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + w - r, y); ctx.quadraticCurveTo(x + w, y, x + w, y + r);
        ctx.lineTo(x + w, y + h - r); ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
        ctx.lineTo(x + r, y + h); ctx.quadraticCurveTo(x, y + h, x, y + h - r);
        ctx.lineTo(x, y + r); ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.closePath();
    }

    // Подпись-«ленточка» над человеком или над деревом
    function makeLabel(line1, line2, pal, o) {
        o = o || {};
        const font = bookFont();
        const fs1 = o.big ? 56 : 30;
        const fs2 = o.big ? 30 : 22;
        const padX = o.big ? 28 : 14;
        const c = document.createElement('canvas');
        const ctx = c.getContext('2d');
        const f1 = `${o.big ? '700' : '600'} ${fs1}px ${font}`;
        const f2 = `400 ${fs2}px ${font}`;
        ctx.font = f1;
        const w1 = ctx.measureText(line1 || '').width;
        ctx.font = f2;
        const w2 = line2 ? ctx.measureText(line2).width : 0;
        const w = Math.ceil(Math.max(w1, w2) + padX * 2);
        const h = Math.ceil(fs1 * 1.35 + (line2 ? fs2 * 1.3 : 0) + (o.big ? 18 : 10));
        c.width = Math.min(2048, w); c.height = h;
        ctx.font = f1;
        roundRect(ctx, 2, 2, c.width - 4, h - 4, o.big ? 18 : 12);
        ctx.fillStyle = o.bg || pal.labelBg;
        ctx.fill();
        ctx.lineWidth = o.highlight ? 4 : 2;
        ctx.strokeStyle = o.highlight ? pal.mine : pal.labelBorder;
        ctx.stroke();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        ctx.fillStyle = o.fg || pal.labelFg;
        ctx.fillText(line1 || '', c.width / 2, (o.big ? 12 : 6));
        if (line2) {
            ctx.font = f2;
            ctx.fillStyle = o.subColor || pal.labelSub;
            ctx.fillText(line2, c.width / 2, (o.big ? 12 : 6) + fs1 * 1.3);
        }
        const tex = canvasTexture(c);
        const mat = new THREE.SpriteMaterial({ map: tex, transparent: true, depthWrite: false });
        const sp = new THREE.Sprite(mat);
        const ppu = o.ppu || 95; // пикселей на единицу мира
        sp.scale.set(c.width / ppu, c.height / ppu, 1);
        sp.center.set(0.5, 0);
        sp.renderOrder = 10;
        return sp;
    }

    // Тамга: символ из скобок «Ашамай (X)» крупно, иначе общий знак
    function drawTamga(ctx, cx, cy, r, tamgaText, color) {
        ctx.save();
        ctx.strokeStyle = color; ctx.fillStyle = color;
        ctx.lineWidth = Math.max(2, r * 0.07);
        ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.stroke();
        ctx.lineWidth = Math.max(1, r * 0.03);
        ctx.beginPath(); ctx.arc(cx, cy, r * 0.86, 0, Math.PI * 2); ctx.stroke();
        const m = /\(([^)]{1,4})\)/.exec(tamgaText || '');
        if (m) {
            ctx.font = `700 ${Math.round(r * 0.95)}px Georgia, serif`;
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillText(m[1], cx, cy + r * 0.04);
        } else {
            // общий знак тамги: вертикаль + дуга + основание
            ctx.lineWidth = Math.max(2, r * 0.1);
            ctx.lineCap = 'round';
            ctx.beginPath(); ctx.moveTo(cx, cy - r * 0.55); ctx.lineTo(cx, cy + r * 0.55); ctx.stroke();
            ctx.beginPath(); ctx.arc(cx, cy - r * 0.2, r * 0.38, 0.1 * Math.PI, 0.9 * Math.PI); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(cx - r * 0.3, cy + r * 0.55); ctx.lineTo(cx + r * 0.3, cy + r * 0.55); ctx.stroke();
        }
        ctx.restore();
    }

    // Табличка рода у корней
    function makeBoard(tree, pal, t) {
        const c = document.createElement('canvas');
        c.width = 640; c.height = 300;
        const ctx = c.getContext('2d');
        const font = bookFont();
        roundRect(ctx, 4, 4, 632, 292, 22);
        ctx.fillStyle = pal.labelBg; ctx.fill();
        ctx.lineWidth = 3; ctx.strokeStyle = pal.orn; ctx.stroke();
        ctx.lineWidth = 1; roundRect(ctx, 14, 14, 612, 272, 16); ctx.stroke();
        drawOrnamentLine(ctx, 40, 268, 560, pal.orn);
        drawTamga(ctx, 120, 135, 78, tree.tamga, pal.orn);
        ctx.fillStyle = pal.labelFg;
        ctx.textAlign = 'left'; ctx.textBaseline = 'top';
        let fs = 52;
        ctx.font = `700 ${fs}px ${font}`;
        while (ctx.measureText(tree.name).width > 400 && fs > 26) { fs -= 2; ctx.font = `700 ${fs}px ${font}`; }
        ctx.fillText(tree.name, 220, 52);
        ctx.font = `400 28px ${font}`;
        ctx.fillStyle = pal.labelSub;
        const clanLine = tree.clanName ? `${t.clan}: ${tree.clanName}` : '';
        const tamgaLine = tree.tamga ? `${t.tamga}: ${tree.tamga}` : t.tamgaUnknown;
        if (clanLine) ctx.fillText(clanLine, 220, 120);
        ctx.fillText(tamgaLine, 220, clanLine ? 158 : 120);
        ctx.fillText(`${t.people(tree.members.length)} · ${t.gens(tree.generations)}`, 220, clanLine ? 196 : 158);
        return canvasTexture(c);
    }

    // Тонкая линия ою-өрнек (қошқар мүйіз) на холсте
    function drawOrnamentLine(ctx, x, y, w, color) {
        ctx.save();
        ctx.strokeStyle = color; ctx.lineWidth = 1.5; ctx.lineCap = 'round';
        const step = 28;
        for (let px = x; px + step <= x + w; px += step) {
            const m = px + step / 2;
            ctx.beginPath();
            ctx.moveTo(px, y);
            ctx.bezierCurveTo(px + 4, y - 9, m - 6, y - 10, m - 3, y - 5);
            ctx.bezierCurveTo(m - 1, y - 2, m - 6, y - 1, m - 6, y - 4);
            ctx.moveTo(px + step, y);
            ctx.bezierCurveTo(px + step - 4, y - 9, m + 6, y - 10, m + 3, y - 5);
            ctx.bezierCurveTo(m + 1, y - 2, m + 6, y - 1, m + 6, y - 4);
            ctx.stroke();
        }
        ctx.beginPath(); ctx.moveTo(x, y + 4); ctx.lineTo(x + w, y + 4); ctx.stroke();
        ctx.restore();
    }

    // Кольцо ою-өрнек на земле вокруг дерева
    function makeOrnamentRingTexture(color) {
        const c = document.createElement('canvas');
        c.width = c.height = 512;
        const ctx = c.getContext('2d');
        const cx = 256, cy = 256;
        ctx.strokeStyle = color; ctx.lineCap = 'round';
        ctx.lineWidth = 2;
        ctx.beginPath(); ctx.arc(cx, cy, 236, 0, Math.PI * 2); ctx.stroke();
        ctx.lineWidth = 1.2;
        ctx.beginPath(); ctx.arc(cx, cy, 206, 0, Math.PI * 2); ctx.stroke();
        const n = 16;
        for (let i = 0; i < n; i++) {
            ctx.save();
            ctx.translate(cx, cy);
            ctx.rotate(i * Math.PI * 2 / n);
            ctx.translate(0, -221);
            ctx.lineWidth = 1.6;
            ctx.beginPath();
            ctx.moveTo(0, 8);
            ctx.lineTo(0, -2);
            ctx.bezierCurveTo(-3, -10, -14, -10, -14, -2);
            ctx.bezierCurveTo(-14, 4, -7, 5, -7, 0);
            ctx.moveTo(0, -2);
            ctx.bezierCurveTo(3, -10, 14, -10, 14, -2);
            ctx.bezierCurveTo(14, 4, 7, 5, 7, 0);
            ctx.stroke();
            ctx.restore();
        }
        return canvasTexture(c);
    }

    // Трава поляны: бесшовная текстура из мелких штрихов разных оттенков (детерминированно)
    function makeGrassTexture(pal) {
        const c = document.createElement('canvas');
        c.width = c.height = 256;
        const ctx = c.getContext('2d');
        ctx.fillStyle = pal.grass;
        ctx.fillRect(0, 0, 256, 256);
        // крупные пятна — трава неровная
        for (let k = 0; k < 26; k++) {
            const x = hash(k * 31 + 7) * 256, y = hash(k * 57 + 3) * 256, r = 18 + hash(k * 13) * 40;
            ctx.globalAlpha = 0.18;
            ctx.fillStyle = hash(k * 91) > 0.5 ? pal.grassLight : pal.grassDark;
            for (let dx = -256; dx <= 256; dx += 256) {
                for (let dy = -256; dy <= 256; dy += 256) {
                    ctx.beginPath(); ctx.arc(x + dx, y + dy, r, 0, Math.PI * 2); ctx.fill();
                }
            }
        }
        // травинки
        ctx.lineCap = 'round';
        for (let k = 0; k < 1400; k++) {
            const x = hash(k * 1.37 + 11) * 256, y = hash(k * 2.71 + 5) * 256;
            const len = 3 + hash(k * 3.3) * 6, lean = (hash(k * 5.9) - 0.5) * 3;
            ctx.globalAlpha = 0.35 + hash(k * 7.1) * 0.4;
            ctx.strokeStyle = hash(k * 4.4) > 0.5 ? pal.grassLight : pal.grassDark;
            ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x + lean, y - len); ctx.stroke();
        }
        ctx.globalAlpha = 1;
        const tex = canvasTexture(c);
        tex.wrapS = THREE.RepeatWrapping;
        tex.wrapT = THREE.RepeatWrapping;
        // повторяющаяся трава без мипмапов «рябит» вдали
        tex.generateMipmaps = true;
        tex.minFilter = THREE.LinearMipmapLinearFilter;
        tex.anisotropy = 4;
        return tex;
    }

    // Мягкий круглый ореол (для людей, солнца/луны, звёзд)
    function makeGlowTexture(inner, outerAlpha) {
        const c = document.createElement('canvas');
        c.width = c.height = 128;
        const ctx = c.getContext('2d');
        const g = ctx.createRadialGradient(64, 64, 0, 64, 64, 64);
        g.addColorStop(0, inner);
        g.addColorStop(0.45, inner);
        g.addColorStop(0.7, 'rgba(255,255,255,' + (outerAlpha === undefined ? 0.35 : outerAlpha) + ')');
        g.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, 128, 128);
        return canvasTexture(c);
    }

    // Небо-купол: градиент от горизонта к зениту, без тумана
    function makeSkyMaterial(pal) {
        return new THREE.ShaderMaterial({
            uniforms: {
                top: { value: new THREE.Color(pal.skyTop) },
                horizon: { value: new THREE.Color(pal.skyHorizon) }
            },
            vertexShader: 'varying vec3 vDir; void main(){ vDir = normalize(position); gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0); }',
            fragmentShader: 'uniform vec3 top; uniform vec3 horizon; varying vec3 vDir; void main(){ float h = clamp(vDir.y, 0.0, 1.0); float k = pow(h, 0.55); gl_FragColor = vec4(mix(horizon, top, k), 1.0);\n#include <colorspace_fragment>\n}',
            side: THREE.BackSide,
            depthWrite: false,
            fog: false
        });
    }

    // Пучок травы: три травинки крестом (нормали вверх — ровно освещены)
    function createGrassTuftGeometry() {
        const pos = [], nor = [];
        for (let k = 0; k < 3; k++) {
            const a = k * Math.PI / 3;
            const ca = Math.cos(a), sa = Math.sin(a);
            const w = 0.09, h = 0.55 + k * 0.12, lean = 0.12 * (k - 1);
            // треугольник: два угла у земли, вершина наверху
            pos.push(-w * ca, 0, -w * sa, w * ca, 0, w * sa, lean * sa, h, -lean * ca);
            nor.push(0, 1, 0, 0, 1, 0, 0, 1, 0);
        }
        const geo = new THREE.BufferGeometry();
        geo.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
        geo.setAttribute('normal', new THREE.Float32BufferAttribute(nor, 3));
        return geo;
    }

    // Тюльпан: чашечка вращения (низ закрыт, верх открыт) — узнаваем издалека
    function createTulipGeometry() {
        const pts = [
            new THREE.Vector2(0.0, -0.55), new THREE.Vector2(0.32, -0.48), new THREE.Vector2(0.62, -0.18),
            new THREE.Vector2(0.72, 0.18), new THREE.Vector2(0.62, 0.52), new THREE.Vector2(0.46, 0.72)
        ];
        const geo = new THREE.LatheGeometry(pts, 14);
        // зубцы лепестков по краю
        const p = geo.attributes.position;
        for (let i = 0; i < p.count; i++) {
            const y = p.getY(i);
            if (y > 0.7) {
                const ang = Math.atan2(p.getZ(i), p.getX(i));
                p.setY(i, y + 0.16 * Math.max(0, Math.cos(ang * 3)));
            }
        }
        geo.computeVertexNormals();
        return geo;
    }

    function makeBarkTexture(pal) {
        const c = document.createElement('canvas');
        c.width = 512; c.height = 512;
        const ctx = c.getContext('2d');

        // Базовый вертикальный градиент коры (как rs-gBarkV в 2D)
        const bgGrad = ctx.createLinearGradient(0, 0, 512, 0);
        bgGrad.addColorStop(0, pal.barkLight);
        bgGrad.addColorStop(0.45, pal.bark);
        bgGrad.addColorStop(1, pal.barkDark);
        ctx.fillStyle = bgGrad;
        ctx.fillRect(0, 0, 512, 512);

        // Продольные борозды коры (детерминированный шум по хешу)
        ctx.lineCap = 'round';
        for (let x = 0; x < 512; x += 6) {
            const h1 = hash(x * 7 + 13);
            const h2 = hash(x * 13 + 37);
            const w = 1.2 + h1 * 3.5;
            const dark = h2 > 0.45;
            ctx.strokeStyle = dark ? pal.barkDark : pal.barkLight;
            ctx.globalAlpha = 0.35 + h1 * 0.45;
            ctx.lineWidth = w;
            ctx.beginPath();
            let px = x;
            ctx.moveTo(px, 0);
            for (let y = 32; y <= 512; y += 32) {
                const shift = (hash(x * 19 + y) - 0.5) * 12;
                px += shift;
                ctx.lineTo(px, y);
            }
            ctx.stroke();
        }

        // Поперечные узлы и шероховатости коры
        ctx.globalAlpha = 0.25;
        ctx.fillStyle = pal.barkDark;
        for (let k = 0; k < 28; k++) {
            const kx = hash(k * 23) * 480 + 16;
            const ky = hash(k * 41) * 480 + 16;
            const krw = 8 + hash(k * 17) * 20;
            const krh = 2 + hash(k * 29) * 4;
            ctx.beginPath();
            ctx.ellipse(kx, ky, krw, krh, 0, 0, Math.PI * 2);
            ctx.fill();
        }
        ctx.globalAlpha = 1.0;

        const tex = canvasTexture(c);
        tex.wrapS = THREE.RepeatWrapping;
        tex.wrapT = THREE.RepeatWrapping;
        tex.generateMipmaps = true;
        tex.minFilter = THREE.LinearMipmapLinearFilter;
        return tex;
    }

    function makeShadowTexture(pal) {
        const c = document.createElement('canvas');
        c.width = 256; c.height = 256;
        const ctx = c.getContext('2d');
        const cx = 128, cy = 128;
        const g = ctx.createRadialGradient(cx, cy, 10, cx, cy, 126);
        g.addColorStop(0, pal.shadow);
        g.addColorStop(0.35, pal.shadow);
        g.addColorStop(0.7, 'rgba(0,0,0,0.18)');
        g.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = g;
        ctx.beginPath();
        ctx.arc(cx, cy, 126, 0, Math.PI * 2);
        ctx.fill();
        return canvasTexture(c);
    }

    // Профиль ствола по высоте (v = 0 у земли, 1 — верх): раструб у корней, плавное сужение в крону
    function trunkFactor(v) {
        if (v < 0.14) {
            const t = (0.14 - v) / 0.14;
            return 1.1 + t * t * 0.9; // до 2.0 у земли
        }
        return 1.1 - (v - 0.14) / 0.86 * 0.5;
    }

    function createSculptedTrunkGeometry() {
        const radialSegs = 16;
        const heightSegs = 14;
        const positions = [];
        const uvs = [];
        const indices = [];

        for (let j = 0; j <= heightSegs; j++) {
            const v = j / heightSegs; // 0..1
            // Раструб у основания ствола (buttress flare, как в 2D)
            const rFactor = trunkFactor(v);

            for (let i = 0; i <= radialSegs; i++) {
                const u = i / radialSegs;
                const theta = u * Math.PI * 2;
                // Рельефные вертикальные складки и ребра коры
                const rib = 1.0 + 0.07 * Math.cos(theta * 5) + 0.03 * Math.sin(theta * 3 + v * 3);
                const r = rFactor * rib;
                positions.push(Math.sin(theta) * r, v, Math.cos(theta) * r);
                uvs.push(u * 2.5, v * 3.5);
            }
        }

        for (let j = 0; j < heightSegs; j++) {
            for (let i = 0; i < radialSegs; i++) {
                const a = j * (radialSegs + 1) + i;
                const b = (j + 1) * (radialSegs + 1) + i;
                const c = (j + 1) * (radialSegs + 1) + (i + 1);
                const d = j * (radialSegs + 1) + (i + 1);
                indices.push(a, b, d);
                indices.push(b, c, d);
            }
        }

        const geo = new THREE.BufferGeometry();
        geo.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
        geo.setAttribute('uv', new THREE.Float32BufferAttribute(uvs, 2));
        geo.setIndex(indices);
        geo.computeVertexNormals();
        return geo;
    }

    function createRootArmGeometry() {
        const segments = 8;
        const radial = 8;
        const positions = [];
        const uvs = [];
        const indices = [];

        for (let j = 0; j <= segments; j++) {
            const t = j / segments;
            // корень начинается внутри раструба и уходит в траву на ~3.4 радиуса ствола
            const y = (1.0 - t) * (1.0 - t) * 0.95 + 0.03;
            const z = 0.6 + t * 2.8;
            const r = (1.0 - t * 0.8) * 0.36;

            for (let i = 0; i <= radial; i++) {
                const u = i / radial;
                const theta = u * Math.PI * 2;
                const x = Math.sin(theta) * r;
                const yr = Math.cos(theta) * r;
                positions.push(x, Math.max(0.01, y + yr * 0.6), z);
                uvs.push(u, t * 2);
            }
        }

        for (let j = 0; j < segments; j++) {
            for (let i = 0; i < radial; i++) {
                const a = j * (radial + 1) + i;
                const b = (j + 1) * (radial + 1) + i;
                const c = (j + 1) * (radial + 1) + (i + 1);
                const d = j * (radial + 1) + (i + 1);
                indices.push(a, b, d);
                indices.push(b, c, d);
            }
        }

        const geo = new THREE.BufferGeometry();
        geo.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
        geo.setAttribute('uv', new THREE.Float32BufferAttribute(uvs, 2));
        geo.setIndex(indices);
        geo.computeVertexNormals();
        return geo;
    }

    function createFoliageClumpGeometry(detail) {
        // комок листвы: бугристый, слегка приплюснутый; нормали — от центра (мягкий объём, без граней)
        const geo = new THREE.IcosahedronGeometry(1.0, detail === undefined ? 1 : detail);
        const pos = geo.attributes.position;
        const nor = geo.attributes.normal;
        const v = new THREE.Vector3();
        for (let i = 0; i < pos.count; i++) {
            v.fromBufferAttribute(pos, i);
            const n = v.clone().normalize();
            const noise = 1.0 + 0.18 * Math.sin(v.x * 5.1 + 0.8) * Math.cos(v.y * 4.3) * Math.sin(v.z * 4.7 + 1.3);
            v.multiplyScalar(noise);
            v.y *= 0.8;
            pos.setXYZ(i, v.x, v.y, v.z);
            nor.setXYZ(i, n.x, n.y, n.z);
        }
        return geo;
    }

    // =========================================================================
    // 3. HUD-СТИЛИ (в духе сайта: книга, золото, ою тонкой линией)
    // =========================================================================
    const ORN_SVG = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='28' height='10' viewBox='0 0 28 10'%3E%3Cpath d='M0 9C4 1 10 0 11 5C12 8 8 8 8 6M28 9C24 1 18 0 17 5C16 8 20 8 20 6' fill='none' stroke='black' stroke-width='1.2' stroke-linecap='round'/%3E%3C/svg%3E";

    function injectStyles() {
        if (document.getElementById('sf3-style')) return;
        const st = document.createElement('style');
        st.id = 'sf3-style';
        st.textContent = `
.sf3-root{position:absolute;inset:0;font-family:var(--font-book, Georgia, serif);color:var(--c-text,#3b2914);-webkit-user-select:none;user-select:none}
.sf3-root canvas{display:block;touch-action:none;outline:none}
.sf3-panel{background:var(--c-island,#fffbf2);border:1px solid var(--c-border,#e6d6b2);border-radius:14px;box-shadow:0 10px 30px rgba(40,25,10,.18)}
.sf3-orn{height:10px;margin:6px 0 10px;background-color:var(--c-accent,#b8862a);-webkit-mask-image:url("${ORN_SVG}");mask-image:url("${ORN_SVG}");-webkit-mask-repeat:repeat-x;mask-repeat:repeat-x;opacity:.75}
.sf3-btn{font:inherit;font-size:14px;line-height:1.2;padding:8px 14px;border-radius:9999px;border:1px solid var(--c-border,#e6d6b2);background:var(--c-island,#fffbf2);color:var(--c-text,#3b2914);cursor:pointer;display:inline-flex;align-items:center;gap:6px;white-space:nowrap}
.sf3-btn:hover{border-color:var(--c-accent,#b8862a)}
.sf3-btn:focus-visible{outline:2px solid var(--c-accent,#b8862a);outline-offset:2px}
.sf3-btn.primary{background:var(--c-bark,#6b4a2b);border-color:var(--c-bark,#6b4a2b);color:#fffbf2}
[data-theme="dark"] .sf3-btn.primary{background:var(--c-accent,#ddb65e);border-color:var(--c-accent,#ddb65e);color:#20170a}
@media (prefers-color-scheme: dark){:root:not([data-theme="light"]) .sf3-btn.primary{background:var(--c-accent,#ddb65e);border-color:var(--c-accent,#ddb65e);color:#20170a}}
.sf3-bar{position:absolute;left:50%;bottom:16px;transform:translateX(-50%);display:flex;gap:8px;align-items:center;padding:6px;z-index:40;max-width:calc(100% - 24px);flex-wrap:wrap;justify-content:center}
.sf3-select{font:inherit;font-size:14px;padding:7px 10px;border-radius:9999px;border:1px solid var(--c-border,#e6d6b2);background:var(--c-island,#fffbf2);color:var(--c-text,#3b2914);max-width:220px}
.sf3-legend{position:absolute;left:14px;bottom:76px;z-index:39;padding:10px 12px;font-size:13px;line-height:1.5;max-width:270px}
.sf3-legend-row{display:flex;align-items:center;gap:8px}
.sf3-legend svg{flex:none}
.sf3-tip{position:absolute;z-index:45;pointer-events:none;padding:8px 12px;font-size:14px;max-width:320px;display:none}
.sf3-tip b{display:block;font-size:15px}
.sf3-tip span{display:block;color:var(--c-text-2,#6b4c2a);font-size:12.5px;margin-top:2px}
.sf3-card{position:absolute;right:14px;top:120px;width:330px;max-width:calc(100% - 28px);max-height:calc(100% - 210px);overflow:auto;z-index:42;padding:14px 16px;display:none;-webkit-user-select:text;user-select:text}
.sf3-card h3{margin:0;font-size:20px;line-height:1.25;padding-right:28px}
.sf3-card .sf3-sub{color:var(--c-text-2,#6b4c2a);font-size:13.5px;margin-top:3px}
.sf3-card .sf3-x{position:absolute;right:10px;top:10px;width:28px;height:28px;border-radius:50%;border:1px solid var(--c-border,#e6d6b2);background:transparent;color:inherit;cursor:pointer;font-size:16px;line-height:1}
.sf3-card .sf3-actions{display:flex;flex-wrap:wrap;gap:6px;margin-top:12px}
.sf3-uz{margin-top:4px}
.sf3-uz h4{margin:10px 0 4px;font-size:15px;color:var(--c-accent-text,#8a5f12)}
.sf3-uz .sf3-uz-desc{font-size:12px;color:var(--c-text-2,#6b4c2a);margin-bottom:4px}
.sf3-uz button.sf3-rel{display:flex;justify-content:space-between;gap:8px;width:100%;text-align:left;font:inherit;font-size:13.5px;background:none;border:0;border-bottom:1px dashed var(--c-border,#e6d6b2);padding:4px 2px;color:inherit;cursor:pointer}
.sf3-uz button.sf3-rel:hover{color:var(--c-accent-text,#8a5f12)}
.sf3-uz .sf3-term{color:var(--c-text-2,#6b4c2a);font-size:12.5px;white-space:nowrap}
.sf3-intro-wrap{position:absolute;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;background:rgba(30,20,10,.35);padding:14px}
.sf3-intro{width:560px;max-width:100%;max-height:100%;overflow:auto;padding:20px 22px}
.sf3-intro h2{margin:0;font-size:24px}
.sf3-intro ul{list-style:none;margin:0;padding:0}
.sf3-intro li{display:flex;gap:10px;align-items:flex-start;margin:8px 0;font-size:15px;line-height:1.45}
.sf3-intro li svg{flex:none;margin-top:2px}
.sf3-intro .sf3-ctrl{font-size:13.5px;color:var(--c-text-2,#6b4c2a);margin:4px 0;line-height:1.4}
.sf3-intro .sf3-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
.sf3-msg{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;padding:24px;text-align:center;font-size:16px}
@media (max-width:640px){.sf3-legend{display:none}.sf3-card{top:auto;bottom:70px;right:7px;left:7px;width:auto;max-height:55%}.sf3-intro li{font-size:14px}}
`;
        document.head.appendChild(st);
    }

    const ICON = {
        tree: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M12 22V11"/><path d="M12 15l-4-3M12 13l4-3"/><circle cx="12" cy="7" r="5"/></svg>',
        branch: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M12 22V4"/><path d="M12 16l-6-4M12 12l6-4M12 8l-4-3"/><path d="M4 20h16" stroke-dasharray="2 2"/></svg>',
        leaf: '<svg width="22" height="22" viewBox="0 0 24 24"><circle cx="12" cy="13" r="10" fill="#fffbe8" opacity=".8"/><circle cx="12" cy="13" r="6.5" fill="#2f7fd0"/><path d="M12 6.5c1-2 3-3 5-3" stroke="#4f8a2e" stroke-width="1.6" fill="none" stroke-linecap="round"/></svg>',
        tulip: '<svg width="22" height="22" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#fffbe8" opacity=".8"/><path d="M6.5 7l2.7 2.6L12 5l2.8 4.6L17.5 7v4.2a5.5 5.5 0 0 1-11 0z" fill="#d9435f"/></svg>',
        gold: '<svg width="22" height="22" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#fffbe8" opacity=".8"/><circle cx="12" cy="12" r="6.5" fill="#c99a2e"/></svg>',
        line: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M2 18C7 6 17 6 22 18" stroke="#c96479" stroke-width="1.4" stroke-linecap="round"/><path d="M19.5 14l2.5 4-4.4-.6" stroke="#c96479" stroke-width="1.4" fill="none" stroke-linejoin="round"/></svg>',
        kelin: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="7" cy="12" r="4" fill="#2f7fd0"/><circle cx="17" cy="12" r="4" fill="#d9435f"/><path d="M11 12h2" stroke="#b8862a" stroke-width="2"/></svg>',
        hand: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M8 13V5a2 2 0 0 1 4 0v6M12 10a2 2 0 0 1 4 0v3M16 12a2 2 0 0 1 4 0v3a7 7 0 0 1-7 7h-1a6 6 0 0 1-5-3l-3-5a2 2 0 0 1 3-2l1 1"/></svg>'
    };

    function el(tag, cls, text) {
        const e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text !== undefined && text !== null) e.textContent = text;
        return e;
    }

    // =========================================================================
    // 4. УПРАВЛЕНИЕ КАМЕРОЙ: облёт + ходьба клавишами + тач
    // =========================================================================
    class ForestCamera {
        constructor(camera, dom, onChange) {
            this.camera = camera;
            this.dom = dom;
            this.onChange = onChange;
            this.target = new THREE.Vector3(0, 8, 0);
            this.dist = 40;
            this.theta = 0;      // азимут: 0 — камера на +Z
            this.phi = 1.15;     // от вертикали
            this.keys = {};
            this.limitR = 200;
            this.tween = null;
            this.pointers = new Map();
            this.dragMode = null;
            this.downPos = null;
            this.moved = false;
            this._bind();
            this.apply();
        }

        _bind() {
            const d = this.dom;
            this._onDown = (e) => {
                try { if (d.setPointerCapture) d.setPointerCapture(e.pointerId); } catch (err) { /* указатель уже отпущен */ }
                this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
                if (this.pointers.size === 1) {
                    this.downPos = { x: e.clientX, y: e.clientY };
                    this.moved = false;
                    this.dragMode = (e.button === 2 || e.shiftKey) ? 'pan' : 'rotate';
                } else {
                    this.dragMode = 'pinch';
                    this._pinch = this._pinchState();
                }
                this.tween = null;
            };
            this._onMove = (e) => {
                if (!this.pointers.has(e.pointerId)) return;
                const prev = this.pointers.get(e.pointerId);
                const dx = e.clientX - prev.x, dy = e.clientY - prev.y;
                this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
                if (this.downPos && Math.hypot(e.clientX - this.downPos.x, e.clientY - this.downPos.y) > 5) this.moved = true;
                if (this.dragMode === 'rotate' && this.pointers.size === 1) {
                    this.theta -= dx * 0.006;
                    this.phi = Math.min(1.45, Math.max(0.2, this.phi - dy * 0.005));
                    this.apply();
                } else if (this.dragMode === 'pan' && this.pointers.size === 1) {
                    this.pan(dx, dy);
                } else if (this.dragMode === 'pinch' && this.pointers.size >= 2) {
                    const s = this._pinchState();
                    if (this._pinch && s.d > 0) {
                        this.dist = this._clampDist(this.dist * (this._pinch.d / s.d));
                        this.pan(s.cx - this._pinch.cx, s.cy - this._pinch.cy);
                    }
                    this._pinch = s;
                    this.moved = true;
                    this.apply();
                }
            };
            this._onUp = (e) => {
                this.pointers.delete(e.pointerId);
                if (this.pointers.size === 0) this.dragMode = null;
                else if (this.pointers.size === 1) { this.dragMode = 'rotate'; this._pinch = null; }
            };
            this._onWheel = (e) => {
                e.preventDefault();
                const k = Math.exp(Math.max(-60, Math.min(60, e.deltaY)) * 0.0022);
                this.dist = this._clampDist(this.dist * k);
                this.tween = null;
                this.apply();
            };
            this._onCtx = (e) => e.preventDefault();
            this._onKeyDown = (e) => {
                if (!this._keysAllowed(e)) return;
                const k = this._keyName(e);
                if (!k) return;
                this.keys[k] = true;
                this.tween = null;
                if (['up', 'down', 'left', 'right'].indexOf(k) !== -1 && e.code && e.code.indexOf('Arrow') === 0) e.preventDefault();
                if (this.onChange) this.onChange('key');
            };
            this._onKeyUp = (e) => {
                const k = this._keyName(e);
                if (k) this.keys[k] = false;
            };
            this._onBlur = () => { this.keys = {}; };
            d.addEventListener('pointerdown', this._onDown);
            d.addEventListener('pointermove', this._onMove);
            d.addEventListener('pointerup', this._onUp);
            d.addEventListener('pointercancel', this._onUp);
            d.addEventListener('wheel', this._onWheel, { passive: false });
            d.addEventListener('contextmenu', this._onCtx);
            window.addEventListener('keydown', this._onKeyDown);
            window.addEventListener('keyup', this._onKeyUp);
            window.addEventListener('blur', this._onBlur);
        }

        _keysAllowed(e) {
            if (e.ctrlKey || e.metaKey || e.altKey) return false;
            if (!this.dom.isConnected || this.dom.offsetParent === null) return false;
            const a = document.activeElement;
            if (a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.tagName === 'SELECT' || a.isContentEditable)) return false;
            // открыта модалка сайта — клавиши ей
            // модалки сайта открываются и через style.display, и через класс .active
            const modalOpen = Array.prototype.some.call(document.querySelectorAll('.modal-overlay'), m => getComputedStyle(m).display !== 'none');
            if (modalOpen) return false;
            // открыта подсказка 3D-леса
            if (this.dom.parentNode && this.dom.parentNode.querySelector('.sf3-intro-wrap')) return false;
            return true;
        }

        _keyName(e) {
            switch (e.code) {
                case 'KeyW': case 'ArrowUp': return 'up';
                case 'KeyS': case 'ArrowDown': return 'down';
                case 'KeyA': case 'ArrowLeft': return 'left';
                case 'KeyD': case 'ArrowRight': return 'right';
                case 'KeyQ': return 'rotL';
                case 'KeyE': return 'rotR';
                case 'Equal': case 'NumpadAdd': return 'zoomIn';
                case 'Minus': case 'NumpadSubtract': return 'zoomOut';
                default: return null;
            }
        }

        _pinchState() {
            const pts = Array.from(this.pointers.values());
            if (pts.length < 2) return { d: 0, cx: 0, cy: 0 };
            return {
                d: Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y),
                cx: (pts[0].x + pts[1].x) / 2,
                cy: (pts[0].y + pts[1].y) / 2
            };
        }

        _clampDist(d) { return Math.min(this.limitR * 2.6, Math.max(5, d)); }

        pan(dx, dy) {
            const k = this.dist * 0.0018;
            const s = Math.sin(this.theta), c = Math.cos(this.theta);
            // вправо по экрану = (cos θ, 0, −sin θ); «вверх» по экрану — вперёд по земле
            this.target.x -= dx * k * c;
            this.target.z += dx * k * s;
            this.target.x -= dy * k * s;
            this.target.z -= dy * k * c;
            this._clampTarget();
            this.apply();
        }

        _clampTarget() {
            const r = Math.hypot(this.target.x, this.target.z);
            if (r > this.limitR) { this.target.x *= this.limitR / r; this.target.z *= this.limitR / r; }
            this.target.y = Math.min(60, Math.max(1, this.target.y));
        }

        isKeyMoving() {
            const k = this.keys;
            return !!(k.up || k.down || k.left || k.right || k.rotL || k.rotR || k.zoomIn || k.zoomOut);
        }

        update(dt) {
            let changed = false;
            if (this.isKeyMoving()) {
                const k = this.keys;
                const speed = Math.max(8, this.dist * 0.7) * dt;
                const f = (k.up ? 1 : 0) - (k.down ? 1 : 0);
                const r = (k.right ? 1 : 0) - (k.left ? 1 : 0);
                const s = Math.sin(this.theta), c = Math.cos(this.theta);
                // вперёд — от камеры к цели по земле: (−sin θ, −cos θ)
                this.target.x += (-s * f + c * r) * speed;
                this.target.z += (-c * f - s * r) * speed;
                if (k.rotL) this.theta += 1.4 * dt;
                if (k.rotR) this.theta -= 1.4 * dt;
                if (k.zoomIn) this.dist = this._clampDist(this.dist * (1 - 1.2 * dt));
                if (k.zoomOut) this.dist = this._clampDist(this.dist * (1 + 1.2 * dt));
                this._clampTarget();
                changed = true;
            }
            if (this.tween) {
                const tw = this.tween;
                tw.t = Math.min(1, tw.t + dt / tw.dur);
                const e = tw.t < 0.5 ? 4 * tw.t * tw.t * tw.t : 1 - Math.pow(-2 * tw.t + 2, 3) / 2;
                this.target.lerpVectors(tw.from.target, tw.to.target, e);
                this.dist = tw.from.dist + (tw.to.dist - tw.from.dist) * e;
                this.phi = tw.from.phi + (tw.to.phi - tw.from.phi) * e;
                this.theta = tw.from.theta + (tw.to.theta - tw.from.theta) * e;
                if (tw.t >= 1) this.tween = null;
                changed = true;
            }
            if (changed) this.apply();
            return changed;
        }

        flyTo(target, dist, theta, phi, dur) {
            let th = (theta === undefined || theta === null) ? this.theta : theta;
            // кратчайший поворот
            while (th - this.theta > Math.PI) th -= Math.PI * 2;
            while (th - this.theta < -Math.PI) th += Math.PI * 2;
            this.tween = {
                t: 0, dur: dur || 1.1,
                from: { target: this.target.clone(), dist: this.dist, theta: this.theta, phi: this.phi },
                to: { target: target.clone(), dist: this._clampDist(dist), theta: th, phi: phi === undefined || phi === null ? this.phi : phi }
            };
        }

        apply() {
            const sp = Math.sin(this.phi);
            this.camera.position.set(
                this.target.x + this.dist * sp * Math.sin(this.theta),
                this.target.y + this.dist * Math.cos(this.phi),
                this.target.z + this.dist * sp * Math.cos(this.theta)
            );
            if (this.camera.position.y < 1.2) this.camera.position.y = 1.2;
            this.camera.lookAt(this.target);
            // матрицы нужны сразу (отбор подписей, наведение) — не ждём render()
            this.camera.updateMatrixWorld();
            if (this.onChange) this.onChange('camera');
        }

        destroy() {
            const d = this.dom;
            d.removeEventListener('pointerdown', this._onDown);
            d.removeEventListener('pointermove', this._onMove);
            d.removeEventListener('pointerup', this._onUp);
            d.removeEventListener('pointercancel', this._onUp);
            d.removeEventListener('wheel', this._onWheel);
            d.removeEventListener('contextmenu', this._onCtx);
            window.removeEventListener('keydown', this._onKeyDown);
            window.removeEventListener('keyup', this._onKeyUp);
            window.removeEventListener('blur', this._onBlur);
        }
    }

    // =========================================================================
    // 5. ГЛАВНЫЙ КЛАСС
    // =========================================================================
    class ShezhireForest3D {
        constructor(options) {
            this.container = options.container;
            this.treeData = options.treeData || null;
            this.lang = options.lang === 'ru' ? 'ru' : 'kk';
            this.userPersonId = Number(options.userPersonId || (this.treeData && this.treeData.user_person_id) || 0);
            this.onPersonSelect = options.onPersonSelect || null;
            this.onOpenUshZhurt = options.onOpenUshZhurt || null;
            this.model = null;
            this.needsRender = true;
            this.frame = 0;
            this.hover = null;
            this.selected = null;
            this.disposables = [];
            this.labelSprites = [];
            this.introShown = false;
            this.init();
        }

        get t() { return TXT[this.lang]; }

        init() {
            injectStyles();
            this.container.innerHTML = '';
            this.container.style.position = 'relative';
            this.container.style.overflow = 'hidden';
            this.root = el('div', 'sf3-root');
            this.container.appendChild(this.root);

            const w = this.container.clientWidth || window.innerWidth;
            const h = this.container.clientHeight || window.innerHeight;
            try {
                this.renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: 'high-performance' });
            } catch (e) {
                this.renderer = null;
                const m = el('div', 'sf3-msg', this.t.noWebgl);
                this.root.appendChild(m);
                return;
            }
            this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
            this.renderer.setSize(w, h);
            this.renderer.domElement.setAttribute('tabindex', '0');
            this.renderer.domElement.setAttribute('aria-label', 'Шежіре-Орман 3D');
            this.root.appendChild(this.renderer.domElement);
            // Safari может сбросить WebGL-контекст (фон, нехватка памяти) — восстанавливаем сцену
            this._onCtxLost = (e) => { e.preventDefault(); this._ctxLost = true; };
            this._onCtxRestored = () => { this._ctxLost = false; this.rebuild(false); this.needsRender = true; };
            this.renderer.domElement.addEventListener('webglcontextlost', this._onCtxLost);
            this.renderer.domElement.addEventListener('webglcontextrestored', this._onCtxRestored);

            this.scene = new THREE.Scene();
            this.camera = new THREE.PerspectiveCamera(55, w / h, 0.8, 2000);
            this.world = new THREE.Group();
            this.scene.add(this.world);

            this.hemi = new THREE.HemisphereLight(0xffffff, 0x888888, 1);
            this.sun = new THREE.DirectionalLight(0xffffff, 0.7);
            this.sun.position.set(-40, 80, 60);
            this.scene.add(this.hemi, this.sun);

            this.cam = new ForestCamera(this.camera, this.renderer.domElement, () => { this.needsRender = true; this._labelsDirty = true; this._hoverDirty = true; });
            this.raycaster = new THREE.Raycaster();
            this.mouse = new THREE.Vector2(-5, -5);
            this.mouseClient = { x: 0, y: 0 };

            this.buildHud();
            this.bindPicking();
            this.applyTheme();
            this.rebuild(true);

            this._onResize = () => this.onWindowResize();
            window.addEventListener('resize', this._onResize);
            if (window.ResizeObserver) {
                this._ro = new ResizeObserver(() => this.onWindowResize());
                this._ro.observe(this.container);
            }
            this._themeObs = new MutationObserver(() => {
                const d = isDarkTheme();
                if (d !== this.dark) { this.applyTheme(); this.rebuild(false); }
            });
            this._themeObs.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
            this._mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
            this._mqHandler = () => { const d = isDarkTheme(); if (d !== this.dark) { this.applyTheme(); this.rebuild(false); } };
            if (this._mq && this._mq.addEventListener) this._mq.addEventListener('change', this._mqHandler);

            this.clock = new THREE.Clock();
            this.animate();
            this.showIntro();
        }

        // ---------- данные/язык ----------
        setData(treeData) {
            this.treeData = treeData;
            if (treeData && treeData.user_person_id) this.userPersonId = Number(treeData.user_person_id);
            if (this.renderer) this.rebuild(false);
        }

        setLang(lang) {
            const l = lang === 'ru' ? 'ru' : 'kk';
            if (l === this.lang) return;
            this.lang = l;
            if (!this.renderer) return;
            this.buildHud();
            this.rebuild(false);
            if (this.introWrap) { this.introWrap.remove(); this.introWrap = null; this.showIntro(true); }
        }

        applyTheme() {
            this.dark = isDarkTheme();
            this.pal = palette(this.dark);
            const p = this.pal;
            this.scene.background = new THREE.Color(p.skyHorizon);
            this.scene.fog = new THREE.Fog(p.fog, 120, 520);
            this.hemi.color.set(p.hemiSky);
            this.hemi.groundColor.set(p.hemiGround);
            this.hemi.intensity = p.hemiI;
            this.sun.color.set(p.sunColor);
            this.sun.intensity = p.sunI;
            this.needsRender = true;
        }

        clearWorld() {
            // буферы матриц InstancedMesh освобождаются только через dispose() самого меша
            this.world.traverse(o => { if (o.isInstancedMesh && typeof o.dispose === 'function') o.dispose(); });
            this.disposables.forEach(d => { try { d.dispose(); } catch (e) { /* уже освобождено */ } });
            this.disposables = [];
            this.labelSprites = [];
            while (this.world.children.length) this.world.remove(this.world.children[0]);
            // небо, солнце/луна и звёзды живут в scene (идут за камерой) — снимаем их тоже
            if (this.sky) { this.scene.remove(this.sky); this.sky = null; }
            (this.skyExtras || []).forEach(o => this.scene.remove(o));
            this.skyExtras = [];
            this.pickables = [];
            this.instanceMap = new Map();
            this.linkObjs = new Map();
            this.personMesh = new Map();
            this.hover = null;
            if (this.tip) this.tip.style.display = 'none';
        }

        track(obj) {
            // у Sprite общая на весь three геометрия — её не освобождаем
            if (obj.geometry && !obj.isSprite) this.disposables.push(obj.geometry);
            if (obj.material) {
                const mats = Array.isArray(obj.material) ? obj.material : [obj.material];
                mats.forEach(m => { this.disposables.push(m); if (m.map) this.disposables.push(m.map); });
            }
            return obj;
        }

        rebuild(first) {
            this.clearWorld();
            this.model = buildForestModel(this.treeData || {}, { lang: this.lang, userPersonId: this.userPersonId });
            const M = this.model;
            this.fillTreeSelect();
            if (this.emptyMsg) { this.emptyMsg.remove(); this.emptyMsg = null; }
            if (!M.trees.length) {
                this.emptyMsg = el('div', 'sf3-msg', this.t.empty);
                this.root.appendChild(this.emptyMsg);
                this.needsRender = true;
                return;
            }
            this.cam.limitR = M.worldR + 30;
            // туман по размеру леса: в обзоре дальние деревья не тонут
            this.scene.fog = new THREE.Fog(this.pal.fog, Math.max(120, M.worldR * 1.2), Math.max(520, M.worldR * 5));
            this.buildGround();
            this.buildTrees();
            this.buildPeople();
            this.buildLinks();
            if (first) this.focusMine(true);
            if (this.selected) this.reselect();
            this.updateLabelVisibility(true);
            this.needsRender = true;
        }

        // ---------- сцена ----------
        // Поляна: небо, трава с пучками и цветами, холмы и дальний лес на горизонте
        buildGround() {
            const M = this.model, p = this.pal;
            const dummy = new THREE.Object3D();
            const R = M.worldR + 60;

            // небо-купол (следует за камерой — см. animate)
            this.sky = this.track(new THREE.Mesh(new THREE.SphereGeometry(1200, 32, 16), makeSkyMaterial(p)));
            this.sky.renderOrder = -10;
            this.sky.frustumCulled = false;
            this.scene.add(this.sky);
            this.skyExtras = [];
            // солнце (днём) или луна (ночью) — мягкое пятно света в небе
            const sunTex = makeGlowTexture(p.sunGlow, 0.25);
            const sun = this.track(new THREE.Sprite(new THREE.SpriteMaterial({ map: sunTex, depthWrite: false, fog: false, transparent: true, opacity: this.dark ? 0.9 : 0.95 })));
            sun.scale.setScalar(this.dark ? 70 : 150);
            sun.userData.skyOffset = new THREE.Vector3(-0.45, 0.38, -0.81).normalize().multiplyScalar(1000);
            sun.renderOrder = -9;
            this.scene.add(sun);
            this.skyExtras.push(sun);
            if (p.stars) {
                const sp = [];
                for (let i = 0; i < 700; i++) {
                    const u = hash(i * 1.7 + 3), v = 0.08 + hash(i * 2.9 + 1) * 0.92;
                    const ang = u * Math.PI * 2, y = v, rr = Math.sqrt(1 - y * y);
                    sp.push(Math.cos(ang) * rr * 1100, y * 1100, Math.sin(ang) * rr * 1100);
                }
                const sg = new THREE.BufferGeometry();
                sg.setAttribute('position', new THREE.Float32BufferAttribute(sp, 3));
                const stars = this.track(new THREE.Points(sg, new THREE.PointsMaterial({ color: 0xfff6e0, size: 2, sizeAttenuation: false, fog: false, depthWrite: false, transparent: true, opacity: 0.85 })));
                stars.userData.skyOffset = new THREE.Vector3(0, 0, 0);
                stars.frustumCulled = false;
                stars.renderOrder = -9;
                this.scene.add(stars);
                this.skyExtras.push(stars);
            }

            // земля — трава
            const grassTex = makeGrassTexture(p);
            const groundR = R + 420;
            const rep = Math.round(groundR * 2 / 9); // плитка травы ~9 единиц
            grassTex.repeat.set(rep, rep);
            const geo = new THREE.CircleGeometry(groundR, 96);
            geo.rotateX(-Math.PI / 2);
            const g = this.track(new THREE.Mesh(geo, new THREE.MeshLambertMaterial({ map: grassTex })));
            this.world.add(g);

            // где стоят деревья — там пучки и цветы не растут
            const nearTree = (x, z, pad) => {
                for (let i = 0; i < M.trees.length; i++) {
                    const tr = M.trees[i];
                    const dx = x - tr.x, dz = z - tr.z;
                    const rr = tr.trunkR * 2.6 + pad;
                    if (dx * dx + dz * dz < rr * rr) return true;
                }
                return false;
            };
            const boardSpot = (x, z) => M.trees.some(tr => {
                const fz = tr.trunkR * 2.2 + 2.2;
                const bx = tr.x + fz * Math.sin(tr.rotY), bz = tr.z + fz * Math.cos(tr.rotY);
                return (x - bx) * (x - bx) + (z - bz) * (z - bz) < 9;
            });

            // пучки травы — сетка с детерминированным сдвигом, не больше ~6000
            const area = Math.PI * R * R;
            const step = Math.max(1.6, Math.sqrt(area / 6000));
            const tufts = [], flowers = [];
            let k = 0;
            for (let x = -R; x <= R; x += step) {
                for (let z = -R; z <= R; z += step) {
                    k++;
                    const jx = x + (hash(k * 1.31) - 0.5) * step, jz = z + (hash(k * 2.17) - 0.5) * step;
                    if (jx * jx + jz * jz > R * R) continue;
                    if (nearTree(jx, jz, 0) || boardSpot(jx, jz)) continue;
                    tufts.push([jx, jz, hash(k * 3.7), hash(k * 5.3)]);
                    if (hash(k * 7.9) > 0.72 && !nearTree(jx, jz, 1.5)) flowers.push([jx + 0.4, jz - 0.3, hash(k * 9.1), hash(k * 4.3)]);
                }
            }
            const tuftGeo = createGrassTuftGeometry();
            // без освещения: у двусторонних травинок Lambert затемняет обратную сторону
            const tuftMat = new THREE.MeshBasicMaterial({ side: THREE.DoubleSide });
            this.disposables.push(tuftGeo, tuftMat);
            if (tufts.length) {
                const im = new THREE.InstancedMesh(tuftGeo, tuftMat, tufts.length);
                const cA = new THREE.Color(p.grassLight), cB = new THREE.Color(p.grassDark), col = new THREE.Color();
                tufts.forEach((tf, i) => {
                    dummy.position.set(tf[0], 0, tf[1]);
                    dummy.rotation.set(0, tf[2] * Math.PI * 2, 0);
                    dummy.scale.setScalar(0.8 + tf[3] * 0.9);
                    dummy.updateMatrix();
                    im.setMatrixAt(i, dummy.matrix);
                    im.setColorAt(i, col.copy(cA).lerp(cB, tf[3]));
                });
                im.instanceMatrix.needsUpdate = true;
                if (im.instanceColor) im.instanceColor.needsUpdate = true;
                this.world.add(im);
            }
            // полевые цветы — мелкие, белые/жёлтые/сиреневые (не розовые — розовые тюльпаны это люди)
            const flowerGeo = new THREE.IcosahedronGeometry(0.16, 0);
            flowerGeo.translate(0, 0.42, 0);
            const flowerMat = new THREE.MeshLambertMaterial({ emissive: 0x222222 });
            this.disposables.push(flowerGeo, flowerMat);
            if (flowers.length) {
                const im = new THREE.InstancedMesh(flowerGeo, flowerMat, flowers.length);
                const col = new THREE.Color();
                flowers.forEach((f, i) => {
                    dummy.position.set(f[0], 0, f[1]);
                    dummy.rotation.set(0, f[3] * 6, 0);
                    dummy.scale.setScalar(0.8 + f[3] * 0.7);
                    dummy.updateMatrix();
                    im.setMatrixAt(i, dummy.matrix);
                    im.setColorAt(i, col.set(p.flowers[Math.floor(f[2] * p.flowers.length) % p.flowers.length]));
                });
                im.instanceMatrix.needsUpdate = true;
                if (im.instanceColor) im.instanceColor.needsUpdate = true;
                this.world.add(im);
            }

            // холмы и дальний лес по кругу — у поляны есть край, это «место»
            const hillGeo = new THREE.SphereGeometry(1, 20, 10, 0, Math.PI * 2, 0, Math.PI / 2);
            const hillMat = new THREE.MeshLambertMaterial({ color: p.hill });
            this.disposables.push(hillGeo, hillMat);
            const nH = 28;
            const hills = new THREE.InstancedMesh(hillGeo, hillMat, nH);
            for (let i = 0; i < nH; i++) {
                const a = i / nH * Math.PI * 2 + hash(i * 3.1) * 0.15;
                const rr = R + 150 + hash(i * 5.7) * 120;
                dummy.position.set(Math.cos(a) * rr, -2, Math.sin(a) * rr);
                dummy.rotation.set(0, a, 0);
                dummy.scale.set(70 + hash(i * 2.3) * 60, 18 + hash(i * 4.9) * 30, 60 + hash(i * 6.1) * 50);
                dummy.updateMatrix();
                hills.setMatrixAt(i, dummy.matrix);
            }
            hills.instanceMatrix.needsUpdate = true;
            this.world.add(hills);

            const farConeGeo = new THREE.ConeGeometry(1, 1, 7);
            farConeGeo.translate(0, 0.5, 0);
            const farMat = new THREE.MeshLambertMaterial({ color: p.farTree });
            this.disposables.push(farConeGeo, farMat);
            const nF = 160;
            const far = new THREE.InstancedMesh(farConeGeo, farMat, nF);
            for (let i = 0; i < nF; i++) {
                const a = i / nF * Math.PI * 2 + hash(i * 7.7) * 0.03;
                const rr = R + 40 + hash(i * 1.9) * 70;
                const h = 9 + hash(i * 8.3) * 10;
                dummy.position.set(Math.cos(a) * rr, 0, Math.sin(a) * rr);
                dummy.rotation.set(0, 0, 0);
                dummy.scale.set(h * 0.32, h, h * 0.32);
                dummy.updateMatrix();
                far.setMatrixAt(i, dummy.matrix);
            }
            far.instanceMatrix.needsUpdate = true;
            this.world.add(far);

            // мягкие тени под кронами (одна отрисовка на все деревья)
            const shadowTex = makeShadowTexture(p);
            const shadowGeo = new THREE.PlaneGeometry(1, 1);
            shadowGeo.rotateX(-Math.PI / 2);
            // тени и кольца лежат почти на траве — сдвиг глубины, чтобы не мерцали в обзоре
            const shadowMat = new THREE.MeshBasicMaterial({ map: shadowTex, transparent: true, opacity: 0.8, depthWrite: false, polygonOffset: true, polygonOffsetFactor: -2, polygonOffsetUnits: -2 });
            this.disposables.push(shadowTex, shadowGeo, shadowMat);
            const shadows = new THREE.InstancedMesh(shadowGeo, shadowMat, M.trees.length);
            M.trees.forEach((tr, i) => {
                const diam = Math.max(tr.crownR * 2.3, tr.trunkR * 7);
                dummy.position.set(tr.x + 1.2, 0.03, tr.z + 0.8);
                dummy.rotation.set(0, 0, 0);
                dummy.scale.set(diam, 1, diam);
                dummy.updateMatrix();
                shadows.setMatrixAt(i, dummy.matrix);
            });
            shadows.instanceMatrix.needsUpdate = true;
            this.world.add(shadows);

            // тонкое кольцо ою-өрнек вокруг каждого дерева — граница рода на поляне
            const ringTex = makeOrnamentRingTexture(p.orn);
            const ringGeo = new THREE.PlaneGeometry(1, 1);
            ringGeo.rotateX(-Math.PI / 2);
            this.disposables.push(ringTex, ringGeo);
            [true, false].forEach(mine => {
                const list = M.trees.filter(tr => !!tr.isMine === mine);
                if (!list.length) return;
                const ringMat = new THREE.MeshBasicMaterial({ map: ringTex, transparent: true, opacity: mine ? 0.85 : 0.4, depthWrite: false, polygonOffset: true, polygonOffsetFactor: -4, polygonOffsetUnits: -4 });
                this.disposables.push(ringMat);
                const rings = new THREE.InstancedMesh(ringGeo, ringMat, list.length);
                list.forEach((tr, i) => {
                    dummy.position.set(tr.x, 0.05, tr.z);
                    dummy.rotation.set(0, tr.rotY, 0);
                    dummy.scale.set(tr.R * 2, 1, tr.R * 2);
                    dummy.updateMatrix();
                    rings.setMatrixAt(i, dummy.matrix);
                });
                rings.instanceMatrix.needsUpdate = true;
                this.world.add(rings);
            });
        }

        buildTrees() {
            const M = this.model, p = this.pal, t = this.t;
            const dummy = new THREE.Object3D();
            const barkTex = makeBarkTexture(p);
            const barkMat = new THREE.MeshLambertMaterial({ map: barkTex, color: 0xffffff });
            const branchMat = new THREE.MeshLambertMaterial({ map: barkTex, color: 0xf2e4d2 });
            this.disposables.push(barkTex, barkMat, branchMat);

            // 0. Каркас каждого дерева: ярусы (поколения), размер ветви (сколько потомков), высота ствола
            M.trees.forEach(tr => {
                const rows = new Map();
                tr.local.forEach(lp => {
                    const row = rows.get(lp.gen) || { y: lp.y, r: 0 };
                    // ярус ветвей — только кровные не на стволе (келін рядом со стволом ярусом не считается)
                    if (!lp.onTrunk && lp.role === 'blood') row.r = Math.max(row.r, Math.hypot(lp.x, lp.z));
                    rows.set(lp.gen, row);
                });
                tr._rows = Array.from(rows.entries()).sort((a, b) => a[0] - b[0]).map(e => e[1]);
                const sub = new Map();
                tr.local.forEach((lp, id) => {
                    if (lp.role === 'kelin') return;
                    let cur = id, guard = 0;
                    while (cur && tr.local.has(cur) && guard++ < 300) {
                        sub.set(cur, (sub.get(cur) || 0) + 1);
                        const per = M.byId.get(cur);
                        cur = per && per.father_id ? Number(per.father_id) : 0;
                    }
                });
                tr._sub = sub;
                tr._trunkH = tr.trunkH; // высота ствола считается в модели (по ней же стоят люди на коре)
            });

            // 1. Ствол: раструб у корней, рёбра коры
            const trunkGeo = createSculptedTrunkGeometry();
            this.disposables.push(trunkGeo);
            const trunks = new THREE.InstancedMesh(trunkGeo, barkMat, M.trees.length);
            M.trees.forEach((tr, i) => {
                dummy.position.set(tr.x, 0, tr.z);
                dummy.rotation.set(0, tr.rotY, 0);
                dummy.scale.set(tr.trunkR, tr._trunkH, tr.trunkR);
                dummy.updateMatrix();
                trunks.setMatrixAt(i, dummy.matrix);
            });
            trunks.instanceMatrix.needsUpdate = true;
            this.world.add(trunks);

            // 2. Корни: 7 узловатых корней разной длины уходят в траву (не прямо вперёд — там табличка)
            const rootGeo = createRootArmGeometry();
            this.disposables.push(rootGeo);
            const numRoots = 7;
            const roots = new THREE.InstancedMesh(rootGeo, barkMat, M.trees.length * numRoots);
            let rootIdx = 0;
            M.trees.forEach(tr => {
                for (let k = 0; k < numRoots; k++) {
                    const h = hash(tr.rootId * 3 + k * 7);
                    const ang = tr.rotY + 0.45 + k * Math.PI * 2 / numRoots + (h - 0.5) * 0.35;
                    dummy.position.set(tr.x, 0, tr.z);
                    dummy.rotation.set(0, ang, 0);
                    dummy.scale.set(tr.trunkR * (0.85 + h * 0.35), tr.trunkR * 0.9, tr.trunkR * (0.6 + h * 0.3));
                    dummy.updateMatrix();
                    roots.setMatrixAt(rootIdx++, dummy.matrix);
                }
            });
            roots.instanceMatrix.needsUpdate = true;
            this.world.add(roots);

            // 3. Ветви «отец → ребёнок»: дуга из 4 сегментов, толщина — по числу потомков на этой ветви
            const segs = [], joints = [], ties = [];
            const branchR = (n, tr) => Math.min(tr.trunkR * 0.55, 0.11 + 0.08 * Math.sqrt(n || 1));
            M.trees.forEach(tr => {
                const c = Math.cos(tr.rotY), s = Math.sin(tr.rotY);
                const toWorld = (lx, ly, lz) => new THREE.Vector3(tr.x + lx * c + lz * s, ly, tr.z - lx * s + lz * c);
                tr.local.forEach((lp, id) => {
                    if (lp.onTrunk) return;
                    if (lp.role === 'kelin') {
                        const hp = tr.local.get(lp.husband);
                        if (hp) {
                            const pA = toWorld(lp.x, lp.y, lp.z);
                            const pB = hp.onTrunk ? toWorld(0, hp.y, 0) : toWorld(hp.x, hp.y, hp.z);
                            ties.push({ a: pA, b: pB, r: 0.06 });
                        }
                        return;
                    }
                    const person = M.byId.get(id);
                    const fid = person && person.father_id ? Number(person.father_id) : 0;
                    const fp = fid ? tr.local.get(fid) : null;
                    let A;
                    if (!fp) A = toWorld(0, Math.min(lp.y - 1.2, tr._trunkH * 0.8), 0);
                    else if (fp.onTrunk) A = toWorld(0, fp.y + 0.8, 0);
                    else A = toWorld(fp.x, fp.y, fp.z);
                    const B = toWorld(lp.x, lp.y, lp.z);
                    const len = A.distanceTo(B);
                    // изгиб: сначала в сторону, потом вверх к ребёнку — как живая ветка
                    const C = A.clone().lerp(B, 0.55);
                    C.y = A.y + (B.y - A.y) * 0.25 + Math.min(1.2, Math.max(0.3, len * 0.12));
                    const r0 = branchR(tr._sub.get(id), tr) * (fp && !fp.onTrunk ? 1.0 : 1.25);
                    const r1 = r0 * 0.55;
                    const N = 4;
                    let prev = A;
                    for (let i = 1; i <= N; i++) {
                        const u = i / N;
                        const q = new THREE.Vector3(
                            (1 - u) * (1 - u) * A.x + 2 * (1 - u) * u * C.x + u * u * B.x,
                            (1 - u) * (1 - u) * A.y + 2 * (1 - u) * u * C.y + u * u * B.y,
                            (1 - u) * (1 - u) * A.z + 2 * (1 - u) * u * C.z + u * u * B.z);
                        const r = r0 + (r1 - r0) * (u - 0.5 / N);
                        segs.push({ a: prev, b: q, r });
                        if (i < N) joints.push({ p: q, r });
                        prev = q;
                    }
                });
            });

            const brGeo = new THREE.CylinderGeometry(1, 1, 1, 7, 1, true);
            brGeo.translate(0, 0.5, 0); // основание в (0,0,0), вершина в (0,1,0)
            const jointGeo = new THREE.SphereGeometry(1, 8, 6);
            this.disposables.push(brGeo, jointGeo);
            const up = new THREE.Vector3(0, 1, 0);
            const fill = (list, mat) => {
                if (!list.length) return;
                const im = new THREE.InstancedMesh(brGeo, mat, list.length);
                list.forEach((sg, i) => {
                    const dir = new THREE.Vector3().subVectors(sg.b, sg.a);
                    const len = dir.length() || 0.01;
                    dummy.position.copy(sg.a);
                    dummy.quaternion.setFromUnitVectors(up, dir.normalize());
                    dummy.scale.set(sg.r, len, sg.r);
                    dummy.updateMatrix();
                    im.setMatrixAt(i, dummy.matrix);
                });
                im.instanceMatrix.needsUpdate = true;
                this.world.add(im);
            };
            fill(segs, branchMat);
            if (joints.length) {
                const jm = new THREE.InstancedMesh(jointGeo, branchMat, joints.length);
                joints.forEach((j, i) => {
                    dummy.position.copy(j.p);
                    dummy.quaternion.identity();
                    dummy.scale.setScalar(j.r);
                    dummy.updateMatrix();
                    jm.setMatrixAt(i, dummy.matrix);
                });
                jm.instanceMatrix.needsUpdate = true;
                this.world.add(jm);
            }
            const tieMat = new THREE.MeshBasicMaterial({ color: p.kelinTie });
            this.disposables.push(tieMat);
            fill(ties, tieMat);

            // 4. Густая крона: комки листвы заполняют каждый ярус ВНУТРИ кольца людей,
            //    люди висят на внешней поверхности кроны, как плоды; сверху — купол.
            const foliageGeo = createFoliageClumpGeometry();
            const foliageMat = new THREE.MeshLambertMaterial({
                color: 0xffffff,
                emissive: p.leafDark,
                emissiveIntensity: this.dark ? 0.35 : 0.22
            });
            this.disposables.push(foliageGeo, foliageMat);
            const clumps = [];
            const cDark = new THREE.Color(p.leafDark), cMid = new THREE.Color(p.leaf), cLight = new THREE.Color(p.leafLight);
            M.trees.forEach(tr => {
                const c = Math.cos(tr.rotY), s = Math.sin(tr.rotY);
                const seed = tr.rootId * 17;
                let n = 0;
                const add = (lx, ly, lz, size) => {
                    n++;
                    const h = hash(seed + n * 3.17);
                    clumps.push({
                        x: tr.x + lx * c + lz * s, y: ly, z: tr.z - lx * s + lz * c,
                        s: size, rot: h * Math.PI * 2, h, tree: tr
                    });
                };
                // диск из колец; заполняем только внешний слой толщиной ~6 (внутри не видно)
                const disc = (y, rIn, base) => {
                    if (rIn < 0.6) { add(0, y, 0, base + 0.3); return; }
                    const inner = Math.max(0, rIn - 6);
                    for (let rr = rIn; rr > inner - 0.01; rr -= 2.1) {
                        const cnt = Math.max(1, Math.round(Math.PI * 2 * rr / 2.0));
                        const off = hash(seed + y * 7 + rr) * Math.PI * 2;
                        for (let i = 0; i < cnt; i++) {
                            const a = off + i * Math.PI * 2 / cnt;
                            const hy = hash(seed + i * 5.3 + rr * 1.7 + y);
                            add(rr * Math.cos(a), y + (hy - 0.5) * 0.7, rr * Math.sin(a), base + hy * 0.5);
                        }
                    }
                    if (inner <= 0.01 && rIn >= 2.1) add(0, y, 0, base + 0.4);
                };
                const branched = tr._rows.filter(r => r.r > 0);
                if (branched.length) {
                    // нижний свод кроны — чтобы снизу крона была круглой, а не плоской
                    const b0 = branched[0];
                    disc(b0.y - 2.0, Math.max(0, b0.r * 0.6 - 1.4), 1.8);
                    disc(b0.y - 3.2, Math.max(0, b0.r * 0.3 - 1.2), 1.7);
                    branched.forEach((row, i) => {
                        disc(row.y - 0.5, Math.max(0, row.r - 2.2), 1.75);
                        const nx = branched[i + 1];
                        if (nx) disc((row.y + nx.y) / 2 - 0.2, Math.max(0, (row.r + nx.r) / 2 - 2.1), 1.85);
                    });
                    // купол сверху — круглая шапка, а не плоский «зонтик»
                    const top = branched[branched.length - 1];
                    disc(top.y + 1.2, Math.max(0, top.r - 2.3), 1.8);
                    disc(top.y + 2.6, Math.max(0, top.r * 0.78 - 2.0), 1.9);
                    disc(top.y + 3.9, Math.max(0, top.r * 0.52 - 1.6), 2.0);
                    disc(top.y + 5.0, Math.max(0, top.r * 0.25 - 1.0), 2.1);
                    tr._foliageTop = top.y + 5.0 + 2.6 * 0.85;
                } else {
                    // род идёт одной линией: люди на стволе, крона — шапка над ними
                    const y = tr.trunkTopY + 2.3;
                    disc(y, 1.8, 1.4);
                    disc(y + 1.4, 0.9, 1.5);
                    add(0, y + 2.4, 0, 1.6);
                    tr._foliageTop = y + 2.4 + 1.6;
                }
                // мелкие листья вокруг каждого человека на ветке — «плод в листве», но не закрывая его
                tr.local.forEach((lp, id) => {
                    if (lp.onTrunk) return;
                    const rr = Math.hypot(lp.x, lp.z) || 1;
                    const ux = lp.x / rr, uz = lp.z / rr; // наружу
                    const tx = -uz, tz = ux;              // вдоль кольца
                    const side = 1.15 + hash(id * 1.3) * 0.2;
                    add(lp.x - ux * 0.45 + tx * side, lp.y - 0.35, lp.z - uz * 0.45 + tz * side, 0.75);
                    add(lp.x - ux * 0.45 - tx * side, lp.y - 0.25, lp.z - uz * 0.45 - tz * side, 0.7);
                    add(lp.x - ux * 1.5, lp.y - 0.55, lp.z - uz * 1.5, 1.0);
                });
                // табличка и подпись над кроной учитывают настоящую высоту листвы
                tr.height = Math.max(tr.height, tr._foliageTop);
            });
            if (clumps.length) {
                // большой лес — комки попроще (80 вершин вместо 240), чтобы MacBook/Safari шёл плавно
                let geoUse = foliageGeo;
                if (clumps.length > 6000) { geoUse = createFoliageClumpGeometry(0); this.disposables.push(geoUse); }
                const im = new THREE.InstancedMesh(geoUse, foliageMat, clumps.length);
                const col = new THREE.Color();
                clumps.forEach((cl, i) => {
                    dummy.position.set(cl.x, cl.y, cl.z);
                    dummy.rotation.set(0, cl.rot, 0);
                    dummy.scale.set(cl.s * 1.15, cl.s * 0.85, cl.s * 1.15);
                    dummy.updateMatrix();
                    im.setMatrixAt(i, dummy.matrix);
                    // ниже и внутри — темнее, наверху — светлее (солнце на кроне)
                    const tr = cl.tree;
                    const f = Math.min(1, Math.max(0, (cl.y - tr.trunkTopY) / Math.max(1, tr._foliageTop - tr.trunkTopY)));
                    if (f < 0.5) col.copy(cDark).lerp(cMid, f * 2);
                    else col.copy(cMid).lerp(cLight, (f - 0.5) * 2 * (0.6 + cl.h * 0.4));
                    col.offsetHSL((cl.h - 0.5) * 0.03, 0, (cl.h - 0.5) * 0.06);
                    im.setColorAt(i, col);
                });
                im.instanceMatrix.needsUpdate = true;
                if (im.instanceColor) im.instanceColor.needsUpdate = true;
                this.world.add(im);
            }

            // табличка у корней, тамга на стволе, имя рода над кроной
            const boardGeo = new THREE.PlaneGeometry(4.6, 2.16);
            this.disposables.push(boardGeo);
            const discGeo = new THREE.CircleGeometry(1, 28);
            const postGeo = new THREE.CylinderGeometry(0.09, 0.11, 1, 6);
            postGeo.translate(0, 0.5, 0);
            this.disposables.push(discGeo, postGeo);
            const posts = [];
            M.trees.forEach(tr => {
                const c = Math.cos(tr.rotY), s = Math.sin(tr.rotY);
                const bTex = makeBoard(tr, p, t);
                const bMat = new THREE.MeshBasicMaterial({ map: bTex, transparent: true, side: THREE.DoubleSide });
                this.disposables.push(bTex, bMat);
                const board = new THREE.Mesh(boardGeo, bMat);
                const small = tr.members.length <= 2;
                const sc = small ? 0.6 : (tr.isMine ? 1.25 : 0.95);
                board.scale.setScalar(sc);
                // деревянная табличка на двух столбиках перед корнями, чуть откинута назад
                const fz = tr.trunkR * 2.3 + 1.8 * sc;
                const by = 1.08 * sc + 0.55;
                board.position.set(tr.x + fz * s, by, tr.z + fz * c);
                board.rotation.set(-0.12, tr.rotY, 0, 'YXZ');
                this.world.add(board);
                this.pickables.push(board);
                this.instanceMap.set(board.uuid, { kind: 'tree', key: tr.key });
                [-1, 1].forEach(side => {
                    const ox = side * 1.95 * sc;
                    posts.push({ x: tr.x + fz * s + ox * c, z: tr.z + fz * c - ox * s, h: by + 0.6 * sc });
                });

                // тамга-медальон на коре ствола, между корнями и первым человеком на стволе
                if (!small) {
                    const tc = document.createElement('canvas');
                    tc.width = tc.height = 128;
                    const tctx = tc.getContext('2d');
                    tctx.fillStyle = p.labelBg;
                    tctx.beginPath(); tctx.arc(64, 64, 62, 0, Math.PI * 2); tctx.fill();
                    drawTamga(tctx, 64, 64, 52, tr.tamga, p.orn);
                    const tt = canvasTexture(tc);
                    const tm = new THREE.MeshBasicMaterial({ map: tt, transparent: true });
                    this.disposables.push(tt, tm);
                    const disc = new THREE.Mesh(discGeo, tm);
                    const dr = Math.min(1.0, Math.max(0.5, tr.trunkR * 0.4));
                    disc.scale.setScalar(dr);
                    const dy = Math.min(Y0 - 0.9 - dr, Math.max(1.0 + dr, tr._trunkH * 0.16 + dr));
                    const dz = tr.trunkR * trunkFactor(dy / tr._trunkH) * 1.12 + 0.06;
                    disc.position.set(tr.x + dz * s, dy, tr.z + dz * c);
                    disc.rotation.y = tr.rotY;
                    this.world.add(disc);
                }

                // название над кроной — видно издалека
                const title = makeLabel(tr.name, tr.clanName ? `${t.clan}: ${tr.clanName}` : t.treeKind, p, { big: true, ppu: tr.isMine ? 34 : (small ? 70 : 46), highlight: tr.isMine });
                this.track(title);
                title.position.set(tr.x, tr.height + 1.0, tr.z);
                title.userData.alwaysVisible = true;
                this.world.add(title);
                this.pickables.push(title);
                this.instanceMap.set(title.uuid, { kind: 'tree', key: tr.key });
            });
            if (posts.length) {
                const pm = new THREE.InstancedMesh(postGeo, barkMat, posts.length);
                posts.forEach((ps, i) => {
                    dummy.position.set(ps.x, 0, ps.z);
                    dummy.rotation.set(0, 0, 0);
                    dummy.scale.set(1, ps.h, 1);
                    dummy.updateMatrix();
                    pm.setMatrixAt(i, dummy.matrix);
                });
                pm.instanceMatrix.needsUpdate = true;
                this.world.add(pm);
            }
        }

        buildPeople() {
            const M = this.model, p = this.pal, t = this.t;
            // мужчина — круглый голубой плод, женщина — тюльпан; оба крупнее листвы и другого цвета
            const leafGeo = new THREE.SphereGeometry(0.78, 18, 12);
            leafGeo.scale(1, 0.95, 1);
            const tulipGeo = createTulipGeometry();
            tulipGeo.scale(1.15, 1.15, 1.15);
            this.disposables.push(leafGeo, tulipGeo);
            const groups = {
                leaf: { geo: leafGeo, color: p.man, ids: [] },
                leafGold: { geo: leafGeo, color: p.manGold, ids: [] },
                tulip: { geo: tulipGeo, color: p.tulip, ids: [] },
                tulipGold: { geo: tulipGeo, color: p.tulipGold, ids: [] }
            };
            M.positions.forEach((pos, id) => {
                const per = M.byId.get(id);
                const dead = Number(per.is_deceased) === 1;
                const fem = per.gender === 'F';
                groups[(fem ? 'tulip' : 'leaf') + (dead ? 'Gold' : '')].ids.push(id);
            });
            this.personMesh = new Map(); // id → {mesh, index, base matrix}
            const dummy = new THREE.Object3D();
            Object.keys(groups).forEach(k => {
                const g = groups[k];
                if (!g.ids.length) return;
                const mat = new THREE.MeshLambertMaterial({ color: g.color, emissive: g.color, emissiveIntensity: this.dark ? 0.45 : 0.28, side: THREE.DoubleSide });
                this.disposables.push(mat);
                const im = new THREE.InstancedMesh(g.geo, mat, g.ids.length);
                g.ids.forEach((id, i) => {
                    const pos = M.positions.get(id);
                    dummy.position.set(pos.x, pos.y, pos.z);
                    dummy.rotation.set(0, (id * 2.399) % (Math.PI * 2), 0);
                    dummy.scale.setScalar(1);
                    dummy.updateMatrix();
                    im.setMatrixAt(i, dummy.matrix);
                    this.personMesh.set(id, { mesh: im, index: i, base: dummy.matrix.clone() });
                });
                im.instanceMatrix.needsUpdate = true;
                this.world.add(im);
                this.pickables.push(im);
                this.instanceMap.set(im.uuid, { kind: 'person', ids: g.ids });
            });

            // светлый ореол за каждым человеком — отделяет его от листвы (одна отрисовка на всех)
            const haloPos = [];
            M.positions.forEach(pos => { haloPos.push(pos.x, pos.y + 0.05, pos.z); });
            if (haloPos.length) {
                const hg = new THREE.BufferGeometry();
                hg.setAttribute('position', new THREE.Float32BufferAttribute(haloPos, 3));
                const haloTex = makeGlowTexture(this.dark ? 'rgba(255,240,205,0.55)' : 'rgba(255,253,236,0.85)', this.dark ? 0.18 : 0.3);
                const halo = this.track(new THREE.Points(hg, new THREE.PointsMaterial({
                    // размер точки в three ≈ size·tan(fov/2) единиц мира → ~2.7 ед. в поперечнике
                    map: haloTex, size: 5.2, sizeAttenuation: true, transparent: true, depthWrite: false, fog: true
                })));
                halo.renderOrder = 2;
                // вблизи ореол не раздувается: Safari всё равно режет размер точки (~64 px)
                halo.material.onBeforeCompile = (sh) => {
                    sh.vertexShader = sh.vertexShader.replace('#include <logdepthbuf_vertex>', 'gl_PointSize = min(gl_PointSize, 60.0);\n#include <logdepthbuf_vertex>');
                };
                this.world.add(halo);
            }

            // «Вы» — золотое кольцо
            if (M.myPersonId && M.positions.has(M.myPersonId)) {
                const mp = M.positions.get(M.myPersonId);
                const ring = this.track(new THREE.Mesh(new THREE.TorusGeometry(1.25, 0.1, 8, 32), new THREE.MeshBasicMaterial({ color: p.mine })));
                ring.position.set(mp.x, mp.y, mp.z);
                ring.rotation.x = Math.PI / 2;
                this.world.add(ring);
            }

            // кольцо выделения
            this.selRing = this.track(new THREE.Mesh(new THREE.TorusGeometry(1.4, 0.08, 8, 36), new THREE.MeshBasicMaterial({ color: p.linkHi })));
            this.selRing.rotation.x = Math.PI / 2;
            this.selRing.visible = false;
            this.world.add(this.selRing);

            // подписи имён
            M.positions.forEach((pos, id) => {
                const per = M.byId.get(id);
                const role = M.roleText.get(id) || '';
                const yrs = yearsText(per);
                const line2 = [role, yrs].filter(Boolean).join(' · ');
                const isMe = id === M.myPersonId;
                const sp = makeLabel(personName(per) || '?', line2, p, { highlight: isMe, ppu: 74 });
                this.track(sp);
                sp.position.set(pos.x, pos.y + 1.0, pos.z);
                // подпись не прячется в листве верхнего яруса; людей с дальней стороны кроны
                // отсекает updateLabelVisibility (по стороне дерева)
                sp.material.depthTest = false;
                sp.renderOrder = 12;
                sp.userData.base = sp.scale.clone();
                const trP = M.treeByKey.get(pos.treeKey);
                if (trP) {
                    let dx = pos.x - trP.x, dz = pos.z - trP.z;
                    const L = Math.hypot(dx, dz);
                    if (pos.onTrunk || L < 0.5) { dx = Math.sin(trP.rotY); dz = Math.cos(trP.rotY); } else { dx /= L; dz /= L; }
                    sp.userData.face = { tx: trP.x, tz: trP.z, dx, dz };
                }
                sp.userData.personId = id;
                sp.userData.isMe = isMe;
                sp.visible = false;
                this.world.add(sp);
                this.labelSprites.push(sp);
            });
        }

        buildLinks() {
            const M = this.model, p = this.pal;
            this.linkObjs = new Map();
            const pairCount = new Map();
            // в покое — тонкая бледная нить, не перечёркивает сцену; при наведении/выборе — яркая и толще
            const lineMat = new THREE.MeshBasicMaterial({ color: p.link, transparent: true, opacity: this.dark ? 0.5 : 0.45, depthWrite: false });
            const lineMatEx = new THREE.MeshBasicMaterial({ color: p.link, transparent: true, opacity: 0.22, depthWrite: false });
            const hiMat = new THREE.MeshBasicMaterial({ color: p.linkHi });
            const hitMat = new THREE.MeshBasicMaterial({ transparent: true, opacity: 0, depthWrite: false, colorWrite: false });
            const arrowGeo = new THREE.ConeGeometry(0.22, 0.6, 8);
            const arrowHiGeo = new THREE.ConeGeometry(0.42, 1.1, 10);
            this.disposables.push(lineMat, lineMatEx, hiMat, hitMat, arrowGeo, arrowHiGeo);
            const up = new THREE.Vector3(0, 1, 0);
            M.links.forEach(l => {
                const a = M.positions.get(l.wifeId), b = M.positions.get(l.husbandId);
                if (!a || !b) return;
                const pk = [l.fromTree, l.toTree].sort().join('|');
                const n = pairCount.get(pk) || 0;
                pairCount.set(pk, n + 1);
                const A = new THREE.Vector3(a.x, a.y, a.z), B = new THREE.Vector3(b.x, b.y, b.z);
                const d = A.distanceTo(B);
                const mid = A.clone().add(B).multiplyScalar(0.5);
                // невысокая дуга над кронами, а не через всё небо
                mid.y = Math.max(A.y, B.y) + Math.min(9, 2 + d * 0.08);
                // несколько браков между теми же родами — разводим дуги вбок
                const side = new THREE.Vector3(B.z - A.z, 0, A.x - B.x).normalize();
                mid.addScaledVector(side, (n % 2 ? -1 : 1) * Math.ceil(n / 2) * 1.6);
                const curve = new THREE.QuadraticBezierCurve3(A, mid, B);
                const segN = Math.max(24, Math.min(80, Math.round(d / 2)));
                const tube = this.track(new THREE.Mesh(new THREE.TubeGeometry(curve, segN, 0.05, 5, false), l.current ? lineMat : lineMatEx));
                tube.renderOrder = 3;
                const tubeHi = new THREE.Mesh(new THREE.TubeGeometry(curve, segN, 0.14, 6, false), hiMat);
                tubeHi.visible = false;
                this.disposables.push(tubeHi.geometry);
                const hit = new THREE.Mesh(new THREE.TubeGeometry(curve, 20, 0.75, 5, false), hitMat);
                hit.visible = false; // только для попадания мышью: Raycaster видимость не проверяет
                this.disposables.push(hit.geometry);
                // стрелка у мужа: направление «дочь → род мужа»
                const tp = curve.getPoint(0.93), tg = curve.getTangent(0.93).normalize();
                const arrow = new THREE.Mesh(arrowGeo, l.current ? lineMat : lineMatEx);
                arrow.position.copy(tp);
                arrow.quaternion.setFromUnitVectors(up, tg);
                const arrowHi = new THREE.Mesh(arrowHiGeo, hiMat);
                arrowHi.position.copy(tp);
                arrowHi.quaternion.setFromUnitVectors(up, tg);
                arrowHi.visible = false;
                this.world.add(tube, tubeHi, hit, arrow, arrowHi);
                this.pickables.push(hit);
                this.instanceMap.set(hit.uuid, { kind: 'link', key: l.key });
                const label = makeLabel(l.label, l.sub, p, { ppu: 60, highlight: true });
                this.track(label);
                label.material.depthTest = false; // подпись линии не прячется за кронами
                label.renderOrder = 20;
                label.position.copy(curve.getPoint(0.5)).add(new THREE.Vector3(0, 0.5, 0));
                label.visible = false;
                this.world.add(label);
                this.linkObjs.set(l.key, { link: l, tube, tubeHi, arrow, arrowHi, label, curve });
            });
        }

        // Какие линии подсветить: наведённая/выбранная линия, линии выбранного человека или рода
        refreshLinkHighlight() {
            if (!this.linkObjs) return;
            const hov = this.hover, sel = this.selected;
            this.linkObjs.forEach((o, key) => {
                const l = o.link;
                const on = (h) => !!h && (
                    (h.kind === 'link' && h.key === key) ||
                    (h.kind === 'person' && (l.wifeId === h.id || l.husbandId === h.id)) ||
                    (h.kind === 'tree' && (l.fromTree === h.key || l.toTree === h.key)));
                const hi = on(hov) || on(sel);
                o.tubeHi.visible = hi; o.arrowHi.visible = hi;
                o.tube.visible = !hi; o.arrow.visible = !hi;
            });
            this.needsRender = true;
        }

        // ---------- выбор и наведение ----------
        bindPicking() {
            const dom = this.renderer.domElement;
            this._onPMove = (e) => {
                const r = dom.getBoundingClientRect();
                this.mouse.set(((e.clientX - r.left) / r.width) * 2 - 1, -((e.clientY - r.top) / r.height) * 2 + 1);
                this.mouseClient = { x: e.clientX - r.left, y: e.clientY - r.top };
                if (e.pointerType === 'mouse' && !this.cam.dragMode) this._hoverDirty = true;
            };
            this._onPUp = (e) => {
                // выбор — только основной кнопкой/касанием без перетаскивания
                if (this.cam.moved || (e.pointerType === 'mouse' && e.button !== 0) || e.shiftKey) return;
                const r = dom.getBoundingClientRect();
                this.mouse.set(((e.clientX - r.left) / r.width) * 2 - 1, -((e.clientY - r.top) / r.height) * 2 + 1);
                const hit = this.pick();
                if (hit) this.select(hit, true);
                else this.clearSelection();
            };
            this._onLeave = () => { this.mouse.set(-5, -5); this._hoverDirty = true; };
            dom.addEventListener('pointermove', this._onPMove);
            dom.addEventListener('pointerup', this._onPUp);
            dom.addEventListener('pointerleave', this._onLeave);
        }

        pick() {
            if (!this.pickables || !this.pickables.length) return null;
            if (Math.abs(this.mouse.x) > 1 || Math.abs(this.mouse.y) > 1) return null;
            this.raycaster.setFromCamera(this.mouse, this.camera);
            const hits = this.raycaster.intersectObjects(this.pickables, false);
            // толстая невидимая «зона» линии не должна перекрывать человека на её конце
            let link = null;
            for (let i = 0; i < hits.length; i++) {
                const h = hits[i];
                const info = this.instanceMap.get(h.object.uuid);
                if (!info) continue;
                const closeEnough = !link || (h.distance - link.distance) < 3;
                if (info.kind === 'person' && h.instanceId !== undefined) {
                    if (closeEnough) return { kind: 'person', id: info.ids[h.instanceId] };
                    break;
                }
                if (info.kind === 'tree') {
                    if (closeEnough) return { kind: 'tree', key: info.key };
                    break;
                }
                if (info.kind === 'link' && !link) link = { kind: 'link', key: info.key, distance: h.distance };
            }
            return link ? { kind: 'link', key: link.key } : null;
        }

        updateHover() {
            if (!this._hoverDirty) return;
            this._hoverDirty = false;
            const hit = this.pick();
            // «ничего» → «ничего» — тоже без изменений (иначе каждое движение мыши над травой перерисовывает кадр)
            const same = (a, b) => (!a && !b) || (!!a && !!b && a.kind === b.kind && a.id === b.id && a.key === b.key);
            if (same(hit, this.hover)) { this.positionTip(); return; }
            this.setHoverScale(this.hover, 1);
            if (this.hover && this.hover.kind === 'link') this.setLinkLabel(this.hover.key, this.isSelected(this.hover));
            this.hover = hit;
            this.setHoverScale(hit, 1.45);
            if (hit && hit.kind === 'link') this.setLinkLabel(hit.key, true);
            this.renderer.domElement.style.cursor = hit ? 'pointer' : 'grab';
            this.showTip(hit);
            this.refreshLinkHighlight();
            this.needsRender = true;
            this._labelsDirty = true;
        }

        isSelected(h) { return !!(h && this.selected && this.selected.kind === h.kind && this.selected.key === h.key && this.selected.id === h.id); }

        setHoverScale(h, s) {
            if (!h || h.kind !== 'person' || !this.personMesh) return;
            const pm = this.personMesh.get(h.id);
            if (!pm) return;
            const m = pm.base.clone();
            if (s !== 1) m.multiply(new THREE.Matrix4().makeScale(s, s, s));
            pm.mesh.setMatrixAt(pm.index, m);
            pm.mesh.instanceMatrix.needsUpdate = true;
            const lab = this.labelSprites.find(x => x.userData.personId === h.id);
            if (lab) lab.userData.forced = s !== 1 || (this.selected && this.selected.id === h.id);
        }

        setLinkLabel(key, on) {
            const o = this.linkObjs && this.linkObjs.get(key);
            if (o) o.label.visible = !!on;
        }

        showTip(hit) {
            const tip = this.tip;
            if (!hit) { tip.style.display = 'none'; return; }
            const M = this.model, t = this.t;
            tip.textContent = '';
            if (hit.kind === 'person') {
                const p = M.byId.get(hit.id);
                const pos = M.positions.get(hit.id);
                const tr = M.treeByKey.get(pos.treeKey);
                tip.appendChild(el('b', null, personName(p)));
                tip.appendChild(el('span', null, [tr ? tr.name : '', t.gen(pos.gen + 1), yearsText(p)].filter(Boolean).join(' · ')));
            } else if (hit.kind === 'link') {
                const o = this.linkObjs.get(hit.key);
                tip.appendChild(el('b', null, o.link.label));
                tip.appendChild(el('span', null, o.link.sub));
            } else {
                const tr = M.treeByKey.get(hit.key);
                tip.appendChild(el('b', null, tr.name));
                tip.appendChild(el('span', null, [tr.clanName ? `${t.clan}: ${tr.clanName}` : '', t.people(tr.members.length), t.gens(tr.generations)].filter(Boolean).join(' · ')));
            }
            tip.style.display = 'block';
            this.positionTip();
        }

        positionTip() {
            if (!this.tip || this.tip.style.display === 'none') return;
            const W = this.root.clientWidth;
            const x = Math.min(W - 330, this.mouseClient.x + 16);
            this.tip.style.left = Math.max(8, x) + 'px';
            this.tip.style.top = Math.max(8, this.mouseClient.y + 18) + 'px';
        }

        select(hit, fromClick) {
            const M = this.model;
            // снять прошлое выделение
            if (this.selected && this.selected.kind === 'link') this.setLinkLabel(this.selected.key, false);
            if (this.selected && this.selected.kind === 'person') {
                const lab = this.labelSprites.find(x => x.userData.personId === this.selected.id);
                if (lab) lab.userData.forced = false;
            }
            this.selected = hit;
            this.selRing.visible = false;
            if (hit.kind === 'person') {
                const pos = M.positions.get(hit.id);
                this.selRing.position.set(pos.x, pos.y, pos.z);
                this.selRing.visible = true;
                const lab = this.labelSprites.find(x => x.userData.personId === hit.id);
                if (lab) lab.userData.forced = true;
                this.showPersonCard(hit.id);
                if (!fromClick) this.flyToPerson(hit.id);
            } else if (hit.kind === 'link') {
                this.setLinkLabel(hit.key, true);
                this.showLinkCard(hit.key);
            } else if (hit.kind === 'tree') {
                this.showTreeCard(hit.key);
                if (fromClick) this.focusTree(hit.key);
            }
            this.refreshLinkHighlight();
            this.updateLabelVisibility(true);
            this.needsRender = true;
        }

        reselect() {
            const s = this.selected;
            this.selected = null;
            if (!s) return;
            if (s.kind === 'person' && this.model.positions.has(s.id)) this.select(s, true);
            else if (s.kind === 'tree' && this.model.treeByKey.has(s.key)) { this.selected = s; this.showTreeCard(s.key); this.refreshLinkHighlight(); }
            else this.clearSelection();
        }

        clearSelection() {
            if (this.selected && this.selected.kind === 'link') this.setLinkLabel(this.selected.key, false);
            if (this.selected && this.selected.kind === 'person') {
                const lab = this.labelSprites.find(x => x.userData.personId === this.selected.id);
                if (lab) lab.userData.forced = false;
            }
            this.selected = null;
            if (this.selRing) this.selRing.visible = false;
            if (this.card) this.card.style.display = 'none';
            this.refreshLinkHighlight();
            this.updateLabelVisibility(true);
            this.needsRender = true;
        }

        selectPersonById(id) {
            id = Number(id);
            if (!this.model || !this.model.positions.has(id)) return false;
            this.select({ kind: 'person', id }, false);
            return true;
        }

        // ---------- карточки ----------
        openCard() {
            this.card.textContent = '';
            const x = el('button', 'sf3-x', '×');
            x.type = 'button';
            x.setAttribute('aria-label', this.t.close);
            x.addEventListener('click', () => this.clearSelection());
            this.card.appendChild(x);
            this.card.style.display = 'block';
            return this.card;
        }

        actionBtn(parent, text, fn, primary) {
            const b = el('button', 'sf3-btn' + (primary ? ' primary' : ''), text);
            b.type = 'button';
            b.addEventListener('click', fn);
            parent.appendChild(b);
            return b;
        }

        showPersonCard(id) {
            const M = this.model, t = this.t;
            const p = M.byId.get(id);
            const pos = M.positions.get(id);
            const info = M.personInfo.get(id);
            const tr = M.treeByKey.get(pos.treeKey);
            const card = this.openCard();
            card.appendChild(el('h3', null, personName(p)));
            const subs = [];
            if (info.role === 'kelin') {
                const hus = M.byId.get(info.husband);
                subs.push(t.kelinOf(tr.name) + (hus ? ' · ' + personName(hus) : ''));
                if (p.maiden_name) subs.push((this.lang === 'ru' ? 'урожд. ' : 'қыз кезінде — ') + p.maiden_name);
            } else {
                subs.push(tr.name + ' · ' + t.gen(pos.gen + 1));
                const out = M.links.filter(l => l.wifeId === id);
                out.forEach(l => subs.push(l.label));
                const inn = M.links.filter(l => l.husbandId === id);
                inn.forEach(l => subs.push(l.label));
            }
            const yrs = yearsText(p);
            if (yrs) subs.push(yrs + (Number(p.is_deceased) ? ' · ' + t.deceased : ''));
            subs.forEach(s => card.appendChild(el('div', 'sf3-sub', s)));
            card.appendChild(el('div', 'sf3-orn'));
            const uz = el('div', 'sf3-uz');
            uz.appendChild(el('div', 'sf3-sub', t.loading));
            card.appendChild(uz);
            const acts = el('div', 'sf3-actions');
            this.actionBtn(acts, t.ushFull, () => {
                if (this.onOpenUshZhurt) this.onOpenUshZhurt(id);
                else if (typeof window.openUshZhurtModal === 'function') window.openUshZhurtModal(id);
            }, true);
            this.actionBtn(acts, t.profile, () => {
                if (this.onPersonSelect) this.onPersonSelect(p);
                else if (typeof window.selectPerson === 'function') window.selectPerson(id);
            });
            this.actionBtn(acts, t.showOnTree, () => this.flyToPerson(id));
            card.appendChild(acts);
            this.loadUshZhurt(id, uz);
        }

        async loadUshZhurt(id, box) {
            const t = this.t;
            const token = this._uzToken = (this._uzToken || 0) + 1;
            let data = null;
            try {
                const res = await fetch('/api/person/ush-zhurt?id=' + encodeURIComponent(id), { credentials: 'same-origin' });
                data = res.ok ? await res.json() : null;
            } catch (e) { data = null; }
            if (token !== this._uzToken || !box.isConnected) return;
            box.textContent = '';
            box.appendChild(el('h4', null, t.ushZhurt));
            let tables = [];
            if (data && Array.isArray(data.tables) && data.tables.length) {
                tables = data.tables.map(tb => (typeof tb === 'string') ? ((data.groups && data.groups[tb]) || { key: tb, items: [] }) : tb);
            } else if (data && data.groups) {
                tables = Object.keys(data.groups).map(k => data.groups[k]);
            }
            if (!tables.length) { box.appendChild(el('div', 'sf3-sub', t.noData)); return; }
            tables.forEach(tb => {
                const items = Array.isArray(tb.items) ? tb.items : [];
                const title = (this.lang === 'ru' ? (tb.title_ru || tb.title_kk) : (tb.title_kk || tb.title_ru)) || tb.key || '';
                const cnt = tb.total_count || items.length || 0;
                box.appendChild(el('h4', null, `${title} · ${cnt}`));
                const desc = this.lang === 'ru' ? (tb.desc_ru || '') : (tb.desc_kk || '');
                if (desc) box.appendChild(el('div', 'sf3-uz-desc', desc));
                items.slice(0, 6).forEach(it => {
                    const b = el('button', 'sf3-rel');
                    b.type = 'button';
                    b.appendChild(el('span', null, [it.first_name, it.last_name].filter(Boolean).join(' ') || it.full_name || '?'));
                    const term = this.lang === 'ru' ? (it.term_ru || it.term_kk) : (it.term_kk || it.term_ru);
                    if (term) b.appendChild(el('span', 'sf3-term', term));
                    b.addEventListener('click', () => this.selectPersonById(it.id));
                    box.appendChild(b);
                });
                if (items.length > 6 || cnt > 6) box.appendChild(el('div', 'sf3-uz-desc', t.more(Math.max(items.length, cnt) - 6)));
            });
        }

        showLinkCard(key) {
            const t = this.t;
            const o = this.linkObjs.get(key);
            if (!o) return;
            const l = o.link;
            const card = this.openCard();
            card.appendChild(el('div', 'sf3-sub', t.linkKind));
            card.appendChild(el('h3', null, l.label));
            card.appendChild(el('div', 'sf3-sub', l.sub));
            card.appendChild(el('div', 'sf3-orn'));
            const acts = el('div', 'sf3-actions');
            this.actionBtn(acts, t.showWife, () => this.selectPersonById(l.wifeId), true);
            this.actionBtn(acts, t.showHusband, () => this.selectPersonById(l.husbandId));
            card.appendChild(acts);
        }

        showTreeCard(key) {
            const t = this.t, M = this.model;
            const tr = M.treeByKey.get(key);
            if (!tr) return;
            const card = this.openCard();
            card.appendChild(el('div', 'sf3-sub', t.treeKind));
            card.appendChild(el('h3', null, tr.name));
            if (tr.clanName) card.appendChild(el('div', 'sf3-sub', `${t.clan}: ${tr.clanName}`));
            card.appendChild(el('div', 'sf3-sub', tr.tamga ? `${t.tamga}: ${tr.tamga}` : t.tamgaUnknown));
            card.appendChild(el('div', 'sf3-sub', `${t.people(tr.members.length)} · ${t.gens(tr.generations)}`));
            const rootP = M.byId.get(tr.rootId);
            if (rootP) card.appendChild(el('div', 'sf3-sub', (this.lang === 'ru' ? 'Родоначальник: ' : 'Түп атасы: ') + personName(rootP)));
            const linked = M.links.filter(l => l.fromTree === key || l.toTree === key);
            if (linked.length) {
                card.appendChild(el('div', 'sf3-orn'));
                const uz = el('div', 'sf3-uz');
                linked.slice(0, 12).forEach(l => {
                    const b = el('button', 'sf3-rel');
                    b.type = 'button';
                    b.appendChild(el('span', null, l.label));
                    b.addEventListener('click', () => this.select({ kind: 'link', key: l.key }, true));
                    uz.appendChild(b);
                });
                if (linked.length > 12) uz.appendChild(el('div', 'sf3-uz-desc', t.more(linked.length - 12)));
                card.appendChild(uz);
            }
            const acts = el('div', 'sf3-actions');
            this.actionBtn(acts, t.goTree, () => this.focusTree(key), true);
            card.appendChild(acts);
        }

        // ---------- навигация ----------
        focusTree(key, instant) {
            const tr = this.model && this.model.treeByKey.get(key);
            if (!tr) return;
            // верх кроны (молодые поколения) не должен уходить под панели сайта сверху — запас ~20 %
            const target = new THREE.Vector3(tr.x, Math.max(3, tr.height * 0.5), tr.z);
            const fov = this.camera.fov * Math.PI / 180;
            const aspect = this.camera.aspect || 1.6;
            const halfH = tr.height * 0.55;
            const halfW = tr.crownR * 1.1;
            const fit = Math.max(halfH, halfW / aspect) / Math.tan(fov / 2);
            const dist = Math.max(14, fit * 1.3);
            if (instant) {
                this.cam.target.copy(target); this.cam.dist = dist; this.cam.theta = tr.rotY; this.cam.phi = 1.22;
                this.cam.apply();
            } else {
                this.cam.flyTo(target, dist, tr.rotY, 1.22);
            }
            this.needsRender = true;
        }

        // камера — снаружи кроны, с той стороны дерева, где висит человек (а не сквозь листву)
        flyToPerson(id) {
            const M = this.model;
            const pos = M && M.positions.get(id);
            if (!pos) return;
            const tr = M.treeByKey.get(pos.treeKey);
            let theta = null;
            if (tr) {
                const dx = pos.x - tr.x, dz = pos.z - tr.z;
                theta = (pos.onTrunk || Math.hypot(dx, dz) < 0.5) ? tr.rotY : Math.atan2(dx, dz);
            }
            this.cam.flyTo(new THREE.Vector3(pos.x, pos.y, pos.z), 20, theta, 1.25);
        }

        focusMine(instant) {
            if (!this.model) return;
            if (this.model.myTreeKey) this.focusTree(this.model.myTreeKey, instant);
        }

        overview() {
            if (!this.model) return;
            const R = this.model.worldR;
            this.cam.flyTo(new THREE.Vector3(0, 4, 0), R * 2.1 + 20, this.cam.theta, 0.72, 1.4);
            this.needsRender = true;
        }

        // ---------- HUD ----------
        buildHud() {
            const t = this.t;
            ['bar', 'legend', 'tip', 'card'].forEach(k => { if (this[k]) this[k].remove(); });
            // нижняя панель
            const bar = el('div', 'sf3-bar sf3-panel');
            this.actionBtn(bar, t.btnMine, () => { this.focusMine(false); }, true);
            this.actionBtn(bar, t.btnAll, () => this.overview());
            const sel = el('select', 'sf3-select');
            sel.setAttribute('aria-label', t.pickTree);
            sel.addEventListener('change', () => {
                if (sel.value) this.select({ kind: 'tree', key: sel.value }, true);
                sel.value = '';
                sel.blur();
            });
            bar.appendChild(sel);
            this.treeSelect = sel;
            const help = this.actionBtn(bar, '?', () => this.showIntro(true));
            help.setAttribute('aria-label', t.btnHelp);
            help.title = t.btnHelp;
            this.root.appendChild(bar);
            this.bar = bar;

            // легенда
            const lg = el('div', 'sf3-legend sf3-panel');
            [[ICON.leaf, t.legendLeaf], [ICON.tulip, t.legendTulip], [ICON.gold, t.legendGold], [ICON.kelin, t.legendKelin], [ICON.line, t.legendLine]].forEach(([ic, txt]) => {
                const r = el('div', 'sf3-legend-row');
                const i = el('span');
                i.innerHTML = ic; // статичные SVG из кода
                r.appendChild(i);
                r.appendChild(el('span', null, txt));
                lg.appendChild(r);
            });
            this.root.appendChild(lg);
            this.legend = lg;

            this.tip = el('div', 'sf3-tip sf3-panel');
            this.root.appendChild(this.tip);
            this.card = el('div', 'sf3-card sf3-panel');
            this.root.appendChild(this.card);
            if (this.model) this.fillTreeSelect();
        }

        fillTreeSelect() {
            const sel = this.treeSelect;
            if (!sel || !this.model) return;
            sel.textContent = '';
            const o0 = el('option', null, this.t.pickTree);
            o0.value = '';
            sel.appendChild(o0);
            const list = this.model.trees.slice().sort((a, b) => (b.isMine ? 1 : 0) - (a.isMine ? 1 : 0) || b.members.length - a.members.length || String(a.name).localeCompare(String(b.name)));
            list.forEach(tr => {
                const o = el('option', null, `${tr.name} (${tr.members.length})`);
                o.value = tr.key;
                sel.appendChild(o);
            });
        }

        showIntro(force) {
            if (!this.renderer) return;
            if (this.introShown && !force) return;
            this.introShown = true;
            const t = this.t;
            if (this.introWrap) this.introWrap.remove();
            const wrap = el('div', 'sf3-intro-wrap');
            const box = el('div', 'sf3-intro sf3-panel');
            box.setAttribute('role', 'dialog');
            box.setAttribute('aria-modal', 'true');
            box.setAttribute('aria-label', t.introTitle);
            box.appendChild(el('h2', null, t.introTitle));
            box.appendChild(el('div', 'sf3-orn'));
            const ul = el('ul');
            [[ICON.tree, t.introTree], [ICON.branch, t.introBranch], [ICON.leaf, t.introLeaf], [ICON.line, t.introLine], [ICON.hand, t.introClick]].forEach(([ic, txt]) => {
                const li = el('li');
                const i = el('span');
                i.innerHTML = ic; // статичные SVG из кода
                li.appendChild(i);
                li.appendChild(el('span', null, txt));
                ul.appendChild(li);
            });
            box.appendChild(ul);
            box.appendChild(el('div', 'sf3-orn'));
            [t.introMouse, t.introKeys, t.introTouch].forEach(s => box.appendChild(el('div', 'sf3-ctrl', s)));
            const acts = el('div', 'sf3-actions');
            const close = () => { wrap.remove(); this.introWrap = null; this.renderer.domElement.focus({ preventScroll: true }); };
            const b1 = this.actionBtn(acts, t.btnMine, () => { close(); this.focusMine(false); }, true);
            this.actionBtn(acts, t.btnAll, () => { close(); this.overview(); });
            this.actionBtn(acts, t.btnGotIt, close);
            box.appendChild(acts);
            wrap.appendChild(box);
            wrap.addEventListener('click', (e) => { if (e.target === wrap) close(); });
            wrap.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
            this.root.appendChild(wrap);
            this.introWrap = wrap;
            setTimeout(() => { try { b1.focus({ preventScroll: true }); } catch (e) { /* старый Safari */ } }, 30);
        }

        // ---------- рендер ----------
        updateLabelVisibility(force) {
            if (!this.labelSprites || !this.camera) return;
            const cp = this.camera.position;
            const near = 42;
            let changed = false;
            const selTree = this.selected && this.selected.kind === 'person' && this.model.positions.get(this.selected.id)
                ? this.model.positions.get(this.selected.id).treeKey : null;
            // 1) кандидаты: близкие, «я», выбранный/наведённый, люди выбранного рода
            const cand = [];
            for (let i = 0; i < this.labelSprites.length; i++) {
                const sp = this.labelSprites[i];
                const d = sp.position.distanceTo(cp);
                let vis = d < near || sp.userData.forced || (sp.userData.isMe && d < 140);
                if (!vis && selTree && d < near * 1.6) {
                    const pos = this.model.positions.get(sp.userData.personId);
                    vis = pos && pos.treeKey === selTree;
                }
                // человек с дальней стороны кроны (листва между ним и зрителем) — без подписи
                const f = sp.userData.face;
                if (vis && f && !sp.userData.forced && !sp.userData.isMe) {
                    const cx = cp.x - f.tx, cz = cp.z - f.tz, cl = Math.hypot(cx, cz) || 1;
                    if ((cx * f.dx + cz * f.dz) / cl < -0.15) vis = false;
                }
                if (vis) {
                    // вблизи подпись не растёт бесконечно: дальше ~26 ед. — как есть, ближе — постоянный экранный размер
                    const b = sp.userData.base;
                    if (b) { const kk = Math.min(1, Math.max(0.25, d / 26)); sp.scale.set(b.x * kk, b.y * kk, 1); }
                    cand.push({ sp, d, pri: sp.userData.forced ? 0 : (sp.userData.isMe ? 1 : 2) });
                } else if (sp.visible) { sp.visible = false; changed = true; }
            }
            // 2) без наложений: в порядке важности и близости, перекрывающие подписи прячем
            const W = this.renderer ? this.renderer.domElement.clientWidth : 1;
            const H = this.renderer ? this.renderer.domElement.clientHeight : 1;
            const k = H / (2 * Math.tan(this.camera.fov * Math.PI / 360));
            cand.sort((a, b) => a.pri - b.pri || a.d - b.d);
            const placed = [];
            const v = this._tmpV || (this._tmpV = new THREE.Vector3());
            for (let i = 0; i < cand.length; i++) {
                const { sp, pri } = cand[i];
                v.copy(sp.position).project(this.camera);
                let vis = v.z < 1 && Math.abs(v.x) < 1.2 && Math.abs(v.y) < 1.2;
                if (vis) {
                    const dist = Math.max(0.5, cand[i].d);
                    const w = sp.scale.x * k / dist, h = sp.scale.y * k / dist;
                    const x = (v.x + 1) / 2 * W - w / 2, y = (1 - v.y) / 2 * H - h; // низ подписи — над человеком
                    const r = { x: x - 3, y: y - 2, w: w + 6, h: h + 4 };
                    const clash = placed.some(q => r.x < q.x + q.w && q.x < r.x + r.w && r.y < q.y + q.h && q.y < r.y + r.h);
                    if (clash && pri > 1) vis = false;
                    if (vis) placed.push(r);
                }
                if (sp.visible !== vis) { sp.visible = vis; changed = true; }
            }
            if (changed || force) this.needsRender = true;
        }

        onWindowResize() {
            if (!this.renderer || !this.container) return;
            const w = this.container.clientWidth, h = this.container.clientHeight;
            if (!w || !h) return;
            const s = this.renderer.getSize(new THREE.Vector2());
            if (s.x === w && s.y === h) return;
            this.camera.aspect = w / h;
            this.camera.updateProjectionMatrix();
            this.renderer.setSize(w, h);
            this.needsRender = true;
        }

        animate() {
            this.animationFrameId = requestAnimationFrame(() => this.animate());
            const dt = Math.min(0.05, this.clock.getDelta());
            // вкладка скрыта или 3D-режим не показан — не рисуем
            if (this._ctxLost || document.hidden || !this.container.isConnected || this.container.offsetParent === null) {
                this._wasHidden = true;
                return;
            }
            if (this._wasHidden) { this._wasHidden = false; this.needsRender = true; this.onWindowResize(); }
            if (this.cam.update(dt)) this.needsRender = true;
            this.frame++;
            if (this._labelsDirty) {
                const moving = !!(this.cam.tween || this.cam.isKeyMoving() || this.cam.dragMode);
                if (!moving || this.frame % 4 === 0) { this._labelsDirty = false; this.updateLabelVisibility(false); }
            }
            this.updateHover();
            if (this.needsRender) {
                // небо «бесконечно далеко»: купол и светила идут за камерой
                const cp = this.camera.position;
                if (this.sky) this.sky.position.copy(cp);
                (this.skyExtras || []).forEach(o => { o.position.copy(cp).add(o.userData.skyOffset); });
                this.renderer.render(this.scene, this.camera);
                this.needsRender = this.cam.isKeyMoving() || !!this.cam.tween;
            }
        }

        destroy() {
            if (this.animationFrameId) cancelAnimationFrame(this.animationFrameId);
            if (this.cam) this.cam.destroy();
            window.removeEventListener('resize', this._onResize);
            if (this._ro) this._ro.disconnect();
            if (this._themeObs) this._themeObs.disconnect();
            if (this._mq && this._mq.removeEventListener) this._mq.removeEventListener('change', this._mqHandler);
            if (this.renderer) {
                const dom = this.renderer.domElement;
                dom.removeEventListener('pointermove', this._onPMove);
                dom.removeEventListener('pointerup', this._onPUp);
                dom.removeEventListener('pointerleave', this._onLeave);
                dom.removeEventListener('webglcontextlost', this._onCtxLost);
                dom.removeEventListener('webglcontextrestored', this._onCtxRestored);
                this.clearWorld();
                this.renderer.dispose();
            }
            if (this.root) this.root.remove();
        }
    }

    ShezhireForest3D.buildForestModel = buildForestModel;
    ShezhireForest3D.helpers = { kkPlural, kkDative, ruPlural, surnameBase, personName, birthName, TXT };
    return ShezhireForest3D;
}));
