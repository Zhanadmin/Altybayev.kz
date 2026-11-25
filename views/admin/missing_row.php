<?php
declare(strict_types=1);

// ALT-107 / ALT-109 / ALT-110: одна строка таблицы «Что не заполнено». Каждый вид данных — в своей колонке (missing.php, thead).
// Подписей над полями нет: их роль играют заголовки колонок; у каждого поля есть aria-label.
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
    'clan'        => $T(['kk' => 'Ру атауы', 'ru' => 'Укажите ру', 'en' => 'Clan name', 'es' => 'Clan']),
    'nationality' => $T(['kk' => 'Ұлты', 'ru' => 'Национальность', 'en' => 'Nationality', 'es' => 'Nacionalidad']),
    'maiden'      => $T(['kk' => 'Қыз кезіндегі тегі', 'ru' => 'Девичья фамилия', 'en' => 'Maiden name', 'es' => 'Apellido de soltera']),
    'gender'      => $T(['kk' => 'Жынысы', 'ru' => 'Выберите пол', 'en' => 'Select gender', 'es' => 'Indique el sexo']),
    'father'      => $T(['kk' => 'Әкесін іздеу…', 'ru' => 'Найти отца…', 'en' => 'Find father…', 'es' => 'Buscar padre…']),
    'mother'      => $T(['kk' => 'Шешесін іздеу…', 'ru' => 'Найти мать…', 'en' => 'Find mother…', 'es' => 'Buscar madre…']),
    'contacts'    => $T(['kk' => 'жоқ', 'ru' => 'нет', 'en' => 'none', 'es' => 'no']),
];
$full = [ // полные пояснения для всплывающей подсказки
    'birth'    => $T(['kk' => 'Туған күнін көрсетіңіз (КК.АА.ЖЖЖЖ)', 'ru' => 'Укажите дату рождения (ДД.ММ.ГГГГ)', 'en' => 'Enter birth date (DD.MM.YYYY)', 'es' => 'Indique la fecha (DD.MM.AAAA)']),
    'death'    => $T(['kk' => 'Қайтыс болған күнін көрсетіңіз (КК.АА.ЖЖЖЖ)', 'ru' => 'Укажите дату смерти (ДД.ММ.ГГГГ)', 'en' => 'Enter death date (DD.MM.YYYY)', 'es' => 'Indique la fecha (DD.MM.AAAA)']),
    'marriage' => $T(['kk' => 'Үйлену күнін көрсетіңіз (КК.АА.ЖЖЖЖ)', 'ru' => 'Укажите дату брака (ДД.ММ.ГГГГ)', 'en' => 'Enter marriage date (DD.MM.YYYY)', 'es' => 'Indique la fecha (DD.MM.AAAA)']),
];
$yearOnlyShort = $ctx['yearOnly'];

// Колонка для каждого вида пробела: отец и мать — «Родители», пол и девичья фамилия — «Другое»
$colOf = static function (string $g): string {
    if (in_array($g, ['photo', 'birth', 'nationality', 'clan', 'marriage', 'death', 'contacts', 'zheti'], true)) { return $g; }
    return ($g === 'father' || $g === 'mother') ? 'parents' : 'other';
};
$cols = ['photo' => '', 'birth' => '', 'nationality' => '', 'clan' => '', 'marriage' => '', 'death' => '', 'parents' => '', 'contacts' => '', 'other' => '', 'zheti' => ''];
// Названия колонок для мобильной раскладки (на узком экране шапки нет)
$colLab = [
    'photo' => $T($gapLabels['photo']), 'birth' => $T($gapLabels['birth']), 'nationality' => $T($gapLabels['nationality']),
    'clan' => $T(['kk' => 'Ру', 'ru' => 'Ру', 'en' => 'Clan', 'es' => 'Clan']), 'marriage' => $T($gapLabels['marriage']),
    'death' => $T($gapLabels['death']), 'parents' => $T(['kk' => 'Ата-ана', 'ru' => 'Родители', 'en' => 'Parents', 'es' => 'Padres']),
    'contacts' => $T($gapLabels['contacts']), 'other' => $T(['kk' => 'Басқа', 'ru' => 'Другое', 'en' => 'Other', 'es' => 'Otros']),
    'zheti' => $T(['kk' => 'Жеті ата', 'ru' => 'Жеті ата', 'en' => 'Seven fathers', 'es' => 'Siete padres']),
];

