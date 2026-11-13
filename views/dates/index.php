<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
$title = I18n::t('nav_dates');
$activeNav = 'dates';

require __DIR__ . '/../layout/master_start.php';
?>

<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
    <div>
        <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin-bottom: 4px;">
            <?= htmlspecialchars(I18n::t('nav_dates')) ?>
        </h1>
        <p style="font-size: 13.5px; color: #64748b;">
            <?= $lang === 'ru' ? "Памятные дни, ближайшие дни рождения и поминальные годовщины на {$daysAhead} дней вперед" : "Алдағы {$daysAhead} күндегі туған күндер, ас беру және ата-бабаларды еске алу күндері" ?>
        </p>
    </div>

    <!-- Быстрая кнопка проверки Жеті ата -->
    <button type="button" onclick="openZhetiAtaPageModal()" style="display: flex; align-items: center; gap: 8px; background: #1e3a8a; color: #ffffff; border: 1px solid #38bdf8; padding: 9px 18px; border-radius: 8px; font-size: 13.5px; font-weight: 700; cursor: pointer; box-shadow: 0 2px 6px rgba(30,58,138,0.25);">
        <span>📜</span>
        <span><?= $lang === 'ru' ? 'Калькулятор «Жеті ата»' : '«Жеті ата» калькуляторы' ?></span>
    </button>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start;">
    
    <!-- Колонка 1: Туған күндер (Дни рождения живых) -->
    <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 22px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 12px;">
            <h2 style="font-size: 17px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <span>🎂</span>
                <span><?= htmlspecialchars(I18n::t('upcoming_birthdays')) ?></span>
            </h2>
            <span style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 12px;">
                <?= count($birthdays) ?>
            </span>
        </div>

        <?php if (empty($birthdays)): ?>
            <div style="padding: 24px; text-align: center; color: #94a3b8; font-size: 13px;">
                <?= htmlspecialchars(I18n::t('no_upcoming_birthdays')) ?>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($birthdays as $b): 
                    $isToday = ($b['days_until'] === 0);
                    $name = trim(($b['person']['last_name'] ?? '') . ' ' . $b['person']['first_name']);
                ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; border-radius: 10px; background: <?= $isToday ? '#fefce8' : '#f8fafc' ?>; border: 1.5px solid <?= $isToday ? '#fde047' : '#e2e8f0' ?>;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: #1e3a8a; color: #fff; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <?= mb_substr($b['person']['first_name'] ?? 'Ш', 0, 1) ?>
                            </div>
                            <div>
                                <a href="/person/<?= (int)$b['person']['id'] ?>" style="font-weight: 700; font-size: 13.5px; color: #1e3a8a; text-decoration: none;">
                                    <?= htmlspecialchars($name) ?>
                                </a>
                                <div style="font-size: 11.5px; color: #64748b;">
                                    <?= htmlspecialchars((string)($b['formatted_date_kk'] ?? $b['birth_date'])) ?>
                                    <?php if (!empty($b['age_turning'])): ?>
                                        • <strong><?= (int)$b['age_turning'] ?> <?= $lang === 'ru' ? 'лет' : 'жасқа толады' ?></strong>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div>
                            <?php if ($isToday): ?>
                                <span style="background: #ef4444; color: #ffffff; padding: 4px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 800;">
                                    🎉 <?= $lang === 'ru' ? 'СЕГОДНЯ!' : 'БҮГІН!' ?>
                                </span>
                            <?php else: ?>
                                <span style="background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700;">
                                    <?= $lang === 'ru' ? "Через {$b['days_until']} дн." : "{$b['days_until']} күннен соң" ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Колонка 2: Еске алу күндері (Поминальные годовщины ушедших) -->
    <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 22px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 12px;">
            <h2 style="font-size: 17px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <span>✨</span>
                <span><?= htmlspecialchars(I18n::t('memorial_anniversaries')) ?></span>
            </h2>
            <span style="background: #fef3c7; color: #92400e; border: 1px solid #fde68a; font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 12px;">
                <?= count($anniversaries) ?>
            </span>
        </div>

        <?php if (empty($anniversaries)): ?>
            <div style="padding: 24px; text-align: center; color: #94a3b8; font-size: 13px;">
                <?= htmlspecialchars(I18n::t('no_memorial_dates')) ?>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($anniversaries as $a): 
                    $isToday = ($a['days_until'] === 0);
                    $name = trim(($a['person']['last_name'] ?? '') . ' ' . $a['person']['first_name']);
                ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; border-radius: 10px; background: <?= $isToday ? '#fefce8' : '#f8fafc' ?>; border: 1.5px solid <?= $isToday ? '#fde047' : '#e2e8f0' ?>;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: #334155; color: #d4af37; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid #d4af37;">
                                <?= mb_substr($a['person']['first_name'] ?? 'Ш', 0, 1) ?>
                            </div>
                            <div>
                                <a href="/person/<?= (int)$a['person']['id'] ?>" style="font-weight: 700; font-size: 13.5px; color: #0f172a; text-decoration: none;">
                                    <?= htmlspecialchars($name) ?> ✨
                                </a>
                                <div style="font-size: 11.5px; color: #64748b;">
                                    <?= htmlspecialchars((string)($a['formatted_date_kk'] ?? $a['death_date'])) ?>
                                    <?php if (!empty($a['years_since'])): ?>
                                        • <strong><?= (int)$a['years_since'] ?> <?= $lang === 'ru' ? 'лет со дня ухода' : 'жыл өтті' ?></strong>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div>
                            <span style="background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700;">
                                <?= $lang === 'ru' ? "Через {$a['days_until']} дн." : "{$a['days_until']} күннен соң" ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

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
                <input type="number" id="zhetiIdA" value="<?= (int)$userPersonId ?>" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
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
