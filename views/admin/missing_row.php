<?php
declare(strict_types=1);

// ALT-107: одна строка таблицы «Что не заполнено». Пустые поля заполняются прямо в ячейках (public/assets/js/missing-inline.js).
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
$ph = [ // подсказки «Укажите…»
    'birth'       => $T(['kk' => 'Туған күнін көрсетіңіз (КК.АА.ЖЖЖЖ)', 'ru' => 'Укажите дату рождения (ДД.ММ.ГГГГ)', 'en' => 'Enter birth date (DD.MM.YYYY)', 'es' => 'Indique la fecha (DD.MM.AAAA)']),
    'death'       => $T(['kk' => 'Қайтыс болған күнін көрсетіңіз (КК.АА.ЖЖЖЖ)', 'ru' => 'Укажите дату смерти (ДД.ММ.ГГГГ)', 'en' => 'Enter death date (DD.MM.YYYY)', 'es' => 'Indique la fecha (DD.MM.AAAA)']),
    'marriage'    => $T(['kk' => 'Үйлену күнін көрсетіңіз (КК.АА.ЖЖЖЖ)', 'ru' => 'Укажите дату брака (ДД.ММ.ГГГГ)', 'en' => 'Enter marriage date (DD.MM.YYYY)', 'es' => 'Indique la fecha (DD.MM.AAAA)']),
    'clan'        => $T(['kk' => 'Русын көрсетіңіз', 'ru' => 'Укажите ру', 'en' => 'Enter clan', 'es' => 'Indique el clan']),
    'nationality' => $T(['kk' => 'Ұлтын көрсетіңіз', 'ru' => 'Укажите национальность', 'en' => 'Enter nationality', 'es' => 'Indique la nacionalidad']),
    'maiden'      => $T(['kk' => 'Қыз кезіндегі тегін көрсетіңіз', 'ru' => 'Укажите девичью фамилию', 'en' => 'Enter maiden name', 'es' => 'Indique el apellido de soltera']),
    'gender'      => $T(['kk' => 'Жынысын көрсетіңіз', 'ru' => 'Укажите пол', 'en' => 'Select gender', 'es' => 'Indique el sexo']),
    'father'      => $T(['kk' => 'Әкесін көрсетіңіз — атын жаза бастаңыз', 'ru' => 'Укажите отца — начните вводить имя', 'en' => 'Enter father — start typing a name', 'es' => 'Indique al padre — escriba el nombre']),
    'mother'      => $T(['kk' => 'Шешесін көрсетіңіз — атын жаза бастаңыз', 'ru' => 'Укажите мать — начните вводить имя', 'en' => 'Enter mother — start typing a name', 'es' => 'Indique a la madre — escriba el nombre']),
];
?>
            <tr data-person="<?= $pid ?>">
                <td><a href="/person/<?= $pid ?>"><?= $e($name !== '' ? $name : ('#' . $pid)) ?></a></td>
                <td><?= $e($genLabel($it['gen'])) ?></td>
                <td>
                    <div class="ms-cells">
                    <?php foreach ($it['gaps'] as $g):
                        $label = $T($gapLabels[$g]);
                        $d = $it['detail'][$g] ?? '';
                        if ($g === 'zheti') {
                            $label .= ': ' . (int)$d . '/7';
                        } elseif ($d === 'year') {
                            $label .= ' (' . $ctx['yearOnly'] . ')';
                        }
                        $fid = 'ms-' . $pid . '-' . $g;
                        // Брак: у человека может быть несколько браков без даты — отдельное поле на каждый
                        if ($g === 'marriage' && !empty($it['marriages'])):
                            foreach ($it['marriages'] as $mr):
                                $mlabel = $T($gapLabels['marriage']) . ': ' . $mr['spouse'] . ($mr['state'] === 'year' ? ' (' . $ctx['yearOnly'] . ')' : '');
                                $mid = $fid . '-' . (int)$mr['id']; ?>
                        <div class="ms-cell" data-gap="marriage">
                            <label class="ms-lab" for="<?= $e($mid) ?>"><?= $e($mlabel) ?></label>
                            <input class="ms-in" id="<?= $e($mid) ?>" type="text" inputmode="numeric" autocomplete="off" maxlength="12"
                                   data-save="marriage" data-marriage="<?= (int)$mr['id'] ?>" placeholder="<?= $e($ph['marriage']) ?>">
                            <span class="ms-msg" role="status"></span>
                        </div>
                            <?php endforeach;
                            continue;
                        endif; ?>
                        <div class="ms-cell" data-gap="<?= $e($g) ?>">
                        <?php if (in_array($g, ['birth', 'death'], true)): ?>
                            <label class="ms-lab" for="<?= $e($fid) ?>"><?= $e($label) ?></label>
                            <input class="ms-in" id="<?= $e($fid) ?>" type="text" inputmode="numeric" autocomplete="off" maxlength="12"
                                   data-save="<?= $e($g) ?>" placeholder="<?= $e($ph[$g]) ?>">
                        <?php elseif ($g === 'clan' && $d === 'free'): ?>
                            <label class="ms-lab" for="<?= $e($fid) ?>"><?= $e($label) ?></label>
                            <input class="ms-in" id="<?= $e($fid) ?>" type="text" autocomplete="off" maxlength="150" list="ms-clans"
                                   data-save="clan" placeholder="<?= $e($ph['clan']) ?>">
                        <?php elseif ($g === 'nationality'): ?>
                            <label class="ms-lab" for="<?= $e($fid) ?>"><?= $e($label) ?></label>
                            <input class="ms-in" id="<?= $e($fid) ?>" type="text" autocomplete="off" maxlength="150" list="ms-nats"
                                   data-save="nationality" placeholder="<?= $e($ph['nationality']) ?>">
                        <?php elseif ($g === 'maiden'): ?>
                            <label class="ms-lab" for="<?= $e($fid) ?>"><?= $e($label) ?></label>
                            <input class="ms-in" id="<?= $e($fid) ?>" type="text" autocomplete="off" maxlength="100"
                                   data-save="maiden" placeholder="<?= $e($ph['maiden']) ?>">
                        <?php elseif ($g === 'gender'): ?>
                            <label class="ms-lab" for="<?= $e($fid) ?>"><?= $e($label) ?></label>
                            <select class="ms-in" id="<?= $e($fid) ?>" data-save="gender">
                                <option value=""><?= $e($ph['gender']) ?></option>
                                <option value="M"><?= $e($T(['kk' => 'Ер', 'ru' => 'Мужской', 'en' => 'Male', 'es' => 'Hombre'])) ?></option>
                                <option value="F"><?= $e($T(['kk' => 'Әйел', 'ru' => 'Женский', 'en' => 'Female', 'es' => 'Mujer'])) ?></option>
                            </select>
                        <?php elseif ($g === 'father' || $g === 'mother'): ?>
                            <label class="ms-lab" for="<?= $e($fid) ?>"><?= $e($label) ?></label>
                            <div class="ms-find">
                                <input class="ms-in" id="<?= $e($fid) ?>" type="text" autocomplete="off" maxlength="100"
                                       data-parent="<?= $e($g) ?>" placeholder="<?= $e($ph[$g]) ?>" role="combobox" aria-expanded="false" aria-controls="<?= $e($fid) ?>-list">
                                <ul class="ms-results" id="<?= $e($fid) ?>-list" role="listbox" hidden></ul>
                            </div>
                        <?php elseif ($g === 'photo'): ?>
                            <span class="ms-lab"><?= $e($label) ?></span>
                            <button type="button" class="btn btn-sm" data-photo="1"><?= $e($T(['kk' => 'Фото жүктеу', 'ru' => 'Загрузить фото', 'en' => 'Upload photo', 'es' => 'Subir foto'])) ?></button>
                            <input type="file" accept="image/jpeg,image/png,image/webp" hidden data-photo-file="1">
                        <?php else: /* контакты, жеті ата, ру «по отцу» — только через «Өзгерту» или после заполнения отца */ ?>
                            <span class="ms-chip" data-gap="<?= $e($g) ?>"><?= $e($label) ?><?= ($g === 'clan' && $d === 'byfather') ? ' · ' . $e($T(['kk' => 'әкесінен', 'ru' => 'по отцу', 'en' => 'from father', 'es' => 'del padre'])) : '' ?></span>
                        <?php endif; ?>
                            <span class="ms-msg" role="status"></span>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </td>
                <td class="fit">
                    <a class="btn btn-sm" href="/tree?focus=<?= $pid ?>&amp;edit=<?= $pid ?>"><?= $e($ctx['editLabel']) ?></a>
                </td>
            </tr>
