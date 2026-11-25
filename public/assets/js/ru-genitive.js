/* ALT-081: родительный падеж мужских имён по-русски («Жандос» → «Жандоса», «Абай» → «Абая») */
(function (root) {
    'use strict';
    var VOWELS = 'аеёиоуыэюяәіөұүАЕЁИОУЫЭЮЯӘІӨҰҮ';
    // после этих согласных вместо -ы пишется -и
    var I_AFTER = 'гкхжчшщ';

    function ruGenitive(name) {
        var s = String(name == null ? '' : name).trim();
        if (!s) return '';
        var low = s.toLowerCase();
        var last = low.charAt(low.length - 1);
        var prev = low.charAt(low.length - 2);
        var base = s.slice(0, -1);

        if (last === 'й') return base + 'я';                         // Абай → Абая
        if (last === 'ь') return base + 'я';                         // Игорь → Игоря
        if (last === 'я') return base + 'и';                         // Илья → Ильи, Мария → Марии
        if (last === 'а') return base + (I_AFTER.indexOf(prev) >= 0 ? 'и' : 'ы'); // Алма → Алмы, Ольга → Ольги
        if (VOWELS.indexOf(last) >= 0 || last === 'ы') return s;     // Амангелді, Бақытжанұ — не склоняются
        return s + 'а';                                              // Жандос → Жандоса, Ғабит → Ғабита
    }

    root.ruGenitive = ruGenitive;
    if (typeof module !== 'undefined' && module.exports) module.exports = ruGenitive;
})(typeof window !== 'undefined' ? window : globalThis);
