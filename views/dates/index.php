<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
$title = I18n::t('nav_dates');
$activeNav = 'dates';

$birthdays = $birthdays ?? [];
$anniversaries = $anniversaries ?? [];
$weddings = $weddings ?? [];
$allEvents = $allEvents ?? [];
$daysAhead = (int)($daysAhead ?? 90);

require __DIR__ . '/../layout/master_start.php';
?>

<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
    <div>
        <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin-bottom: 6px;">
            <?= htmlspecialchars(I18n::t('nav_dates')) ?>
        </h1>
        <p style="font-size: 13.5px; color: #64748b; line-height: 1.5;">
            <?= $lang === 'ru'
                ? "Ближайшие дни рождения, поминальные годовщины и юбилеи свадеб на {$daysAhead} дней вперед"
                : "Алдағы {$daysAhead} күндегі туған күндер, ата-бабаларды еске алу және неке мерейтойлары" ?>
        </p>
    </div>

    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <!-- Выбор диапазона дней -->
        <div style="display: inline-flex; background: #e2e8f0; border-radius: 8px; padding: 2px;">
            <a href="/dates?days=30" style="padding: 6px 12px; font-size: 12px; font-weight: 700; text-decoration: none; border-radius: 6px; <?= $daysAhead === 30 ? 'background:#1e3a8a; color:#fff;' : 'color:#475569;' ?>">30 <?= $lang === 'ru' ? 'дн' : 'күн' ?></a>
            <a href="/dates?days=90" style="padding: 6px 12px; font-size: 12px; font-weight: 700; text-decoration: none; border-radius: 6px; <?= $daysAhead === 90 ? 'background:#1e3a8a; color:#fff;' : 'color:#475569;' ?>">90 <?= $lang === 'ru' ? 'дн' : 'күн' ?></a>
            <a href="/dates?days=180" style="padding: 6px 12px; font-size: 12px; font-weight: 700; text-decoration: none; border-radius: 6px; <?= $daysAhead === 180 ? 'background:#1e3a8a; color:#fff;' : 'color:#475569;' ?>">180 <?= $lang === 'ru' ? 'дн' : 'күн' ?></a>
            <a href="/dates?days=365" style="padding: 6px 12px; font-size: 12px; font-weight: 700; text-decoration: none; border-radius: 6px; <?= $daysAhead === 365 ? 'background:#1e3a8a; color:#fff;' : 'color:#475569;' ?>">1 <?= $lang === 'ru' ? 'год' : 'жыл' ?></a>
        </div>

        <!-- Быстрая кнопка проверки Жеті ата -->
        <button type="button" onclick="openZhetiAtaPageModal()" style="display: flex; align-items: center; gap: 8px; background: #1e3a8a; color: #ffffff; border: 1px solid #38bdf8; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; box-shadow: 0 2px 6px rgba(30,58,138,0.25);">
            <span>📜</span>
            <span><?= $lang === 'ru' ? 'Калькулятор «Жеті ата»' : '«Жеті ата» тексеру' ?></span>
        </button>
    </div>
</div>

