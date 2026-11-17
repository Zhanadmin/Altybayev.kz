<?php
// ALT-004: «Поле: было → стало» для одной записи журнала. Ожидает $l (строка из ChangeLogService) и $lang.
$chs = $l['changes'] ?? [];
if (!$chs) {
    echo '<span class="muted small">' . htmlspecialchars((string)$l['summary']) . '</span>';
    return;
}
?>
<ul class="hs-changes">
    <?php foreach (array_slice($chs, 0, 12) as $c): ?>
        <li><b><?= htmlspecialchars($c['field']) ?>:</b>
            <span class="hs-old"><?= htmlspecialchars($c['old']) ?></span>
            <span aria-hidden="true">→</span>
            <span class="hs-new"><?= htmlspecialchars($c['new']) ?></span></li>
    <?php endforeach; ?>
    <?php if (count($chs) > 12): ?>
        <li class="muted small">… +<?= count($chs) - 12 ?></li>
    <?php endif; ?>
</ul>
