<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
$title = I18n::t('nav_families');
$activeNav = 'families';
$dsThemeReady = true; // TASK-008: страница на цветах дизайн-системы — доступна тёмная тема

require __DIR__ . '/../layout/master_start.php';
?>

<div class="fm">
    <div class="ds-page-head">
        <div>
            <h1><?= htmlspecialchars(I18n::t('families_title')) ?></h1>
            <p class="sub"><?= htmlspecialchars(I18n::t('families_subtitle')) ?></p>
        </div>
        <div class="actions pp-stats">
            <span class="badge"><?= $lang === 'ru' ? 'Всего союзов:' : 'Барлық шаңырақ:' ?> <b><?= (int)$totalAll ?></b></span>
            <span class="badge badge-success"><?= $lang === 'ru' ? 'В браке:' : 'Некеде:' ?> <b><?= (int)$totalMarried ?></b></span>
            <span class="badge"><?= $lang === 'ru' ? 'В разводе:' : 'Ажырасқан:' ?> <b><?= (int)$totalDivorced ?></b></span>
        </div>
    </div>

    <!-- Фильтр и поиск -->
    <form method="GET" action="/families" id="familiesFilterForm" class="island toolbar">
        <div class="search">
            <svg class="ic" aria-hidden="true"><use href="#i-search"/></svg>
            <input type="text" name="q" class="input" value="<?= htmlspecialchars((string)($filters['q'] ?? '')) ?>"
                   placeholder="<?= $lang === 'ru' ? 'Поиск по фамилии супругов, роду, месту...' : 'Ерлі-зайыптылардың аты-жөні, руы бойынша іздеу...' ?>">
        </div>
        <select name="status" class="select" onchange="document.getElementById('familiesFilterForm').submit()">
            <option value=""><?= $lang === 'ru' ? 'Статус: Все' : 'Күйі: Барлығы' ?></option>
            <option value="married" <?= ($filters['status'] ?? '') === 'married' ? 'selected' : '' ?>><?= $lang === 'ru' ? 'В браке' : 'Некеде' ?></option>
            <option value="divorced" <?= ($filters['status'] ?? '') === 'divorced' ? 'selected' : '' ?>><?= $lang === 'ru' ? 'В разводе' : 'Ажырасқан' ?></option>
        </select>
        <button type="submit" class="btn btn-primary"><?= $lang === 'ru' ? 'Найти' : 'Іздеу' ?></button>
        <?php if (!empty($filters['q']) || !empty($filters['status'])): ?>
            <a href="/families" class="btn btn-ghost"><?= $lang === 'ru' ? 'Сброс' : 'Тазарту' ?></a>
        <?php endif; ?>
    </form>

    <!-- Сетка семей с группировкой по поколениям -->
    <?php if (empty($families)): ?>
        <div class="island pp-empty">
            <svg class="ic" aria-hidden="true"><use href="#i-family"/></svg>
            <b><?= $lang === 'ru' ? 'Семьи не найдены' : 'Шаңырақтар табылмады' ?></b>
            <span class="muted small"><?= $lang === 'ru' ? 'Попробуйте изменить параметры поиска' : 'Іздеу параметрлерін өзгертіп көріңіз' ?></span>
        </div>
    <?php else: ?>
        <?php foreach ($generationGroups as $gKey => $group):
            if (empty($group['families'])) continue;
            $gTitle = ($lang === 'ru') ? $group['title_ru'] : $group['title_kk'];
            $isAncestors = ($gKey === 'ancestors');
        ?>
            <section class="fm-group">
                <div class="island-head">
                    <svg class="ic<?= $isAncestors ? ' pc-accent' : '' ?>" aria-hidden="true"><use href="#i-<?= $isAncestors ? 'tamga' : 'family' ?>"/></svg>
                    <h3><?= htmlspecialchars($gTitle) ?></h3>
                    <span class="badge more"><?= count($group['families']) ?> <?= $lang === 'ru' ? 'союзов' : 'шаңырақ' ?></span>
                </div>

                <div class="fm-grid">
                    <?php foreach ($group['families'] as $idx => $f):
                        $m = $f['marriage'];
                        $isMarried = ($f['status'] === 'married');
                        $h = $f['husband'];
                        $w = $f['wife'];
                        $children = $f['children'];
                    ?>
                        <article class="island fm-card<?= $isAncestors ? ' fm-ancestors' : '' ?>">
                            <div class="fm-card-head">
                                <span class="small muted">#<?= $idx + 1 ?> (ID <?= (int)$m['id'] ?>)</span>
                                <span class="badge<?= $isMarried ? ' badge-success' : '' ?>">
                                    <?= $isMarried ? ($lang === 'ru' ? 'В браке' : 'Некеде') : ($lang === 'ru' ? 'В разводе' : 'Ажырасқан') ?>
                                </span>
                                <?php if ($canEdit): ?>
                                    <button type="button" class="btn btn-ghost btn-icon btn-sm" data-tip="Өңдеу" aria-label="Өңдеу"
                                            onclick="openMarriageEditModal(<?= (int)$m['id'] ?>, '<?= htmlspecialchars($m['status'] ?? 'married') ?>', '<?= htmlspecialchars((string)($m['start_date'] ?: $m['marriage_date'])) ?>', '<?= htmlspecialchars((string)($m['end_date'] ?: $m['divorce_date'])) ?>', '<?= htmlspecialchars((string)($m['notes'] ?? '')) ?>')">
                                        <svg class="ic" aria-hidden="true"><use href="#i-edit"/></svg>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <!-- Супруги (Пара) -->
                            <div class="fm-pair">
                                <div class="island-sub fm-spouse">
                                    <div class="pc-label"><?= $lang === 'ru' ? 'Муж' : 'Отағасы' ?></div>
                                    <?php if ($h): ?>
                                        <a href="/person/<?= (int)$h['id'] ?>" class="fm-name" title="<?= htmlspecialchars($f['h_name']) ?>"><?= htmlspecialchars($f['h_name']) ?></a>
                                        <div class="small muted">
                                            <?= $h['clan_ru'] ? htmlspecialchars($h['clan_ru']) : '' ?>
                                            <?= $h['birth_year'] ? ' · ' . htmlspecialchars((string)$h['birth_year']) . ' ж.т.' : '' ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="muted">Белгісіз</span>
                                    <?php endif; ?>
                                </div>

                                <div class="island-sub fm-spouse">
                                    <div class="pc-label"><?= $lang === 'ru' ? 'Жена' : 'Отанасы' ?></div>
                                    <?php if ($w): ?>
                                        <a href="/person/<?= (int)$w['id'] ?>" class="fm-name" title="<?= htmlspecialchars($f['w_name']) ?>"><?= htmlspecialchars($f['w_name']) ?></a>
                                        <?php if (!empty($w['maiden_name'])): ?>
                                            <div class="small muted">(<?= $lang === 'ru' ? 'урожд.' : 'қыз кезінде:' ?> <?= htmlspecialchars($w['maiden_name']) ?>)</div>
                                        <?php endif; ?>
                                        <div class="small muted"><?= $w['birth_year'] ? htmlspecialchars((string)$w['birth_year']) . ' ж.т.' : '' ?></div>
                                    <?php else: ?>
                                        <span class="muted">Белгісіз</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Даты брака / развода -->
                            <?php if (!empty($m['marriage_date']) || !empty($m['start_date']) || !empty($m['end_date'])): ?>
                                <div class="fm-dates small muted">
                                    <svg class="ic" aria-hidden="true"><use href="#i-calendar"/></svg>
                                    <span>
                                        <?= $lang === 'ru' ? 'Период союза:' : 'Неке мерзімі:' ?>
                                        <strong><?= htmlspecialchars((string)($m['start_date'] ?: $m['marriage_date'])) ?></strong>
                                        <?php if (!empty($m['end_date']) || !empty($m['divorce_date'])): ?>
                                            — <strong><?= htmlspecialchars((string)($m['end_date'] ?: $m['divorce_date'])) ?></strong>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <!-- Балалары (Дети, отсортированные по старшинству) -->
                            <div>
                                <div class="pc-sub-title"><?= $lang === 'ru' ? 'Дети (' . count($children) . ')' : 'Балалары (' . count($children) . ')' ?></div>
                                <?php if (empty($children)): ?>
                                    <div class="small muted"><?= $lang === 'ru' ? 'Общие дети не зарегистрированы' : 'Ортақ балалар тіркелмеген' ?></div>
                                <?php else: ?>
                                    <div class="pc-tags">
                                        <?php foreach ($children as $c): ?>
                                            <a href="/person/<?= (int)$c['id'] ?>" class="badge pc-tag" title="<?= ($c['gender'] === 'M') ? ($lang === 'ru' ? 'Сын' : 'Ұлы') : ($lang === 'ru' ? 'Дочь' : 'Қызы') ?>">
                                                <?= htmlspecialchars($c['first_name']) ?>
                                                <?php if (!empty($c['birth_year'])): ?>
                                                    <span><?= htmlspecialchars((string)$c['birth_year']) ?></span>
                                                <?php endif; ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Быстрый переход в древо -->
                            <?php if ($h || $w):
                                $focalId = $h ? (int)$h['id'] : (int)$w['id'];
                            ?>
                                <div class="fm-foot">
                                    <a href="/tree?focus=<?= $focalId ?>" class="btn btn-sm btn-ghost">
                                        <svg class="ic" aria-hidden="true"><use href="#i-tree"/></svg><?= $lang === 'ru' ? 'Показать семью в древе' : 'Древода көрсету' ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Модальное окно редактирования брака — белый остров поверх затемнения -->