<!-- Вкладки фильтрации дат -->
<div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 12px; flex-wrap: wrap;">
    <button type="button" class="date-tab-btn active" onclick="switchDateFilter('all', this)" style="padding: 8px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 700; cursor: pointer; border: 1.5px solid #1e3a8a; background: #1e3a8a; color: #ffffff; display: flex; align-items: center; gap: 8px;">
        <span>🌟</span>
        <span><?= $lang === 'ru' ? 'Все события' : 'Барлық күндер' ?></span>
        <span style="background: rgba(255,255,255,0.25); padding: 1px 7px; border-radius: 10px; font-size: 11px;"><?= count($allEvents) ?></span>
    </button>

    <button type="button" class="date-tab-btn" onclick="switchDateFilter('birthday', this)" style="padding: 8px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 700; cursor: pointer; border: 1.5px solid #e2e8f0; background: #ffffff; color: #334155; display: flex; align-items: center; gap: 8px;">
        <span>🎂</span>
        <span><?= $lang === 'ru' ? 'Дни рождения' : 'Туған күндер' ?></span>
        <span style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 1px 7px; border-radius: 10px; font-size: 11px;"><?= count($birthdays) ?></span>
    </button>

    <button type="button" class="date-tab-btn" onclick="switchDateFilter('death_anniversary', this)" style="padding: 8px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 700; cursor: pointer; border: 1.5px solid #e2e8f0; background: #ffffff; color: #334155; display: flex; align-items: center; gap: 8px;">
        <span>✨</span>
        <span><?= $lang === 'ru' ? 'Дни памяти' : 'Еске алу күндері' ?></span>
        <span style="background: #fef3c7; color: #92400e; border: 1px solid #fde68a; padding: 1px 7px; border-radius: 10px; font-size: 11px;"><?= count($anniversaries) ?></span>
    </button>

    <button type="button" class="date-tab-btn" onclick="switchDateFilter('wedding', this)" style="padding: 8px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 700; cursor: pointer; border: 1.5px solid #e2e8f0; background: #ffffff; color: #334155; display: flex; align-items: center; gap: 8px;">
        <span>💍</span>
        <span><?= $lang === 'ru' ? 'Годовщины свадеб' : 'Үйленген күндер' ?></span>
        <span style="background: #fdf2f8; color: #9d174d; border: 1px solid #fbcfe8; padding: 1px 7px; border-radius: 10px; font-size: 11px;"><?= count($weddings) ?></span>
    </button>
</div>

<?php if (empty($allEvents)): ?>
    <div style="background: #ffffff; border-radius: 14px; border: 1.5px solid #e2e8f0; padding: 48px 24px; text-align: center; color: #64748b;">
        <div style="font-size: 40px; margin-bottom: 12px;">📅</div>
        <div style="font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 6px;">
            <?= $lang === 'ru' ? "На ближайшие {$daysAhead} дней событий не найдено" : "Алдағы {$daysAhead} күнге белгіленген даталар жоқ" ?>
        </div>
        <div style="font-size: 13.5px;">
            <a href="/dates?days=365" style="color: #1e3a8a; font-weight: 700; text-decoration: underline;">
                <?= $lang === 'ru' ? 'Посмотреть события на целый год' : 'Бүкіл 1 жылдың оқиғаларын көру' ?>
            </a>
        </div>
    </div>
