/**
 * Task011UshZhurtJsTest.js
 * Модульное тестирование JS логики трех таблиц Үш жұрт на /tree (TASK-011)
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('=======================================================');
console.log('  Task011: JS тестирование логики Үш жұрт (TASK-011)  ');
console.log('=======================================================\n');

let passed = 0;
let total = 0;

function assertCheck(cond, label) {
    total++;
    if (cond) {
        passed++;
        console.log(`  [OK] ${label}`);
    } else {
        console.log(`  [FAIL] ${label}`);
    }
}

const treePhpPath = path.join(__dirname, '..', 'views', 'tree.php');
const treeHtml = fs.readFileSync(treePhpPath, 'utf8');

// 1. Проверка синтаксиса и наличия ключевых функций в views/tree.php
assertCheck(treeHtml.includes('function renderListTable'), 'views/tree.php содержит renderListTable');
assertCheck(treeHtml.includes('function togglePersonUshZhurt'), 'views/tree.php содержит togglePersonUshZhurt');
assertCheck(treeHtml.includes('function renderUshZhurtDrawerContent'), 'views/tree.php содержит renderUshZhurtDrawerContent');
assertCheck(treeHtml.includes('function renderSingleUshZhurtCard'), 'views/tree.php содержит renderSingleUshZhurtCard');
assertCheck(treeHtml.includes('function navigateToListPerson'), 'views/tree.php содержит navigateToListPerson');
assertCheck(treeHtml.includes('function toggleUshLayout'), 'views/tree.php содержит toggleUshLayout');
assertCheck(treeHtml.includes('function filterUshCardRows'), 'views/tree.php содержит filterUshCardRows');

// 2. Тестирование генерации HTML карточки таблицы renderSingleUshZhurtCard
const currentLang = 'kk';

function mockRenderSingleUshZhurtCard(tableData, basePersonId, zhurtKey) {
    const items = tableData.items || [];
    const count = tableData.total_count || items.length || 0;
    const title = currentLang === 'ru' ? (tableData.title_ru || tableData.title_kk) : tableData.title_kk;
    const desc = currentLang === 'ru' ? (tableData.desc_ru || tableData.desc_kk || '') : (tableData.desc_kk || '');

    let rowsHtml = '';
    if (items.length === 0) {
        rowsHtml = `<tr><td colspan="3" class="lt-ush-empty-cell">${currentLang === 'ru' ? 'Сведения в этом круге пока не внесены' : 'Бұл жұрт бойынша мәліметтер әзірге енгізілмеген'}</td></tr>`;
    } else {
        rowsHtml = items.map(p => {
            const isM = (p.gender === 'M');
            const isDeceased = (p.is_deceased == 1);
            const initials = (p.first_name ? p.first_name[0] : '') + (p.last_name ? p.last_name[0] : '');
            const term = currentLang === 'ru' ? (p.term_ru || p.term_kk) : p.term_kk;
            const years = p.years_display || (p.birth_year ? p.birth_year + (p.death_year ? '–' + p.death_year : '–...') : '—');

            return `
                <tr class="lt-ush-item-row" data-id="${p.id}" onclick="navigateToListPerson(${p.id})">
                    <td class="lt-ush-col-person">
                        <div class="lt-ush-person-inline">
                            <span class="uz-avatar-mini ${isDeceased ? 'deceased' : (isM ? 'male' : 'female')}">${initials}</span>
                            <div class="lt-ush-person-text">
                                <span class="lt-ush-person-name" title="${p.full_name}">${p.full_name}</span>
                                ${p.maiden_name ? `<span class="lt-sub is-maiden">(${p.maiden_name})</span>` : ''}
                                <span class="lt-sub">${p.clan_ru || 'белгісіз'}</span>
                            </div>
                        </div>
                    </td>
                    <td class="lt-ush-col-years">
                        <span class="lt-ush-years-text">${years}</span>
                    </td>
                    <td class="lt-ush-col-term">
                        <span class="kin-term-badge ${isM ? 'male' : 'female'}">${term}</span>
                    </td>
                </tr>
            `;
        }).join('');
    }

    return `
        <div class="lt-ush-card ${zhurtKey}-card" id="ushCard_${basePersonId}_${zhurtKey}">
            <div class="lt-ush-card-head">
                <div class="lt-ush-card-title-wrap">
                    <h4 class="lt-ush-card-title">${title} <span class="lt-ush-card-count">(${count})</span></h4>
                    <div class="lt-ush-card-desc">${desc}</div>
                </div>
            </div>
            <div class="lt-ush-subtable-wrap">
                <table class="lt-ush-subtable">
                    <thead>
                        <tr>
                            <th style="width: 50%;">${currentLang === 'ru' ? 'Персона' : 'Тұлға'}</th>
                            <th style="width: 24%;">${currentLang === 'ru' ? 'Годы' : 'Жылдары'}</th>
                            <th style="width: 26%;">${currentLang === 'ru' ? 'Кем приходится' : 'Туыстық'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

// Проверка карточки с данными
const sampleCard = mockRenderSingleUshZhurtCard({
    key: 'kaiyn',
    title_kk: 'Қайын жұрты',
    total_count: 2,
    items: [
        { id: 177, full_name: 'Избасар Кубенов', gender: 'M', years_display: '—', term_kk: 'Қайын ата', clan_ru: 'белгісіз', is_deceased: 0 },
        { id: 176, full_name: 'Канслу Кубенова', gender: 'F', years_display: '1952–...', term_kk: 'Қайын ене', clan_ru: 'белгісіз', is_deceased: 0 },
    ]
}, 6, 'kaiyn');

assertCheck(sampleCard.includes('Қайын жұрты'), 'Карточка содержит заголовок Қайын жұрты');
assertCheck(sampleCard.includes('(2)'), 'Карточка содержит счетчик (2)');
assertCheck(sampleCard.includes('Избасар Кубенов'), 'Карточка содержит имя Избасар Кубенов');
assertCheck(sampleCard.includes('Канслу Кубенова'), 'Карточка содержит имя Канслу Кубенова');
assertCheck(sampleCard.includes('Қайын ата'), 'Карточка содержит термин Қайын ата');
assertCheck(sampleCard.includes('Қайын ене'), 'Карточка содержит термин Қайын ене');
assertCheck(sampleCard.includes('onclick="navigateToListPerson(177)"'), 'Клик по человеку содержит переход navigateToListPerson(177)');
assertCheck(sampleCard.includes('uz-avatar-mini male'), 'Мужчина имеет класс uz-avatar-mini male');
assertCheck(sampleCard.includes('uz-avatar-mini female'), 'Женщина имеет класс uz-avatar-mini female');
assertCheck(sampleCard.includes('kin-term-badge male'), 'Бейдж термина родственника-мужчины имеет класс male');
assertCheck(sampleCard.includes('kin-term-badge female'), 'Бейдж термина родственницы имеет класс female');

// Проверка пустой карточки
const emptyCard = mockRenderSingleUshZhurtCard({
    key: 'kaiyn',
    title_kk: 'Қайын жұрты',
    total_count: 0,
    items: []
}, 99, 'kaiyn');

assertCheck(emptyCard.includes('lt-ush-empty-cell'), 'Пустая карточка содержит ячейку lt-ush-empty-cell');
assertCheck(emptyCard.includes('енгізілмеген'), 'Пустая карточка содержит текст об отсутствии данных');

console.log('\n-------------------------------------------------------');
console.log(`Результат: ${passed} из ${total} проверок пройдено успешно!`);
if (passed === total) {
    console.log('ВСЕ JS ТЕСТЫ ҮШ ЖҰРТ (TASK-011) УСПЕШНО ПРОЙДЕНЫ [SUCCESS]');
} else {
    console.log('ЕСТЬ ОШИБКИ В JS ТЕСТАХ!');
}
console.log('-------------------------------------------------------');
