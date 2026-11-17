<?php
declare(strict_types=1);
$__ru = \App\Core\I18n::lang() === 'ru';
$__t = static fn(string $kk, string $ru): string => $__ru ? $ru : $kk;
?>
                </div>
                <!-- TASK-014: подвал со ссылками на политику и условия -->
                <footer class="ds-legal-foot" style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin:24px 0 8px;font-size:13px;opacity:.75;">
                    <a href="/privacy"><?= \App\Core\I18n::lang() === 'ru' ? 'Политика конфиденциальности' : 'Құпиялылық саясаты' ?></a>
                    <span aria-hidden="true">·</span>
                    <a href="/terms"><?= \App\Core\I18n::lang() === 'ru' ? 'Условия' : 'Шарттар' ?></a>
                </footer>
            </main>
        </div>
    </div>

    <!-- Модальное окно редактирования/добавления персоны (общее для страниц) — белый остров поверх затемнения -->
    <div id="globalPersonEditModal" class="modal-backdrop" style="display:none;">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalPersonEditTitle">
            <div class="modal-head">
                <h3 id="modalPersonEditTitle"><?= $__t('Тұлғаны өңдеу', 'Редактирование человека') ?></h3>
                <button type="button" class="btn btn-ghost btn-icon" onclick="closeGlobalPersonEditModal()" aria-label="<?= $__t('Жабу', 'Закрыть') ?>">
                    <svg class="ic"><use href="#i-x"/></svg>
                </button>
            </div>
            <form id="globalPersonEditForm" onsubmit="handleGlobalPersonSave(event)">
                <input type="hidden" id="editModalPersonId" name="id" value="">
                <div class="modal-body">
                    <?php /* TASK-037: аватарка и обложка убраны из окна — меняются на самой карточке (person_media_card.php) */ ?>
                    <div class="form-row">
                        <div class="field">
                            <label for="editModalFirstName"><?= $__t('Аты', 'Имя') ?> *</label>
                            <input type="text" id="editModalFirstName" class="input" required>
                        </div>
                        <div class="field">
                            <label for="editModalLastName"><?= $__t('Тегі', 'Фамилия') ?></label>
                            <input type="text" id="editModalLastName" class="input">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="editModalMiddleName"><?= $__t('Әкесінің аты', 'Отчество') ?></label>
                            <input type="text" id="editModalMiddleName" class="input">
                        </div>
                        <div class="field">
                            <label for="editModalMaidenName"><?= $__t('Қыз кезіндегі тегі', 'Девичья фамилия') ?></label>
                            <input type="text" id="editModalMaidenName" class="input">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="editModalGender"><?= $__t('Жынысы', 'Пол') ?></label>
                            <select id="editModalGender" class="select">
                                <option value="M"><?= $__t('Ер', 'Мужской') ?></option>
                                <option value="F"><?= $__t('Әйел', 'Женский') ?></option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="editModalClanRu"><?= $__t('Руы', 'Род') ?></label>
                            <input type="text" id="editModalClanRu" class="input" placeholder="Керей, Жағалбайлы...">
                            <div id="editModalClanHint" class="hint"></div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="editModalBirthDate"><?= $__t('Туған күні', 'Дата рождения') ?></label>
                            <input type="date" id="editModalBirthDate" class="input">
                        </div>
                        <div class="field">
                            <label for="editModalBirthYear"><?= $__t('Туған жылы', 'Год рождения') ?></label>
                            <input type="text" id="editModalBirthYear" class="input" placeholder="<?= $__t('мысалы, 1985', 'например, 1985') ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label for="editModalBirthPlace"><?= $__t('Туған жері', 'Место рождения') ?></label>
                        <input type="text" id="editModalBirthPlace" class="input">
                    </div>
                    <!-- TASK-036: тұрғылықты жері — история переездов -->
                    <div class="field" id="editModalResBlock">
                        <label><?= $__t('Тұрғылықты жері', 'Город проживания') ?></label>
                        <div id="editModalResidences"></div>
                        <button type="button" class="btn btn-sm" id="editModalResAdd" onclick="resAddRow()">+ <?= $__t('Жер қосу', 'Добавить место') ?></button>
                        <div class="hint"><?= $__t('Көшу ретімен: ескіден жаңаға. Қазіргі жерін «қазір» деп белгілеңіз', 'В порядке переездов: от раннего к позднему. Текущее место отметьте «қазір»') ?></div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="editModalReligion"><?= $__t('Діні', 'Религия') ?></label>
                            <input type="text" id="editModalReligion" class="input" maxlength="150">
                        </div>
                        <div class="field">
                            <label for="editModalNationality"><?= $__t('Ұлты', 'Национальность') ?></label>
                            <input type="text" id="editModalNationality" class="input" maxlength="150">
                        </div>
                    </div>
                    <div class="field">
                        <label for="editModalEducation"><?= $__t('Білімі', 'Образование') ?></label>
                        <input type="text" id="editModalEducation" class="input" maxlength="255">
                    </div>
                    <!-- TASK-033: отметка о кончине спрятана в свёрнутый блок «Дополнительно» -->
                    <details class="island-sub" id="editModalExtra">
                        <summary style="cursor:pointer;"><?= $__t('Қосымша', 'Дополнительно') ?></summary>
                        <label class="check" style="margin-top:10px;">
                            <input type="checkbox" id="editModalIsDeceased" onchange="toggleEditModalDeceasedFields(this.checked)">
                            <span><?= $__t('Өмірден өткен', 'Умер(ла)') ?></span>
                        </label>
                        <div id="editModalDeceasedFields" class="form-row" style="display:none; margin-top:12px;">
                            <div class="field">
                                <label for="editModalDeathDate"><?= $__t('Қайтыс болған күні', 'Дата смерти') ?></label>
                                <input type="date" id="editModalDeathDate" class="input">
                            </div>
                            <div class="field">
                                <label for="editModalDeathYear"><?= $__t('Қайтыс болған жылы', 'Год смерти') ?></label>
                                <input type="text" id="editModalDeathYear" class="input" placeholder="<?= $__t('мысалы, 2020', 'например, 2020') ?>">
                            </div>
                        </div>
                    </details>
                    <?php require __DIR__ . '/person_contact_fields.php'; ?>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn" onclick="closeGlobalPersonEditModal()"><?= $__t('Бас тарту', 'Отмена') ?></button>
                    <button type="submit" class="btn btn-primary"><?= $__t('Сақтау', 'Сохранить') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Уведомления -->
    <div class="toast-stack" id="appToastStack" aria-live="polite"></div>

    <script>
        function toggleMobileSidebar() {
            const sidebar = document.getElementById('appSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar && overlay) {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('open');
            }
        }

        function switchAppLanguage(lang) {
            // Устанавливаем cookie на 1 год
            document.cookie = `shezhire_lang=${lang}; path=/; max-age=31536000; SameSite=Lax`;
            localStorage.setItem('shezhire_lang', lang);
            // Перезагружаем текущую страницу или переходим на маршрут установки языка
            window.location.href = `/lang/${lang}?ref=${encodeURIComponent(window.location.pathname + window.location.search)}`;
        }

        // TASK-008: светлая / тёмная тема; без выбора — как в системе
        function toggleAppTheme() {
            const root = document.documentElement;
            const current = root.getAttribute('data-theme')
                || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            const next = current === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('shezhire_theme', next); } catch (e) {}
            document.cookie = `shezhire_theme=${next}; path=/; max-age=31536000; SameSite=Lax`;
        }
        (function () {
            // Иконка солнце/луна должна соответствовать системной теме, если выбора ещё нет
            const root = document.documentElement;
            if (!root.getAttribute('data-theme') && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                root.setAttribute('data-theme', 'dark');
            }
        })();

        // TASK-008: уведомления в едином стиле (вместо системных alert)
        function showAppToast(message, type) {
            const stack = document.getElementById('appToastStack');
            if (!stack) { alert(message); return; }
            const kind = (type === 'success') ? 'success' : 'error';
            const toast = document.createElement('div');
            toast.className = 'toast ' + kind;
            toast.setAttribute('role', kind === 'error' ? 'alert' : 'status');
            const svgNs = 'http://www.w3.org/2000/svg';
            const icon = document.createElementNS(svgNs, 'svg');
            icon.setAttribute('class', 'ic');
            const use = document.createElementNS(svgNs, 'use');
            use.setAttribute('href', kind === 'success' ? '#i-check' : '#i-alert');
            icon.appendChild(use);
            const text = document.createElement('div');
            text.textContent = String(message);
            toast.appendChild(icon);
            toast.appendChild(text);
            stack.appendChild(toast);
            setTimeout(function () { toast.remove(); }, kind === 'error' ? 7000 : 3500);
        }

        // TASK-036: редактор истории проживания
        function resAddRow(r) {
            r = r || {};
            const box = document.getElementById('editModalResidences');
            if (!box) return;
            const row = document.createElement('div');
            row.className = 'form-row res-row';
            row.style.cssText = 'align-items:center; gap:6px; margin-bottom:6px;';
            const mk = (cls, ph, val, w) => {
                const i = document.createElement('input');
                i.type = 'text'; i.className = 'input ' + cls; i.placeholder = ph; i.value = val || '';
                i.style.cssText = 'flex:' + w + ';min-width:0;';
                return i;
            };
            row.appendChild(mk('res-place', 'Қала / Город', r.place, '3'));
            row.appendChild(mk('res-from', 'с', r.year_from, '1'));
            const to = mk('res-to', 'по', r.year_to, '1');
            row.appendChild(to);
            const lab = document.createElement('label');
            lab.className = 'check';
            const cb = document.createElement('input');
            cb.type = 'checkbox'; cb.className = 'res-cur'; cb.checked = !!(r.is_current == 1);
            cb.onchange = function () {
                if (cb.checked) {
                    document.querySelectorAll('#editModalResidences .res-cur').forEach(function (o) { if (o !== cb) o.checked = false; });
                }
                to.disabled = cb.checked; if (cb.checked) to.value = '';
            };
            to.disabled = cb.checked;
            const sp = document.createElement('span'); sp.textContent = 'қазір';
            lab.appendChild(cb); lab.appendChild(sp);
            row.appendChild(lab);
            const del = document.createElement('button');
            del.type = 'button'; del.className = 'btn btn-sm'; del.textContent = '×'; del.title = 'Жою / Удалить';
            del.onclick = function () { row.remove(); };
            row.appendChild(del);
            box.appendChild(row);
        }

        function resCollect() {
            const out = [];
            document.querySelectorAll('#editModalResidences .res-row').forEach(function (row) {
                const place = row.querySelector('.res-place').value.trim();
                if (!place) return;
                out.push({
                    place: place,
                    year_from: row.querySelector('.res-from').value.trim(),
                    year_to: row.querySelector('.res-to').value.trim(),
                    is_current: row.querySelector('.res-cur').checked ? 1 : 0
                });
            });
            return out;
        }

        function toggleEditModalDeceasedFields(isDeceased) {
            const fields = document.getElementById('editModalDeceasedFields');
            if (fields) {
                fields.style.display = isDeceased ? 'grid' : 'none';
            }
        }

        function openGlobalPersonEditModal(id) {
            const modal = document.getElementById('globalPersonEditModal');
            if (!modal) return;
            modal.style.display = 'flex';
            fetch(`/api/person?id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data && data.person) {
                        const p = data.person;
                        document.getElementById('editModalPersonId').value = p.id;
                        document.getElementById('editModalFirstName').value = p.first_name || '';
                        document.getElementById('editModalLastName').value = p.last_name || '';
                        document.getElementById('editModalMiddleName').value = p.middle_name || '';
                        document.getElementById('editModalMaidenName').value = p.maiden_name || '';
                        document.getElementById('editModalGender').value = p.gender || 'M';

                        const clanInput = document.getElementById('editModalClanRu');
                        const clanHint = document.getElementById('editModalClanHint');
                        clanInput.value = p.clan_ru || '';
                        const canEditClan = (data.can_edit_clan !== false) && (!p.father_id);
                        // Вид поля «только чтение» задаёт дизайн-система (.input[readonly]) — в обеих темах
                        clanInput.readOnly = !canEditClan;
                        if (clanHint) {
                            clanHint.textContent = canEditClan ? '' : (data.clan_hint || 'әкесінен / по отцу');
                        }

                        document.getElementById('editModalBirthDate').value = p.birth_date || '';
                        document.getElementById('editModalBirthYear').value = p.birth_year || '';
                        document.getElementById('editModalBirthPlace').value = p.birth_place || '';
                        // TASK-036
                        document.getElementById('editModalReligion').value = p.religion || '';
                        document.getElementById('editModalNationality').value = p.nationality || '';
                        document.getElementById('editModalEducation').value = p.education || '';
                        window.__pcProfileReady = !!data.profile_ready;
                        window.__pcResReady = !!data.residences_ready;
                        const resBox = document.getElementById('editModalResidences');
                        resBox.innerHTML = '';
                        (data.residences || []).forEach(function (r) { resAddRow(r); });
                        const isDec = (p.is_deceased == 1);
                        document.getElementById('editModalIsDeceased').checked = isDec;
                        toggleEditModalDeceasedFields(isDec);
                        var exTr = document.getElementById('editModalExtra'); if (exTr) { exTr.open = isDec; } // TASK-033: у ушедших блок раскрыт
                        document.getElementById('editModalDeathDate').value = p.death_date || '';
                        document.getElementById('editModalDeathYear').value = p.death_year || '';
                        if (typeof pcfLoad === 'function') { pcfLoad(p.id, data.contacts || null); } // TASK-022
                        if (typeof pmeLoad === 'function') { pmeLoad(p.id, data.media_edit || null); } // TASK-022: фото и обложка
                        // TASK-022: сам человек (не editor) правит только фото, обложку и контакты; основные поля — только чтение
                        window.__pcFullEdit = !!data.can_edit;
                        document.querySelectorAll('#globalPersonEditForm [id^="editModal"]').forEach(function (el) {
                            if (el.id !== 'editModalPersonId' && el.id !== 'editModalClanHint') { el.disabled = !data.can_edit; }
                        });
                        document.querySelectorAll('#editModalResBlock input, #editModalResBlock button').forEach(function (el) { el.disabled = !data.can_edit; });
                    }
                })
                .catch(e => showAppToast('Қате / Ошибка: ' + e.message, 'error'));
        }

        function closeGlobalPersonEditModal() {
            const modal = document.getElementById('globalPersonEditModal');
            if (modal) modal.style.display = 'none';
            if (window.pcfDirty) { window.pcfDirty = false; window.location.reload(); } // TASK-022: данные на странице устарели
        }

        async function handleGlobalPersonSave(e) {
            e.preventDefault();
            const id = document.getElementById('editModalPersonId').value;
            const isDec = document.getElementById('editModalIsDeceased').checked ? 1 : 0;
            const formData = new URLSearchParams();
            formData.append('id', id);
            formData.append('first_name', document.getElementById('editModalFirstName').value.trim());
            formData.append('last_name', document.getElementById('editModalLastName').value.trim());
            formData.append('middle_name', document.getElementById('editModalMiddleName').value.trim());
            formData.append('maiden_name', document.getElementById('editModalMaidenName').value.trim());
            formData.append('gender', document.getElementById('editModalGender').value);
            formData.append('clan_ru', document.getElementById('editModalClanRu').value.trim());
            formData.append('birth_date', document.getElementById('editModalBirthDate').value);
            formData.append('birth_year', document.getElementById('editModalBirthYear').value.trim());
            formData.append('birth_place', document.getElementById('editModalBirthPlace').value.trim());
            if (window.__pcProfileReady) {
                formData.append('profile_present', '1');
                formData.append('religion', document.getElementById('editModalReligion').value.trim());
                formData.append('nationality', document.getElementById('editModalNationality').value.trim());
                formData.append('education', document.getElementById('editModalEducation').value.trim());
            }
            if (window.__pcResReady) { formData.append('residences', JSON.stringify(resCollect())); }
            formData.append('is_deceased', isDec);
            formData.append('death_date', isDec ? document.getElementById('editModalDeathDate').value : '');
            formData.append('death_year', isDec ? document.getElementById('editModalDeathYear').value.trim() : '');

            try {
                let data;
                if (window.__pcFullEdit === false) {
                    data = { success: true }; // не editor: основные данные не отправляем (TASK-022)
                } else {
                    const res = await fetch('/api/person/update', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData.toString()
                    });
                    data = await res.json();
                }
                if (data && (data.status === 'success' || data.success)) {
                    // TASK-022: контакты — тем же нажатием «Сақтау»
                    if (typeof pmeSave === 'function') { await pmeSave(); } // положение/затемнение обложки
                    const ct = (typeof pcfSave === 'function') ? await pcfSave() : { ok: true, offerInvite: false };
                    if (!ct.ok) { window.pcfDirty = true; return; } // остаёмся в окне, ошибка уже показана
                    if (ct.offerInvite) {
                        // email сохранён — показываем кнопку «Шақыру жіберу», окно не закрываем
                        window.pcfDirty = true;
                        showAppToast('Сақталды. Енді шақыру жіберуге болады / Сохранено. Теперь можно отправить приглашение', 'success');
                        return;
                    }
                    window.pcfDirty = false;
                    closeGlobalPersonEditModal();
                    window.location.reload();
                } else {
                    showAppToast('Қате: ' + (data.error || 'Сақтау мүмкін болмады'), 'error');
                }
            } catch (err) {
                showAppToast('Сұраныс қатесі: ' + err.message, 'error');
            }
        }
    </script>
</body>
</html>