<?php else: ?>
    <!-- Сетка карточек дат -->
    <div id="eventsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px; align-items: stretch;">
        <?php foreach ($allEvents as $ev):
            $type = $ev['event_type'] ?? 'birthday';
            $isToday = !empty($ev['is_today']);
            $isTomorrow = !empty($ev['is_tomorrow']);
            $daysUntil = (int)($ev['days_until'] ?? 0);

            // Цветовые акценты по типу события
            $cardBorder = '#e2e8f0';
            $cardBg = '#ffffff';
            $tagBg = '#f1f5f9';
            $tagColor = '#334155';
            $tagBorder = '#cbd5e1';

            if ($isToday) {
                $cardBorder = '#fde047';
                $cardBg = '#fefce8';
            } elseif ($type === 'birthday') {
                $tagBg = '#ecfdf5';
                $tagColor = '#065f46';
                $tagBorder = '#a7f3d0';
            } elseif ($type === 'death_anniversary') {
                $tagBg = '#fef3c7';
                $tagColor = '#92400e';
                $tagBorder = '#fde68a';
            } elseif ($type === 'wedding') {
                $tagBg = '#fdf2f8';
                $tagColor = '#9d174d';
                $tagBorder = '#fbcfe8';
            }

            // Инициалы и аватарка
            $person = $ev['person'] ?? null;
            $avatarPath = $ev['avatar_path'] ?? ($person['avatar_path'] ?? null);
            $initials = $ev['initials'] ?? 'Ш';
            $gender = $ev['gender'] ?? ($person['gender'] ?? 'M');

            $avatarBg = ($gender === 'F') ? '#db2777' : '#1e3a8a';
            if ($type === 'death_anniversary') {
                $avatarBg = '#334155';
            } elseif ($type === 'wedding') {
                $avatarBg = '#be185d';
            }
        ?>
            <div class="event-card" data-type="<?= htmlspecialchars($type) ?>" style="background: <?= $cardBg ?>; border-radius: 14px; border: 1.5px solid <?= $cardBorder ?>; padding: 18px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.15s ease, box-shadow 0.15s ease;">
                <div>
                    <!-- Верхняя полоска: Тип события + Обратный отсчет -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                        <span style="background: <?= $tagBg ?>; color: <?= $tagColor ?>; border: 1px solid <?= $tagBorder ?>; font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 12px; display: inline-flex; align-items: center; gap: 5px;">
                            <span><?= $ev['event_icon'] ?? '📅' ?></span>
                            <span><?= htmlspecialchars($lang === 'ru' ? ($ev['event_name_ru'] ?? '') : ($ev['event_name_kk'] ?? '')) ?></span>
                        </span>

                        <?php if ($isToday): ?>
                            <span style="background: #ef4444; color: #ffffff; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 800; box-shadow: 0 2px 4px rgba(239,68,68,0.3); animation: pulse 2s infinite;">
                                🎉 <?= $lang === 'ru' ? 'СЕГОДНЯ!' : 'БҮГІН!' ?>
                            </span>
                        <?php elseif ($isTomorrow): ?>
                            <span style="background: #f97316; color: #ffffff; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700;">
                                ⏰ <?= $lang === 'ru' ? 'Завтра' : 'Ертең' ?>
                            </span>
                        <?php else: ?>
                            <span style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700;">
                                <?= $lang === 'ru' ? "Через {$daysUntil} дн." : "{$daysUntil} күннен соң" ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Основной блок с аватаром и именами -->
                    <div style="display: flex; gap: 14px; align-items: flex-start; margin-bottom: 12px;">
                        <?php if ($type === 'wedding'): ?>
                            <!-- Для свадьбы: сдвоенный аватар или кольца -->
                            <div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #1e3a8a, #be185d); color: #fff; font-size: 20px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 6px rgba(190,24,93,0.3); border: 2px solid #fff;">
                                💍
                            </div>
                        <?php elseif ($avatarPath): ?>
                            <img src="<?= htmlspecialchars($avatarPath) ?>" alt="<?= htmlspecialchars($ev['full_name'] ?? '') ?>" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 2px solid <?= $cardBorder ?>; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <?php else: ?>
                            <div style="width: 44px; height: 44px; border-radius: 50%; background: <?= $avatarBg ?>; color: #ffffff; font-size: 15px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: <?= $type === 'death_anniversary' ? '1.5px solid #d4af37' : 'none' ?>; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <?= htmlspecialchars($initials) ?>
                            </div>
                        <?php endif; ?>

                        <div style="flex: 1; min-width: 0;">
                            <?php if ($type === 'wedding'): ?>
                                <div style="font-size: 14.5px; font-weight: 800; color: #0f172a; line-height: 1.35; margin-bottom: 4px;">
                                    <?php if (!empty($ev['husband_id'])): ?>
                                        <a href="/person/<?= (int)$ev['husband_id'] ?>" style="color: #1e3a8a; text-decoration: none; border-bottom: 1px dotted #1e3a8a;">
                                            <?= htmlspecialchars(trim(($ev['husband']['first_name'] ?? '') . ' ' . ($ev['husband']['last_name'] ?? ''))) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($ev['husband']['first_name'] ?? '—') ?>
                                    <?php endif; ?>
                                    <span style="color: #be185d; font-weight: 900;"> & </span>
                                    <?php if (!empty($ev['wife_id'])): ?>
                                        <a href="/person/<?= (int)$ev['wife_id'] ?>" style="color: #be185d; text-decoration: none; border-bottom: 1px dotted #be185d;">
                                            <?= htmlspecialchars(trim(($ev['wife']['first_name'] ?? '') . ' ' . ($ev['wife']['last_name'] ?? ''))) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($ev['wife']['first_name'] ?? '—') ?>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div style="font-size: 15px; font-weight: 800; line-height: 1.3; margin-bottom: 3px;">
                                    <a href="/person/<?= (int)($ev['person_id'] ?? 0) ?>" style="color: <?= $type === 'death_anniversary' ? '#0f172a' : '#1e3a8a' ?>; text-decoration: none; border-bottom: 1px dotted currentColor;">
                                        <?= htmlspecialchars($ev['full_name'] ?? '') ?>
                                    </a>
                                    <?php if ($type === 'death_anniversary'): ?>
                                        <span title="<?= $lang === 'ru' ? 'Аруағы разы болсын' : 'Жатқан жері жайлы болсын' ?>" style="color: #d4af37; font-size: 13px;">✨</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Родительская принадлежность или девичий род / принадлежность к мужу -->
                            <?php 
                                $affText = ($lang === 'ru') ? ($ev['affiliation_ru'] ?? '') : ($ev['affiliation_kk'] ?? '');
                                if ($affText !== '' && $type !== 'wedding'): 
                            ?>
                                <div style="font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 4px; display: flex; align-items: center; gap: 4px;">
                                    <span style="opacity: 0.7;">🌿</span>
                                    <span><?= htmlspecialchars($affText) ?></span>
                                </div>
                            <?php endif; ?>

                            <!-- Возраст / Сколько лет прошло / Юбилей свадьбы -->
                            <div style="font-size: 13px; color: #1e293b; margin-top: 4px;">
                                <?php if ($type === 'birthday'): ?>
                                    <span style="color: #047857; font-weight: 700;">
                                        <?= (int)($ev['age_turning'] ?? 0) ?> <?= $lang === 'ru' ? 'лет исполняется' : 'жасқа толады' ?>
                                    </span>
                                <?php elseif ($type === 'death_anniversary'): ?>
                                    <span style="color: #92400e; font-weight: 700;">
                                        <?= htmlspecialchars($lang === 'ru' ? ($ev['memorial_label_ru'] ?? '') : ($ev['memorial_label_kk'] ?? '')) ?>
                                    </span>
                                <?php elseif ($type === 'wedding'): ?>
                                    <span style="color: #9d174d; font-weight: 700;">
                                        <?= htmlspecialchars($lang === 'ru' ? ($ev['jubilee_ru'] ?? '') : ($ev['jubilee_kk'] ?? '')) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Нижняя полоска: Дата и Родство пользователю -->
                <div style="border-top: 1px dashed #e2e8f0; padding-top: 10px; margin-top: 8px; display: flex; justify-content: space-between; align-items: center; font-size: 12px; gap: 8px; flex-wrap: wrap;">
                    <div style="color: #475569; font-weight: 600; display: flex; align-items: center; gap: 5px;">
                        <span>🗓️</span>
                        <span><?= htmlspecialchars((string)($lang === 'ru' ? ($ev['formatted_date_ru'] ?? $ev['event_date']) : ($ev['formatted_date_kk'] ?? $ev['event_date']))) ?></span>
                    </div>

                    <?php 
                        $kinshipText = ($lang === 'ru') ? ($ev['kinship_ru'] ?? '') : ($ev['kinship_kk'] ?? '');
                        if ($kinshipText !== ''):
                    ?>
                        <div style="background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 8px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                            <span>🤝</span>
                            <span><?= htmlspecialchars($kinshipText) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Модальное окно Жеті ата -->
