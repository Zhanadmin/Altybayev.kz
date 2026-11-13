/**
 * TASK-010: Модульный тест клиентской логики сворачивания по поколениям и поиска
 */

const assert = require('assert');

// 1. Мокирование браузерного окружения
const storage = {};
global.localStorage = {
    getItem: (key) => storage[key] || null,
    setItem: (key, val) => { storage[key] = String(val); },
    removeItem: (key) => { delete storage[key]; },
    clear: () => { Object.keys(storage).forEach(k => delete storage[k]); }
};

class MockElement {
    constructor(tag, attrs = {}) {
        this.tagName = tag.toUpperCase();
        this.attributes = attrs;
        this.classList = new Set();
        this.children = [];
        this.style = {};
        this.textContent = '';
    }

    getAttribute(name) {
        return this.attributes[name] !== undefined ? this.attributes[name] : null;
    }

    setAttribute(name, val) {
        this.attributes[name] = String(val);
    }

    querySelector(selector) {
        return this.querySelectorAll(selector)[0] || null;
    }

    querySelectorAll(selector) {
        const results = [];
        const match = (el) => {
            if (selector.startsWith('.') && el.classList.has(selector.slice(1))) {
                results.push(el);
            } else if (selector.startsWith('#') && el.attributes['id'] === selector.slice(1)) {
                results.push(el);
            } else if (selector.includes('[data-gen-key=')) {
                const matchVal = selector.match(/\[data-gen-key="?([^"\]]+)"?\]/);
                if (matchVal && el.attributes['data-gen-key'] === matchVal[1]) {
                    results.push(el);
                }
            } else if (selector === 'tr' && el.tagName === 'TR') {
                results.push(el);
            }
            el.children.forEach(match);
        };
        this.children.forEach(match);
        return results;
    }
}

// 2. Создаем структуру групп
const groupAncestors = new MockElement('section', { 'data-gen-key': 'ancestors' });
const headAncestors = new MockElement('div');
headAncestors.classList.add('pp-group-head');
const bodyAncestors = new MockElement('div');
bodyAncestors.classList.add('pp-group-body');
const tr1 = new MockElement('tr'); tr1.textContent = 'Алтыбай Ата 1890';
const tr2 = new MockElement('tr'); tr2.textContent = 'Қаракөз Әже 1895';
bodyAncestors.children.push(tr1, tr2);
groupAncestors.children.push(headAncestors, bodyAncestors);

const group1 = new MockElement('section', { 'data-gen-key': '1' });
const head1 = new MockElement('div');
head1.classList.add('pp-group-head');
const body1 = new MockElement('div');
body1.classList.add('pp-group-body');
const tr3 = new MockElement('tr'); tr3.textContent = 'Ғабит Алтыбаев 1950';
const tr4 = new MockElement('tr'); tr4.textContent = 'Раушан 1955';
body1.children.push(tr3, tr4);
group1.children.push(head1, body1);

const group2 = new MockElement('section', { 'data-gen-key': '2' });
const head2 = new MockElement('div');
head2.classList.add('pp-group-head');
const body2 = new MockElement('div');
body2.classList.add('pp-group-body');
const tr5 = new MockElement('tr'); tr5.textContent = 'Жандос Алтыбаев 1985';
const tr6 = new MockElement('tr'); tr6.textContent = 'Асхат Алтыбаев 1988';
body2.children.push(tr5, tr6);
group2.children.push(head2, body2);

const allGroups = [groupAncestors, group1, group2];

// Реализация функций из views/persons/index.php
const STORAGE_KEY = 'shezhire_collapsed_generations';

function getStoredCollapsed() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return [];
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed.map(String) : [];
    } catch (e) {
        return [];
    }
}

function saveStoredCollapsed(keys) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(keys));
}

function setGroupState(groupEl, isCollapsed) {
    const head = groupEl.querySelector('.pp-group-head');
    if (isCollapsed) {
        groupEl.classList.add('is-collapsed');
        if (head) head.setAttribute('aria-expanded', 'false');
    } else {
        groupEl.classList.delete('is-collapsed');
        if (head) head.setAttribute('aria-expanded', 'true');
    }
}

function toggleGeneration(genKey) {
    const groupEl = allGroups.find(g => g.getAttribute('data-gen-key') === String(genKey));
    if (!groupEl) return;
    const isNowCollapsed = !groupEl.classList.has('is-collapsed');
    setGroupState(groupEl, isNowCollapsed);

    let list = getStoredCollapsed();
    const keyStr = String(genKey);
    if (isNowCollapsed) {
        if (!list.includes(keyStr)) list.push(keyStr);
    } else {
        list = list.filter(k => k !== keyStr);
    }
    saveStoredCollapsed(list);
}

