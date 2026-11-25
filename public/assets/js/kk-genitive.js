/* ALT-075: родительный падеж казахских имён («Жандос» → «Жандостың») */
(function (root) {
    'use strict';
    var SOFT = 'әөүіеиэю';
    var HARD = 'аоұыя';
    var VOWELS = SOFT + HARD;
    // после гласных и р, й, у, а также м, н, ң → -ның/-нің
    var N_END = VOWELS + 'рйумнң';
    // после л, ж, з → -дың/-дің; всё остальное (глухие и б, в, г, ғ, д) → -тың/-тің
    var D_END = 'лжз';

    function kkGenitive(name) {
        var s = String(name == null ? '' : name).trim();
        if (!s) return '';
        var low = s.toLowerCase();
        var last = low.charAt(low.length - 1);
        var soft = false;
        for (var i = low.length - 1; i >= 0; i--) {
            var ch = low.charAt(i);
            if (SOFT.indexOf(ch) >= 0) { soft = true; break; }
            if (HARD.indexOf(ch) >= 0) { soft = false; break; }
        }
        var cons;
        if (N_END.indexOf(last) >= 0) cons = 'н';
        else if (D_END.indexOf(last) >= 0) cons = 'д';
        else cons = 'т';
        return s + cons + (soft ? 'ің' : 'ың');
    }

    root.kkGenitive = kkGenitive;
    if (typeof module !== 'undefined' && module.exports) module.exports = kkGenitive;
})(typeof window !== 'undefined' ? window : globalThis);
