<?php
declare(strict_types=1);
$__ru = (\App\Core\I18n::lang() === 'ru' || \App\Core\I18n::lang() === 'en' || \App\Core\I18n::lang() === 'es');
$__t = static fn(string $kk, string $ru): string => $__ru ? $ru : $kk;
?>
                </div>
                <!-- TASK-014: подвал со ссылками на политику и условия -->
                <footer class="ds-legal-foot" style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin:24px 0 8px;font-size:13px;opacity:.75;">
                    <a href="/privacy"><?= (\App\Core\I18n::lang() === 'ru' || \App\Core\I18n::lang() === 'en' || \App\Core\I18n::lang() === 'es') ? 'Политика конфиденциальности' : 'Құпиялылық саясаты' ?></a>
                    <span aria-hidden="true">·</span>
                    <a href="/terms"><?= (\App\Core\I18n::lang() === 'ru' || \App\Core\I18n::lang() === 'en' || \App\Core\I18n::lang() === 'es') ? 'Условия' : 'Шарттар' ?></a>
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
                        <div class="field" id="editModalMaidenGroup">
                            <label for="editModalMaidenName"><?= $__t('Қыз кезіндегі тегі', 'Девичья фамилия') ?></label>
                            <input type="text" id="editModalMaidenName" class="input">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="editModalGender"><?= $__t('Жынысы', 'Пол') ?></label>
                            <select id="editModalGender" class="select" onchange="syncEditModalMaiden()">
                                <option value="M"><?= $__t('Ер', 'Мужской') ?></option>
                                <option value="F"><?= $__t('Әйел', 'Женский') ?></option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="editModalClanRu"><?= $__t('Руы', 'Род') ?></label>
                            <input type="text" id="editModalClanRu" class="input">
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
                            <input type="text" id="editModalBirthYear" class="input">
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
                                <input type="text" id="editModalDeathYear" class="input">
                            </div>
                        </div>
                        <?php /* ALT-002: «Место упокоения» раскрывается сразу под датой смерти, только при отметке «Умер(ла)» */ ?>
                        <div id="editModalBurialBlock" style="display:none; margin-top:12px;">
                            <div class="field">
                                <label for="editModalDeathPlace"><?= $__t('Мәңгілік мекені (жерленген жері)', 'Место упокоения') ?></label>
                                <input type="text" id="editModalDeathPlace" class="input" maxlength="255">
                            </div>
                            <div id="editModalBurialExtra" style="display:none;">
                                <div class="form-row">
                                    <div class="field">
                                        <label for="editModalBurialSettlement"><?= $__t('Елді мекен', 'Населённый пункт') ?></label>
                                        <input type="text" id="editModalBurialSettlement" class="input" maxlength="255">
                                    </div>
                                    <div class="field">
                                        <label for="editModalBurialCemetery"><?= $__t('Зираттың атауы', 'Название кладбища') ?></label>
                                        <input type="text" id="editModalBurialCemetery" class="input" maxlength="255">
                                    </div>
                                </div>
                                <div class="field">
                                    <label for="editModalBurialAddress"><?= $__t('Толық мекенжайы', 'Полный адрес') ?></label>
                                    <input type="text" id="editModalBurialAddress" class="input" maxlength="255">
                                </div>
                                <div class="field">
                                    <label for="editModalBurialPlot"><?= $__t('Қабірдің орны (учаске / қатар / бағдар)', 'Расположение могилы (участок / ряд / ориентир)') ?></label>
                                    <input type="text" id="editModalBurialPlot" class="input" maxlength="255">
                                </div>
                                <div class="field">
                                    <label><?= $__t('Картадағы нүкте', 'Точка на карте') ?></label>
                                    <div id="editModalBurialMap" style="height:260px; border-radius:8px; border:1px solid var(--border, #d0d5dd); overflow:hidden;"></div>
                                    <div class="hint"><?= $__t('Картаны басып белгі қойыңыз, белгіні сүйреп жылжытуға болады', 'Нажмите на карту, чтобы поставить метку; метку можно перетащить') ?></div>
                                    <div class="form-row" style="margin-top:6px; align-items:center;">
                                        <input type="text" id="editModalBurialLat" class="input" placeholder="lat" readonly aria-label="lat">
                                        <input type="text" id="editModalBurialLng" class="input" placeholder="lng" readonly aria-label="lng">
                                        <button type="button" class="btn btn-sm" id="editModalBurialClear" onclick="burialClearPoint()"><?= $__t('Белгіні өшіру', 'Убрать метку') ?></button>
                                    </div>
                                </div>
                            </div>
                            <div class="hint" id="editModalBurialNotReady" style="display:none;"><?= $__t('Зират пен карта өрістері дерекқор жаңартылғаннан кейін қосылады', 'Поля кладбища и карты появятся после обновления базы данных') ?></div>
                        </div>
                    </details>
                    <?php $relBtnCls = 'btn btn-sm'; require __DIR__ . '/person_relatives.php'; /* ALT-067 */ ?>
                    <?php require __DIR__ . '/person_contact_fields.php'; ?>
                    <?php /* ALT-061: удаление — только здесь, в самом низу, неброским блоком; права как в ALT-018 (can_delete с сервера) */ ?>
                    <div id="editModalDangerBlock" style="display:none;margin-top:28px;padding-top:14px;border-top:1px dashed var(--c-border);">
                        <div style="font-size:13px;color:var(--c-text-muted,#7a7468);margin-bottom:8px;"><?= $__t('Қауіпті әрекет', 'Опасное действие') ?></div>
                        <button type="button" class="btn btn-ghost btn-danger btn-sm" id="editModalDeleteBtn" onclick="handleGlobalPersonDelete()">
                            <svg class="ic" aria-hidden="true" viewBox="0 0 24 24"><path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12"/></svg>
                            <span><?= $__t('Жою', 'Удалить') ?></span>
                        </button>
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn" onclick="closeGlobalPersonEditModal()"><?= $__t('Бас тарту', 'Отмена') ?></button>
                    <button type="submit" class="btn btn-primary"><?= $__t('Сақтау', 'Сохранить') ?></button>
                </div>
            </form>
        </div>
    </div>

    <?php
    // ALT-067: диалог добавления родственника; если окно правки закрыто (кнопка в карточке) — страница перезагружается
    $relBtnCls = 'btn'; $relBtnPrimaryCls = 'btn btn-primary'; $relInputCls = 'input';
    $relOnDoneJs = "var m = document.getElementById('globalPersonEditModal'); if (!m || m.style.display === 'none') { window.location.reload(); }";
    require __DIR__ . '/person_relatives_dialog.php';
    ?>

    <!-- Уведомления -->
    <div class="toast-stack" id="appToastStack" aria-live="polite"></div>

    <script>
        // Девичья фамилия — только при женском поле
        function syncEditModalMaiden() {
            const g = document.getElementById('editModalGender');
            const grp = document.getElementById('editModalMaidenGroup');
            if (!g || !grp) return;
            if (g.value === 'F') {
                grp.style.display = '';
            } else {
                grp.style.display = 'none';
                document.getElementById('editModalMaidenName').value = '';
            }
        }
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

        // ALT-071: после перезагрузки страницы показать «Сақталды»
        (function () {
            try {
                if (sessionStorage.getItem('altSavedToast')) {
                    sessionStorage.removeItem('altSavedToast');
                    showAppToast('Сақталды / Сохранено', 'success');
                }
            } catch (e) {}
        })();

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
            // ALT-002: «Место упокоения» — сразу под датой смерти, только у умерших
            const burial = document.getElementById('editModalBurialBlock');
            if (burial) {
                burial.style.display = isDeceased ? 'block' : 'none';
                const extra = document.getElementById('editModalBurialExtra');
                const note = document.getElementById('editModalBurialNotReady');
                if (extra) { extra.style.display = (isDeceased && window.__pcBurialReady) ? 'block' : 'none'; }
                if (note) { note.style.display = (isDeceased && !window.__pcBurialReady) ? 'block' : 'none'; }
                if (isDeceased && window.__pcBurialReady) { burialMapShow(); }
            }
        }

        // ALT-002: карта OpenStreetMap (Leaflet, без ключей) для точки места упокоения
        var burialMap = null, burialMarker = null, burialLeafletLoading = null;
        function burialLoadLeaflet() {
            if (window.L) { return Promise.resolve(); }
            if (burialLeafletLoading) { return burialLeafletLoading; }
            burialLeafletLoading = new Promise(function (resolve, reject) {
                const css = document.createElement('link');
                css.rel = 'stylesheet';
                css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                document.head.appendChild(css);
                const js = document.createElement('script');
                js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                js.onload = function () { resolve(); };
                js.onerror = function () { burialLeafletLoading = null; reject(new Error('Leaflet')); };
                document.head.appendChild(js);
            });
            return burialLeafletLoading;
        }
        function burialSetPoint(lat, lng) {
            document.getElementById('editModalBurialLat').value = (lat === null) ? '' : Number(lat).toFixed(6);
            document.getElementById('editModalBurialLng').value = (lng === null) ? '' : Number(lng).toFixed(6);
            if (!burialMap) { return; }
            if (lat === null) {
                if (burialMarker) { burialMap.removeLayer(burialMarker); burialMarker = null; }
                return;
            }
            if (!burialMarker) {
                burialMarker = L.marker([lat, lng], { draggable: true }).addTo(burialMap);
                burialMarker.on('dragend', function () {
                    const ll = burialMarker.getLatLng();
                    burialSetPoint(ll.lat, ll.lng);
                });
            } else {
                burialMarker.setLatLng([lat, lng]);
            }
        }
        function burialClearPoint() { burialSetPoint(null, null); }
        function burialMapShow() {
            const box = document.getElementById('editModalBurialMap');
            if (!box) { return; }
            burialLoadLeaflet().then(function () {
                if (!burialMap) {
                    burialMap = L.map(box).setView([48.0, 67.0], 4); // Казахстан
                    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    }).addTo(burialMap);
                    burialMap.on('click', function (ev) { burialSetPoint(ev.latlng.lat, ev.latlng.lng); });
                }
                const lat = parseFloat(document.getElementById('editModalBurialLat').value);
                const lng = parseFloat(document.getElementById('editModalBurialLng').value);
                setTimeout(function () {
                    burialMap.invalidateSize();
                    if (!isNaN(lat) && !isNaN(lng)) {
                        burialSetPoint(lat, lng);
                        burialMap.setView([lat, lng], 15);
                    } else if (burialMarker) {
                        burialSetPoint(null, null);
                    }
                }, 50);
            }).catch(function () {
                box.textContent = 'Карта жүктелмеді / Карта не загрузилась';
            });
        }
        document.addEventListener('DOMContentLoaded', function () {
            const ex = document.getElementById('editModalExtra');
            if (ex) {
                ex.addEventListener('toggle', function () {
                    if (ex.open && window.__pcBurialReady && document.getElementById('editModalIsDeceased').checked) { burialMapShow(); }
                });
            }
        });

        // ALT-061: удаление из окна «Өзгерту». Параметры (lang, csrf, onDone) задаёт страница в window.__pcDelete
        function handleGlobalPersonDelete() {
            const cfg = window.__pcDelete || {};
            const id = document.getElementById('editModalPersonId').value;
            if (!id || typeof altDeletePerson !== 'function') { return; }
            const nm = (document.getElementById('editModalLastName').value + ' ' + document.getElementById('editModalFirstName').value).trim();
            altDeletePerson(id, nm, {
                lang: cfg.lang || (localStorage.getItem('shezhire_lang') || 'kk'),
                csrf: cfg.csrf || '',
                onDone: cfg.onDone || function () { window.location.reload(); }
            });
        }

        function openGlobalPersonEditModal(id) {
            const modal = document.getElementById('globalPersonEditModal');
            if (!modal) return;
            const dangerBlock = document.getElementById('editModalDangerBlock');
            if (dangerBlock) { dangerBlock.style.display = 'none'; }
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
                        // TASK-028: авто-отчество — только подсказка, ручное значение не затирается
                        document.getElementById('editModalMiddleName').placeholder = (localStorage.getItem('shezhire_lang') === 'ru' ? p.middle_name_ru : p.middle_name_kk) || '';
                        document.getElementById('editModalMaidenName').value = p.maiden_name || '';
                        document.getElementById('editModalGender').value = p.gender || 'M';
                        syncEditModalMaiden();

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
                        // ALT-002: место упокоения (значения — до показа блока, чтобы карта сразу встала на точку)
                        window.__pcBurialReady = !!data.burial_ready;
                        document.getElementById('editModalDeathPlace').value = p.death_place || '';
                        document.getElementById('editModalBurialSettlement').value = p.burial_settlement || '';
                        document.getElementById('editModalBurialCemetery').value = p.burial_cemetery || '';
                        document.getElementById('editModalBurialAddress').value = p.burial_address || '';
                        document.getElementById('editModalBurialPlot').value = p.burial_plot || '';
                        document.getElementById('editModalBurialLat').value = p.burial_lat || '';
                        document.getElementById('editModalBurialLng').value = p.burial_lng || '';
                        document.getElementById('editModalIsDeceased').checked = isDec;
                        toggleEditModalDeceasedFields(isDec);
                        var exTr = document.getElementById('editModalExtra'); if (exTr) { exTr.open = isDec; } // TASK-033: у ушедших блок раскрыт
                        document.getElementById('editModalDeathDate').value = p.death_date || '';
                        document.getElementById('editModalDeathYear').value = p.death_year || '';
                        if (typeof pcfLoad === 'function') { pcfLoad(p.id, data.contacts || null); } // TASK-022
                        if (typeof pmeLoad === 'function') { pmeLoad(p.id, data.media_edit || null); } // TASK-022: фото и обложка
                        if (typeof relLoad === 'function') { relLoad(p.id); } // ALT-067: блок «Туыстар»
                        // TASK-022: сам человек (не editor) правит только фото, обложку и контакты; основные поля — только чтение
                        window.__pcFullEdit = !!data.can_edit;
                        document.querySelectorAll('#globalPersonEditForm [id^="editModal"]').forEach(function (el) {
                            if (el.id !== 'editModalPersonId' && el.id !== 'editModalClanHint') { el.disabled = !data.can_edit; }
                        });
                        document.querySelectorAll('#editModalResBlock input, #editModalResBlock button').forEach(function (el) { el.disabled = !data.can_edit; });
                        // ALT-061: блок удаления — только тем, кому удаление разрешено (ALT-018)
                        const dgr = document.getElementById('editModalDangerBlock');
                        const dgrBtn = document.getElementById('editModalDeleteBtn');
                        if (dgrBtn) { dgrBtn.disabled = false; }
                        if (dgr) { dgr.style.display = data.can_delete ? 'block' : 'none'; }
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
            formData.append('death_place', isDec ? document.getElementById('editModalDeathPlace').value.trim() : '');
            if (window.__pcBurialReady) {
                formData.append('burial_settlement', document.getElementById('editModalBurialSettlement').value.trim());
                formData.append('burial_cemetery', document.getElementById('editModalBurialCemetery').value.trim());
                formData.append('burial_address', document.getElementById('editModalBurialAddress').value.trim());
                formData.append('burial_plot', document.getElementById('editModalBurialPlot').value.trim());
                formData.append('burial_lat', document.getElementById('editModalBurialLat').value);
                formData.append('burial_lng', document.getElementById('editModalBurialLng').value);
            }

            // ALT-071: кнопка «Сақталуда… / Сохраняется…», повторное нажатие заблокировано
            const saveBtn = e.target && e.target.querySelector ? e.target.querySelector('button[type="submit"]') : null;
            const saveBtnText = saveBtn ? saveBtn.textContent : '';
            if (saveBtn) {
                if (saveBtn.disabled) return;
                saveBtn.disabled = true;
                saveBtn.textContent = 'Сақталуда… / Сохраняется…';
            }
            let reloading = false;
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
                    // ALT-071: обложка и контакты уходят параллельно
                    const [, ct] = await Promise.all([
                        (typeof pmeSave === 'function') ? pmeSave() : Promise.resolve(true), // положение/затемнение обложки
                        (typeof pcfSave === 'function') ? pcfSave() : Promise.resolve({ ok: true, offerInvite: false })
                    ]);
                    if (!ct.ok) { window.pcfDirty = true; return; } // остаёмся в окне, ошибка уже показана
                    if (ct.offerInvite) {
                        // email сохранён — показываем кнопку «Шақыру жіберу», окно не закрываем
                        window.pcfDirty = true;
                        showAppToast('Сақталды. Енді шақыру жіберуге болады / Сохранено. Теперь можно отправить приглашение', 'success');
                        return;
                    }
                    window.pcfDirty = false;
                    closeGlobalPersonEditModal();
                    // ALT-071: уведомление «Сақталды» покажется на обновлённой странице
                    try { sessionStorage.setItem('altSavedToast', '1'); } catch (e2) {}
                    reloading = true;
                    window.location.reload();
                } else {
                    showAppToast('Қате: ' + (data.error || 'Сақтау мүмкін болмады'), 'error');
                }
            } catch (err) {
                showAppToast('Сұраныс қатесі: ' + err.message, 'error');
            } finally {
                if (saveBtn && !reloading) { saveBtn.disabled = false; saveBtn.textContent = saveBtnText; }
            }
        }
    </script>
</body>
</html>