<div id="marriageEditModal" class="modal-backdrop" style="display:none;">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="marriageEditTitle" style="max-width: 440px;">
        <div class="modal-head">
            <h3 id="marriageEditTitle">Некені өңдеу / Редактирование брака</h3>
            <button type="button" class="btn btn-ghost btn-icon" onclick="closeMarriageEditModal()" aria-label="Жабу / Закрыть">
                <svg class="ic"><use href="#i-x"/></svg>
            </button>
        </div>
        <form id="marriageEditForm" onsubmit="handleMarriageSave(event)">
            <input type="hidden" id="editMarriageId" name="id" value="">
            <div class="modal-body">
                <div class="field">
                    <label for="editMarriageStatus">Күйі / Статус</label>
                    <select id="editMarriageStatus" class="select">
                        <option value="married">Некеде / В браке</option>
                        <option value="divorced">Ажырасқан / В разводе</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="editMarriageStartDate">Неке күні / Дата</label>
                        <input type="text" id="editMarriageStartDate" class="input" placeholder="1995">
                    </div>
                    <div class="field">
                        <label for="editMarriageEndDate">Ажырасу / Дата</label>
                        <input type="text" id="editMarriageEndDate" class="input" placeholder="2010">
                    </div>
                </div>
                <div class="field">
                    <label for="editMarriageNotes">Жазба / Примечание</label>
                    <input type="text" id="editMarriageNotes" class="input">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" onclick="closeMarriageEditModal()">Бас тарту / Отмена</button>
                <button type="submit" class="btn btn-primary">Сақтау / Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openMarriageEditModal(id, status, startDate, endDate, notes) {
        document.getElementById('editMarriageId').value = id;
        document.getElementById('editMarriageStatus').value = status;
        document.getElementById('editMarriageStartDate').value = startDate || '';
        document.getElementById('editMarriageEndDate').value = endDate || '';
        document.getElementById('editMarriageNotes').value = notes || '';
        document.getElementById('marriageEditModal').style.display = 'flex';
    }

    function closeMarriageEditModal() {
        document.getElementById('marriageEditModal').style.display = 'none';
    }

    function fmNotify(message) {
        if (typeof showAppToast === 'function') { showAppToast(message, 'error'); } else { alert(message); }
    }

    async function handleMarriageSave(e) {
        e.preventDefault();
        const id = document.getElementById('editMarriageId').value;
        const formData = new URLSearchParams();
        formData.append('id', id);
        formData.append('status', document.getElementById('editMarriageStatus').value);
        formData.append('start_date', document.getElementById('editMarriageStartDate').value.trim());
        formData.append('end_date', document.getElementById('editMarriageEndDate').value.trim());
        formData.append('notes', document.getElementById('editMarriageNotes').value.trim());

        try {
            const res = await fetch('/api/marriage/update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            const data = await res.json();
            if (data && (data.status === 'success' || data.success)) {
                window.location.reload();
            } else {
                fmNotify('Қате: ' + (data.error || 'Сақтау мүмкін болмады'));
            }
        } catch (err) {
            fmNotify('Сұраныс қатесі: ' + err.message);
        }
    }
</script>

<?php
require __DIR__ . '/../layout/master_end.php';
?>
