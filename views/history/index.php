<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
$title = ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Журнал изменений' : 'Өзгерістер журналы';
$activeNav = 'history';
$dsThemeReady = true; // TASK-008: страница на цветах дизайн-системы — доступна тёмная тема

require __DIR__ . '/../layout/master_start.php';

$ru = (($lang === 'ru' || $lang === 'en' || $lang === 'es'));
$f = $filters;
$hasFilter = ($f['user'] > 0 || $f['date_from'] !== '' || $f['date_to'] !== '' || $f['person'] > 0 || $f['action'] !== '' || $f['table'] !== '' || $f['q'] !== '' || $f['kind'] !== '');
// ALT-024: вкладки по видам действий (счётчики — при текущих остальных фильтрах)
$kinds = [
    ''        => $ru ? 'Все' : 'Барлығы',
    'create'  => $ru ? 'Добавления' : 'Қосулар',
    'update'  => $ru ? 'Изменения' : 'Өзгерістер',
    'delete'  => $ru ? 'Удаления' : 'Жоюлар',
    'restore' => $ru ? 'Восстановления' : 'Қалпына келтіру',
    'account' => $ru ? 'Входы и аккаунты' : 'Кіру және аккаунттар',
];
$pages = max(1, (int)ceil($totalCount / max(1, $perPage)));
$qs = static function (array $over) use ($f): string {
    $p = array_filter(array_merge($f, $over), static fn($v) => $v !== '' && $v !== 0 && $v !== null);
    return '/history' . ($p ? '?' . http_build_query($p) : '');
};
$actions = [
    'CREATE'   => $ru ? 'Добавил' : 'Қосты',
    'UPDATE'   => $ru ? 'Изменил' : 'Өзгертті',
    'MOVE'     => $ru ? 'Перенёс (смена отца/матери)' : 'Көшірді (әке/ана ауысты)',
    'DELETE'   => $ru ? 'Удалил' : 'Жойды',
    'ROLLBACK' => $ru ? 'Откат' : 'Кері қайтару',
    'IMPORT_GEDCOM' => $ru ? 'Импорт' : 'Импорт',
];
?>

