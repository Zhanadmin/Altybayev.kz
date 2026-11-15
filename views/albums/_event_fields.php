<?php
/**
 * TASK-018: поля формы мероприятия (создание и изменение).
 * Ожидает: $L (kk, ru), $ev (массив: title, event_date, place, description, people[], families[]),
 *          $families (список всех семей [id, name]) — для выбора.
 */
$evPeople = implode(',', array_map(fn($p) => is_array($p) ? (int)$p['id'] : (int)$p, $ev['people'] ?? []));
$evFamilies = array_map(fn($f) => is_array($f) ? (int)$f['id'] : (int)$f, $ev['families'] ?? []);
$uid = 'ev' . substr(md5((string)($ev['id'] ?? 'new')), 0, 6);
?>
<div class="field">
    <label for="<?= $uid ?>Title"><?= $L('Атауы', 'Название') ?></label>
    <input id="<?= $uid ?>Title" name="title" class="input" required maxlength="200" value="<?= htmlspecialchars((string)($ev['title'] ?? '')) ?>"
           placeholder="<?= htmlspecialchars($L('Мысалы: Айшаның туған күні', 'Например: День рождения Айши')) ?>">
</div>
<div class="form-row">
    <div class="field">
        <label for="<?= $uid ?>Date"><?= $L('Күні', 'Дата') ?></label>
        <input id="<?= $uid ?>Date" name="event_date" type="date" class="input" value="<?= htmlspecialchars((string)($ev['event_date'] ?? '')) ?>">
    </div>
    <div class="field">
        <label for="<?= $uid ?>Place"><?= $L('Орны', 'Место') ?></label>
        <input id="<?= $uid ?>Place" name="place" class="input" maxlength="200" value="<?= htmlspecialchars((string)($ev['place'] ?? '')) ?>">
    </div>
</div>
<div class="field">
    <label for="<?= $uid ?>Descr"><?= $L('Сипаттама', 'Описание') ?></label>
    <textarea id="<?= $uid ?>Descr" name="description" class="input textarea" maxlength="2000"><?= htmlspecialchars((string)($ev['description'] ?? '')) ?></textarea>
</div>
<div class="field">
    <label for="<?= $uid ?>Fam"><?= $L('Қай отбасылар', 'Какие семьи') ?></label>
    <select id="<?= $uid ?>Fam" class="select" multiple size="5" onchange="this.form.families.value=[...this.selectedOptions].map(o=>o.value).join(',')">
        <?php foreach ($families as $f): ?>
            <option value="<?= (int)$f['id'] ?>"<?= in_array((int)$f['id'], $evFamilies, true) ? ' selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="hidden" name="families" value="<?= htmlspecialchars(implode(',', $evFamilies)) ?>">
    <span class="al-note"><?= $L('Бірнешеуін таңдау: Ctrl (компьютерде) немесе саусақпен (телефонда)', 'Несколько: с Ctrl (на компьютере) или касанием (на телефоне)') ?></span>
</div>
<div class="field al-picker" id="<?= $uid ?>People">
    <label for="<?= $uid ?>PeopleInput"><?= $L('Қай адамдар', 'Какие люди') ?></label>
    <input type="text" id="<?= $uid ?>PeopleInput" class="input" list="alPeopleList" placeholder="<?= htmlspecialchars($L('Атын жазыңыз…', 'Начните вводить имя…')) ?>" autocomplete="off">
    <input type="hidden" name="people" value="<?= htmlspecialchars($evPeople) ?>">
    <div class="al-picked"></div>
</div>
