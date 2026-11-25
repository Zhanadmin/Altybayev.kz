<?php
// ALT-067: блок «Туыстар / Родственники» внутри окна «Өзгерту» (master_end.php и tree.php).
// Параметр: $relBtnCls — класс кнопок. Диалог и скрипт — в person_relatives_dialog.php (подключается один раз на странице).
declare(strict_types=1);

$relBtnCls = $relBtnCls ?? 'btn btn-sm';
$relRu = in_array(\App\Core\I18n::lang(), ['ru', 'en', 'es'], true);
$relL = static fn(string $kk, string $ru): string => htmlspecialchars($relRu ? $ru : $kk);
$relCls = htmlspecialchars($relBtnCls);
?>
<div id="relBlock" style="display:none; margin-top:14px; padding-top:12px; border-top:1px solid rgba(128,128,128,.25);">
    <div style="font-weight:600; margin-bottom:8px;"><?= $relL('Туыстар', 'Родственники') ?></div>
    <div style="display:flex; flex-wrap:wrap; gap:8px;">
        <button type="button" class="<?= $relCls ?>" id="relBtnFather" onclick="relOpen('father')">+ <?= $relL('Әкесін қосу', 'Добавить отца') ?></button>
        <button type="button" class="<?= $relCls ?>" id="relBtnMother" onclick="relOpen('mother')">+ <?= $relL('Анасын қосу', 'Добавить мать') ?></button>
        <button type="button" class="<?= $relCls ?>" id="relBtnSpouse" onclick="relOpen('spouse')">+ <?= $relL('Жұбайын қосу', 'Добавить мужа (жену)') ?></button>
        <button type="button" class="<?= $relCls ?>" id="relBtnChild" onclick="relOpen('child')">+ <?= $relL('Баласын қосу', 'Добавить ребёнка') ?></button>
    </div>
</div>
