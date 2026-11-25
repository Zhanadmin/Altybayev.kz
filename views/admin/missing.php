<?php
declare(strict_types=1);

use App\Config\Nationalities;

/** @var array $items */
/** @var array $counts */
/** @var int $totalAll */
/** @var int $totalGaps */
/** @var array $genKeys */
/** @var string $gapFilter */
/** @var string $genFilter */
/** @var array $clanNames */
/** @var string $csrf */

// ALT-098: тексты на всех языках сайта (kk, ru, en, es). Общие подписи — в missing_ctx.php (их же берёт строка таблицы).
$ctx = require __DIR__ . '/missing_ctx.php';
$lang = $ctx['lang'];
$T = $ctx['T'];
$e = $ctx['e'];
$gapLabels = $ctx['gapLabels'];
$genLabel = $ctx['genLabel'];
$title = $T(['kk' => 'Толтырылмаған', 'ru' => 'Что не заполнено', 'en' => 'Missing data', 'es' => 'Datos faltantes']);
$activeNav = 'missing';
$dsThemeReady = true;

require __DIR__ . '/../layout/master_start.php';

$url = static function (string $gap, string $gen): string {
    $q = array_filter(['gap' => $gap, 'gen' => $gen], static fn($v) => $v !== '');
    return '/admin/missing' . ($q ? '?' . http_build_query($q) : '');
};
$emptyText = $T(['kk' => 'Ештеңе табылмады', 'ru' => 'Ничего не найдено', 'en' => 'Nothing found', 'es' => 'No se encontró nada']);
?>
<style>
.ms-wrap { display: flex; flex-direction: column; gap: 16px; font-size: var(--fs-lg, 16px); line-height: 1.5; }
.ms-counters { display: flex; flex-wrap: wrap; gap: 8px; }
.ms-counter { display: inline-flex; gap: 8px; align-items: baseline; padding: 8px 12px; border: 1px solid var(--c-border, #ddd); border-radius: 8px; text-decoration: none; color: inherit; }
.ms-counter b { font-variant-numeric: tabular-nums; }
.ms-counter.active { background: var(--c-accent, #2a6df4); color: #fff; border-color: transparent; }
.ms-filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.ms-filters select { height: 42px; font: inherit; }
.ms-table { width: 100%; border-collapse: collapse; }
.ms-table th, .ms-table td { padding: 10px 12px; text-align: left; vertical-align: top; border-bottom: 1px solid var(--c-border, #e3e3e3); font-weight: 400; }
.ms-table th { opacity: .7; white-space: nowrap; }
.ms-chip { display: inline-block; padding: 2px 10px; border-radius: 999px; border: 1px solid var(--c-border, #ccc); font-size: 14px; background: rgba(128,128,128,.08); }
.ms-table .fit { width: 1%; white-space: nowrap; }
/* ALT-107: поля прямо в ячейках */
.ms-cells { display: flex; flex-wrap: wrap; gap: 10px 14px; }
.ms-cell { display: flex; flex-direction: column; gap: 3px; min-width: 190px; max-width: 280px; flex: 1 1 190px; }
.ms-lab { font-size: 13px; opacity: .75; }
.ms-in { width: 100%; box-sizing: border-box; min-height: 38px; padding: 6px 10px; font: inherit; font-size: 15px; border: 1px solid var(--c-border, #ccc); border-radius: 8px; background: var(--c-surface, transparent); color: inherit; }
.ms-in:focus { outline: 2px solid var(--c-accent, #2a6df4); outline-offset: 1px; }
.ms-in.busy { opacity: .6; }
.ms-in.err { border-color: #c0392b; }
.ms-msg { font-size: 13px; min-height: 0; }
.ms-msg.err { color: #c0392b; }
.ms-msg.ok { color: #1e8449; }
.ms-find { position: relative; }
.ms-results { position: absolute; z-index: 20; left: 0; right: 0; top: 100%; margin: 2px 0 0; padding: 4px; list-style: none; max-height: 240px; overflow-y: auto; background: var(--c-surface, #fff); color: inherit; border: 1px solid var(--c-border, #ccc); border-radius: 8px; box-shadow: 0 6px 18px rgba(0,0,0,.18); }
.ms-results[hidden] { display: none; }
.ms-results li { padding: 6px 8px; border-radius: 6px; cursor: pointer; font-size: 15px; }
.ms-results li:hover, .ms-results li:focus { background: rgba(128,128,128,.18); outline: none; }
.ms-results li.none { cursor: default; opacity: .7; }
.ms-results li small { opacity: .7; margin-left: 6px; }
</style>

<div class="ds-page-head">
    <div>
        <h1><?= $e($title) ?></h1>
        <p class="sub" id="ms-sub"><?= $e($T([
            'kk' => 'Толтырылмаған деректері бар адамдар: ' . $totalGaps . ' / ' . $totalAll,
            'ru' => 'Людей с пробелами в данных: ' . $totalGaps . ' из ' . $totalAll,
            'en' => 'People with missing data: ' . $totalGaps . ' of ' . $totalAll,
            'es' => 'Personas con datos faltantes: ' . $totalGaps . ' de ' . $totalAll,
        ])) ?></p>
    </div>
</div>

<div class="ms-wrap" id="ms-wrap" data-csrf="<?= $e($csrf) ?>" data-gap-filter="<?= $e($gapFilter) ?>" data-gen-filter="<?= $e($genFilter) ?>"
     data-msg-saved="<?= $e($T(['kk' => 'Сақталды', 'ru' => 'Сохранено', 'en' => 'Saved', 'es' => 'Guardado'])) ?>"
     data-msg-error="<?= $e($T(['kk' => 'Сақтау мүмкін болмады', 'ru' => 'Не удалось сохранить', 'en' => 'Could not save', 'es' => 'No se pudo guardar'])) ?>"
     data-msg-nobody="<?= $e($T(['kk' => 'Ешкім табылмады', 'ru' => 'Никого не найдено', 'en' => 'Nobody found', 'es' => 'Nadie encontrado'])) ?>"
     data-msg-empty="<?= $e($emptyText) ?>">
    <div class="ms-counters" aria-label="<?= $e($T(['kk' => 'Санауыштар', 'ru' => 'Счётчики', 'en' => 'Counters', 'es' => 'Contadores'])) ?>">
        <a class="ms-counter<?= $gapFilter === '' ? ' active' : '' ?>" href="<?= $e($url('', $genFilter)) ?>" data-gap="">
            <span><?= $e($T(['kk' => 'Барлығы', 'ru' => 'Все', 'en' => 'All', 'es' => 'Todos'])) ?></span><b id="ms-total"><?= (int)$totalGaps ?></b>
        </a>
        <?php foreach ($gapLabels as $key => $lab): ?>
            <a class="ms-counter<?= $gapFilter === $key ? ' active' : '' ?>" href="<?= $e($url($key, $genFilter)) ?>" data-gap="<?= $e($key) ?>">
                <span><?= $e($T($lab)) ?></span><b><?= (int)($counts[$key] ?? 0) ?></b>
            </a>
        <?php endforeach; ?>
    </div>

    <form class="ms-filters" method="get" action="/admin/missing">
        <label>
            <?= $e($T(['kk' => 'Пробел түрі', 'ru' => 'Вид пробела', 'en' => 'Gap type', 'es' => 'Tipo de dato'])) ?>
            <select name="gap" class="input" onchange="this.form.submit()">
                <option value=""><?= $e($T(['kk' => 'Барлығы', 'ru' => 'Все', 'en' => 'All', 'es' => 'Todos'])) ?></option>
                <?php foreach ($gapLabels as $key => $lab): ?>
                    <option value="<?= $e($key) ?>"<?= $gapFilter === $key ? ' selected' : '' ?>><?= $e($T($lab)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <?= $e($T(['kk' => 'Ұрпақ', 'ru' => 'Поколение', 'en' => 'Generation', 'es' => 'Generación'])) ?>
            <select name="gen" class="input" onchange="this.form.submit()">
                <option value=""><?= $e($T(['kk' => 'Барлығы', 'ru' => 'Все', 'en' => 'All', 'es' => 'Todos'])) ?></option>
                <?php foreach ($genKeys as $gk): ?>
                    <option value="<?= $e($gk) ?>"<?= $genFilter === (string)$gk ? ' selected' : '' ?>><?= $e($genLabel((string)$gk)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <noscript><button class="btn" type="submit">OK</button></noscript>
    </form>

    <datalist id="ms-clans">
        <?php foreach ($clanNames as $cn): ?><option value="<?= $e($cn) ?>"><?php endforeach; ?>
    </datalist>
    <datalist id="ms-nats">
        <?php foreach (Nationalities::codes() as $code): ?><option value="<?= $e(Nationalities::label($code, $lang)) ?>"><?php endforeach; ?>
    </datalist>

    <p id="ms-empty"<?= $items ? ' hidden' : '' ?>><?= $e($emptyText) ?></p>
    <div style="overflow-x:auto"<?= $items ? '' : ' hidden' ?> id="ms-table-box">
    <table class="ms-table">
        <thead>
            <tr>
                <th><?= $e($T(['kk' => 'Адам', 'ru' => 'Человек', 'en' => 'Person', 'es' => 'Persona'])) ?></th>
                <th><?= $e($T(['kk' => 'Ұрпақ', 'ru' => 'Поколение', 'en' => 'Generation', 'es' => 'Generación'])) ?></th>
                <th><?= $e($T(['kk' => 'Толтырылмаған өрістер', 'ru' => 'Что не заполнено', 'en' => 'Missing fields', 'es' => 'Campos vacíos'])) ?></th>
                <th class="fit"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $it) { require __DIR__ . '/missing_row.php'; } ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../layout/person_avatar_edit.php'; // обрезка фото овалом: та же, что в окне «Өңдеу» ?>
<script src="/assets/js/missing-inline.js?v=1" defer></script>
<?php require __DIR__ . '/../layout/master_end.php'; ?>
