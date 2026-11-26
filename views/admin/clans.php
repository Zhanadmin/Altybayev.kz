<?php
declare(strict_types=1);

use App\Core\I18n;
use App\Core\Session;

/** @var array $clans */
/** @var bool $registryReady */
/** @var bool|null $canEdit */

$canEdit = !empty($canEdit);
$lang = I18n::lang();
$ru = (($lang === 'ru' || $lang === 'en' || $lang === 'es'));
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
$zhuzFilterLabels = [
    'senior' => $ru ? 'Старший жуз' : 'Ұлы жүз',
    'middle' => $ru ? 'Средний жуз' : 'Орта жүз',
    'junior' => $ru ? 'Младший жуз' : 'Кіші жүз',
    'other'  => $ru ? 'Жуз не указан / вне жузов' : 'Жүзі көрсетілмеген',
];
$zhuzCounts = ['senior' => 0, 'middle' => 0, 'junior' => 0, 'other' => 0];
foreach ($clans as $c0) {
    $k0 = in_array($c0['zhuz'], ['senior', 'middle', 'junior'], true) ? $c0['zhuz'] : 'other';
    $zhuzCounts[$k0]++;
}
$csrf = Session::csrfToken();
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . '/../partials/clan_tamga_medallion.php'; // ALT-117: единый компонент карточки

