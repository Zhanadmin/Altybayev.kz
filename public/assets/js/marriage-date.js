/* ALT-078: поле «Дата заключения брака / развода» — полная дата (input type=date).
   Старые записи (только год или текст) не теряются: показываются подсказкой, пока не уточнена дата. */
(function () {
    'use strict';
    function hintOf(el) {
        var h = el.parentNode.querySelector('.md-legacy-hint[data-for="' + el.id + '"]');
        if (!h) {
            h = document.createElement('div');
            h.className = 'md-legacy-hint muted small';
            h.setAttribute('data-for', el.id);
            h.style.display = 'none';
            el.parentNode.appendChild(h);
        }
        return h;
    }
    // Записать значение из базы в поле
    window.mdSet = function (el, raw) {
        if (!el) { return; }
        raw = String(raw || '').trim();
        var iso = '';
        var m;
        if ((m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(raw))) { iso = raw; }
        else if ((m = /^(\d{1,2})\.(\d{1,2})\.(\d{4})$/.exec(raw))) { iso = m[3] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[1]).slice(-2); }
        el.value = iso;
        el.dataset.legacy = iso ? '' : raw;
        var h = hintOf(el);
        if (iso || !raw) { h.style.display = 'none'; h.textContent = ''; }
        else { h.style.display = ''; h.textContent = 'Қазір: ' + raw + ' — нақты күнді көрсетіңіз / Сейчас: ' + raw + ' — укажите точную дату'; }
    };
    // Значение для отправки: новая дата, иначе прежнее (год/текст) без изменений
    window.mdGet = function (el) {
        if (!el) { return ''; }
        return (el.value || el.dataset.legacy || '').trim();
    };
    // Поле очищено вручную: прежнее значение тоже убрать
    window.mdClear = function (el) {
        if (!el) { return; }
        el.value = '';
        el.dataset.legacy = '';
        var h = el.parentNode.querySelector('.md-legacy-hint[data-for="' + el.id + '"]');
        if (h) { h.style.display = 'none'; }
    };
})();