function collapseAllGenerations() {
    const keys = [];
    allGroups.forEach(g => {
        setGroupState(g, true);
        keys.push(g.getAttribute('data-gen-key'));
    });
    saveStoredCollapsed(keys);
}

function expandAllGenerations() {
    allGroups.forEach(g => setGroupState(g, false));
    saveStoredCollapsed([]);
}

function onSearchInput(query) {
    const val = query.trim().toLowerCase();
    if (!val) {
        // Restore
        const stored = getStoredCollapsed();
        allGroups.forEach(g => {
            const key = g.getAttribute('data-gen-key');
            setGroupState(g, stored.includes(key));
        });
        return;
    }

    allGroups.forEach(group => {
        const rows = group.querySelectorAll('tr');
        let matchCount = 0;
        rows.forEach(tr => {
            if (tr.textContent.toLowerCase().includes(val)) {
                matchCount++;
            }
        });
        if (matchCount > 0) {
            // Раскрываем группу с найденным человеком
            setGroupState(group, false);
        }
    });
}

console.log("=======================================================");
console.log("  TASK-010: JS Unit Tests (localStorage, collapse, search)");
console.log("=======================================================\n");

// Test 1: начальное состояние
assert.deepStrictEqual(getStoredCollapsed(), []);
console.log("  [OK] Исходно в localStorage пусто");

// Test 2: сворачивание ancestors
toggleGeneration('ancestors');
assert.strictEqual(groupAncestors.classList.has('is-collapsed'), true);
assert.strictEqual(headAncestors.getAttribute('aria-expanded'), 'false');
assert.deepStrictEqual(getStoredCollapsed(), ['ancestors']);
console.log("  [OK] toggleGeneration('ancestors') сворачивает группу и обновляет localStorage");

// Test 3: повторный клик разворачивает
toggleGeneration('ancestors');
assert.strictEqual(groupAncestors.classList.has('is-collapsed'), false);
assert.strictEqual(headAncestors.getAttribute('aria-expanded'), 'true');
assert.deepStrictEqual(getStoredCollapsed(), []);
console.log("  [OK] Повторный клик разворачивает группу и очищает localStorage");

// Test 4: collapseAllGenerations
collapseAllGenerations();
assert.strictEqual(groupAncestors.classList.has('is-collapsed'), true);
assert.strictEqual(group1.classList.has('is-collapsed'), true);
assert.strictEqual(group2.classList.has('is-collapsed'), true);
assert.deepStrictEqual(getStoredCollapsed(), ['ancestors', '1', '2']);
console.log("  [OK] collapseAllGenerations сворачивает все группы и сохраняет в localStorage");

// Test 5: expandAllGenerations
expandAllGenerations();
assert.strictEqual(groupAncestors.classList.has('is-collapsed'), false);
assert.strictEqual(group1.classList.has('is-collapsed'), false);
assert.strictEqual(group2.classList.has('is-collapsed'), false);
assert.deepStrictEqual(getStoredCollapsed(), []);
console.log("  [OK] expandAllGenerations разворачивает все группы и очищает localStorage");

// Test 6: поиск раскрывает группу с найденным человеком!
// Сначала все сворачиваем
collapseAllGenerations();
assert.strictEqual(group2.classList.has('is-collapsed'), true);

// Ищем 'Жандос' (он в group2)
onSearchInput('Жандос');
assert.strictEqual(group2.classList.has('is-collapsed'), false, 'Группа с Жандосом должна автоматически раскрыться!');
assert.strictEqual(head2.getAttribute('aria-expanded'), 'true');
console.log("  [OK] Поиск 'Жандос' автоматически раскрыл свёрнутую группу 2-го поколения!");

// Ищем 'Алтыбай' (он в groupAncestors)
onSearchInput('Алтыбай');
assert.strictEqual(groupAncestors.classList.has('is-collapsed'), false, 'Группа с Алтыбаем должна автоматически раскрыться!');
console.log("  [OK] Поиск 'Алтыбай' автоматически раскрыл свёрнутую группу предков!");

console.log("\n-------------------------------------------------------");
console.log("Все 6 JS тестов успешно пройдены! [SUCCESS]");
console.log("-------------------------------------------------------");
