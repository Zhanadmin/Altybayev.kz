/* ALT-013: подсказка-расшифровка к термину родства.
 * Кнопка .kin-hint[data-hint]: наведение (мышь) или тап открывает плашку, тап вне — закрывает.
 * Делегирование событий — работает и для элементов, добавленных позже (дерево, Үш жұрт). */
(function () {
    'use strict';
    var pop = null;
    var current = null;

    function ensurePop() {
        if (pop) return pop;
        pop = document.createElement('div');
        pop.className = 'kin-hint-pop';
        pop.setAttribute('role', 'tooltip');
        pop.hidden = true;
        document.body.appendChild(pop);
        return pop;
    }

    function hide() {
        if (pop) pop.hidden = true;
        current = null;
    }

    function show(btn) {
        var text = btn.getAttribute('data-hint');
        if (!text) return;
        var p = ensurePop();
        p.textContent = text;
        p.hidden = false;
        p.style.left = '0px';
        p.style.top = '0px';
        var r = btn.getBoundingClientRect();
        var w = p.offsetWidth;
        var h = p.offsetHeight;
        var vw = document.documentElement.clientWidth;
        var left = Math.max(8, Math.min(r.left + r.width / 2 - w / 2, vw - w - 8));
        var top = r.bottom + 6;
        if (top + h > window.innerHeight - 8 && r.top - h - 6 > 8) {
            top = r.top - h - 6;
        }
        p.style.left = left + 'px';
        p.style.top = top + 'px';
        current = btn;
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.kin-hint') : null;
        if (btn) {
            // не запускаем клик по карточке/строке под значком
            e.preventDefault();
            e.stopPropagation();
            if (current === btn) hide(); else show(btn);
            return;
        }
        hide();
    }, true);

    document.addEventListener('mouseover', function (e) {
        if (e.pointerType === 'touch') return;
        var btn = e.target.closest ? e.target.closest('.kin-hint') : null;
        if (btn && current !== btn) show(btn);
    });

    document.addEventListener('mouseout', function (e) {
        var btn = e.target.closest ? e.target.closest('.kin-hint') : null;
        if (btn && current === btn && document.activeElement !== btn) hide();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') hide();
    });
    window.addEventListener('scroll', hide, true);
    window.addEventListener('resize', hide);
})();
