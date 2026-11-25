<?php
declare(strict_types=1);

// ALT-107 / ALT-109: одна строка таблицы «Что не заполнено». Каждый вид данных — в своей колонке (missing.php, thead).
// Пустые поля заполняются прямо в ячейках (public/assets/js/missing-inline.js).
// Ожидает: $it (элемент из MissingDataController::analyze) и $ctx (views/admin/missing_ctx.php).
/** @var array $it */
/** @var array $ctx */

$T = $ctx['T'];
$e = $ctx['e'];
$gapLabels = $ctx['gapLabels'];
$genLabel = $ctx['genLabel'];
$pid = (int)$it['id'];
$p = $it['p'];
$name = trim(($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? ''));
$ph = [ // короткие подсказки в полях (полный текст — в title)
    'birth'       => $T(['kk' => 'КК.АА.ЖЖЖЖ', 'ru' => 'ДД.ММ.ГГГГ', 'en' => 'DD.MM.YYYY', 'es' => 'DD.MM.AAAA']),
    'death'       => $T(['kk' => 'КК.АА.ЖЖЖЖ', 'ru' => 'ДД.ММ.ГГГГ', 'en' => 'DD.MM.YYYY', 'es' => 'DD.MM.AAAA']),
    'marriage'    => $T(['kk' => 'КК.АА.ЖЖЖЖ', 'ru' => 'ДД.ММ.ГГГГ', 'en' => 'DD.MM.YYYY', 'es' => 'DD.MM.AAAA']),
    'clan'        => $T(['kk' => 'Русын көрсетіңіз', 'ru' => 'Укажите ру', 'en' => 'Enter clan', 'es' => 'Indique el clan']),
    'nationality' => $T(['kk' => 'Ұлтын көрсетіңіз', 'ru' => 'Укажите национальность', 'en' => 'Enter nationality', 'es' => 'Nacionalidad']),
    'maiden'      => $T(['kk' => 'Қыз кезіндегі тегі', 'ru' => 'Девичья фамилия', 'en' => 'Maiden name', 'es' => 'Apellido de soltera']),
    'gender'      => $T(['kk' => 'Жынысын таңдаңыз', 'ru' => 'Выберите пол', 'en' => 'Select gender', 'es' => 'Indique el sexo']),
    'father'      => $T(['kk' => 'Әкесін іздеу…', 'ru' => 'Найти отца…', 'en' => 'Find father…', 'es' => 'Buscar padre…']),
    'mother'      => $T(['kk' => 'Шешесін іздеу…', 'ru' => 'Найти мать…', 'en' => 'Find mother…', 'es' => 'Buscar madre…']),
];
$full = [ // полные пояснения для всплывающей подсказки
    'birth'    => $T(['kk' => 'Туған күнін көрсетіңіз (КК.АА.ЖЖЖЖ)', 'ru' => 'Укажите дату рождения (ДД.ММ.ГГГГ)', 'en' => 'Enter birth date (DD.MM.YYYY)', 'es' => 'Indique la fecha (DD.MM.AAAA)']),
    'death'    => $T(['kk' => 'Қайтыс болған күнін көрсетіңіз (КК.АА.ЖЖЖЖ)', 'ru' => 'Укажите дату смерти (ДД.ММ.ГГГГ)', 'en' => 'Enter death date (DD.MM.YYYY)', 'es' => 'Indique la fecha (DD.MM.AAAA)']),
    'marriage' => $T(['kk' => 'Үйлену күнін көрсетіңіз (КК.АА.ЖЖЖЖ)', 'ru' => 'Укажите дату брака (ДД.ММ.ГГГГ)', 'en' => 'Enter marriage date (DD.MM.YYYY)', 'es' => 'Indique la fecha (DD.MM.AAAA)']),
];
$yearOnlyShort = $ctx['yearOnly'];