<div class="hs">
    <div class="ds-page-head">
        <div>
            <h1><?= $ru ? 'Журнал изменений' : 'Өзгерістер журналы' ?></h1>
            <p class="sub"><?= $ru ? 'Кто, когда и что изменил в древе — от новых к старым. Записи нельзя править или удалять.' : 'Кім, қашан және не өзгертті — жаңадан ескіге қарай. Жазбаларды өңдеуге немесе жоюға болмайды.' ?></p>
        </div>
        <div class="actions">
            <span class="badge"><?= $ru ? 'Записей:' : 'Жазба:' ?> <b><?= (int)$totalCount ?></b></span>
        </div>
    </div>

    <!-- ALT-024: вкладки по видам -->
    <nav class="hs-chips" aria-label="<?= $ru ? 'Вид действия' : 'Әрекет түрі' ?>">
        <?php foreach ($kinds as $k => $lbl):
            $cnt = $k === '' ? ($kindCounts['all'] ?? 0) : ($kindCounts[$k] ?? 0); ?>
            <a class="hs-chip<?= $f['kind'] === $k ? ' is-active' : '' ?>" href="<?= htmlspecialchars($qs(['kind' => $k, 'page' => 1])) ?>"<?= $f['kind'] === $k ? ' aria-current="true"' : '' ?>>
                <?= htmlspecialchars($lbl) ?> <b><?= (int)$cnt ?></b></a>
        <?php endforeach; ?>
    </nav>

    <!-- Фильтры -->
    <form method="GET" action="/history" class="island toolbar pp-filters" id="hsFilters">
        <?php if ($f['kind'] !== ''): ?><input type="hidden" name="kind" value="<?= htmlspecialchars($f['kind']) ?>"><?php endif; ?>
        <input type="search" name="q" class="input" value="<?= htmlspecialchars($f['q']) ?>" maxlength="80"
               placeholder="<?= $ru ? 'Поиск по имени…' : 'Аты бойынша іздеу…' ?>" aria-label="<?= $ru ? 'Поиск по имени' : 'Аты бойынша іздеу' ?>">
        <select name="user" class="select" aria-label="<?= $ru ? 'Пользователь' : 'Пайдаланушы' ?>">
            <option value=""><?= $ru ? 'Пользователь: все' : 'Пайдаланушы: барлығы' ?></option>
            <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['user_id'] ?>" <?= (int)$u['user_id'] === $f['user'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$u['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="table" class="select" aria-label="<?= $ru ? 'Объект' : 'Нысан' ?>">
            <option value=""><?= $ru ? 'Объект: все' : 'Нысан: барлығы' ?></option>
            <?php foreach ($tableLabels as $k => $lbl): ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= $f['table'] === $k ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="small muted"><?= $ru ? 'с' : 'бастап' ?>
            <input type="date" name="date_from" class="input" value="<?= htmlspecialchars($f['date_from']) ?>"></label>
        <label class="small muted"><?= $ru ? 'по' : 'дейін' ?>
            <input type="date" name="date_to" class="input" value="<?= htmlspecialchars($f['date_to']) ?>"></label>
        <?php if ($f['person'] > 0): ?>
            <input type="hidden" name="person" value="<?= (int)$f['person'] ?>">
            <span class="badge badge-accent"><?= $ru ? 'Человек:' : 'Адам:' ?> <?= htmlspecialchars($personName !== '' ? $personName : ('#' . $f['person'])) ?></span>
        <?php endif; ?>
        <button type="submit" class="btn"><?= $ru ? 'Применить' : 'Қолдану' ?></button>
        <?php if ($hasFilter): ?>
            <a href="/history" class="btn btn-ghost"><?= $ru ? 'Сбросить' : 'Тазарту' ?></a>
        <?php endif; ?>
    </form>

    <div class="island hs-island">
        <div class="table-wrap">
            <table class="table hs-table" id="hsTable">
                <thead>
                    <tr>
                        <th class="hs-th-fit"><?= $ru ? 'Когда' : 'Қашан' ?></th>
                        <th class="hs-th-fit"><?= $ru ? 'Кто' : 'Кім' ?></th>
                        <th class="hs-th-fit"><?= $ru ? 'Действие' : 'Әрекет' ?></th>
                        <th class="hs-th-obj"><?= $ru ? 'Кого / что' : 'Кімді / нені' ?></th>
                        <th><?= $ru ? 'Что изменено (по клику — было → стало)' : 'Не өзгерді (басқанда — бұрын → қазір)' ?></th>
                        <th class="actions hs-th-fit"><?= $ru ? 'Откат' : 'Кері қайтару' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="hm-empty hs-empty">
                                <b><?= $ru ? 'Записей нет' : 'Жазба жоқ' ?></b>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l):
                            $actClass = match ($l['action']) {
                                'CREATE'   => 'badge-success',
                                'DELETE'   => 'hs-del',
                                'ROLLBACK' => 'badge-accent',
                                default    => '',
                            };
                            $who = (string)($l['who'] ?: ($ru ? 'Система' : 'Жүйе'));
                        ?>
                            <?php
                                $fieldNames = implode(', ', array_map(static fn($c) => (string)$c['field'], array_slice($l['changes'] ?? [], 0, 6)));
                                if ($fieldNames === '') {
                                    $fieldNames = (string)$l['summary'];
                                }
                            ?>
                            <tr class="hs-row" data-log-id="<?= (int)$l['id'] ?>" tabindex="0" aria-expanded="false">
                                <td class="hs-c-time muted"><?= htmlspecialchars(date('H:i · d.m.Y', strtotime((string)$l['created_at']))) ?></td>
                                <td class="hs-c-who" title="<?= htmlspecialchars($who) ?>"><b><?= htmlspecialchars($who) ?></b></td>
                                <td class="hs-c-act"><span class="badge <?= $actClass ?>"><?= htmlspecialchars($l['verb']) ?></span></td>
                                <td title="<?= htmlspecialchars($l['object_label'] . ': ' . $l['object_name']) ?>">
                                    <span class="muted"><?= htmlspecialchars($l['object_label']) ?>:</span>
                                    <?php if (!empty($l['link'])): ?>
                                        <a href="<?= htmlspecialchars($l['link']) ?>" class="pp-name"><?= htmlspecialchars($l['object_name']) ?></a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($l['object_name']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="hs-c-brief" title="<?= htmlspecialchars($fieldNames) ?>"><?= htmlspecialchars($fieldNames) ?></td>
                                <td class="actions hs-c-roll">
                                    <?php if (!empty($l['rolled_back'])): ?>
                                        <span class="badge"><?= $ru ? 'откачено' : 'қайтарылды' ?></span>
                                    <?php elseif (!empty($l['can_rollback'])): ?>
                                        <button type="button" class="btn btn-sm" onclick="handleRollback(<?= (int)$l['id'] ?>)">
                                            <svg class="ic" aria-hidden="true"><use href="#i-history"/></svg><?= $ru ? 'Откатить' : 'Қайтару' ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr class="hs-detail" hidden>
                                <td colspan="6"><?php include __DIR__ . '/_changes.php'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="toolbar" aria-label="<?= $ru ? 'Страницы' : 'Беттер' ?>" id="hsPager">
            <?php if ($page > 1): ?><a class="btn btn-ghost" href="<?= htmlspecialchars($qs(['page' => $page - 1])) ?>">←</a><?php endif; ?>
            <span class="muted"><?= (int)$page ?> / <?= (int)$pages ?></span>
            <?php if ($page < $pages): ?><a class="btn btn-ghost" href="<?= htmlspecialchars($qs(['page' => $page + 1])) ?>">→</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</div>

<style>
    .hs-changes { list-style: none; margin: 0; padding: 0; display: grid; gap: 2px; }
    .hs-old { text-decoration: line-through; opacity: .7; }
    .hs-new { font-weight: 600; }
    .hs-row td { padding-top: 3px; padding-bottom: 3px; }
</style>
<script>
    // ALT-024: клик по строке раскрывает «было → стало»
    (function () {
        const tb = document.querySelector('#hsTable tbody');
        if (!tb) return;
        const toggle = (row) => {
            const d = row.nextElementSibling;
            if (!d || !d.classList.contains('hs-detail')) return;
            d.hidden = !d.hidden;
            row.setAttribute('aria-expanded', d.hidden ? 'false' : 'true');
        };
        tb.addEventListener('click', (e) => {
            if (e.target.closest('a, button')) return;
            const row = e.target.closest('tr.hs-row');
            if (row) toggle(row);
        });
        tb.addEventListener('keydown', (e) => {
            if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('tr.hs-row')) { e.preventDefault(); toggle(e.target); }
        });
    })();

    function hsNotify(message, type) {
        if (typeof showAppToast === 'function') { showAppToast(message, type); } else { alert(message); }
    }

    async function handleRollback(logId) {
        if (!confirm('<?= $ru ? 'Вернуть прежние значения? Будет добавлена запись об откате.' : 'Бұрынғы мәндерді қайтару керек пе? Кері қайтару туралы жазба қосылады.' ?>')) {
            return;
        }
        const formData = new URLSearchParams();
        formData.append('log_id', logId);
        try {
            const res = await fetch('/api/history/rollback', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            const data = await res.json();
            if (data && (data.success || data.status === 'success')) {
                hsNotify('<?= $ru ? 'Изменение успешно откачено!' : 'Өзгерту сәтті кері қайтарылды!' ?>', 'success');
                setTimeout(() => window.location.reload(), 900);
            } else {
                hsNotify('Қате: ' + (data.error || 'Откат жасау мүмкін болмады'), 'error');
            }
        } catch (err) {
            hsNotify('Сұраныс қатесі: ' + err.message, 'error');
        }
    }
</script>

<?php
require __DIR__ . '/../layout/master_end.php';
?>
