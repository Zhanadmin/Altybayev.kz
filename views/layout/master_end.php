<?php
declare(strict_types=1);
?>
                </div>
            </main>
        </div>
    </div>

    <!-- Модальное окно редактирования/добавления персоны (общее для страниц) — белый остров поверх затемнения -->
    <div id="globalPersonEditModal" class="modal-backdrop" style="display:none;">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalPersonEditTitle">
            <div class="modal-head">
                <h3 id="modalPersonEditTitle">Тұлғаны өңдеу</h3>
                <button type="button" class="btn btn-ghost btn-icon" onclick="closeGlobalPersonEditModal()" aria-label="Жабу / Закрыть">
                    <svg class="ic"><use href="#i-x"/></svg>
                </button>
            </div>
            <form id="globalPersonEditForm" onsubmit="handleGlobalPersonSave(event)">
                <input type="hidden" id="editModalPersonId" name="id" value="">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="field">
                            <label for="editModalFirstName">Аты / Имя *</label>
                            <input type="text" id="editModalFirstName" class="input" required>
                        </div>
                        <div class="field">
                            <label for="editModalLastName">Тегі / Фамилия</label>
                            <input type="text" id="editModalLastName" class="input">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="editModalMiddleName">Әкесінің аты / Отчество</label>
                            <input type="text" id="editModalMiddleName" class="input">
                        </div>
                        <div class="field">
                            <label for="editModalMaidenName">Қыз кезіндегі тегі / Девичья фамилия</label>
                            <input type="text" id="editModalMaidenName" class="input">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="editModalGender">Жынысы / Пол</label>
                            <select id="editModalGender" class="select">
                                <option value="M">Ер / Мужской</option>
                                <option value="F">Әйел / Женский</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="editModalClanRu">Руы / Род</label>
                            <input type="text" id="editModalClanRu" class="input" placeholder="Керей, Жағалбайлы...">
                            <div id="editModalClanHint" class="hint"></div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="editModalBirthDate">Туған күні / Дата рождения</label>
                            <input type="date" id="editModalBirthDate" class="input">
                        </div>
                        <div class="field">
                            <label for="editModalBirthYear">Туған жылы / Год</label>
                            <input type="text" id="editModalBirthYear" class="input" placeholder="мысалы, 1985">
                        </div>
                    </div>
                    <div class="field">
                        <label for="editModalBirthPlace">Туған жері / Место рождения</label>
                        <input type="text" id="editModalBirthPlace" class="input">
                    </div>
                    <div class="island-sub">
                        <label class="check">
                            <input type="checkbox" id="editModalIsDeceased" onchange="toggleEditModalDeceasedFields(this.checked)">
                            <span>Өмірден өткен / Умер(ла)</span>
                        </label>
                        <div id="editModalDeceasedFields" class="form-row" style="display:none; margin-top:12px;">
                            <div class="field">
                                <label for="editModalDeathDate">Қайтыс болған күні / Дата</label>
                                <input type="date" id="editModalDeathDate" class="input">
                            </div>
                            <div class="field">
                                <label for="editModalDeathYear">Жылы / Год</label>
                                <input type="text" id="editModalDeathYear" class="input" placeholder="мысалы, 2020">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn" onclick="closeGlobalPersonEditModal()">Бас тарту / Отмена</button>
                    <button type="submit" class="btn btn-primary">Сақтау / Сохранить</button>
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
                        const isDec = (p.is_deceased == 1);
                        document.getElementById('editModalIsDeceased').checked = isDec;
                        toggleEditModalDeceasedFields(isDec);
                        document.getElementById('editModalDeathDate').value = p.death_date || '';
                        document.getElementById('editModalDeathYear').value = p.death_year || '';
                    }
                })
                .catch(e => showAppToast('Қате / Ошибка: ' + e.message, 'error'));
        }

        function closeGlobalPersonEditModal() {
            const modal = document.getElementById('globalPersonEditModal');
            if (modal) modal.style.display = 'none';
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
            formData.append('is_deceased', isDec);
            formData.append('death_date', isDec ? document.getElementById('editModalDeathDate').value : '');
            formData.append('death_year', isDec ? document.getElementById('editModalDeathYear').value.trim() : '');

            try {
                const res = await fetch('/api/person/update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData.toString()
                });
                const data = await res.json();
                if (data && (data.status === 'success' || data.success)) {
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
