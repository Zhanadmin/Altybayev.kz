/*!
 * TASK-006 · Шежіре-Ағаш (стиль иллюстрированной родословной) — генератор SVG из базы.
 * Стиль повторяет согласованный эскиз public/assets/img/rodoslov_sketch_zhandos.svg (13.11.2026).
 *
 * Вход: данные /api/tree — { persons: [...], marriages: [...], user_person_id }.
 * Выход: RodoslovGenerator.build(data, opts) → { svg, width, height, positions, focalId, stats }.
 *        RodoslovGenerator.mount(container, data, opts) — вставляет SVG и вешает клик по ленте.
 *
 * Раскладка:
 *  - ярус = поколение: старшие у корней (внизу), младшие в кроне (вверху);
 *  - главный ствол — потомки старшего предка по отцовской линии фокусной персоны;
 *  - остальные роды (род матери, роды жён, прочие) — отдельные стволы слева и справа,
 *    каждая персона рисуется один раз; родство между стволами — золотые нити;
 *  - пары стоят рядом, между ними кольца, под кольцами лента с датой брака;
 *  - сыновья — лист, дочери и жёны — тюльпан, ушедшие — золотая лента и золотые листья.
 * Все тексты экранируются (escapeXml), поэтому SVG безопасно вставлять через innerHTML.
 */
