<?php
declare(strict_types=1);

use App\Core\I18n;
use App\Core\Session;

/** @var array $clans */
/** @var bool $registryReady */

$lang = I18n::lang();
$ru = ($lang === 'ru');
$title = $ru ? 'Ру таңбалары' : 'Ру таңбалары';
$activeNav = 'clans';
$dsThemeReady = true;

require __DIR__ . '/../layout/master_start.php';

$zhuzLabels = [
    'senior' => $ru ? 'Старший жуз' : 'Ұлы жүз',
    'middle' => $ru ? 'Средний жуз' : 'Орта жүз',
    'junior' => $ru ? 'Младший жуз' : 'Кіші жүз',
    'other'  => $ru ? 'Вне жузов / не указан' : 'Жүзден тыс / көрсетілмеген',
];
$csrf = Session::csrfToken();
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<style>
.cl-wrap { display: flex; flex-direction: column; gap: var(--sp-5, 20px); }
.cl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
.cl-card { display: flex; flex-direction: column; gap: 8px; }
.cl-top { display: flex; gap: 12px; align-items: center; }
.cl-tamga { width: 56px; height: 56px; flex: 0 0 56px; border: 1px solid var(--c-border, #ddd); border-radius: 8px; display: flex; align-items: center; justify-content: center; background: repeating-conic-gradient(rgba(128,128,128,.12) 0 25%, transparent 0 50%) 0 0 / 12px 12px; overflow: hidden; }
.cl-tamga img { max-width: 100%; max-height: 100%; object-fit: contain; }
.cl-tamga span { font-size: 11px; opacity: .55; text-align: center; }
.cl-name { font-weight: 600; }
.cl-sub { font-size: 13px; opacity: .75; }
.cl-actions { display: flex; flex-wrap: wrap; gap: 6px; }
.cl-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
.cl-form label { display: flex; flex-direction: column; gap: 4px; font-size: 13px; }
.cl-draw { display: none; flex-direction: column; gap: 8px; }
.cl-draw.open { display: flex; }
.cl-canvas { width: 256px; height: 256px; border: 1px solid var(--c-border, #ccc); border-radius: 8px; touch-action: none; cursor: crosshair; background: repeating-conic-gradient(rgba(128,128,128,.12) 0 25%, transparent 0 50%) 0 0 / 16px 16px; }
.cl-msg { font-size: 13px; min-height: 18px; }
.cl-msg.err { color: #c0392b; }
.cl-msg.ok { color: #2e7d32; }
</style>

<div class="cl-wrap">
    <div class="ds-page-head">
        <div>
            <h1>Ру таңбалары</h1>
            <p class="sub"><?= $ru ? 'Справочник ру и их знаков (таңба). Знак показывается рядом с названием ру.' : 'Ру тізімі және олардың таңбалары. Таңба ру атауының жанында көрсетіледі.' ?></p>
        </div>
    </div>

    <?php if (!$registryReady): ?>
        <div class="island">
            <p><strong><?= $ru ? 'Справочник ещё не включён.' : 'Анықтамалық әлі қосылмаған.' ?></strong>
            <?= $ru ? 'Нужно применить миграцию 2026_11_17_clans_tamga_registry.sql (php database/migrate.php) — это делает владелец.' : '2026_11_17_clans_tamga_registry.sql миграциясын қолдану керек (php database/migrate.php).' ?></p>
        </div>
    <?php else: ?>

    <div class="island">
        <div class="island-head"><h3 id="clFormTitle"><?= $ru ? 'Новый ру' : 'Жаңа ру' ?></h3></div>
        <form id="clForm" class="cl-form" autocomplete="off">
            <input type="hidden" name="id" value="0">
            <label><?= $ru ? 'Название (қаз)' : 'Атауы (қаз)' ?>
                <input class="input" name="name" maxlength="100" required placeholder="Керей"></label>
            <label><?= $ru ? 'Название (рус)' : 'Атауы (орыс)' ?>
                <input class="input" name="name_ru" maxlength="100" placeholder="Кереи"></label>
            <label><?= $ru ? 'Жуз' : 'Жүз' ?>
                <select class="input" name="zhuz">
                    <?php foreach ($zhuzLabels as $k => $v): ?><option value="<?= $e($k) ?>"><?= $e($v) ?></option><?php endforeach; ?>
                </select></label>
            <label style="grid-column: 1 / -1"><?= $ru ? 'Краткое описание' : 'Қысқаша сипаттама' ?>
                <input class="input" name="summary" maxlength="255"></label>
            <div class="cl-actions" style="grid-column: 1 / -1">
                <button type="submit" class="btn btn-primary"><?= $ru ? 'Сохранить' : 'Сақтау' ?></button>
                <button type="button" class="btn" id="clReset"><?= $ru ? 'Новый' : 'Жаңа' ?></button>
            </div>
        </form>
        <div class="cl-msg" id="clMsg" role="status"></div>
    </div>

    <div class="island">
        <div class="island-head" style="gap:10px;flex-wrap:wrap">
            <h3><?= $ru ? 'Список ру' : 'Ру тізімі' ?> (<span id="clCount"><?= count($clans) ?></span>)</h3>
            <input class="input" id="clSearch" type="search" placeholder="<?= $ru ? 'Поиск…' : 'Іздеу…' ?>" style="max-width:240px;margin-left:auto">
        </div>
        <div class="cl-grid" id="clList">
        <?php foreach ($clans as $c): ?>
            <div class="island-sub cl-card" data-id="<?= (int)$c['id'] ?>"
                 data-search="<?= $e(mb_strtolower($c['name'] . ' ' . ($c['name_ru'] ?? ''))) ?>"
                 data-json="<?= $e(json_encode(['id' => (int)$c['id'], 'name' => $c['name'], 'name_ru' => $c['name_ru'] ?? '', 'zhuz' => $c['zhuz'], 'summary' => $c['summary'] ?? ''], JSON_UNESCAPED_UNICODE)) ?>">
                <div class="cl-top">
                    <div class="cl-tamga">
                        <?php if (!empty($c['tamga_url'])): ?><img src="<?= $e($c['tamga_url']) ?>" alt="" loading="lazy"><?php else: ?><span><?= $ru ? 'нет знака' : 'таңба жоқ' ?></span><?php endif; ?>
                    </div>
                    <div>
                        <div class="cl-name"><?= $e($c['name']) ?><?= !empty($c['name_ru']) ? ' · ' . $e($c['name_ru']) : '' ?></div>
                        <div class="cl-sub"><?= $e($zhuzLabels[$c['zhuz']] ?? '') ?> · <?= (int)$c['persons_count'] ?> <?= $ru ? 'чел.' : 'адам' ?></div>
                    </div>
                </div>
                <?php if (!empty($c['summary'])): ?><div class="cl-sub"><?= $e($c['summary']) ?></div><?php endif; ?>
                <div class="cl-actions">
                    <button type="button" class="btn" data-act="edit"><?= $ru ? 'Изменить' : 'Өзгерту' ?></button>
                    <label class="btn" style="cursor:pointer"><?= $ru ? 'Загрузить знак' : 'Таңба жүктеу' ?>
                        <input type="file" accept=".svg,.png,image/svg+xml,image/png" data-act="upload" hidden></label>
                    <button type="button" class="btn" data-act="draw"><?= $ru ? 'Нарисовать' : 'Салу' ?></button>
                    <?php if (!empty($c['tamga_url'])): ?><button type="button" class="btn" data-act="rmtamga"><?= $ru ? 'Убрать знак' : 'Таңбаны алу' ?></button><?php endif; ?>
                    <button type="button" class="btn" data-act="delete"><?= $ru ? 'Удалить' : 'Өшіру' ?></button>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <div class="island cl-draw" id="clDraw">
        <div class="island-head"><h3><?= $ru ? 'Рисование таңба' : 'Таңба салу' ?>: <span id="clDrawFor"></span></h3></div>
        <canvas id="clCanvas" class="cl-canvas" width="256" height="256"></canvas>
        <div class="cl-actions">
            <label><?= $ru ? 'Толщина' : 'Қалыңдығы' ?> <input type="range" id="clWidth" min="2" max="24" value="8"></label>
            <button type="button" class="btn" id="clPen"><?= $ru ? 'Кисть' : 'Қылқалам' ?></button>
            <button type="button" class="btn" id="clEraser"><?= $ru ? 'Ластик' : 'Өшіргіш' ?></button>
            <button type="button" class="btn" id="clClear"><?= $ru ? 'Очистить' : 'Тазалау' ?></button>
            <button type="button" class="btn btn-primary" id="clSaveDraw"><?= $ru ? 'Сохранить как таңба' : 'Таңба ретінде сақтау' ?></button>
            <button type="button" class="btn" id="clCancelDraw"><?= $ru ? 'Закрыть' : 'Жабу' ?></button>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($registryReady): ?>
<script>
(function () {
    const CSRF = <?= json_encode($csrf) ?>;
    const RU = <?= $ru ? 'true' : 'false' ?>;
    const msg = document.getElementById('clMsg');
    const form = document.getElementById('clForm');
    function say(t, ok) { msg.textContent = t || ''; msg.className = 'cl-msg ' + (ok ? 'ok' : 'err'); }
    async function post(url, fd) {
        fd.append('csrf_token', CSRF);
        try {
            const r = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
            const j = await r.json().catch(() => ({ success: false, error: 'HTTP ' + r.status }));
            return j;
        } catch (e) { return { success: false, error: String(e) }; }
    }
    function fdOf(obj) { const f = new FormData(); Object.keys(obj).forEach(k => f.append(k, obj[k])); return f; }
    function done(j, okText) {
        if (j.success) { say(okText, true); setTimeout(() => location.reload(), 500); }
        else say(j.error || 'Error', false);
    }

    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        done(await post('/api/clans/save', new FormData(form)), RU ? 'Сохранено' : 'Сақталды');
    });
    document.getElementById('clReset').addEventListener('click', () => {
        form.reset(); form.id.value = 0;
        document.getElementById('clFormTitle').textContent = RU ? 'Новый ру' : 'Жаңа ру';
    });
    document.getElementById('clSearch').addEventListener('input', (e) => {
        const q = e.target.value.trim().toLowerCase(); let n = 0;
        document.querySelectorAll('#clList .cl-card').forEach(c => {
            const show = !q || c.dataset.search.includes(q); c.style.display = show ? '' : 'none'; if (show) n++;
        });
        document.getElementById('clCount').textContent = n;
    });

    // рисование
    const draw = document.getElementById('clDraw'), cv = document.getElementById('clCanvas'), ctx = cv.getContext('2d');
    let drawId = 0, erasing = false, down = false, last = null;
    ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    function pos(e) { const r = cv.getBoundingClientRect(); return { x: (e.clientX - r.left) * cv.width / r.width, y: (e.clientY - r.top) * cv.height / r.height }; }
    cv.addEventListener('pointerdown', (e) => { down = true; last = pos(e); cv.setPointerCapture(e.pointerId); stroke(last, last); });
    cv.addEventListener('pointermove', (e) => { if (!down) return; const p = pos(e); stroke(last, p); last = p; });
    ['pointerup', 'pointercancel'].forEach(t => cv.addEventListener(t, () => { down = false; }));
    function stroke(a, b) {
        ctx.globalCompositeOperation = erasing ? 'destination-out' : 'source-over';
        ctx.strokeStyle = '#1b1b1b'; ctx.lineWidth = +document.getElementById('clWidth').value;
        ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
    }
    document.getElementById('clPen').onclick = () => { erasing = false; };
    document.getElementById('clEraser').onclick = () => { erasing = true; };
    document.getElementById('clClear').onclick = () => ctx.clearRect(0, 0, cv.width, cv.height);
    document.getElementById('clCancelDraw').onclick = () => draw.classList.remove('open');
    document.getElementById('clSaveDraw').onclick = () => {
        cv.toBlob(async (blob) => {
            if (!blob) return say(RU ? 'Не удалось получить рисунок' : 'Сурет алынбады', false);
            const fd = fdOf({ id: drawId }); fd.append('tamga', blob, 'tamga.png');
            done(await post('/api/clans/tamga/upload', fd), RU ? 'Знак сохранён' : 'Таңба сақталды');
        }, 'image/png');
    };

    document.getElementById('clList').addEventListener('click', async (ev) => {
        const t = ev.target.closest('[data-act]'); if (!t || t.tagName === 'INPUT') return;
        const card = t.closest('.cl-card'); const d = JSON.parse(card.dataset.json); const act = t.dataset.act;
        if (act === 'edit') {
            form.id.value = d.id; form.name.value = d.name; form.name_ru.value = d.name_ru; form.zhuz.value = d.zhuz; form.summary.value = d.summary;
            document.getElementById('clFormTitle').textContent = (RU ? 'Изменить: ' : 'Өзгерту: ') + d.name;
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else if (act === 'draw') {
            drawId = d.id; ctx.clearRect(0, 0, cv.width, cv.height); erasing = false;
            document.getElementById('clDrawFor').textContent = d.name; draw.classList.add('open'); draw.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else if (act === 'rmtamga') {
            if (confirm(RU ? 'Убрать знак у «' + d.name + '»?' : '«' + d.name + '» таңбасын алу?')) done(await post('/api/clans/tamga/delete', fdOf({ id: d.id })), RU ? 'Знак убран' : 'Таңба алынды');
        } else if (act === 'delete') {
            if (confirm(RU ? 'Удалить ру «' + d.name + '»?' : '«' + d.name + '» руын өшіру?')) done(await post('/api/clans/delete', fdOf({ id: d.id })), RU ? 'Удалено' : 'Өшірілді');
        }
    });
    document.getElementById('clList').addEventListener('change', async (ev) => {
        const inp = ev.target.closest('input[data-act="upload"]'); if (!inp || !inp.files[0]) return;
        const d = JSON.parse(inp.closest('.cl-card').dataset.json);
        const fd = fdOf({ id: d.id }); fd.append('tamga', inp.files[0]);
        done(await post('/api/clans/tamga/upload', fd), RU ? 'Знак загружен' : 'Таңба жүктелді');
    });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../layout/master_end.php'; ?>