foreach ($it['gaps'] as $g) {
    $label = $T($gapLabels[$g]);
    $d = $it['detail'][$g] ?? '';
    $fid = 'ms-' . $pid . '-' . $g;
    ob_start();

    if ($g === 'marriage') {
        // Брак показывается у мужа; несколько браков — поля друг под другом, имя супруга мелко над полем
        foreach ($it['marriages'] as $mr) {
            $mid = $fid . '-' . (int)$mr['id'];
            $spouse = (string)$mr['spouse'];
            $mfull = $T($gapLabels['marriage']) . ': ' . $spouse . ($mr['state'] === 'year' ? ' (' . $yearOnlyShort . ')' : '');
            ?>
            <div class="ms-cell" data-gap="marriage">
                <span class="ms-note" title="<?= $e($mfull) ?>"><?= $e($spouse . ($mr['state'] === 'year' ? ' · ' . $yearOnlyShort : '')) ?></span>
                <input class="ms-in" id="<?= $e($mid) ?>" type="text" inputmode="numeric" autocomplete="off" maxlength="12"
                       data-save="marriage" data-marriage="<?= (int)$mr['id'] ?>" placeholder="<?= $e($ph['marriage']) ?>" title="<?= $e($mfull) ?>" aria-label="<?= $e($mfull) ?>">
                <span class="ms-msg" role="status"></span>
            </div>
            <?php
        }
    } elseif ($g === 'zheti') {
        $zl = $T(['kk' => 'Жеті ата', 'ru' => 'Жеті ата', 'en' => 'Seven fathers', 'es' => 'Siete padres']) . ' ' . (int)$d . '/7';
        ?>
            <div class="ms-cell" data-gap="zheti">
                <span class="ms-chip" title="<?= $e($label . ': ' . (int)$d . '/7') ?>"><?= $e($zl) ?></span>
            </div>
        <?php
    } else {
        ?>
            <div class="ms-cell" data-gap="<?= $e($g) ?>">
            <?php if (in_array($g, ['birth', 'death'], true)): ?>
                <?php if ($d === 'year'): ?><span class="ms-note"><?= $e($yearOnlyShort) ?></span><?php endif; ?>
                <input class="ms-in" id="<?= $e($fid) ?>" type="text" inputmode="numeric" autocomplete="off" maxlength="12"
                       data-save="<?= $e($g) ?>" placeholder="<?= $e($ph[$g]) ?>" title="<?= $e($full[$g]) ?>" aria-label="<?= $e($full[$g]) ?>">
            <?php elseif ($g === 'clan' && $d === 'free'): ?>
                <input class="ms-in" id="<?= $e($fid) ?>" type="text" autocomplete="off" maxlength="150" list="ms-clans"
                       data-save="clan" placeholder="<?= $e($ph['clan']) ?>" title="<?= $e($ph['clan']) ?>" aria-label="<?= $e($label) ?>">
            <?php elseif ($g === 'nationality'): ?>
                <input class="ms-in" id="<?= $e($fid) ?>" type="text" autocomplete="off" maxlength="150" list="ms-nats"
                       data-save="nationality" placeholder="<?= $e($ph['nationality']) ?>" title="<?= $e($ph['nationality']) ?>" aria-label="<?= $e($label) ?>">
            <?php elseif ($g === 'maiden'): ?>
                <input class="ms-in" id="<?= $e($fid) ?>" type="text" autocomplete="off" maxlength="100"
                       data-save="maiden" placeholder="<?= $e($ph['maiden']) ?>" title="<?= $e($ph['maiden']) ?>" aria-label="<?= $e($label) ?>">
            <?php elseif ($g === 'gender'): ?>
                <select class="ms-in" id="<?= $e($fid) ?>" data-save="gender" title="<?= $e($ph['gender']) ?>" aria-label="<?= $e($label) ?>">
                    <option value=""><?= $e($ph['gender']) ?></option>
                    <option value="M"><?= $e($T(['kk' => 'Ер', 'ru' => 'Мужской', 'en' => 'Male', 'es' => 'Hombre'])) ?></option>
                    <option value="F"><?= $e($T(['kk' => 'Әйел', 'ru' => 'Женский', 'en' => 'Female', 'es' => 'Mujer'])) ?></option>
                </select>
            <?php elseif ($g === 'father' || $g === 'mother'): ?>
                <div class="ms-find">
                    <input class="ms-in" id="<?= $e($fid) ?>" type="text" autocomplete="off" maxlength="100"
                           data-parent="<?= $e($g) ?>" placeholder="<?= $e($ph[$g]) ?>" title="<?= $e($ph[$g]) ?>" aria-label="<?= $e($label) ?>" role="combobox" aria-expanded="false" aria-controls="<?= $e($fid) ?>-list">
                    <ul class="ms-results" id="<?= $e($fid) ?>-list" role="listbox" hidden></ul>
                </div>
            <?php elseif ($g === 'photo'): ?>
                <button type="button" class="btn btn-sm ms-btn" data-photo="1"><?= $e($T(['kk' => 'Фото жүктеу', 'ru' => 'Загрузить фото', 'en' => 'Upload photo', 'es' => 'Subir foto'])) ?></button>
                <input type="file" accept="image/jpeg,image/png,image/webp" hidden data-photo-file="1">
            <?php elseif ($g === 'contacts'): /* контакты вносятся через «Өзгерту»: поле только показывает «нет» и ведёт туда */ ?>
                <a class="ms-ro" href="/tree?focus=<?= $pid ?>&amp;edit=<?= $pid ?>" title="<?= $e($label . ': ' . $ph['contacts']) ?>">
                    <input class="ms-in" type="text" readonly tabindex="-1" placeholder="<?= $e($ph['contacts']) ?>" aria-label="<?= $e($label) ?>">
                </a>
            <?php else: /* ру «по отцу» — заполняется после заполнения отца */ ?>
                <span class="ms-chip" data-gap="<?= $e($g) ?>"><?= $e($T(['kk' => 'әкесінен', 'ru' => 'по отцу', 'en' => 'from father', 'es' => 'del padre'])) ?></span>
            <?php endif; ?>
                <span class="ms-msg" role="status"></span>
            </div>
        <?php
    }
    $cols[$colOf($g)] .= ob_get_clean();
}
$td = static function (string $key, string $html) use ($colLab): string {
    $html = trim($html);
    return '<td class="ms-c-' . $key . '" data-label="' . htmlspecialchars($colLab[$key] ?? '', ENT_QUOTES, 'UTF-8') . '">' . ($html === '' ? '' : '<div class="ms-stack">' . $html . '</div>') . '</td>';
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
                <?= $td('parents', $cols['parents']) ?>
                <?= $td('contacts', $cols['contacts']) ?>
                <?= $td('other', $cols['other']) ?>
                <?= $td('zheti', $cols['zheti']) ?>
                <td class="ms-c-edit">
                    <a class="btn btn-sm ms-btn" href="/tree?focus=<?= $pid ?>&amp;edit=<?= $pid ?>"><?= $e($ctx['editLabel']) ?></a>
                </td>
            </tr>