<div id="zhetiAtaModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.65); z-index:999; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#ffffff; border-radius:14px; width:100%; max-width:680px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 40px rgba(0,0,0,0.3); padding:24px; border:2px solid #d4af37;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">
            <h3 style="font-size:18px; font-weight:700; color:#0f172a;">📜 <?= $lang === 'ru' ? 'Проверка традиции «Жеті ата»' : '«Жеті ата» тексеру дәстүрі' ?></h3>
            <button type="button" onclick="closeZhetiAtaPageModal()" style="background:none; border:none; font-size:22px; cursor:pointer; color:#64748b;">&times;</button>
        </div>
        
        <p style="font-size:13px; color:#64748b; margin-bottom:16px; line-height:1.5;">
            <?= $lang === 'ru' ? 'Выберите двух людей из шежіре, чтобы проверить наличие общего предка по мужской линии до 7 колен.' : 'Шежіредегі екі адамды таңдап, олардың 7 буын аралығында ортақ атасының бар-жоғын тексеріңіз.' ?>
        </p>

        <form onsubmit="handleZhetiAtaCalc(event)" style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:16px;">
            <div>
                <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">1-тұлға / 1-я персона (ID):</label>
                <input type="number" id="zhetiIdA" value="<?= (int)($userPersonId ?? 6) ?>" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
            </div>
            <div>
                <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">2-тұлға / 2-я персона (ID):</label>
                <input type="number" id="zhetiIdB" value="25" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
            </div>
            <div style="grid-column: 1 / -1;">
                <button type="submit" style="width:100%; padding:10px; background:#1b3b6f; color:#ffffff; border:none; border-radius:8px; font-weight:700; font-size:14px; cursor:pointer;">
                    <?= $lang === 'ru' ? 'Проверить 7 колен' : '7 буынды тексеру' ?>
                </button>
            </div>
        </form>

        <div id="zhetiResultBox" style="display:none; padding:16px; border-radius:10px; background:#f8fafc; border:1px solid #e2e8f0; font-size:13.5px; line-height:1.6;">
            <!-- Результат калькулятора -->
        </div>
    </div>
