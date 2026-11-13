<?php
declare(strict_types=1);
?>
            </div>
        </main>
    </div>

    <!-- Модальное окно редактирования/добавления персоны (общее для страниц) -->
    <div id="globalPersonEditModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15, 23, 42, 0.65); z-index:999; align-items:center; justify-content:center; padding:16px;">
        <div style="background:#ffffff; border-radius:14px; width:100%; max-width:600px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 40px rgba(0,0,0,0.3); padding:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; border-bottom:1px solid #e2e8f0; padding-bottom:12px;">
                <h3 id="modalPersonEditTitle" style="font-size:18px; font-weight:700; color:#0f172a;">Тұлғаны өңдеу</h3>
                <button type="button" onclick="closeGlobalPersonEditModal()" style="background:none; border:none; font-size:22px; cursor:pointer; color:#64748b;">&times;</button>
            </div>
            <form id="globalPersonEditForm" onsubmit="handleGlobalPersonSave(event)">
                <input type="hidden" id="editModalPersonId" name="id" value="">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Аты / Имя *</label>
                        <input type="text" id="editModalFirstName" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Тегі / Фамилия</label>
                        <input type="text" id="editModalLastName" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Әкесінің аты / Отчество</label>
                        <input type="text" id="editModalMiddleName" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Қыз кезіндегі тегі / Девичья фамилия</label>
                        <input type="text" id="editModalMaidenName" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Жынысы / Пол</label>
                        <select id="editModalGender" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                            <option value="M">Ер / Мужской</option>
                            <option value="F">Әйел / Женский</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Руы / Род</label>
                        <input type="text" id="editModalClanRu" placeholder="Керей, Жағалбайлы..." style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Туған күні / Дата рождения</label>
                        <input type="date" id="editModalBirthDate" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Туған жылы / Год</label>
                        <input type="text" id="editModalBirthYear" placeholder="мысалы, 1985" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                    </div>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Туған жері / Место рождения</label>
                    <input type="text" id="editModalBirthPlace" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                </div>
                <div style="margin-bottom:14px; padding:10px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">
                    <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:#1e293b; cursor:pointer;">
                        <input type="checkbox" id="editModalIsDeceased" onchange="toggleEditModalDeceasedFields(this.checked)">
                        <span>Өмірден өткен / Умер(ла) †</span>
                    </label>
                    <div id="editModalDeceasedFields" style="display:none; margin-top:10px; grid-template-columns:1fr 1fr; gap:10px;">
                        <div>
                            <label style="display:block; font-size:11px; color:#64748b; margin-bottom:2px;">Қайтыс болған күні / Дата</label>
                            <input type="date" id="editModalDeathDate" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; color:#64748b; margin-bottom:2px;">Жылы / Год</label>
                            <input type="text" id="editModalDeathYear" placeholder="мысалы, 2020" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        </div>
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" onclick="closeGlobalPersonEditModal()" style="padding:9px 16px; border:1px solid #cbd5e1; background:#ffffff; color:#475569; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer;">Бас тарту / Отмена</button>
                    <button type="submit" style="padding:9px 18px; border:none; background:#1b3b6f; color:#ffffff; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer;">Сақтау / Сохранить</button>
                </div>
            </form>
        </div>
    </div>

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
                        document.getElementById('editModalClanRu').value = p.clan_ru || '';
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
                .catch(e => alert('Қате / Ошибка: ' + e.message));
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
                    alert('Қате: ' + (data.error || 'Сақтау мүмкін болмады'));
                }
            } catch (err) {
                alert('Сұраныс қатесі: ' + err.message);
            }
        }
    </script>
</body>
</html>