(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.RodoslovGenerator = factory();
    }
})(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    // ---------- геометрия ----------
    var SLOT = 262;          // шаг ленты внутри семьи (лента с хвостами — 248 px)
    var UNIT_GAP = 46;       // зазор между семьями в начальной (контурной) раскладке
    var SIB_GAP = 12;        // плотная раскладка: зазор между братьями и сёстрами
    var FAM_GAP = 36;        // плотная раскладка: зазор между разными семьями одного рода
    var TREE_GAP = 70;       // плотная раскладка: зазор между разными родами
    var TRUNK_W = 300;       // место под ствол рода на ярусах ниже его корня
    var SMALL_TREE = 6;      // род до 6 человек вставляется прямо рядом со связью
    var FOCAL_WT = 6;        // вес прямой линии фокусной персоны в плотной раскладке
    var SWEEPS = 8;        // число проходов «вверх-вниз» плотной раскладки
    var TIER = 330;          // шаг яруса (поколения)
    var MARGIN_X = 260;      // поля слева и справа
    var HEADER_H = 300;      // место под заголовок
    var GROUND_TO_TIER0 = 340; // от линии земли до нижнего яруса
    var FOOTER_H = 250;      // земля, подписи стволов, легенда
    var buildCounter = 0;    // счётчик построений — префикс id в <defs>

    // ---------- утилиты ----------
    function escapeXml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function num(v) {
        var n = parseInt(v, 10);
        return isFinite(n) && n > 0 ? n : 0;
    }

    function r1(v) {
        return Math.round(v * 10) / 10;
    }

    function hash(n) {
        // детерминированный «шум» для поворотов листьев
        var x = (Math.floor(Math.abs(n) * 1000) * 2654435761) >>> 0;
        x = (x ^ (x >>> 15)) >>> 0;
        return (x % 1000) / 1000;
    }

    function clamp(v, a, b) {
        return Math.max(a, Math.min(b, v));
    }

    function cleanName(s) {
        return String(s == null ? '' : s).replace(/[,;]+\s*$/, '').replace(/\s+/g, ' ').trim();
    }

    var FEM_END = /(ова|ева|ёва|ина|ына|ая)$/i;
    var MASC_END = /(ов|ев|ёв|ин|ын)$/i;

    // Фамилия по полу: «Сатыбалды Айткалиева» → «Айткалиев», «Жансая Алтыбаев» → «Алтыбаева».
    function genderSurname(surname, gender) {
        var s = cleanName(surname);
        if (!s) return s;
        if (gender === 'M' && /(ова|ева|ёва|ина|ына)$/i.test(s)) return s.slice(0, -1);
        if (gender === 'F' && MASC_END.test(s) && !FEM_END.test(s)) return s + 'а';
        return s;
    }

    function mascSurname(surname) {
        return genderSurname(surname, 'M');
    }

    // Казахское множественное число по гармонии гласных: Алтыбаев → Алтыбаевтар, Айткалиев → Айткалиевтер.
    function clanPlural(surname) {
        var s = mascSurname(surname);
        if (!s) return '';
        // гармония по корню: русский суффикс -ов/-ев/-ин не учитываем (Алтыба|ев → тар)
        var stem = s.toLowerCase().replace(/(ов|ев|ёв|ин|ын)$/, '') || s.toLowerCase();
        var m = stem.match(/[аәеёиіоөуұүыэюя]/g);
        var last = m ? m[m.length - 1] : 'а';
        return s + ('әеёиіөүэ'.indexOf(last) >= 0 ? 'тер' : 'тар');
    }

    function fmtDate(dateStr, year) {
        var d = String(dateStr || '').trim();
        var m = d.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (m && m[1] !== '0000') {
            if (m[2] === '00') return m[1];
            if (m[3] === '00') return m[2] + '.' + m[1];
            return String(parseInt(m[3], 10)) + '.' + m[2] + '.' + m[1];
        }
        var y = num(year);
        return y ? String(y) : '';
    }

    function sortKey(p) {
        var d = String(p.birthDate || '');
        if (/^\d{4}-\d{2}-\d{2}/.test(d) && d.slice(0, 4) !== '0000') return d.slice(0, 10);
        if (p.birthYear) return ('000' + p.birthYear).slice(-4) + '-99-99';
        return '9999-99-99';
    }

    // ---------- модель ----------
    function buildModel(data, opts) {
        var persons = new Map();
        (data.persons || []).forEach(function (p) {
            if (!p || typeof p !== 'object') return;
            var id = num(p.id);
            if (!id || num(p.is_deleted)) return;
            var gender = p.gender === 'F' ? 'F' : (p.gender === 'M' ? 'M' : 'U');
            var deceased = !!(num(p.is_deceased) || p.death_date || num(p.death_year));
            var last = opts.fixSurnames ? genderSurname(p.last_name, gender) : cleanName(p.last_name);
            var maiden = cleanName(p.maiden_name);
            persons.set(id, {
                id: id,
                gedcom: String(p.gedcom_id || ''),
                first: cleanName(p.first_name),
                last: last,
                rawLast: cleanName(p.last_name),
                maiden: maiden && maiden !== last ? maiden : '',
                gender: gender,
                deceased: deceased,
                birthDate: p.birth_date || '',
                birthYear: num(p.birth_year),
                deathDate: p.death_date || '',
                deathYear: num(p.death_year),
                clan: cleanName(p.clan_ru),
                fatherId: num(p.father_id),
                motherId: num(p.mother_id)
            });
        });

        persons.forEach(function (p) {
            if (p.fatherId && !persons.has(p.fatherId)) p.fatherId = 0;
            if (p.motherId && !persons.has(p.motherId)) p.motherId = 0;
        });

        var spouses = new Map();
        var marriageDate = new Map();
        function addSpouse(a, b) {
            if (!spouses.has(a)) spouses.set(a, []);
            if (spouses.get(a).indexOf(b) < 0) spouses.get(a).push(b);
        }
        (data.marriages || []).forEach(function (m) {
            if (!m || typeof m !== 'object') return;
            var h = num(m.husband_id);
            var w = num(m.wife_id);
            if (!h || !w || h === w || !persons.has(h) || !persons.has(w)) return;
            addSpouse(h, w);
            addSpouse(w, h);
            var key = h + ':' + w;
            var date = m.marriage_date || m.start_date || '';
            if (date && !marriageDate.has(key)) marriageDate.set(key, date);
        });
        // Пара без записи в marriages, но с общими детьми — тоже пара.
        persons.forEach(function (p) {
            if (p.fatherId && p.motherId) {
                addSpouse(p.fatherId, p.motherId);
                addSpouse(p.motherId, p.fatherId);
            }
        });

        var children = new Map();
        persons.forEach(function (p) {
            [p.fatherId, p.motherId].forEach(function (pid) {
                if (!pid) return;
                if (!children.has(pid)) children.set(pid, []);
                children.get(pid).push(p.id);
            });
        });
        children.forEach(function (list) {
            list.sort(function (a, b) {
                var ka = sortKey(persons.get(a));
                var kb = sortKey(persons.get(b));
                return ka < kb ? -1 : (ka > kb ? 1 : a - b);
            });
        });

        return {
            persons: persons,
            spousesOf: function (id) { return spouses.get(id) || []; },
            childrenOf: function (id) { return children.get(id) || []; },
            marriageDate: function (h, w) { return marriageDate.get(h + ':' + w) || marriageDate.get(w + ':' + h) || ''; }
        };
    }

    // Поколения: супруги — один ярус, ребёнок — на ярус выше родителя.
    function assignGenerations(model, focalId) {
        var gen = new Map();
        var ids = Array.from(model.persons.keys());
        if (focalId && model.persons.has(focalId)) ids.unshift(focalId);
        ids.forEach(function (seed) {
            if (gen.has(seed)) return;
            gen.set(seed, 0);
            var queue = [seed];
            var compMin = 0;
            var comp = [seed];
            while (queue.length) {
                var id = queue.shift();
                var g = gen.get(id);
                var p = model.persons.get(id);
                var next = [];
                if (p.fatherId) next.push([p.fatherId, g - 1]);
                if (p.motherId) next.push([p.motherId, g - 1]);
                model.spousesOf(id).forEach(function (s) { next.push([s, g]); });
                model.childrenOf(id).forEach(function (c) { next.push([c, g + 1]); });
                next.forEach(function (pair) {
                    if (!gen.has(pair[0])) {
                        gen.set(pair[0], pair[1]);
                        compMin = Math.min(compMin, pair[1]);
                        comp.push(pair[0]);
                        queue.push(pair[0]);
                    }
                });
            }
            // каждый связный род — со своего нижнего яруса (0)
            comp.forEach(function (id) { gen.set(id, gen.get(id) - compMin); });
        });
        return gen;
    }

    // Старший предок по отцу (если отца нет — по матери), пока предок ещё не размещён.
    function climbRoot(model, id, placed) {
        var cur = id;
        var guard = 0;
        while (guard++ < 200) {
            var p = model.persons.get(cur);
            var up = (p.fatherId && !placed.has(p.fatherId)) ? p.fatherId
                : ((p.motherId && !placed.has(p.motherId)) ? p.motherId : 0);
            if (!up) break;
            cur = up;
        }
        return cur;
    }

    // ---------- семьи (узлы раскладки) ----------
    function buildUnit(model, pid, level, placed, parentUnit, unitOf) {
        var p = model.persons.get(pid);
        placed.add(pid);
        var sp = model.spousesOf(pid).filter(function (s) { return !placed.has(s); });
        sp.forEach(function (s) { placed.add(s); });

        var members;
        if (!sp.length) {
            members = [pid];
        } else if (p.gender === 'F') {
            members = sp.slice(0, 1).concat([pid], sp.slice(1));
        } else if (sp.length >= 2) {
            members = [sp[0], pid].concat(sp.slice(1));
        } else {
            members = [pid, sp[0]];
        }

        var unit = {
            blood: pid,
            members: members,
            level: level,
            parent: parentUnit,
            children: [],
            childAnchor: [],
            offsets: [],
            descendants: 0
        };
        members.forEach(function (m) { unitOf.set(m, unit); });

        var kids = [];
        model.childrenOf(pid).forEach(function (c) { if (kids.indexOf(c) < 0) kids.push(c); });
        sp.forEach(function (s) {
            model.childrenOf(s).forEach(function (c) {
                var cp = model.persons.get(c);
                var other = cp.fatherId === s ? cp.motherId : cp.fatherId;
                if ((!other || other === pid) && kids.indexOf(c) < 0) kids.push(c);
            });
        });
        kids = kids.filter(function (c) { return !placed.has(c); });
        kids.sort(function (a, b) {
            var ka = sortKey(model.persons.get(a));
            var kb = sortKey(model.persons.get(b));
            return ka < kb ? -1 : (ka > kb ? 1 : a - b);
        });
        kids.forEach(function (c) { placed.add(c); });
        kids.forEach(function (c) {
            placed.delete(c);
            var cu = buildUnit(model, c, level + 1, placed, unit, unitOf);
            unit.children.push(cu);
            unit.descendants += 1 + cu.descendants;
        });
        return unit;
    }

    // ---------- раскладка с контурами (без наложений по ярусам) ----------
    function contourOf(unit) {
        var half = unit.members.length * SLOT / 2;
        var c = {};
        c[unit.level] = [-half, half];
        return c;
    }

    function shiftContour(c, dx) {
        var out = {};
        Object.keys(c).forEach(function (k) { out[k] = [c[k][0] + dx, c[k][1] + dx]; });
        return out;
    }

    function mergeContour(a, b) {
        var out = {};
        Object.keys(a).forEach(function (k) { out[k] = a[k].slice(); });
        Object.keys(b).forEach(function (k) {
            if (out[k]) {
                out[k] = [Math.min(out[k][0], b[k][0]), Math.max(out[k][1], b[k][1])];
            } else {
                out[k] = b[k].slice();
            }
        });
        return out;
    }

    // На сколько сдвинуть b вправо, чтобы не задеть a (с зазором gap).
    function separation(a, b, gap) {
        var need = -Infinity;
        Object.keys(b).forEach(function (k) {
            if (a[k]) need = Math.max(need, a[k][1] - b[k][0] + gap);
        });
        return need;
    }

    function layoutUnit(unit) {
        var own = contourOf(unit);
        if (!unit.children.length) {
            unit.contour = own;
            return own;
        }
        var acc = null;
        var offsets = [];
        unit.children.forEach(function (cu, i) {
            var c = layoutUnit(cu);
            if (i === 0) {
                offsets.push(0);
                acc = c;
            } else {
                var dx = separation(acc, c, UNIT_GAP);
                if (!isFinite(dx)) dx = 0;
                dx = Math.max(dx, offsets[i - 1]);
                offsets.push(dx);
                acc = mergeContour(acc, shiftContour(c, dx));
            }
        });
        var mid = (offsets[0] + offsets[offsets.length - 1]) / 2;
        unit.offsets = offsets.map(function (o) { return o - mid; });
        unit.contour = mergeContour(own, shiftContour(acc, -mid));
        return unit.contour;
    }

    function assignX(unit, cx, out) {
        unit.cx = cx;
        var n = unit.members.length;
        unit.memberX = unit.members.map(function (m, i) {
            return cx - (n * SLOT) / 2 + SLOT / 2 + i * SLOT;
        });
        out.push(unit);
        unit.children.forEach(function (cu, i) { assignX(cu, cx + unit.offsets[i], out); });
    }

    // ---------- плотная раскладка по ярусам (TASK-006, замечание 17:18) ----------
    // Смещение ленты i от центра семьи.
    function memberOffset(unit, i) {
        return -unit.members.length * SLOT / 2 + SLOT / 2 + i * SLOT;
    }

    function setUnitX(unit, cx) {
        unit.cx = cx;
        unit.memberX = unit.members.map(function (m, i) { return cx + memberOffset(unit, i); });
    }

    // Смещение от центра семьи точки, откуда растёт ветвь к ребёнку (между родителями или над кровным).
    function anchorOffset(model, unit, childId) {
        var c = model.persons.get(childId);
        var iF = c ? unit.members.indexOf(c.fatherId) : -1;
        var iM = c ? unit.members.indexOf(c.motherId) : -1;
        if (iF >= 0 && iM >= 0) return (memberOffset(unit, iF) + memberOffset(unit, iM)) / 2;
        if (iF >= 0) return memberOffset(unit, iF);
        if (iM >= 0) return memberOffset(unit, iM);
        return memberOffset(unit, Math.max(0, unit.members.indexOf(unit.blood)));
    }

    // Связь рода t с уже размещёнными родами: ребёнок, супруг или родитель вне t.
    function findLink(model, t, unitOf, done) {
        var types = ['child', 'spouse', 'parent'];
        for (var k = 0; k < types.length; k++) {
            for (var a = 0; a < t.units.length; a++) {
                var u = t.units[a];
                for (var b = 0; b < u.members.length; b++) {
                    var m = u.members[b];
                    var p = model.persons.get(m);
                    var cand = types[k] === 'child' ? model.childrenOf(m)
                        : (types[k] === 'spouse' ? model.spousesOf(m) : [p.fatherId, p.motherId]);
                    for (var c = 0; c < cand.length; c++) {
                        var ou = cand[c] ? unitOf.get(cand[c]) : null;
                        if (ou && ou.tree !== t && done.has(ou.tree)) {
                            return { unit: u, inId: m, outUnit: ou, outId: cand[c], type: types[k] };
                        }
                    }
                }
            }
        }
        return null;
    }

    // Плотная укладка одного яруса: порядок сохраняется, ленты не накладываются,
    // сумма квадратов отклонений от желаемых x минимальна (изотоническая регрессия, PAVA).
    function packLevel(items, ideals, gapFn) {
        var n = items.length;
        if (!n) return [];
        var c = [0];
        for (var i = 1; i < n; i++) {
            c[i] = c[i - 1] + (items[i - 1].w + items[i].w) / 2 + gapFn(items[i - 1], items[i]);
        }
        var blocks = [];
        for (var j = 0; j < n; j++) {
            var wt = items[j].wt || 1;
            blocks.push({ sum: (ideals[j] - c[j]) * wt, wt: wt, start: j, end: j });
            while (blocks.length > 1) {
                var q = blocks[blocks.length - 1];
                var pr = blocks[blocks.length - 2];
                if (pr.sum / pr.wt <= q.sum / q.wt) break;
                pr.sum += q.sum;
                pr.wt += q.wt;
                pr.end = q.end;
                blocks.pop();
            }
        }
        var xs = new Array(n);
        blocks.forEach(function (bl) {
            var v = bl.sum / bl.wt;
            for (var k = bl.start; k <= bl.end; k++) xs[k] = v + c[k];
        });
        return xs;
    }

    // Все роды на одном полотне: род матери и род жены вставляются рядом со своей связью,
    // дети стоят плотной группой над родителями, родители — под своими детьми.
    function compactLayout(model, trees, unitOf, spliceDepth, focalId) {
        var main = trees[0];
        // прямая линия фокусной персоны (предки, её семья, дети) держится ровнее остальных
        var focalLine = new Set();
        var fu = unitOf.get(focalId);
        for (var g = 0; fu && g < 200; g++) { focalLine.add(fu); fu = fu.parent; }
        var fself = unitOf.get(focalId);
        if (fself) fself.children.forEach(function (c) { focalLine.add(c); });
        var done = new Set([main]);
        var linkOf = new Map();      // семья → связь её рода
        var splices = new Map();     // семья → роды, вставляемые до/после неё
        var linked = [];
        var pending = trees.slice(1);
        var progress = true;
        while (pending.length && progress) {
            progress = false;
            pending = pending.filter(function (t) {
                var link = findLink(model, t, unitOf, done);
                if (!link) return true;
                linkOf.set(link.unit, link);
                var ou = link.outUnit;
                var side = ou.members.indexOf(link.outId) >= ou.members.indexOf(ou.blood) ? 'after' : 'before';
                link.side = side;
                // большой род вставляем после поддерева предка связи, чтобы не раздвигать братьев
                var at = ou;
                var size = 0;
                t.units.forEach(function (u) { size += u.members.length; });
                if (size > SMALL_TREE) {
                    for (var d = 0; d < (spliceDepth || 0) && at.parent; d++) at = at.parent;
                }
                if (!splices.has(at)) splices.set(at, { before: [], after: [] });
                splices.get(at)[side].push(t);
                done.add(t);
                linked.push(t);
                progress = true;
                return false;
            });
        }
        var unlinked = pending;

        function linkIdeal(link) {
            var ou = link.outUnit;
            var u = link.unit;
            var outX = ou.cx + memberOffset(ou, ou.members.indexOf(link.outId));
            if (link.type === 'child') return outX - anchorOffset(model, u, link.outId);
            var inOff = memberOffset(u, u.members.indexOf(link.inId));
            if (link.type === 'spouse') return outX + (link.side === 'after' ? 1 : -1) * (SLOT + FAM_GAP) - inOff;
            return ou.cx + anchorOffset(model, ou, link.inId) - inOff;
        }

        // начальные положения: контурная раскладка рода + сдвиг к связи
        var right = -Infinity;
        function shiftTree(t, dx) {
            t.units.forEach(function (u) { setUnitX(u, u.cx + dx); });
        }
        function treeRight(t) {
            var r = -Infinity;
            t.units.forEach(function (u) { r = Math.max(r, u.cx + u.members.length * SLOT / 2); });
            return r;
        }
        function treeLeft(t) {
            var l = Infinity;
            t.units.forEach(function (u) { l = Math.min(l, u.cx - u.members.length * SLOT / 2); });
            return l;
        }
        right = treeRight(main);
        linked.forEach(function (t) {
            var link = null;
            t.units.forEach(function (u) { if (linkOf.has(u)) link = linkOf.get(u); });
            shiftTree(t, linkIdeal(link) - link.unit.cx);
            right = Math.max(right, treeRight(t));
        });
        unlinked.forEach(function (t) {
            shiftTree(t, right + TREE_GAP - treeLeft(t));
            right = treeRight(t);
        });

        // порядок семей слева направо на каждом ярусе — обход в глубину с вставками родов;
        // место ствола на нижних ярусах выбирается заново на каждом проходе — по положению корня
        var levels = {};
        var trunkLevels = {};
        function push(item) {
            var bucket = item.trunk ? trunkLevels : levels;
            (bucket[item.level] = bucket[item.level] || []).push(item);
        }
        function visitUnit(u) {
            var sp = splices.get(u);
            if (sp) sp.before.forEach(visitTree);
            push({ unit: u, level: u.level, w: u.members.length * SLOT, tree: u.tree, wt: focalLine.has(u) ? FOCAL_WT : 1 });
            u.children.forEach(visitUnit);
            if (sp) sp.after.forEach(visitTree);
        }
        function visitTree(t) {
            t.trunkItems = [];
            // до земли идут только стволы рода отца, матери и жены; у прочих родов — короткий отросток
            t.grounded = t.kind !== 'other' || t.root.level === 0;
            for (var l = 0; t.grounded && l < t.root.level; l++) {
                var ti = { trunk: t, level: l, w: TRUNK_W, tree: t, x: t.root.cx, wt: 0.6 };
                t.trunkItems.push(ti);
                push(ti);
            }
            visitUnit(t.root);
        }
        visitTree(main);
        unlinked.forEach(visitTree);

        function gapFn(a, b) {
            if (a.unit && b.unit && a.unit.parent && a.unit.parent === b.unit.parent) return SIB_GAP;
            return a.tree === b.tree ? FAM_GAP : TREE_GAP;
        }
        function bloodX(u) {
            return u.cx + memberOffset(u, u.members.indexOf(u.blood));
        }
        // над родителями: группа братьев и сестёр плотно, центр кровных лент — над точкой ветви
        function parentIdeal(u) {
            var p = u.parent;
            var kids = p.children;
            var anchor = 0;
            kids.forEach(function (k) { anchor += p.cx + anchorOffset(model, p, k.blood); });
            anchor /= kids.length;
            var pos = 0;
            var centers = [];
            kids.forEach(function (k, i) {
                if (i) pos += SIB_GAP;
                centers.push(pos + k.members.length * SLOT / 2);
                pos += k.members.length * SLOT;
            });
            var meanBlood = 0;
            kids.forEach(function (k, i) { meanBlood += centers[i] + memberOffset(k, k.members.indexOf(k.blood)); });
            meanBlood /= kids.length;
            return centers[kids.indexOf(u)] + anchor - meanBlood;
        }
        // под детьми: точка ветви — посередине между крайними детьми
        function childIdeal(u) {
            var lo = Infinity, hi = -Infinity, off = 0;
            u.children.forEach(function (k) {
                var bx = bloodX(k);
                lo = Math.min(lo, bx);
                hi = Math.max(hi, bx);
                off += anchorOffset(model, u, k.blood);
            });
            return (lo + hi) / 2 - off / u.children.length;
        }
        // null — у ленты нет привязки (одиночка без родителей и детей): её прижимаем к соседу
        function idealOf(item, dir) {
            if (item.trunk) return item.trunk.root.cx;
            var u = item.unit;
            if (linkOf.has(u)) return linkIdeal(linkOf.get(u));
            var up = u.parent ? parentIdeal(u) : null;
            var down = u.children.length ? childIdeal(u) : null;
            if (dir === 'up') return up !== null ? up : down;
            return down !== null ? down : up;
        }
        function fillFree(items, ideals) {
            var i, any = -1;
            for (i = 0; i < items.length; i++) if (ideals[i] !== null) { any = i; break; }
            if (any < 0) {
                // на ярусе нет ни одной привязки — оставляем как есть
                for (i = 0; i < items.length; i++) ideals[i] = items[i].trunk ? items[i].x : items[i].unit.cx;
                return;
            }
            for (i = any - 1; i >= 0; i--) {
                ideals[i] = ideals[i + 1] - (items[i].w + items[i + 1].w) / 2 - gapFn(items[i], items[i + 1]);
            }
            for (i = any + 1; i < items.length; i++) {
                if (ideals[i] === null) ideals[i] = ideals[i - 1] + (items[i - 1].w + items[i].w) / 2 + gapFn(items[i - 1], items[i]);
            }
        }
        function itemsOf(k) {
            var units = levels[k] || [];
            var tr = (trunkLevels[k] || []).slice().sort(function (a, b) { return a.trunk.root.cx - b.trunk.root.cx; });
            if (!tr.length) return units;
            var out = [];
            var j = 0;
            units.forEach(function (it) {
                while (j < tr.length && tr[j].trunk.root.cx < it.unit.cx) out.push(tr[j++]);
                out.push(it);
            });
            while (j < tr.length) out.push(tr[j++]);
            return out;
        }
        var keySet = {};
        Object.keys(levels).concat(Object.keys(trunkLevels)).forEach(function (k) { keySet[k] = 1; });
        var keys = Object.keys(keySet).map(Number).sort(function (a, b) { return a - b; });
        function sweep(dir) {
            var ks = dir === 'up' ? keys : keys.slice().reverse();
            ks.forEach(function (k) {
                var items = itemsOf(k);
                var ideals = items.map(function (it) { return idealOf(it, dir); });
                fillFree(items, ideals);
                var xs = packLevel(items, ideals, gapFn);
                items.forEach(function (it, i) {
                    if (it.trunk) it.x = xs[i];
                    else setUnitX(it.unit, xs[i]);
                });
            });
        }
        // последний проход — сверху вниз: родители встают под центр своих детей
        // (широкие верхние ярусы задают места, узкие нижние подстраиваются)
        for (var s = 0; s < SWEEPS; s++) { sweep('up'); sweep('down'); }

        var minX = Infinity, maxX = -Infinity, maxLevel = 0;
        keys.forEach(function (k) {
            itemsOf(k).forEach(function (it) {
                var x = it.trunk ? it.x : it.unit.cx;
                minX = Math.min(minX, x - it.w / 2);
                maxX = Math.max(maxX, x + it.w / 2);
                if (it.unit) maxLevel = Math.max(maxLevel, it.unit.level);
            });
        });
        return { minX: minX, maxX: maxX, maxLevel: maxLevel };
    }

    // ---------- рисование ----------
    function bezierPoint(p0, p1, p2, p3, t) {
        var u = 1 - t;
        return [
            u * u * u * p0[0] + 3 * u * u * t * p1[0] + 3 * u * t * t * p2[0] + t * t * t * p3[0],
            u * u * u * p0[1] + 3 * u * u * t * p1[1] + 3 * u * t * t * p2[1] + t * t * t * p3[1]
        ];
    }

    function bezierTangent(p0, p1, p2, p3, t) {
        var u = 1 - t;
        return [
            3 * u * u * (p1[0] - p0[0]) + 6 * u * t * (p2[0] - p1[0]) + 3 * t * t * (p3[0] - p2[0]),
            3 * u * u * (p1[1] - p0[1]) + 6 * u * t * (p2[1] - p1[1]) + 3 * t * t * (p3[1] - p2[1])
        ];
    }

    // Сужающаяся залитая ветвь вдоль кубической кривой (ширина по нормали, без «провалов» на горизонталях).
    function taperedPath(p0, p1, p2, p3, widthAt, steps) {
        var left = [];
        var right = [];
        for (var i = 0; i <= steps; i++) {
            var t = i / steps;
            var pt = bezierPoint(p0, p1, p2, p3, t);
            var tg = bezierTangent(p0, p1, p2, p3, t);
            var len = Math.sqrt(tg[0] * tg[0] + tg[1] * tg[1]) || 1;
            var nx = -tg[1] / len;
            var ny = tg[0] / len;
            var w = widthAt(t) / 2;
            left.push(r1(pt[0] + nx * w) + ',' + r1(pt[1] + ny * w));
            right.push(r1(pt[0] - nx * w) + ',' + r1(pt[1] - ny * w));
        }
        return 'M' + left.join(' L') + ' L' + right.reverse().join(' L') + ' Z';
    }

    function curvePath(p0, p1, p2, p3) {
        return 'M' + r1(p0[0]) + ',' + r1(p0[1]) + ' C' + r1(p1[0]) + ',' + r1(p1[1]) + ' ' +
            r1(p2[0]) + ',' + r1(p2[1]) + ' ' + r1(p3[0]) + ',' + r1(p3[1]);
    }

    function defs(opts) {
        return '' +
            '<defs>' +
            '<style>' +
            '.rs-nm{font-family:Georgia,\'Times New Roman\',serif;font-style:italic;font-size:18px;fill:#3b2914;text-anchor:middle}' +
            '.rs-nmB{font-weight:bold}' +
            '.rs-dt{font-family:Georgia,\'Times New Roman\',serif;font-size:13.5px;fill:#5a4128;text-anchor:middle;paint-order:stroke;stroke:#fbf7ee;stroke-width:4px;stroke-linejoin:round}' +
            '.rs-mn{font-family:Georgia,\'Times New Roman\',serif;font-style:italic;font-size:12.5px;fill:#7a5733;text-anchor:middle;paint-order:stroke;stroke:#fbf7ee;stroke-width:4px;stroke-linejoin:round}' +
            '.rs-sm{font-family:Georgia,\'Times New Roman\',serif;font-style:italic;font-size:12px;fill:#4a3419;text-anchor:middle}' +
            '.rs-ttl{font-family:Georgia,\'Times New Roman\',serif;font-size:34px;letter-spacing:5px;fill:#3b2914;text-anchor:middle}' +
            '.rs-sub{font-family:Georgia,\'Times New Roman\',serif;font-style:italic;font-size:19px;fill:#6b4c2a;text-anchor:middle}' +
            '.rs-lb{font-family:Georgia,\'Times New Roman\',serif;font-size:15px;letter-spacing:2px;fill:#5b4024;text-anchor:middle;paint-order:stroke;stroke:#f3f1df;stroke-width:4px}' +
            '.rs-lg{font-family:Georgia,\'Times New Roman\',serif;font-size:15px;fill:#4a3419}' +
            '.rs-md{font-family:Georgia,\'Times New Roman\',serif;fill:#6b4a1e;text-anchor:middle}' +
            '.rs-p{cursor:pointer}' +
            '.rs-p:hover .rs-hl,.rs-p:focus .rs-hl{opacity:1}' +
            '.rs-p:focus{outline:none}' +
            '</style>' +
            '<linearGradient id="rs-gBarkV" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#9a714a"/><stop offset=".45" stop-color="#76512f"/><stop offset="1" stop-color="#4b321d"/></linearGradient>' +
            '<linearGradient id="rs-gBarkH" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#9a714a"/><stop offset=".5" stop-color="#74502f"/><stop offset="1" stop-color="#4b321d"/></linearGradient>' +
            '<linearGradient id="rs-gRib" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fdf6e4"/><stop offset="1" stop-color="#eedfbb"/></linearGradient>' +
            '<linearGradient id="rs-gRibG" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f6dd92"/><stop offset=".55" stop-color="#e3bb5a"/><stop offset="1" stop-color="#c99a35"/></linearGradient>' +
            '<linearGradient id="rs-gGold" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f7e2a0"/><stop offset=".5" stop-color="#c99a35"/><stop offset="1" stop-color="#8f6a1f"/></linearGradient>' +
            '<linearGradient id="rs-gLeaf" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#9cc163"/><stop offset="1" stop-color="#4f7d2b"/></linearGradient>' +
            '<linearGradient id="rs-gLeaf2" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#7aa548"/><stop offset="1" stop-color="#3c6421"/></linearGradient>' +
            '<linearGradient id="rs-gLeafG" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f1d27a"/><stop offset="1" stop-color="#b9861f"/></linearGradient>' +
            '<linearGradient id="rs-gGround" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#e4e8cc"/><stop offset="1" stop-color="#f4f2e2"/></linearGradient>' +
            '<radialGradient id="rs-gCrown" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#cfe0a8" stop-opacity=".42"/><stop offset=".7" stop-color="#dbe7bd" stop-opacity=".2"/><stop offset="1" stop-color="#e8eed2" stop-opacity="0"/></radialGradient>' +
            '<radialGradient id="rs-gGlow" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#f3d27a" stop-opacity=".55"/><stop offset="1" stop-color="#f3d27a" stop-opacity="0"/></radialGradient>' +
            (opts.shadows ?
                '<filter id="rs-sh" x="-15%" y="-30%" width="130%" height="170%"><feDropShadow dx="1.5" dy="2.5" stdDeviation="1.8" flood-color="#3b2a17" flood-opacity=".28"/></filter>' +
                '<filter id="rs-shB" x="-10%" y="-10%" width="120%" height="125%"><feDropShadow dx="3" dy="4" stdDeviation="3" flood-color="#2e1f10" flood-opacity=".3"/></filter>' : '') +
            (opts.paper ?
                '<filter id="rs-paper" x="0" y="0" width="100%" height="100%"><feTurbulence type="fractalNoise" baseFrequency=".9" numOctaves="2" seed="7" result="n"/><feColorMatrix in="n" type="matrix" values="0 0 0 0 .45  0 0 0 0 .36  0 0 0 0 .22  0 0 0 .09 0"/></filter>' : '') +
            // лента: живые
            '<g id="rs-rb">' +
            '<path d="M-100,-6 L-124,-6 L-115,12 L-124,30 L-100,30 Z" fill="#e3cf9e" stroke="#b29963" stroke-width="1"/>' +
            '<path d="M100,-6 L124,-6 L115,12 L124,30 L100,30 Z" fill="#e3cf9e" stroke="#b29963" stroke-width="1"/>' +
            '<path d="M-110,20 L-100,30 L-100,19 Z" fill="#b59b62"/><path d="M110,20 L100,30 L100,19 Z" fill="#b59b62"/>' +
            '<path d="M-110,-18 Q0,-28 110,-18 L110,20 Q0,10 -110,20 Z" fill="url(#rs-gRib)" stroke="#b29963" stroke-width="1.2"/>' +
            '<path d="M-104,-13.5 Q0,-22.6 104,-13.5" fill="none" stroke="#cdb684" stroke-width=".8"/>' +
            '<path d="M-104,15.5 Q0,6.4 104,15.5" fill="none" stroke="#cdb684" stroke-width=".8"/>' +
            '</g>' +
            // лента: ушедшие
            '<g id="rs-rbG">' +
            '<path d="M-100,-6 L-124,-6 L-115,12 L-124,30 L-100,30 Z" fill="#cf9f3b" stroke="#946b1f" stroke-width="1"/>' +
            '<path d="M100,-6 L124,-6 L115,12 L124,30 L100,30 Z" fill="#cf9f3b" stroke="#946b1f" stroke-width="1"/>' +
            '<path d="M-110,20 L-100,30 L-100,19 Z" fill="#8f661c"/><path d="M110,20 L100,30 L100,19 Z" fill="#8f661c"/>' +
            '<path d="M-110,-18 Q0,-28 110,-18 L110,20 Q0,10 -110,20 Z" fill="url(#rs-gRibG)" stroke="#946b1f" stroke-width="1.2"/>' +
            '<path d="M-104,-13.5 Q0,-22.6 104,-13.5" fill="none" stroke="#fbeab8" stroke-width=".9"/>' +
            '<path d="M-104,15.5 Q0,6.4 104,15.5" fill="none" stroke="#a8792a" stroke-width=".8"/>' +
            '</g>' +
            // подсветка при наведении
            '<path id="rs-hlp" d="M-114,-22 Q0,-33 114,-22 L114,24 Q0,14 -114,24 Z"/>' +
            // листья
            '<g id="rs-lf"><path d="M0,0 C5,-9 17,-12 28,-4 C19,5 7,6 0,0 Z" fill="url(#rs-gLeaf)"/><path d="M1,0 C9,-4 17,-5 26,-4" fill="none" stroke="#3d621f" stroke-width=".9"/></g>' +
            '<g id="rs-lf2"><path d="M0,0 C5,-9 17,-12 28,-4 C19,5 7,6 0,0 Z" fill="url(#rs-gLeaf2)"/><path d="M1,0 C9,-4 17,-5 26,-4" fill="none" stroke="#2c4a17" stroke-width=".9"/></g>' +
            '<g id="rs-lfG"><path d="M0,0 C5,-9 17,-12 28,-4 C19,5 7,6 0,0 Z" fill="url(#rs-gLeafG)"/><path d="M1,0 C9,-4 17,-5 26,-4" fill="none" stroke="#8a5f12" stroke-width=".9"/></g>' +
            '<g id="rs-sprayA"><path d="M0,0 C10,-12 22,-22 42,-28" fill="none" stroke="#6b4a2b" stroke-width="2.4" stroke-linecap="round"/>' +
            '<use href="#rs-lf" transform="translate(9,-10) rotate(-105)"/><use href="#rs-lf2" transform="translate(11,-12) rotate(-12)"/>' +
            '<use href="#rs-lf" transform="translate(23,-21) rotate(-125)"/><use href="#rs-lf2" transform="translate(25,-22) rotate(-30)"/>' +
            '<use href="#rs-lf" transform="translate(41,-28) rotate(-55)"/><use href="#rs-lf2" transform="translate(41,-28) rotate(-5) scale(.8)"/></g>' +
            '<g id="rs-sprayB"><path d="M0,0 C12,-6 26,-8 44,-4" fill="none" stroke="#6b4a2b" stroke-width="2.4" stroke-linecap="round"/>' +
            '<use href="#rs-lf2" transform="translate(10,-5) rotate(-75)"/><use href="#rs-lf" transform="translate(12,-5) rotate(40)"/>' +
            '<use href="#rs-lf2" transform="translate(26,-7) rotate(-80)"/><use href="#rs-lf" transform="translate(28,-7) rotate(30)"/>' +
            '<use href="#rs-lf2" transform="translate(43,-4) rotate(-20)"/><use href="#rs-lf" transform="translate(43,-4) rotate(25) scale(.8)"/></g>' +
            '<g id="rs-sprayG"><path d="M0,0 C10,-12 22,-22 42,-28" fill="none" stroke="#6b4a2b" stroke-width="2.4" stroke-linecap="round"/>' +
            '<use href="#rs-lfG" transform="translate(9,-10) rotate(-105)"/><use href="#rs-lfG" transform="translate(11,-12) rotate(-12)"/>' +
            '<use href="#rs-lfG" transform="translate(23,-21) rotate(-125)"/><use href="#rs-lfG" transform="translate(25,-22) rotate(-30)"/>' +
            '<use href="#rs-lfG" transform="translate(41,-28) rotate(-55)"/></g>' +
            // тюльпан
            '<g id="rs-tulip"><path d="M0,0 C-2,-18 1,-34 0,-46" fill="none" stroke="#5f8a34" stroke-width="2.6" stroke-linecap="round"/>' +
            '<path d="M0,-4 C-14,-12 -19,-27 -15,-40 C-9,-28 -4,-18 0,-10 Z" fill="url(#rs-gLeaf)"/>' +
            '<path d="M0,-8 C12,-15 15,-26 12,-34 C7,-25 3,-18 0,-13 Z" fill="url(#rs-gLeaf2)"/>' +
            '<path d="M-12,-50 C-14,-62 -8,-71 0,-75 C8,-71 14,-62 12,-50 C8,-44 -8,-44 -12,-50 Z" fill="#e88b98"/>' +
            '<path d="M0,-45 C-10,-47 -16,-57 -13,-70 C-7,-64 -3,-58 0,-52 Z" fill="#cf5b6d"/>' +
            '<path d="M0,-45 C10,-47 16,-57 13,-70 C7,-64 3,-58 0,-52 Z" fill="#f2a6b0"/>' +
            '<path d="M0,-45 C-6,-52 -6,-63 0,-73 C6,-63 6,-52 0,-45 Z" fill="#dc6f80"/>' +
            '<path d="M-1,-50 C-2,-56 -2,-62 0,-68" fill="none" stroke="#fbd7dc" stroke-width="1" opacity=".8"/></g>' +
            '<g id="rs-tulipG"><path d="M0,0 C-2,-18 1,-34 0,-46" fill="none" stroke="#8a6a2a" stroke-width="2.6" stroke-linecap="round"/>' +
            '<path d="M0,-4 C-14,-12 -19,-27 -15,-40 C-9,-28 -4,-18 0,-10 Z" fill="url(#rs-gLeafG)"/>' +
            '<path d="M-12,-50 C-14,-62 -8,-71 0,-75 C8,-71 14,-62 12,-50 C8,-44 -8,-44 -12,-50 Z" fill="#e7c062"/>' +
            '<path d="M0,-45 C-10,-47 -16,-57 -13,-70 C-7,-64 -3,-58 0,-52 Z" fill="#b8862a"/>' +
            '<path d="M0,-45 C10,-47 16,-57 13,-70 C7,-64 3,-58 0,-52 Z" fill="#f1d27a"/></g>' +
            '<g id="rs-bl"><ellipse cx="0" cy="-5" rx="3.2" ry="5" fill="#f3b3bd"/><ellipse cx="0" cy="-5" rx="3.2" ry="5" fill="#f3b3bd" transform="rotate(72)"/>' +
            '<ellipse cx="0" cy="-5" rx="3.2" ry="5" fill="#eea0ac" transform="rotate(144)"/><ellipse cx="0" cy="-5" rx="3.2" ry="5" fill="#f3b3bd" transform="rotate(216)"/>' +
            '<ellipse cx="0" cy="-5" rx="3.2" ry="5" fill="#eea0ac" transform="rotate(288)"/><circle r="2.4" fill="#e3b343"/></g>' +
            // значки в ленте
            '<g id="rs-icoM"><g transform="translate(-10,6) rotate(-40) scale(.66)"><use href="#rs-lf"/></g></g>' +
            '<g id="rs-icoMg"><g transform="translate(-10,6) rotate(-40) scale(.66)"><use href="#rs-lfG"/></g></g>' +
            '<g id="rs-icoF"><path d="M0,8 L0,0" stroke="#5f8a34" stroke-width="1.6"/><path d="M-5.5,-1 C-6.5,-7 -3.5,-11 0,-12 C3.5,-11 6.5,-7 5.5,-1 C3,1.2 -3,1.2 -5.5,-1 Z" fill="#e88b98"/><path d="M0,0 C-3,-3.5 -3,-8 0,-12 C3,-8 3,-3.5 0,0 Z" fill="#cf5b6d"/></g>' +
            '<g id="rs-icoFg"><path d="M0,8 L0,0" stroke="#8a6a2a" stroke-width="1.6"/><path d="M-5.5,-1 C-6.5,-7 -3.5,-11 0,-12 C3.5,-11 6.5,-7 5.5,-1 C3,1.2 -3,1.2 -5.5,-1 Z" fill="#e7c062"/><path d="M0,0 C-3,-3.5 -3,-8 0,-12 C3,-8 3,-3.5 0,0 Z" fill="#b8862a"/></g>' +
            // кольца
            '<g id="rs-rings"><ellipse rx="27" ry="19" fill="#fbf7ee" opacity=".9"/><g fill="none" stroke-linecap="round">' +
            '<circle cx="-7" cy="1.5" r="11" stroke="#5b4214" stroke-width="5" opacity=".25"/><circle cx="9" cy="1.5" r="11" stroke="#5b4214" stroke-width="5" opacity=".25"/>' +
            '<circle cx="-8" cy="0" r="11" stroke="url(#rs-gGold)" stroke-width="4"/><circle cx="8" cy="0" r="11" stroke="url(#rs-gGold)" stroke-width="4"/>' +
            '<path d="M-2.5,-9.53 A11,11 0 0 1 1.97,-4.65" stroke="#d8ac4a" stroke-width="4"/>' +
            '<path d="M8,-11 A11,11 0 0 1 16.6,-6.9" stroke="#fff4cf" stroke-width="1.2"/><path d="M-8,-11 A11,11 0 0 0 -16.6,-6.9" stroke="#fff4cf" stroke-width="1.2"/></g>' +
            '<path d="M8,-17 L12,-12.5 L8,-8.5 L4,-12.5 Z" fill="#fffaf0" stroke="#a88232" stroke-width="1"/></g>' +
            // қошқар мүйіз
            '<g id="rs-horn" fill="none" stroke="#b8903f" stroke-width="2.2" stroke-linecap="round">' +
            '<path d="M0,0 C0,-26 22,-40 40,-30 C54,-22 50,-4 36,-4 C26,-4 24,-16 32,-18"/>' +
            '<path d="M0,0 C0,-26 -22,-40 -40,-30 C-54,-22 -50,-4 -36,-4 C-26,-4 -24,-16 -32,-18"/>' +
            '<path d="M0,0 L0,20 M-7,13 L0,20 L7,13"/></g>' +
            '<g id="rs-tuft" fill="none" stroke="#8c9a5e" stroke-width="1.6" stroke-linecap="round"><path d="M0,0 Q-2,-9 -7,-14"/><path d="M3,0 Q3,-11 1,-18"/><path d="M6,0 Q8,-8 13,-12"/></g>' +
            '</defs>';
    }

    function filt(opts, id) {
        return opts.shadows ? ' filter="url(#' + id + ')"' : '';
    }

    function nameFontSize(text) {
        var len = Array.from(text).length;
        var est = len * 9.6; // Georgia italic 18px, кириллица
        if (est <= 196) return 18;
        return clamp(Math.floor(18 * 196 / est * 10) / 10, 11, 18);
    }

    function datesLine(p) {
        var b = fmtDate(p.birthDate, p.birthYear);
        var d = fmtDate(p.deathDate, p.deathYear);
        var parts = [];
        if (b) parts.push('* ' + b);
        if (p.deceased) parts.push(d ? '† ' + d : '†');
        return parts.join(' · ');
    }

    function personRibbon(p, x, y, isFocal, opts) {
        var gold = p.deceased;
        var name = (p.first + ' ' + p.last).trim() || '—';
        var fs = nameFontSize(name);
        var ico = p.gender === 'F' ? (gold ? 'rs-icoFg' : 'rs-icoF') : (gold ? 'rs-icoMg' : 'rs-icoM');
        var out = '<g class="rs-p" data-person-id="' + p.id + '"' +
            (p.gedcom ? ' data-gedcom="' + escapeXml(p.gedcom) + '"' : '') +
            ' transform="translate(' + r1(x) + ',' + r1(y) + ')" tabindex="0" role="button" aria-label="' + escapeXml(name) + '">';
        out += '<title>' + escapeXml(name) + '</title>';
        out += '<use class="rs-hl" href="#rs-hlp" fill="none" stroke="#e0b44c" stroke-width="3" opacity="0"/>';
        out += '<use href="#' + (gold ? 'rs-rbG' : 'rs-rb') + '"' + filt(opts, 'rs-sh') + '/>';
        out += '<use href="#' + ico + '" x="-92"/>';
        out += '<text class="rs-nm' + (isFocal ? ' rs-nmB' : '') + '" x="8" y="6"' +
            (fs !== 18 ? ' style="font-size:' + fs + 'px"' : '') + '>' + escapeXml(name) + '</text>';
        var dl = datesLine(p);
        if (dl) out += '<text class="rs-dt" y="44">' + escapeXml(dl) + '</text>';
        if (p.gender === 'F' && p.maiden) {
            out += '<text class="rs-mn" y="' + (dl ? 62 : 44) + '">' + escapeXml(opts.maidenPrefix + ' ' + p.maiden) + '</text>';
        }
        out += '</g>';
        return out;
    }

    function medallion(x, y, label, opts) {
        var lab = String(label || '').toUpperCase();
        var fs = Array.from(lab).length > 10 ? 11 : 13;
        return '<g transform="translate(' + r1(x) + ',' + r1(y) + ')"' + filt(opts, 'rs-sh') + '>' +
            '<circle r="58" fill="#fbf3df" stroke="url(#rs-gGold)" stroke-width="3.5"/>' +
            '<circle r="52" fill="none" stroke="#b8903f" stroke-width="2.4" stroke-dasharray="0.1 7" stroke-linecap="round"/>' +
            '<circle r="45" fill="none" stroke="#c9a85a" stroke-width="1"/>' +
            '<circle r="22" fill="none" stroke="#b8903f" stroke-width="1.2" stroke-dasharray="4 3"/>' +
            '<text class="rs-md" y="5" font-size="11" font-style="italic">' + escapeXml(opts.tamgaPlaceholder) + '</text>' +
            '<text class="rs-md" y="38" font-size="' + fs + '" letter-spacing="1">' + escapeXml(lab) + '</text>' +
            '</g>';
    }

    // ---------- главная функция ----------
    function build(data, userOpts) {
        var opts = Object.assign({
            focalId: 0,
            title: 'ШЕЖІРЕ',
            subtitle: '',
            mainClan: '',
            maidenPrefix: 'қыз кезінде —',
            marriagePrefix: 'неке ·',
            tamgaPlaceholder: 'таңба',
            unknownClan: 'руы белгісіз',
            fixSurnames: true,
            spliceDepth: 0,
            shadows: null,
            paper: null,
            legend: true,
            footnote: ''
        }, userOpts || {});
        // вложенные подписи — сливаем отдельно, чтобы частичная замена не давала «undefined»
        var DEF_LABELS = { father: 'ӘКЕ ЖАҒЫ', mother: 'АНА ЖАҒЫ', inlaw: 'ҚАЙЫН ЖҰРТ', other: 'ЖЕКЖАТ' };
        var DEF_LEGEND = {
            son: 'ұл',
            daughter: 'қыз / келін',
            deceased: 'марқұм (алтын таспа, алтын жапырақ)',
            marriage: 'неке',
            dates: '*  туған күні     †  қайтыс болған күні'
        };
        opts.labels = Object.assign({}, DEF_LABELS, (userOpts && userOpts.labels) || {});
        opts.legendText = Object.assign({}, DEF_LEGEND, (userOpts && userOpts.legendText) || {});
        data = data || {};

        var model = buildModel(data, opts);
        var focalId = num(opts.focalId) || num(data.user_person_id) || num(data.focal_id);
        if (!model.persons.has(focalId)) focalId = model.persons.size ? model.persons.keys().next().value : 0;

        if (!model.persons.size) {
            var emptySvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 300" width="800" height="300">' +
                '<rect width="800" height="300" fill="#fbf7ee"/>' +
                '<text x="400" y="155" text-anchor="middle" font-family="Georgia,serif" font-size="20" fill="#5a4128">' +
                escapeXml(opts.emptyText || 'Деректер жоқ') + '</text></svg>';
            return { svg: emptySvg, width: 800, height: 300, positions: {}, focalId: 0, stats: { persons: 0, placed: 0, trees: 0, levels: 0 } };
        }

        var gen = assignGenerations(model, focalId);

        // --- деревья (стволы) ---
        var placed = new Set();
        var unitOf = new Map();
        var trees = [];
        var focal = model.persons.get(focalId);

        // фамилия рода для подписи ствола: ближайший к startId мужчина по отцовской линии с фамилией
        function lineSurname(startId) {
            var cur = model.persons.get(startId);
            var guard = 0;
            while (cur && guard++ < 50) {
                if (cur.gender === 'M' && cur.last) return cur.last;
                if (cur.gender === 'F' && cur.maiden) return cur.maiden;
                cur = cur.fatherId ? model.persons.get(cur.fatherId) : null;
            }
            return '';
        }

        function plantTree(startId, kind) {
            if (placed.has(startId)) return null;
            var rootId = climbRoot(model, startId, placed);
            var rootUnit = buildUnit(model, rootId, gen.get(rootId) || 0, placed, null, unitOf);
            var tree = { root: rootUnit, rootId: rootId, kind: kind, surname: lineSurname(startId) };
            trees.push(tree);
            return tree;
        }

        // род человека, вошедшего в семью: от его родителей (сам он уже стоит рядом с супругом)
        function plantParentsOf(id, kind) {
            var p = model.persons.get(id);
            if (!p) return;
            if (!placed.has(id)) { plantTree(id, kind); return; }
            if (p.fatherId) plantTree(p.fatherId, kind);
            if (p.motherId) plantTree(p.motherId, kind);
        }

        plantTree(focalId, 'father');
        if (focal.motherId) plantParentsOf(focal.motherId, 'mother');
        model.spousesOf(focalId).forEach(function (s) { plantParentsOf(s, 'inlaw'); });
        // прочие роды: по близости к фокусной персоне (обход в ширину)
        var seen = new Set([focalId]);
        var queue = [focalId];
        var order = [];
        while (queue.length) {
            var cur = queue.shift();
            order.push(cur);
            var cp = model.persons.get(cur);
            [cp.fatherId, cp.motherId].concat(model.spousesOf(cur), model.childrenOf(cur)).forEach(function (n) {
                if (n && !seen.has(n)) { seen.add(n); queue.push(n); }
            });
        }
        model.persons.forEach(function (p, id) { if (!seen.has(id)) order.push(id); });
        order.forEach(function (id) { if (!placed.has(id)) plantTree(id, 'other'); });

        // --- раскладка каждого дерева и расстановка стволов: главный в центре, остальные попеременно справа и слева ---
        // начальное приближение — контурная раскладка каждого рода, затем общая плотная раскладка
        trees.forEach(function (t) {
            layoutUnit(t.root);
            t.units = [];
            assignX(t.root, 0, t.units);
            t.units.forEach(function (u) { u.tree = t; });
        });
        var lay = compactLayout(model, trees, unitOf, opts.spliceDepth, focalId);

        var allUnits = [];
        trees.forEach(function (t) { allUnits = allUnits.concat(t.units); });

        // --- габариты ---
        var minX = lay.minX;
        var maxX = lay.maxX;
        var maxLevel = lay.maxLevel;
        var shiftX = MARGIN_X - minX;
        var width = Math.max(1400, Math.ceil(maxX - minX + 2 * MARGIN_X));
        if (maxX - minX + 2 * MARGIN_X < 1400) shiftX += (1400 - (maxX - minX + 2 * MARGIN_X)) / 2;
        var height = Math.ceil(HEADER_H + maxLevel * TIER + GROUND_TO_TIER0 + FOOTER_H);
        var groundY = height - FOOTER_H;
        function Y(level) { return groundY - GROUND_TO_TIER0 - level * TIER; }
        function X(x) { return x + shiftX; }

        var area = width * height;
        if (opts.shadows === null) opts.shadows = allUnits.length <= 400;
        if (opts.paper === null) opts.paper = area <= 12000000;

        var positions = {};
        allUnits.forEach(function (u) {
            u.members.forEach(function (m, i) {
                positions[m] = { x: r1(X(u.memberX[i])), y: r1(Y(u.level)) };
            });
        });

        // --- слои ---
        var bg = [];
        var crown = [];
        var trunks = [];
        var branches = [];
        var bark = [];
        var leaves = [];
        var threads = [];
        var people = [];
        var marks = [];

        bg.push('<rect width="' + width + '" height="' + height + '" fill="#fbf7ee"/>');
        if (opts.paper) bg.push('<rect width="' + width + '" height="' + height + '" filter="url(#rs-paper)"/>');
        bg.push('<rect x="20" y="20" width="' + (width - 40) + '" height="' + (height - 40) + '" fill="none" stroke="#c4a35a" stroke-width="2"/>');
        bg.push('<rect x="32" y="32" width="' + (width - 64) + '" height="' + (height - 64) + '" fill="none" stroke="#c4a35a" stroke-width=".8"/>');

        // земля
        var gy = groundY;
        var segs = Math.max(2, Math.round(width / 600));
        var top = 'M0,' + (gy + 14);
        var bottom = '';
        for (var s = 1; s <= segs; s++) {
            var xa = width * (s - 0.5) / segs;
            var xb = width * s / segs;
            top += ' Q' + r1(xa) + ',' + r1(gy - 12 + hash(s) * 10) + ' ' + r1(xb) + ',' + r1(gy + (s === segs ? 14 : hash(s + 7) * 8));
        }
        bottom = ' L' + width + ',' + (gy + 100) + ' L0,' + (gy + 100) + ' Z';
        bg.push('<path d="' + top + bottom + '" fill="url(#rs-gGround)"/>');
        bg.push('<path d="' + top + '" fill="none" stroke="#b5bd8c" stroke-width="2"/>');
        for (var tx = 90; tx < width - 60; tx += 120 + hash(tx) * 90) {
            bg.push('<use href="#rs-tuft" x="' + r1(tx) + '" y="' + r1(gy + 2) + '"/>');
        }

        // крона-подложка над каждым деревом
        trees.forEach(function (t) {
            var lo = Infinity, hi = -Infinity, lvMin = Infinity, lvMax = -Infinity;
            t.units.forEach(function (u) {
                lo = Math.min(lo, u.memberX[0] - SLOT / 2);
                hi = Math.max(hi, u.memberX[u.memberX.length - 1] + SLOT / 2);
                lvMin = Math.min(lvMin, u.level);
                lvMax = Math.max(lvMax, u.level);
            });
            if (lvMax <= lvMin && t.units.length < 3) return;
            var cx = X((lo + hi) / 2);
            var cy = (Y(lvMax) + Y(lvMin)) / 2 - 40;
            var rx = (hi - lo) / 2 + 160;
            var ry = (Y(lvMin) - Y(lvMax)) / 2 + 220;
            crown.push('<ellipse cx="' + r1(cx) + '" cy="' + r1(cy) + '" rx="' + r1(rx) + '" ry="' + r1(ry) + '" fill="url(#rs-gCrown)"/>');
        });

        // анкер для детей: между родителями (если оба в семье) или над кровным родителем
        function anchorFor(unit, childId) {
            var c = model.persons.get(childId);
            var iF = unit.members.indexOf(c.fatherId);
            var iM = unit.members.indexOf(c.motherId);
            if (iF >= 0 && iM >= 0) return (unit.memberX[iF] + unit.memberX[iM]) / 2;
            if (iF >= 0) return unit.memberX[iF];
            if (iM >= 0) return unit.memberX[iM];
            return unit.memberX[unit.members.indexOf(unit.blood)];
        }

        function branchWidth(u) {
            return clamp(7 + Math.sqrt(u.descendants + 1) * 4.2, 7, 34);
        }

        // ветви от семьи к детям
        allUnits.forEach(function (u) {
            u.children.forEach(function (cu) {
                var ax = X(anchorFor(u, cu.blood));
                var ay = Y(u.level) - 24;
                var bi = cu.members.indexOf(cu.blood);
                var bx = X(cu.memberX[bi]);
                var by = Y(cu.level) + 16;
                var k = (ay - by) * 0.55;
                var p0 = [ax, ay], p1 = [ax, ay - k], p2 = [bx, by + k * 0.8], p3 = [bx, by];
                var w0 = branchWidth(cu) * 1.15;
                var w1 = Math.max(5, w0 * 0.42);
                branches.push('<path d="' + taperedPath(p0, p1, p2, p3, function (t) { return w0 + (w1 - w0) * t; }, 18) + '" fill="url(#rs-gBarkV)"/>');
                bark.push('<path d="' + curvePath([ax - w0 * 0.18, ay], [ax - w0 * 0.18, ay - k], [bx - w1 * 0.2, by + k * 0.8], [bx - w1 * 0.2, by]) +
                    '" stroke-width="' + r1(Math.max(1.2, w0 * 0.14)) + '"/>');
                // листья на ветви
                var person = model.persons.get(cu.blood);
                var spray = person.deceased ? 'rs-sprayG' : (hash(cu.blood) < 0.5 ? 'rs-sprayA' : 'rs-sprayB');
                [0.42, 0.7].forEach(function (t, j) {
                    var pt = bezierPoint(p0, p1, p2, p3, t);
                    var tg = bezierTangent(p0, p1, p2, p3, t);
                    var ang = Math.atan2(tg[1], tg[0]) * 180 / Math.PI;
                    var side = ((cu.blood + j) % 2 === 0) ? 1 : -1;
                    var rot = ang + side * (55 + hash(cu.blood * 3 + j) * 30);
                    leaves.push('<use href="#' + spray + '" transform="translate(' + r1(pt[0]) + ',' + r1(pt[1]) +
                        ') rotate(' + r1(rot) + ') scale(' + r1(1 + hash(cu.blood + j) * 0.4) + ')"/>');
                });
            });
        });

        // стволы с корнями — по одному на род
        trees.forEach(function (t) {
            var u = t.root;
            var bi = u.members.indexOf(u.blood);
            var ax = X(u.members.length > 1 ? (u.memberX[0] + u.memberX[u.memberX.length - 1]) / 2 : u.memberX[bi]);
            var topY = Y(u.level) + 18;
            var grounded = t.grounded !== false;
            // отросток прочего рода: короткий ствол с корнями под корневой лентой
            var baseY = grounded ? groundY + 8 : Y(u.level) + 190;
            var big = grounded ? clamp(46 + Math.sqrt(u.descendants + 1) * 7, 46, 120) : 30;
            var lean = (hash(t.rootId) - 0.5) * 40;
            // ствол идёт по месту, отведённому ему на нижних ярусах плотной раскладки
            var ti = t.trunkItems || [];
            var bx0 = ti.length ? X(ti[0].x) : ax;
            var bx1 = ti.length ? X(ti[Math.floor(ti.length / 3)].x) : ax;
            var bx2 = ti.length ? X(ti[Math.floor(ti.length * 2 / 3)].x) : ax;
            var p0 = [bx0 + lean * 0.3, baseY], p1 = [bx1 - lean, baseY - (baseY - topY) * 0.35],
                p2 = [bx2 + lean, topY + (baseY - topY) * 0.35], p3 = [ax, topY];
            trunks.push('<path d="' + taperedPath(p0, p1, p2, p3, function (tt) {
                var flare = tt < 0.14 ? Math.pow((0.14 - tt) / 0.14, 2) * big * 0.9 : 0;
                return big + (big * 0.5 - big) * tt + flare;
            }, 24) + '" fill="url(#rs-gBarkV)"' + filt(opts, 'rs-shB') + '/>');
            bark.push('<path d="' + curvePath([p0[0] - big * 0.2, p0[1] - 10], [p1[0] - big * 0.18, p1[1]], [p2[0] - big * 0.12, p2[1]], [p3[0] - big * 0.1, p3[1] + 10]) + '" stroke-width="1.8"/>');
            bark.push('<path d="' + curvePath([p0[0] + big * 0.16, p0[1] - 20], [p1[0] + big * 0.14, p1[1]], [p2[0] + big * 0.1, p2[1]], [p3[0] + big * 0.08, p3[1] + 10]) + '" stroke-width="1.4"/>');
            // корни
            var rootsSvg = '';
            var rs = grounded ? 1 : 0.45;
            [-1, 1].forEach(function (side) {
                [0, 1, 2].forEach(function (j) {
                    var sx = p0[0] + side * big * (0.25 + j * 0.15);
                    var ex = p0[0] + side * (big * 0.9 + (j * 46 + hash(t.rootId + j) * 30) * rs);
                    var ey = baseY + (18 + j * 14) * rs;
                    rootsSvg += '<path d="M' + r1(sx) + ',' + r1(baseY - 6) + ' C' + r1(sx + side * 20 * rs) + ',' + r1(baseY + 6 * rs) + ' ' +
                        r1(ex - side * 30 * rs) + ',' + r1(ey) + ' ' + r1(ex) + ',' + r1(ey + 4 * rs) + '" stroke-width="' + r1((11 - j * 2) * (grounded ? 1 : 0.6)) + '"/>';
                });
            });
            trunks.push('<g fill="none" stroke="#5a3d24" stroke-linecap="round"' + filt(opts, 'rs-sh') + '>' + rootsSvg + '</g>');

            // подпись ствола
            var rootP = model.persons.get(t.rootId);
            var surname = t.surname;
            var label = (opts.labels[t.kind] || opts.labels.other) + (surname ? ' · ' + clanPlural(surname).toUpperCase() : '');
            marks.push('<text class="rs-lb" x="' + r1(p0[0]) + '" y="' + r1(grounded ? groundY + 82 : baseY + 52) + '">' + escapeXml(label) + '</text>');
            if (!grounded) return;

            // тамга рода на стволе (если ствол достаточно высок)
            var clan = t.kind === 'father' && opts.mainClan ? opts.mainClan : (rootP.clan && rootP.clan !== 'белгісіз' ? rootP.clan : opts.unknownClan);
            if (baseY - topY > 260) {
                marks.push(medallion((p1[0] + p0[0]) / 2, baseY - Math.min(170, (baseY - topY) * 0.45), clan, opts));
            } else {
                marks.push(medallion(p0[0], groundY + 150, clan, opts));
            }
        });

        // нити родства между стволами (ребёнок размещён не под своими родителями)
        allUnits.forEach(function (u) {
            u.members.forEach(function (m, i) {
                var p = model.persons.get(m);
                [p.fatherId, p.motherId].forEach(function (pid) {
                    if (!pid) return;
                    var pu = unitOf.get(pid);
                    if (!pu) return;
                    if (u.parent === pu && m === u.blood) return;
                    // одна нить на пару родителей
                    if (pid === p.motherId && p.fatherId && unitOf.get(p.fatherId) === pu) return;
                    var ax = X(anchorFor(pu, m));
                    var ay = Y(pu.level) - 24;
                    var bx = X(u.memberX[i]);
                    var by = Y(u.level) + 20;
                    var k = Math.max(120, Math.abs(ay - by) * 0.6);
                    threads.push('<path d="' + curvePath([ax, ay], [ax, ay - k], [bx, by + k], [bx, by]) + '"/>');
                });
            });
        });

        // фокусная пара — мягкое свечение
        if (positions[focalId]) {
            var fu = unitOf.get(focalId);
            var fx = X((fu.memberX[0] + fu.memberX[fu.memberX.length - 1]) / 2);
            crown.push('<ellipse cx="' + r1(fx) + '" cy="' + r1(Y(fu.level)) + '" rx="' + r1(fu.members.length * SLOT / 2 + 80) + '" ry="70" fill="url(#rs-gGlow)"/>');
        }

        // ленты, кольца, даты брака, цветы
        allUnits.forEach(function (u) {
            var y = Y(u.level);
            u.members.forEach(function (m, i) {
                var p = model.persons.get(m);
                people.push(personRibbon(p, X(u.memberX[i]), y, m === focalId, opts));
                // верхушка кроны: у бездетных — тюльпаны (дочери) или веточки (сыновья)
                if (m === u.blood && !u.children.length) {
                    var px = X(u.memberX[i]);
                    if (p.gender === 'F') {
                        var tl = p.deceased ? 'rs-tulipG' : 'rs-tulip';
                        leaves.push('<use href="#' + tl + '" transform="translate(' + r1(px) + ',' + r1(y - 24) + ') scale(.85)"/>');
                        leaves.push('<use href="#' + tl + '" transform="translate(' + r1(px - 28) + ',' + r1(y - 20) + ') rotate(-18) scale(.68)"/>');
                        leaves.push('<use href="#' + tl + '" transform="translate(' + r1(px + 28) + ',' + r1(y - 20) + ') rotate(18) scale(.68)"/>');
                    } else {
                        var sp = p.deceased ? 'rs-sprayG' : 'rs-sprayA';
                        leaves.push('<use href="#' + sp + '" transform="translate(' + r1(px - 30) + ',' + r1(y - 20) + ') rotate(-125) scale(1.1)"/>');
                        leaves.push('<use href="#' + sp + '" transform="translate(' + r1(px + 30) + ',' + r1(y - 20) + ') rotate(-55) scale(1.1)"/>');
                    }
                }
            });
            // кольца между кровным членом семьи и каждым супругом
            var bi = u.members.indexOf(u.blood);
            u.members.forEach(function (m, i) {
                if (m === u.blood) return;
                // кольца — в промежутке между супругом и соседом в сторону кровного члена семьи
                var rx = X((u.memberX[i] + u.memberX[i > bi ? i - 1 : i + 1]) / 2);
                marks.push('<use href="#rs-rings" x="' + r1(rx) + '" y="' + r1(y) + '"/>');
                var bp = model.persons.get(u.blood);
                var h = bp.gender === 'M' ? u.blood : m;
                var w = bp.gender === 'M' ? m : u.blood;
                var md = fmtDate(model.marriageDate(h, w), 0);
                if (md) {
                    marks.push('<g transform="translate(' + r1(rx) + ',' + r1(y + 92) + ')"' + filt(opts, 'rs-sh') + '><use href="#rs-rb" transform="scale(.62)"/></g>');
                    marks.push('<text class="rs-sm" x="' + r1(rx) + '" y="' + r1(y + 96) + '">' + escapeXml(opts.marriagePrefix + ' ' + md) + '</text>');
                }
            });
        });

        // заголовок
        var head = [];
        var hx = width / 2;
        head.push('<g transform="translate(' + r1(hx) + ',112)"' + filt(opts, 'rs-sh') + '><use href="#rs-rb" transform="scale(2.9,2.2)"/></g>');
        head.push('<text class="rs-ttl" x="' + r1(hx) + '" y="120">' + escapeXml(opts.title) + '</text>');
        if (opts.subtitle) head.push('<text class="rs-sub" x="' + r1(hx) + '" y="196">' + escapeXml(opts.subtitle) + '</text>');
        head.push('<use href="#rs-horn" transform="translate(' + r1(hx - 445) + ',122) rotate(-90) scale(.8)"/>');
        head.push('<use href="#rs-horn" transform="translate(' + r1(hx + 445) + ',122) rotate(90) scale(.8)"/>');
        head.push('<use href="#rs-horn" transform="translate(84,84) rotate(-45)"/>');
        head.push('<use href="#rs-horn" transform="translate(' + (width - 84) + ',84) rotate(45)"/>');
        head.push('<use href="#rs-horn" transform="translate(84,' + (height - 84) + ') rotate(-135)"/>');
        head.push('<use href="#rs-horn" transform="translate(' + (width - 84) + ',' + (height - 84) + ') rotate(135)"/>');

        // легенда
        if (opts.legend) {
            var lx = width / 2;
            var ly = height - 72;
            var L = opts.legendText;
            head.push('<g transform="translate(' + r1(lx) + ',' + r1(ly) + ')">' +
                '<rect x="-610" y="-30" width="1220" height="56" rx="10" fill="#fbf3df" stroke="#c4a35a" stroke-width="1.4"/>' +
                '<use href="#rs-icoM" x="-570" y="0"/><text class="rs-lg" x="-552" y="5">' + escapeXml(L.son) + '</text>' +
                '<use href="#rs-icoF" x="-480" y="0"/><text class="rs-lg" x="-466" y="5">' + escapeXml(L.daughter) + '</text>' +
                '<g transform="translate(-300,0) scale(.32)"><use href="#rs-rbG"/></g><text class="rs-lg" x="-250" y="5">' + escapeXml(L.deceased) + '</text>' +
                '<use href="#rs-rings" transform="translate(45,0) scale(.8)"/><text class="rs-lg" x="72" y="5">' + escapeXml(L.marriage) + '</text>' +
                '<text class="rs-lg" x="150" y="5">' + escapeXml(L.dates) + '</text>' +
                '</g>');
            if (opts.footnote) {
                head.push('<text class="rs-sm" x="' + r1(lx) + '" y="' + (height - 26) + '" fill="#8a6c48">' + escapeXml(opts.footnote) + '</text>');
            }
        }

        var svg = '<svg xmlns="http://www.w3.org/2000/svg" class="rs-svg" viewBox="0 0 ' + width + ' ' + height + '" width="' + width + '" height="' + height + '" lang="kk">' +
            '<title>' + escapeXml(opts.title) + '</title>' +
            defs(opts) +
            '<g class="rs-bg">' + bg.join('') + '</g>' +
            '<g class="rs-crown">' + crown.join('') + '</g>' +
            '<g class="rs-trunks">' + trunks.join('') + '</g>' +
            '<g class="rs-branches"' + filt(opts, 'rs-shB') + '>' + branches.join('') + '</g>' +
            '<g class="rs-bark" fill="none" stroke="#b88c5e" stroke-linecap="round" opacity=".45">' + bark.join('') + '</g>' +
            '<g class="rs-leaves">' + leaves.join('') + '</g>' +
            '<g class="rs-threads" fill="none" stroke="#d2a94a" stroke-width="2.2" stroke-dasharray="2 7" stroke-linecap="round" opacity=".85">' + threads.join('') + '</g>' +
            '<g class="rs-marks">' + marks.join('') + '</g>' +
            '<g class="rs-people">' + people.join('') + '</g>' +
            '<g class="rs-head">' + head.join('') + '</g>' +
            '</svg>';

        // уникальные id для каждого построения: несколько SVG на одной странице не мешают друг другу
        buildCounter++;
        var pfx = 'rs' + buildCounter + '-';
        svg = svg.replace(/(id="|href="#|url\(#)rs-/g, function (m0, lead) { return lead + pfx; });

        return {
            svg: svg,
            width: width,
            height: height,
            positions: positions,
            focalId: focalId,
            stats: {
                persons: model.persons.size,
                placed: Object.keys(positions).length,
                trees: trees.length,
                levels: maxLevel + 1,
                threads: threads.length,
                treeInfo: trees.map(function (t) {
                    var lo = Infinity, hi = -Infinity, n = 0;
                    t.units.forEach(function (u) {
                        n += u.members.length;
                        lo = Math.min(lo, u.memberX[0]);
                        hi = Math.max(hi, u.memberX[u.memberX.length - 1]);
                    });
                    return { kind: t.kind, rootId: t.rootId, rootLevel: t.root.level, persons: n, width: Math.round(hi - lo + SLOT) };
                })
            }
        };
    }

    // Вставка в контейнер и клик/Enter по ленте → opts.onPersonClick(id).
    function mount(container, data, opts) {
        opts = opts || {};
        var result = build(data, opts);
        container.innerHTML = result.svg;
        if (container.getAttribute('data-rs-bound') !== '1') {
            container.setAttribute('data-rs-bound', '1');
            var fire = function (e) {
                var g = e.target && e.target.closest ? e.target.closest('.rs-p') : null;
                if (!g) return;
                var id = parseInt(g.getAttribute('data-person-id'), 10);
                if (id && typeof container.__rsOnClick === 'function') container.__rsOnClick(id, e);
            };
            container.addEventListener('click', fire);
            container.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    fire(e);
                    if (e.target && e.target.closest && e.target.closest('.rs-p')) e.preventDefault();
                }
            });
        }
        container.__rsOnClick = opts.onPersonClick || null;
        return result;
    }

    return {
        build: build,
        mount: mount,
        _internal: {
            escapeXml: escapeXml,
            genderSurname: genderSurname,
            clanPlural: clanPlural,
            fmtDate: fmtDate,
            taperedPath: taperedPath
        }
    };
});