$zhuzOrder = ['senior', 'middle', 'junior', 'other'];
$grouped = ['senior' => [], 'middle' => [], 'junior' => [], 'other' => []];
foreach ($clans as $c0) {
    $gk = in_array($c0['zhuz'], ['senior', 'middle', 'junior'], true) ? $c0['zhuz'] : 'other';
    $grouped[$gk][] = $c0;
}
?>
<style>
.cl-wrap { display: flex; flex-direction: column; gap: var(--sp-5, 20px); }
.cl-bar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.cl-bar h3 { margin: 0; }
.cl-wrap, .cl-dlg { font-family: var(--font-sans); font-size: var(--fs-lg, 16px); line-height: 1.5; }
.cl-wrap .btn, .cl-dlg .btn { font-family: inherit; font-size: var(--fs-lg, 16px); height: 42px; padding: 0 16px; }
.cl-wrap .input, .cl-dlg .input { font-family: inherit; font-size: var(--fs-lg, 16px); height: 44px; }
.cl-dlg input[type=range] { width: 160px; height: 28px; }
.cl-filter { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
.cl-fbtn.active { background: var(--c-accent, #2a6df4); color: #fff; border-color: transparent; }
.cl-fn { opacity: .75; margin-left: 4px; font-variant-numeric: tabular-nums; }
.cl-actions { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.cl-dlg { border: 1px solid var(--c-border, #ccc); border-radius: 10px; padding: 16px; width: min(560px, 94vw); max-height: 90vh; max-height: 90dvh; overflow: auto; box-sizing: border-box; background: var(--c-surface, #fff); color: inherit; }
.cl-dlg[open] { position: fixed; top: 0; right: 0; bottom: 0; left: 0; margin: auto; height: fit-content; z-index: 1000; }
.cl-dlg::backdrop { background: rgba(0,0,0,.5); }
.cl-dlg h3 { margin: 0 0 10px; }
.cl-form { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.cl-form label { display: flex; flex-direction: column; gap: 6px; font-size: var(--fs-lg, 16px); }
.cl-form .full { grid-column: 1 / -1; }
.cl-draw { display: none; flex-direction: column; gap: 8px; margin-top: 10px; }
.cl-draw.open { display: flex; }
.cl-canvas { width: 256px; height: 256px; border: 1px solid var(--c-border, #ccc); border-radius: 8px; touch-action: none; cursor: crosshair; background: repeating-conic-gradient(rgba(128,128,128,.12) 0 25%, transparent 0 50%) 0 0 / 16px 16px; }
.cl-msg { font-size: var(--fs-lg, 16px); min-height: 22px; }
.cl-msg.err { color: #c0392b; }
.cl-msg.ok { color: #2e7d32; }
.cl-toolbar-island { padding: var(--sp-4, 16px); }
.cl-toolbar-island .cl-filter { margin-bottom: 0; }
.cl-bg-note { padding: 12px 14px; border-radius: 12px; border: 1px solid rgba(201, 168, 106, .45); background: rgba(201, 168, 106, .09); color: var(--c-text-2); font-size: 13.5px; line-height: 1.5; }
.cl-bg-note strong { color: var(--c-text); }
.cl-prev-emblem { position: relative; width: 260px; max-width: 100%; aspect-ratio: 1 / 1; border-radius: 12px; overflow: hidden; border: 1px solid var(--c-border, #ccc); background: #0d0a06; }
.cl-prev-emblem .ctm-emblem { position: absolute; inset: 0; }
.cl-prev-emblem .ctm-emblem svg { display: block; width: 100%; height: 100%; }
@media (max-width: 560px) { .cl-bar #clSearch { max-width: none !important; width: 100%; margin-left: 0 !important; } .cl-form { grid-template-columns: 1fr; } .cl-toolbar-island { padding: 12px; } }
</style>
<style><?= clan_tamga_medallion_styles() ?></style>

<div class="cl-wrap">
    <div class="ds-page-head">
        <div>
            <h1>Ру таңбалары</h1>
            <p class="sub"><?= $ru ? 'Единая галерея родов: квадратная эмблема с исходной таңба, названием и ұраном из справочника.' : 'Рулардың біртұтас галереясы: түпнұсқа таңба, атауы және ұраны бар шаршы елтаңба.' ?></p>
        </div>
    </div>

    <?php if (clan_emblem_background_url() === ''): ?>
    <div class="cl-bg-note">
        <strong><?= $ru ? 'Утверждённый художественный фон ещё не загружен.' : 'Бекітілген көркем фон әлі жүктелмеген.' ?></strong>
        <?= $ru ? 'Показан нейтральный каменный макет без пейзажа. Положите утверждённый растр из ChatGPT в public/assets/img/clan-emblem/approved-background.jpg — он включится без перерисовки.' : 'Пейзажсыз бейтарап тас макет көрсетілді. ChatGPT-тен бекітілген растрды public/assets/img/clan-emblem/approved-background.jpg жеріне салыңыз — ол қайта салынбай қосылады.' ?>
    </div>
    <?php endif; ?>

    <?php if (!$registryReady): ?>
        <div class="island">
            <p><strong><?= $ru ? 'Справочник ещё не включён.' : 'Анықтамалық әлі қосылмаған.' ?></strong>
            <?= $ru ? 'Нужно применить миграцию 2026_11_17_clans_tamga_registry.sql (php database/migrate.php) — это делает владелец.' : '2026_11_17_clans_tamga_registry.sql миграциясын қолдану керек (php database/migrate.php).' ?></p>
        </div>
    <?php else: ?>

    <div class="island cl-toolbar-island">
        <div class="cl-bar" style="margin-bottom:10px">
            <?php if ($canEdit): ?>
                <button type="button" class="btn btn-primary btn-sm" id="clAdd">+ <?= $ru ? 'Добавить' : 'Қосу' ?></button>
            <?php endif; ?>
            <h3><?= $ru ? 'Список ру' : 'Ру тізімі' ?> (<span id="clCount"><?= count($clans) ?></span>)</h3>
            <input class="input" id="clSearch" type="search" placeholder="<?= $ru ? 'Поиск по названию, ұрану, описанию…' : 'Атауы, ұраны, сипаттамасы бойынша іздеу…' ?>" style="max-width:320px;margin-left:auto">
        </div>
        <div class="cl-filter" id="clFilter" role="group" aria-label="<?= $ru ? 'Фильтр по жузу' : 'Жүз бойынша сүзгі' ?>">
            <button type="button" class="btn btn-sm cl-fbtn active" data-z="all"><?= $ru ? 'Все' : 'Барлығы' ?> <span class="cl-fn" data-n="all"><?= count($clans) ?></span></button>
            <?php foreach ($zhuzFilterLabels as $zk => $zl): ?>
                <button type="button" class="btn btn-sm cl-fbtn" data-z="<?= $e($zk) ?>"><?= $e($zl) ?> <span class="cl-fn" data-n="<?= $e($zk) ?>"><?= (int)($zhuzCounts[$zk] ?? 0) ?></span></button>
            <?php endforeach; ?>
        </div>
        <div class="cl-msg" id="clListMsg" role="status"></div>
    </div>

    <div id="clList" class="cl-gallery">
        <?php foreach ($zhuzOrder as $zk): ?>
            <?php if (empty($grouped[$zk])) continue; ?>
            <section class="cl-zhuz" data-z="<?= $e($zk) ?>">
                <div class="cl-zhuz-head">
                    <h2><?= $e($zhuzFilterLabels[$zk]) ?> <span class="cl-zhuz-n">(<span class="cl-gn"><?= count($grouped[$zk]) ?></span>)</span></h2>
                </div>
                <div class="cl-grid">
                    <?php foreach ($grouped[$zk] as $c): ?>
                        <?= clan_tamga_card($c, ['can_edit' => $canEdit, 'ru' => $ru, 'lang' => $lang]) ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        <div class="cl-empty" id="clEmpty" hidden><?= $ru ? 'Ничего не найдено. Измените запрос или фильтр.' : 'Ештеңе табылмады. Сұранысты немесе сүзгіні өзгертіңіз.' ?></div>
    </div>

    <?php if ($canEdit): ?>
    <dialog class="cl-dlg" id="clDlg">
        <h3 id="clFormTitle"><?= $ru ? 'Новый ру' : 'Жаңа ру' ?></h3>
        <form id="clForm" class="cl-form" autocomplete="off">
            <input type="hidden" name="id" value="0">
            <label><?= $ru ? 'Название (қаз) *' : 'Атауы (қаз) *' ?>
                <input class="input" name="name" maxlength="100" required placeholder="<?= $ru ? 'Введите название' : 'Атауын енгізіңіз' ?>"></label>
            <label><?= $ru ? 'Название (рус)' : 'Атауы (орыс)' ?>
                <input class="input" name="name_ru" maxlength="100"></label>
            <label><?= $ru ? 'Жуз' : 'Жүз' ?>
                <select class="input" name="zhuz">
                    <?php foreach ($zhuzLabels as $k => $v): ?><option value="<?= $e($k) ?>"><?= $e($v) ?></option><?php endforeach; ?>
                </select></label>
            <label><?= $ru ? 'Уран (боевой клич)' : 'Ұран (ұраны)' ?>
                <input class="input" name="uran" maxlength="100" placeholder="<?= $ru ? 'Например: Ақжол!' : 'Мысалы: Ошыбай!' ?>"></label>
            <div class="full">
                <div style="font-size:var(--fs-lg,16px);margin-bottom:4px"><?= $ru ? 'Знак (таңба)' : 'Таңба' ?></div>
                <div class="cl-actions">
                    <div class="cl-prev-emblem" id="clPrev" role="img" aria-label="<?= $ru ? 'Предпросмотр герба' : 'Елтаңбаны алдын ала қарау' ?>"></div>
                    <label class="btn btn-sm" style="cursor:pointer"><?= $ru ? 'Загрузить' : 'Жүктеу' ?>
                        <input type="file" id="clFile" accept=".svg,.png,image/svg+xml,image/png" hidden></label>
                    <button type="button" class="btn btn-sm" id="clDrawBtn"><?= $ru ? 'Нарисовать' : 'Салу' ?></button>
                    <button type="button" class="btn btn-sm" id="clRmTamga"><?= $ru ? 'Убрать знак' : 'Таңбаны алу' ?></button>
                    <span id="clFileName" style="font-size:var(--fs-md,14px);opacity:.7"></span>
                </div>
            </div>
            <label class="full"><?= $ru ? 'Краткое описание / источник' : 'Қысқаша сипаттама / дереккөз' ?>
                <input class="input" name="summary" maxlength="255" placeholder="<?= $ru ? 'Например: Источник: el.kz...' : 'Мысалы: Дереккөз: el.kz...' ?>"></label>
            <label class="full"><?= $ru ? 'Описание / история рода' : 'Сипаттамасы / ру тарихы' ?>
                <textarea class="input" name="description" rows="3" style="height:auto;padding:8px 12px;font-family:inherit;font-size:inherit" placeholder="<?= $ru ? 'Исторические сведения, шежіре...' : 'Тарихы, шежірелік жазбалар...' ?>"></textarea></label>
            <div class="cl-actions full">
                <button type="submit" class="btn btn-primary btn-sm"><?= $ru ? 'Сохранить' : 'Сақтау' ?></button>
                <button type="button" class="btn btn-sm" id="clCancel"><?= $ru ? 'Отмена' : 'Бас тарту' ?></button>
            </div>
        </form>
        <div class="cl-msg" id="clMsg" role="status"></div>

        <div class="cl-draw" id="clDraw">
            <canvas id="clCanvas" class="cl-canvas" width="256" height="256"></canvas>
            <div class="cl-actions">
                <label><?= $ru ? 'Толщина' : 'Қалыңдығы' ?> <input type="range" id="clWidth" min="2" max="24" value="8"></label>
                <button type="button" class="btn btn-sm" id="clPen"><?= $ru ? 'Кисть' : 'Қылқалам' ?></button>
                <button type="button" class="btn btn-sm" id="clEraser"><?= $ru ? 'Ластик' : 'Өшіргіш' ?></button>
                <button type="button" class="btn btn-sm" id="clClear"><?= $ru ? 'Очистить' : 'Тазалау' ?></button>
                <button type="button" class="btn btn-primary btn-sm" id="clSaveDraw"><?= $ru ? 'Сохранить рисунок как таңба' : 'Таңба ретінде сақтау' ?></button>
            </div>
        </div>
    </dialog>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($registryReady): ?>
<script>window.__zaLang = <?= json_encode($lang) ?>;</script>
<script src="/assets/js/clan-emblem.js?v=<?= (int)@filemtime(__DIR__ . '/../../public/assets/js/clan-emblem.js') ?>"></script>
<script src="/assets/js/zheti-ata.js?v=<?= (int)@filemtime(__DIR__ . '/../../public/assets/js/zheti-ata.js') ?>"></script>
<script>
(function () {
    const CSRF = <?= json_encode($csrf) ?>;
    const RU = <?= $ru ? 'true' : 'false' ?>;
    const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
    const msg = document.getElementById('clMsg');
    const form = document.getElementById('clForm');
    const dlg = document.getElementById('clDlg');
    const prev = document.getElementById('clPrev');
    const fileInp = document.getElementById('clFile');
    const draw = document.getElementById('clDraw'), cv = document.getElementById('clCanvas'), ctx = cv ? cv.getContext('2d') : null;
    let cur = null; // редактируемый ру или null (новый)
    let fileUrl = '';
    let prevToken = 0;
    function say(t, ok) { if (msg) { msg.textContent = t || ''; msg.className = 'cl-msg ' + (ok ? 'ok' : 'err'); } }
    async function post(url, fd) {
        fd.append('csrf_token', CSRF);
        try {
            const r = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
            return await r.json().catch(() => ({ success: false, error: 'HTTP ' + r.status }));
        } catch (e) { return { success: false, error: String(e) }; }
    }
    function fdOf(obj) { const f = new FormData(); Object.keys(obj).forEach(k => f.append(k, obj[k])); return f; }
    function reloadSoon() { setTimeout(() => location.reload(), 400); }
    // ALT-117: живое превью в редакторе — тот же ClanEmblem.buildSvg, что и в
    // галерее/окне/экспорте. Никакого отдельного круглого медальона.
    function renderPrev(d, uploadedUrl) {
        if (!prev) return;
        const nameKk = (form && form.name ? form.name.value : (d && d.name) || '').trim();
        const nameRu = (form && form.name_ru ? form.name_ru.value : (d && d.name_ru) || '').trim();
        const name = RU && nameRu ? nameRu : nameKk;
        const uran = (form && form.uran ? form.uran.value : (d && d.uran) || '').trim();
        let zhuzLabel = '';
        if (form && form.zhuz) {
            const opt = form.zhuz.options[form.zhuz.selectedIndex];
            zhuzLabel = opt ? String(opt.textContent || '') : '';
        }
        const tamgaUrl = uploadedUrl || (d && d.tamga_url) || '';
        const token = ++prevToken;
        const label = (RU ? 'Герб рода ' : 'Ру елтаңбасы: ') + (name || (RU ? 'новый род' : 'жаңа ру'));
        prev.innerHTML = '<div class="ctm-emblem-fallback"><span>…</span></div>';
        prev.setAttribute('aria-label', label);
        const host = document.createElement('div');
        host.className = 'ctm-emblem';
        if (!window.ClanEmblem || typeof window.ClanEmblem.mount !== 'function') {
            if (tamgaUrl) {
                prev.innerHTML = '<div class="ctm-emblem-fallback"><img src="' + tamgaUrl.replace(/"/g, '&quot;') + '" alt=""></div>';
            }
            return;
        }
        window.ClanEmblem.mount(host, { name: name, uran: uran, zhuz: zhuzLabel, tamgaUrl: tamgaUrl, ariaLabel: label })
            .then((svg) => {
                if (token !== prevToken) return;
                prev.innerHTML = '<div class="ctm-emblem is-mounted">' + svg + '</div>';
            })
            .catch(() => {
                if (token !== prevToken) return;
                prev.innerHTML = '<div class="ctm-emblem-fallback"><span>' + (RU ? 'Не удалось построить превью' : 'Алдын ала қарау сәтсіз') + '</span></div>';
            });
    }
    function openDlg(d) {
        if (!dlg || !form) return;
        cur = d; form.reset(); say('', true); if (draw) draw.classList.remove('open'); if (fileInp) fileInp.value = '';
        fileUrl = '';
        const fn = document.getElementById('clFileName'); if (fn) fn.textContent = '';
        form.id.value = d ? d.id : 0;
        if (d) {
            form.name.value = d.name || '';
            form.name_ru.value = d.name_ru || '';
            form.zhuz.value = d.zhuz || 'other';
            if (form.uran) form.uran.value = d.uran || '';
            if (form.summary) form.summary.value = d.summary || '';
            if (form.description) form.description.value = d.description || '';
        }
        document.getElementById('clFormTitle').textContent = d ? (RU ? 'Изменить: ' : 'Өзгерту: ') + d.name : (RU ? 'Новый ру' : 'Жаңа ру');
        const rm = document.getElementById('clRmTamga'); if (rm) rm.style.display = d && d.tamga_url ? '' : 'none';
        const dr = document.getElementById('clDrawBtn'); if (dr) dr.style.display = d ? '' : 'none';
        renderPrev(d, '');
        dlg.showModal();
    }
    const addBtn = document.getElementById('clAdd');
    if (addBtn) addBtn.addEventListener('click', () => openDlg(null));
    const cancelBtn = document.getElementById('clCancel');
    if (cancelBtn) cancelBtn.addEventListener('click', () => dlg && dlg.close());
    if (dlg) {
        dlg.addEventListener('mousedown', (ev) => {
            if (ev.target !== dlg) return;
            const r = dlg.getBoundingClientRect();
            if (ev.clientX < r.left || ev.clientX > r.right || ev.clientY < r.top || ev.clientY > r.bottom) dlg.close();
        });
    }

    if (fileInp) {
        fileInp.addEventListener('change', () => {
            const f = fileInp.files[0];
            const fn = document.getElementById('clFileName');
            if (fn) fn.textContent = f ? f.name : '';
            fileUrl = f ? URL.createObjectURL(f) : '';
            renderPrev(cur, fileUrl);
        });
    }

    // ALT-117: любое изменение названия/жуза/ұрана сразу обновляет превью.
    if (form) {
        let previewTimer = 0;
        const refreshPreview = () => {
            clearTimeout(previewTimer);
            previewTimer = setTimeout(() => renderPrev(cur, fileUrl), 120);
        };
        ['name', 'name_ru', 'zhuz', 'uran'].forEach((k) => {
            const el = form.elements.namedItem(k);
            if (!el) return;
            el.addEventListener('input', refreshPreview);
            el.addEventListener('change', refreshPreview);
        });
    }

    if (form) {
        form.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const j = await post('/api/clans/save', new FormData(form));
            if (!j.success) return say(j.error || 'Error', false);
            const id = j.clan && j.clan.id ? j.clan.id : form.id.value;
            if (fileInp && fileInp.files[0]) {
                const fd = fdOf({ id: id }); fd.append('tamga', fileInp.files[0]);
                const u = await post('/api/clans/tamga/upload', fd);
                if (!u.success) return say(u.error || 'Error', false);
            }
            say(RU ? 'Сохранено' : 'Сақталды', true); reloadSoon();
        });
    }
    const rmTamgaBtn = document.getElementById('clRmTamga');
    if (rmTamgaBtn) {
        rmTamgaBtn.addEventListener('click', async () => {
            if (!cur || !(await appConfirm(RU ? 'Убрать знак у «' + cur.name + '»?' : '«' + cur.name + '» таңбасын алу?'))) return;
            const j = await post('/api/clans/tamga/delete', fdOf({ id: cur.id }));
            if (j.success) { say(RU ? 'Знак убран' : 'Таңба алынды', true); reloadSoon(); } else say(j.error || 'Error', false);
        });
    }

    let zf = 'all';
    function applyFilter() {
        const q = document.getElementById('clSearch').value.trim().toLowerCase();
        const per = { all: 0, senior: 0, middle: 0, junior: 0, other: 0 };
        document.querySelectorAll('#clList .cl-card').forEach(c => {
            const bySearch = !q || (c.dataset.search || '').includes(q);
            if (bySearch) { per.all++; per[c.dataset.z]++; }
            c.style.display = bySearch && (zf === 'all' || zf === c.dataset.z) ? '' : 'none';
        });
        document.querySelectorAll('#clList .cl-zhuz').forEach(g => {
            const z = g.dataset.z;
            g.style.display = per[z] > 0 && (zf === 'all' || zf === z) ? '' : 'none';
            const gn = g.querySelector('.cl-gn'); if (gn) gn.textContent = per[z];
        });
        document.querySelectorAll('#clFilter .cl-fn').forEach(s => { s.textContent = per[s.dataset.n]; });
        document.getElementById('clCount').textContent = per[zf];
        const empty = document.getElementById('clEmpty');
        if (empty) empty.hidden = per[zf] > 0;
    }
    document.getElementById('clSearch').addEventListener('input', applyFilter);
    document.getElementById('clFilter').addEventListener('click', (e) => {
        const b = e.target.closest('.cl-fbtn'); if (!b) return;
        zf = b.dataset.z;
        document.querySelectorAll('#clFilter .cl-fbtn').forEach(x => x.classList.toggle('active', x === b));
        applyFilter();
    });
    applyFilter();

    if (cv && ctx) {
        // рисование
        let erasing = false, down = false, last = null;
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
        const drawBtn = document.getElementById('clDrawBtn');
        if (drawBtn) drawBtn.onclick = () => { ctx.clearRect(0, 0, cv.width, cv.height); erasing = false; draw.classList.toggle('open'); };
        const penBtn = document.getElementById('clPen');
        if (penBtn) penBtn.onclick = () => { erasing = false; };
        const eraserBtn = document.getElementById('clEraser');
        if (eraserBtn) eraserBtn.onclick = () => { erasing = true; };
        const clearBtn = document.getElementById('clClear');
        if (clearBtn) clearBtn.onclick = () => ctx.clearRect(0, 0, cv.width, cv.height);
        const saveDrawBtn = document.getElementById('clSaveDraw');
        if (saveDrawBtn) saveDrawBtn.onclick = () => {
            if (!cur) return;
            cv.toBlob(async (blob) => {
                if (!blob) return say(RU ? 'Не удалось получить рисунок' : 'Сурет алынбады', false);
                const fd = fdOf({ id: cur.id }); fd.append('tamga', blob, 'tamga.png');
                const j = await post('/api/clans/tamga/upload', fd);
                if (j.success) { say(RU ? 'Знак сохранён' : 'Таңба сақталды', true); reloadSoon(); } else say(j.error || 'Error', false);
            }, 'image/png');
        };
    }

    document.getElementById('clList').addEventListener('click', async (ev) => {
        const t = ev.target.closest('[data-act]'); if (!t) return;
        const card = t.closest('.cl-card'); if (!card) return;
        let d;
        try { d = JSON.parse(card.dataset.json); } catch (e) { return; }
        if (t.dataset.act === 'edit') openDlg(d);
        else if (t.dataset.act === 'delete' && await appConfirm(RU ? 'Удалить ру «' + d.name + '»?' : '«' + d.name + '» руын өшіру?')) {
            const j = await post('/api/clans/delete', fdOf({ id: d.id }));
            const lm = document.getElementById('clListMsg');
            if (j.success) { lm.textContent = RU ? 'Удалено' : 'Өшірілді'; lm.className = 'cl-msg ok'; reloadSoon(); }
            else { lm.textContent = j.error || 'Error'; lm.className = 'cl-msg err'; }
        }
    });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../layout/master_end.php'; ?>