</div>

<script>
    function openZhetiAtaPageModal() {
        document.getElementById('zhetiAtaModal').style.display = 'flex';
    }

    function closeZhetiAtaPageModal() {
        document.getElementById('zhetiAtaModal').style.display = 'none';
    }

    function switchDateFilter(filterType, btn) {
        document.querySelectorAll('.date-tab-btn').forEach(b => {
            b.style.background = '#ffffff';
            b.style.color = '#334155';
            b.style.borderColor = '#e2e8f0';
        });
        btn.style.background = '#1e3a8a';
        btn.style.color = '#ffffff';
        btn.style.borderColor = '#1e3a8a';

        const cards = document.querySelectorAll('.event-card');
        cards.forEach(card => {
            const cType = card.getAttribute('data-type');
            if (filterType === 'all' || cType === filterType) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    async function handleZhetiAtaCalc(e) {
        e.preventDefault();
        const a = document.getElementById('zhetiIdA').value;
        const b = document.getElementById('zhetiIdB').value;
        const box = document.getElementById('zhetiResultBox');

        box.style.display = 'block';
        box.innerHTML = 'Есептелуде...';

        try {
            const res = await fetch(`/api/zheti-ata?a=${a}&b=${b}`);
            const data = await res.json();
            if (data && data.success) {
                const z = data.zheti_ata;
                const isWithin = z.is_within_7_generations;
                box.style.background = isWithin ? '#fef2f2' : '#ecfdf5';
                box.style.borderColor = isWithin ? '#fecaca' : '#a7f3d0';
                box.innerHTML = `
                    <div style="font-weight:800; font-size:15px; color:${isWithin ? '#b91c1c' : '#15803d'}; margin-bottom:8px;">
                        ${isWithin ? '⚠️ Жеті атаға тола қоймаған (Некеге тыйым салынады)' : '✅ Жеті атадан асқан (Құда болуға рұқсат)'}
                    </div>
                    <div style="color:#1e293b; margin-bottom:8px;">
                        ${z.description_kk || ''}
                    </div>
                    ${z.common_ancestor ? `<div style="font-size:12px; color:#475569;">Ортақ баба: <strong>${z.common_ancestor.first_name} ${z.common_ancestor.last_name || ''}</strong></div>` : ''}
                `;
            } else {
                box.innerHTML = '<span style="color:#dc2626;">Қате: ' + (data.error || 'Есептеу мүмкін болмады') + '</span>';
            }
        } catch (err) {
            box.innerHTML = '<span style="color:#dc2626;">Сұраныс қатесі: ' + err.message + '</span>';
        }
    }
</script>

<?php
require __DIR__ . '/../layout/master_end.php';
?>