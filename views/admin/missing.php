<?php
declare(strict_types=1);

use App\Core\I18n;

/** @var array $items */
/** @var array $counts */
/** @var int $totalAll */
/** @var int $totalGaps */
/** @var array $genKeys */
/** @var string $gapFilter */
/** @var string $genFilter */

$lang = I18n::lang();
// ALT-098: тексты на всех языках сайта (kk, ru, en, es)
$T = static function (array $t) use ($lang): string {
    return $t[$lang] ?? $t['ru'];
};
$title = $T(['kk' => 'Толтырылмаған', 'ru' => 'Что не заполнено', 'en' => 'Missing data', 'es' => 'Datos faltantes']);
$activeNav = 'missing';
$dsThemeReady = true;

require __DIR__ . '/../layout/master_start.php';

$gapLabels = [
    'birth'    => ['kk' => 'Туған күні', 'ru' => 'Дата рождения', 'en' => 'Birth date', 'es' => 'Fecha de nacimiento'],
    'photo'    => ['kk' => 'Фото', 'ru' => 'Фото', 'en' => 'Photo', 'es' => 'Foto'],
    'clan'     => ['kk' => 'Ру', 'ru' => 'Ру (род)', 'en' => 'Clan', 'es' => 'Clan'],
    'father'   => ['kk' => 'Әкесі', 'ru' => 'Отец', 'en' => 'Father', 'es' => 'Padre'],
    'mother'   => ['kk' => 'Шешесі', 'ru' => 'Мать', 'en' => 'Mother', 'es' => 'Madre'],
    'gender'   => ['kk' => 'Жынысы', 'ru' => 'Пол', 'en' => 'Gender', 'es' => 'Sexo'],
    'marriage' => ['kk' => 'Үйлену күні', 'ru' => 'Дата брака', 'en' => 'Marriage date', 'es' => 'Fecha de boda'],
    'death'    => ['kk' => 'Қайтыс болған күні', 'ru' => 'Дата смерти', 'en' => 'Death date', 'es' => 'Fecha de fallecimiento'],
    'maiden'   => ['kk' => 'Қыз кезіндегі тегі', 'ru' => 'Девичья фамилия', 'en' => 'Maiden name', 'es' => 'Apellido de soltera'],
    'contacts' => ['kk' => 'Байланыс', 'ru' => 'Контакты', 'en' => 'Contacts', 'es' => 'Contactos'],
    'zheti'    => ['kk' => 'Жеті ата', 'ru' => 'Жеті ата (цепочка < 7)', 'en' => 'Seven fathers (chain < 7)', 'es' => 'Siete padres (cadena < 7)'],
];
$yearOnly = $T(['kk' => 'тек жылы', 'ru' => 'только год', 'en' => 'year only', 'es' => 'solo el año']);
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$genLabel = static function (string $k) use ($T): string {
    if ($k === 'ancestors') {
        return $T(['kk' => 'Ата-бабалар', 'ru' => 'Предки', 'en' => 'Ancestors', 'es' => 'Antepasados']);
    }
    return $T(['kk' => $k . '-ұрпақ', 'ru' => $k . '-е поколение', 'en' => 'Generation ' . $k, 'es' => 'Generación ' . $k]);
};
$url = static function (string $gap, string $gen): string {
    $q = array_filter(['gap' => $gap, 'gen' => $gen], static fn($v) => $v !== '');
    return '/admin/missing' . ($q ? '?' . http_build_query($q) : '');
};
$editLabel = 'Өзгерту';
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
.ms-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.ms-chip { padding: 2px 10px; border-radius: 999px; border: 1px solid var(--c-border, #ccc); font-size: 14px; background: rgba(128,128,128,.08); }
.ms-table .fit { width: 1%; white-space: nowrap; }
</style>

<div class="ds-page-head">
    <div>
        <h1><?= $e($title) ?></h1>
        <p class="sub"><?= $e($T([
            'kk' => 'Толтырылмаған деректері бар адамдар: ' . $totalGaps . ' / ' . $totalAll,
            'ru' => 'Людей с пробелами в данных: ' . $totalGaps . ' из ' . $totalAll,
            'en' => 'People with missing data: ' . $totalGaps . ' of ' . $totalAll,
            'es' => 'Personas con datos faltantes: ' . $totalGaps . ' de ' . $totalAll,
        ])) ?></p>
    </div>
</div>

<div class="ms-wrap">
    <div class="ms-counters" aria-label="<?= $e($T(['kk' => 'Санауыштар', 'ru' => 'Счётчики', 'en' => 'Counters', 'es' => 'Contadores'])) ?>">
        <a class="ms-counter<?= $gapFilter === '' ? ' active' : '' ?>" href="<?= $e($url('', $genFilter)) ?>">
            <span><?= $e($T(['kk' => 'Барлығы', 'ru' => 'Все', 'en' => 'All', 'es' => 'Todos'])) ?></span><b><?= (int)$totalGaps ?></b>
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

    <?php if (!$items): ?>
        <p><?= $e($T(['kk' => 'Ештеңе табылмады', 'ru' => 'Ничего не найдено', 'en' => 'Nothing found', 'es' => 'No se encontró nada'])) ?></p>
    <?php else: ?>
    <div style="overflow-x:auto">
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
        <?php foreach ($items as $it):
            $p = $it['p'];
            $name = trim(($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? ''));
        ?>
            <tr data-person="<?= (int)$it['id'] ?>">
                <td><a href="/person/<?= (int)$it['id'] ?>"><?= $e($name !== '' ? $name : ('#' . $it['id'])) ?></a></td>
                <td><?= $e($genLabel($it['gen'])) ?></td>
                <td>
                    <div class="ms-chips">
                    <?php foreach ($it['gaps'] as $g):
                        $label = $T($gapLabels[$g]);
                        $d = $it['detail'][$g] ?? '';
                        if ($g === 'zheti') {
                            $label = $T($gapLabels['zheti']) . ': ' . (int)$d . '/7';
                        } elseif ($d === 'year') {
                            $label .= ' (' . $yearOnly . ')';
                        }
                    ?>
                        <span class="ms-chip" data-gap="<?= $e($g) ?>"><?= $e($label) ?></span>
                    <?php endforeach; ?>
                    </div>
                </td>
                <td class="fit">
                    <a class="btn btn-sm" href="/tree?focus=<?= (int)$it['id'] ?>&amp;edit=<?= (int)$it['id'] ?>"><?= $e($editLabel) ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../layout/master_end.php'; ?>
