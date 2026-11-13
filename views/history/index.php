<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
$title = I18n::t('nav_history');
$activeNav = 'history';
$dsThemeReady = true; // TASK-008: страница на цветах дизайн-системы — доступна тёмная тема

require __DIR__ . '/../layout/master_start.php';
?>

<div class="hs">
    <div class="ds-page-head">
        <div>
            <h1><?= htmlspecialchars(I18n::t('history_title')) ?></h1>
            <p class="sub"><?= htmlspecialchars(I18n::t('history_subtitle')) ?></p>
        </div>
        <div class="actions">
            <span class="badge"><?= $lang === 'ru' ? 'Всего записей в аудите:' : 'Барлық жазбалар:' ?> <b><?= (int)$totalCount ?></b></span>
        </div>
    </div>

    <!-- Фильтры истории -->
    <form method="GET" action="/history" class="island toolbar pp-filters">
        <select name="action" class="select" onchange="this.form.submit()" aria-label="<?= $lang === 'ru' ? 'Действие' : 'Әрекет' ?>">
            <option value=""><?= $lang === 'ru' ? 'Действие: Все' : 'Әрекет: Барлығы' ?></option>
            <option value="CREATE" <?= ($filters['action'] ?? '') === 'CREATE' ? 'selected' : '' ?>>CREATE (Қосу)</option>
            <option value="UPDATE" <?= ($filters['action'] ?? '') === 'UPDATE' ? 'selected' : '' ?>>UPDATE (Өзгерту)</option>
            <option value="DELETE" <?= ($filters['action'] ?? '') === 'DELETE' ? 'selected' : '' ?>>DELETE (Өшіру)</option>
            <option value="ROLLBACK" <?= ($filters['action'] ?? '') === 'ROLLBACK' ? 'selected' : '' ?>>ROLLBACK (Откат)</option>
            <option value="IMPORT_GEDCOM" <?= ($filters['action'] ?? '') === 'IMPORT_GEDCOM' ? 'selected' : '' ?>>IMPORT_GEDCOM (Импорт)</option>
        </select>
        <select name="table" class="select" onchange="this.form.submit()" aria-label="<?= $lang === 'ru' ? 'Объект' : 'Нысан' ?>">
            <option value=""><?= $lang === 'ru' ? 'Объект: Все' : 'Нысан: Барлығы' ?></option>
            <option value="persons" <?= ($filters['table'] ?? '') === 'persons' ? 'selected' : '' ?>>persons (Тұлғалар)</option>
            <option value="marriages" <?= ($filters['table'] ?? '') === 'marriages' ? 'selected' : '' ?>>marriages (Некелер)</option>
            <option value="person_media" <?= ($filters['table'] ?? '') === 'person_media' ? 'selected' : '' ?>>person_media (Медиа)</option>
            <option value="clans" <?= ($filters['table'] ?? '') === 'clans' ? 'selected' : '' ?>>clans (Рулар)</option>
        </select>
        <?php if (!empty($filters['action']) || !empty($filters['table'])): ?>
            <a href="/history" class="btn btn-ghost"><?= $lang === 'ru' ? 'Сбросить фильтр' : 'Сүзгіні тазарту' ?></a>
        <?php endif; ?>
    </form>

    <!-- Таблица аудита: без горизонтальной прокрутки, второстепенные колонки скрываются -->
    <div class="island hs-island">
        <div class="table-wrap">
            <table class="table hs-table">
                <thead>
                    <tr>
                        <th class="num col-opt-lg">ID</th>
                        <th class="hs-w-time col-opt-md"><?= $lang === 'ru' ? 'Время' : 'Уақыты' ?></th>
                        <th class="hs-w-user col-opt-md"><?= $lang === 'ru' ? 'Пользователь' : 'Пайдаланушы' ?></th>
                        <th class="hs-w-act"><?= $lang === 'ru' ? 'Действие' : 'Әрекет' ?></th>
                        <th class="hs-w-obj col-opt-lg"><?= $lang === 'ru' ? 'Объект' : 'Нысан' ?></th>
                        <th><?= $lang === 'ru' ? 'Описание события' : 'Оқиға сипаттамасы' ?></th>
                        <th class="hs-w-roll actions"><?= $lang === 'ru' ? 'Откат' : 'Откат' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" class="hm-empty">
                                <?= $lang === 'ru' ? 'Записи аудита отсутствуют' : 'Өзгертулер журналы бос' ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l):
                            $action = $l['action'];
                            $actClass = match ($action) {
                                'CREATE'   => 'badge-success',
                                'DELETE'   => 'hs-del',
                                'ROLLBACK' => 'badge-accent',
                                default    => '',
                            };
                            $canRollback = ($isAdmin && !empty($l['old_values']) && ($action === 'UPDATE' || $action === 'DELETE'));
                            $userName = (string)($l['user_name'] ?: 'Система');
                            $summary = (string)$l['summary'];
                        ?>
                            <tr>
                                <td class="num col-opt-lg"><?= (int)$l['id'] ?></td>
                                <td class="clip muted small col-opt-md"><?= date('d.m.Y H:i', strtotime($l['created_at'])) ?></td>
                                <td class="clip col-opt-md" title="<?= htmlspecialchars($userName) ?>"><b><?= htmlspecialchars($userName) ?></b></td>
                                <td class="clip"><span class="badge <?= $actClass ?>"><?= htmlspecialchars($action) ?></span></td>
                                <td class="clip small col-opt-lg">
                                    <?php if ($l['entity_table'] === 'persons'): ?>
                                        <a href="/person/<?= (int)$l['entity_id'] ?>" class="pp-name">persons #<?= (int)$l['entity_id'] ?></a>
                                    <?php else: ?>
                                        <span class="hs-mono"><?= htmlspecialchars($l['entity_table']) ?> #<?= (int)$l['entity_id'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="hs-summary">
                                    <?= htmlspecialchars($summary) ?>
                                    <div class="small muted hs-narrow">
                                        <span class="hs-nm-time"><?= date('d.m.Y H:i', strtotime($l['created_at'])) ?></span>
                                        <span class="hs-nm-user"><?= htmlspecialchars($userName) ?></span>
                                        <span class="hs-nm-obj"><?php if ($l['entity_table'] === 'persons'): ?><a href="/person/<?= (int)$l['entity_id'] ?>" class="pp-name">persons #<?= (int)$l['entity_id'] ?></a><?php else: ?><?= htmlspecialchars((string)$l['entity_table']) ?> #<?= (int)$l['entity_id'] ?><?php endif; ?></span>
                                    </div>
                                </td>
                                <td class="actions">
                                    <?php if ($canRollback): ?>
                                        <button type="button" class="btn btn-sm" onclick="handleRollback(<?= (int)$l['id'] ?>)">
                                            <svg class="ic" aria-hidden="true"><use href="#i-history"/></svg><?= $lang === 'ru' ? 'Откатить' : 'Откат' ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function hsNotify(message, type) {
        if (typeof showAppToast === 'function') { showAppToast(message, type); } else { alert(message); }
    }

    async function handleRollback(logId) {
        if (!confirm('<?= $lang === 'ru' ? 'Вы уверены, что хотите откатить это изменение?' : 'Бұл өзгертуді кері қайтарғыңыз келетініне сенімдісіз бе?' ?>')) {
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
                hsNotify('<?= $lang === 'ru' ? 'Изменение успешно откачено!' : 'Өзгерту сәтті кері қайтарылды!' ?>', 'success');
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