// Колонка для каждого вида пробела; «other» — отец, мать, пол, девичья фамилия, контакты
$colOf = static fn(string $g): string => in_array($g, ['photo', 'birth', 'nationality', 'clan', 'marriage', 'death', 'zheti'], true) ? $g : 'other';
$cols = ['photo' => '', 'birth' => '', 'nationality' => '', 'clan' => '', 'marriage' => '', 'death' => '', 'other' => '', 'zheti' => ''];

foreach ($it['gaps'] as $g) {
    $label = $T($gapLabels[$g]);
    $d = $it['detail'][$g] ?? '';
    $fid = 'ms-' . $pid . '-' . $g;
    // Подпись над полем: одна строка; пояснение «только год» — коротко
    $short = in_array($g, ['birth', 'death'], true) ? ($d === 'year' ? $yearOnlyShort : '') : $label;
    ob_start();

    if ($g === 'marriage') {
        // Брак показывается у мужа; несколько браков — поля друг под другом
        foreach ($it['marriages'] as $mr) {
            $mid = $fid . '-' . (int)$mr['id'];
            $spouse = (string)$mr['spouse'];
            $mfull = $T($gapLabels['marriage']) . ': ' . $spouse . ($mr['state'] === 'year' ? ' (' . $yearOnlyShort . ')' : '');
            ?>
            <div class="ms-cell" data-gap="marriage">
                <label class="ms-lab" for="<?= $e($mid) ?>" title="<?= $e($mfull) ?>"><?= $e($spouse . ($mr['state'] === 'year' ? ' · ' . $yearOnlyShort : '')) ?></label>
                <input class="ms-in" id="<?= $e($mid) ?>" type="text" inputmode="numeric" autocomplete="off" maxlength="12"
                       data-save="marriage" data-marriage="<?= (int)$mr['id'] ?>" placeholder="<?= $e($ph['marriage']) ?>" title="<?= $e($full['marriage']) ?>">
                <span class="ms-msg" role="status"></span>
            </div>
            <?php
        }
    } elseif ($g === 'zheti') {
        $zl = $T(['kk' => 'Жеті ата', 'ru' => 'Жеті ата', 'en' => 'Seven fathers', 'es' => 'Siete padres']) . ' ' . (int)$d . '/7';
        ?>
            <div class="ms-cell" data-gap="zheti">
                <span class="ms-lab">&nbsp;</span>
                <span class="ms-chip" title="<?= $e($label . ': ' . (int)$d . '/7') ?>"><?= $e($zl) ?></span>
            </div>
        <?php
    } else {
        ?>
            <div class="ms-cell" data-gap="<?= $e($g) ?>">
            <?php if (in_array($g, ['birth', 'death'], true)): ?>
                <label class="ms-lab" for="<?= $e($fid) ?>" title="<?= $e($full[$g]) ?>"><?= $short !== '' ? $e($short) : '&nbsp;' ?></label>
                <input class="ms-in" id="<?= $e($fid) ?>" type="text" inputmode="numeric" autocomplete="off" maxlength="12"
                       data-save="<?= $e($g) ?>" placeholder="<?= $e($ph[$g]) ?>" title="<?= $e($full[$g]) ?>">
            <?php elseif ($g === 'clan' && $d === 'free'): ?>
                <label class="ms-lab" for="<?= $e($fid) ?>">&nbsp;</label>
                <input class="ms-in" id="<?= $e($fid) ?>" type="text" autocomplete="off" maxlength="150" list="ms-clans"
                       data-save="clan" placeholder="<?= $e($ph['clan']) ?>" title="<?= $e($ph['clan']) ?>">
            <?php elseif ($g === 'nationality'): ?>
                <label class="ms-lab" for="<?= $e($fid) ?>">&nbsp;</label>
                <input class="ms-in" id="<?= $e($fid) ?>" type="text" autocomplete="off" maxlength="150" list="ms-nats"
                       data-save="nationality" placeholder="<?= $e($ph['nationality']) ?>" title="<?= $e($ph['nationality']) ?>">
            <?php elseif ($g === 'maiden'): ?>
                <label class="ms-lab" for="<?= $e($fid) ?>"><?= $e($label) ?></label>
                <input class="ms-in" id="<?= $e($fid) ?>" type="text" autocomplete="off" maxlength="100"
                       data-save="maiden" placeholder="<?= $e($ph['maiden']) ?>" title="<?= $e($ph['maiden']) ?>">
            <?php elseif ($g === 'gender'): ?>
                <label class="ms-lab" for="<?= $e($fid) ?>"><?= $e($label) ?></label>
                <select class="ms-in" id="<?= $e($fid) ?>" data-save="gender" title="<?= $e($ph['gender']) ?>">
                    <option value=""><?= $e($ph['gender']) ?></option>
                    <option value="M"><?= $e($T(['kk' => 'Ер', 'ru' => 'Мужской', 'en' => 'Male', 'es' => 'Hombre'])) ?></option>
                    <option value="F"><?= $e($T(['kk' => 'Әйел', 'ru' => 'Женский', 'en' => 'Female', 'es' => 'Mujer'])) ?></option>
                </select>
            <?php elseif ($g === 'father' || $g === 'mother'): ?>
                <label class="ms-lab" for="<?= $e($fid) ?>"><?= $e($label) ?></label>
                <div class="ms-find">
                    <input class="ms-in" id="<?= $e($fid) ?>" type="text" autocomplete="off" maxlength="100"
                           data-parent="<?= $e($g) ?>" placeholder="<?= $e($ph[$g]) ?>" title="<?= $e($ph[$g]) ?>" role="combobox" aria-expanded="false" aria-controls="<?= $e($fid) ?>-list">
                    <ul class="ms-results" id="<?= $e($fid) ?>-list" role="listbox" hidden></ul>
                </div>
            <?php elseif ($g === 'photo'): ?>
                <span class="ms-lab">&nbsp;</span>
                <button type="button" class="btn btn-sm ms-btn" data-photo="1"><?= $e($T(['kk' => 'Фото жүктеу', 'ru' => 'Загрузить фото', 'en' => 'Upload photo', 'es' => 'Subir foto'])) ?></button>
                <input type="file" accept="image/jpeg,image/png,image/webp" hidden data-photo-file="1">
            <?php else: /* контакты, ру «по отцу» — только через «Өзгерту» или после заполнения отца */ ?>
                <span class="ms-lab"><?= $g === 'clan' ? '&nbsp;' : $e($label) ?></span>
                <span class="ms-chip" data-gap="<?= $e($g) ?>"><?= $g === 'clan' ? $e($T(['kk' => 'әкесінен', 'ru' => 'по отцу', 'en' => 'from father', 'es' => 'del padre'])) : $e($T(['kk' => 'жоқ', 'ru' => 'нет', 'en' => 'none', 'es' => 'no'])) ?></span>
            <?php endif; ?>
                <span class="ms-msg" role="status"></span>
            </div>
        <?php
    }
    $cols[$colOf($g)] .= ob_get_clean();
}
$td = static function (string $key, string $html) use ($T): string {
    $html = trim($html);
    return '<td class="ms-c-' . $key . '">' . ($html === '' ? '' : '<div class="ms-stack">' . $html . '</div>') . '</td>';
};
?>
            <tr data-person="<?= $pid ?>">
                <td class="ms-c-name"><a href="/person/<?= $pid ?>"><?= $e($name !== '' ? $name : ('#' . $pid)) ?></a></td>
                <td class="ms-c-gen"><?= $e($genLabel($it['gen'])) ?></td>
                <?= $td('photo', $cols['photo']) ?>
                <?= $td('birth', $cols['birth']) ?>
                <?= $td('nationality', $cols['nationality']) ?>
                <?= $td('clan', $cols['clan']) ?>
                <?= $td('marriage', $cols['marriage']) ?>
                <?= $td('death', $cols['death']) ?>
                <?= $td('other', $cols['other']) ?>
                <?= $td('zheti', $cols['zheti']) ?>
                <td class="ms-c-edit">
                    <a class="btn btn-sm ms-btn" href="/tree?focus=<?= $pid ?>&amp;edit=<?= $pid ?>"><?= $e($ctx['editLabel']) ?></a>
                </td>
            </tr>
