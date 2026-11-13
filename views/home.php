<?php
declare(strict_types=1);

use App\Core\I18n;

$lang = I18n::lang();
$title = I18n::t('nav_home');
$activeNav = 'home';

$userName = $user['name'] ?? 'Туысқан';
$userEmail = $user['email'] ?? '';
$userRole = $user['role'] ?? 'viewer';
$birthdays = $birthdays ?? [];
$anniversaries = $anniversaries ?? [];

require __DIR__ . '/layout/master_start.php';
?>

<!-- Приветствие (Hero) -->
<div style="background: linear-gradient(135deg, #1b263b, #0d1b2a); color: #ffffff; border-radius: 16px; padding: 32px 28px; margin-bottom: 24px; box-shadow: 0 10px 25px rgba(13, 27, 42, 0.2); border-bottom: 3px solid #d4af37;">
    <h2 style="font-size: 24px; font-weight: 800; margin-bottom: 8px;">
        <?= htmlspecialchars(I18n::t('welcome_heading')) ?>, <?= htmlspecialchars($userName) ?>!
    </h2>
    <p style="font-size: 15px; color: #cbd5e1; max-width: 740px; line-height: 1.6; margin-bottom: 12px;">
        <?= $lang === 'ru' 
            ? 'Добро пожаловать в закрытую систему родословной. Здесь бережно сохраняется преемственность поколений, история семьи, памятные даты и воспоминания.' 
            : 'Әулет шежіресінің жабық отбасылық порталына қош келдіңіз. Мұнда ата-бабалар тарихы, ұрпақтар сабақтастығы, естеліктер мен маңызды күндер сақталады.' ?>
    </p>
    <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.1); padding: 6px 14px; border-radius: 20px; font-size: 12.5px; border: 1px solid rgba(255,255,255,0.15);">
        <span style="color: #d4af37;">★</span>
        <span><?= htmlspecialchars($youAreText) ?></span>
    </div>
</div>

<!-- Жылдам сілтемелер (Быстрые ссылки) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <!-- 1. Адамдар тізімі (270 тұлға) -->
    <a href="/persons" style="display: flex; align-items: center; gap: 14px; background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; color: #0f172a; text-decoration: none; box-shadow: 0 2px 6px rgba(0,0,0,0.03); transition: transform 0.2s ease, border-color 0.2s ease;" onmouseover="this.style.borderColor='#2563eb'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.transform='translateY(0)';">
        <span style="font-size: 28px; width: 44px; height: 44px; border-radius: 10px; background: #eff6ff; display: flex; align-items: center; justify-content: center;">👥</span>
        <div>
            <div style="font-weight: 700; font-size: 15px; color: #1e3a8a;"><?= htmlspecialchars(I18n::t('nav_persons')) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                <?= $lang === 'ru' ? '270 человек, поиск, карточки' : '270 тұлға, іздеу, парақшалар' ?> ➔
            </div>
        </div>
    </a>

    <!-- 2. Шежіре ағашы (Өңдеу / Орман) -->
    <a href="/tree" style="display: flex; align-items: center; gap: 14px; background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; color: #0f172a; text-decoration: none; box-shadow: 0 2px 6px rgba(0,0,0,0.03); transition: transform 0.2s ease, border-color 0.2s ease;" onmouseover="this.style.borderColor='#d4af37'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.transform='translateY(0)';">
        <span style="font-size: 28px; width: 44px; height: 44px; border-radius: 10px; background: #fefce8; display: flex; align-items: center; justify-content: center;">🌳</span>
        <div>
            <div style="font-weight: 700; font-size: 15px; color: #854d0e;"><?= htmlspecialchars(I18n::t('nav_tree')) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                <?= $lang === 'ru' ? 'Виды: Өңдеу, Шежіре-Орман' : 'Өңдеу және Шежіре-Орман түрлері' ?> ➔
            </div>
        </div>
    </a>

    <!-- 3. Отбасылар мен некелер -->
    <a href="/families" style="display: flex; align-items: center; gap: 14px; background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; color: #0f172a; text-decoration: none; box-shadow: 0 2px 6px rgba(0,0,0,0.03); transition: transform 0.2s ease, border-color 0.2s ease;" onmouseover="this.style.borderColor='#ec4899'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.transform='translateY(0)';">
        <span style="font-size: 28px; width: 44px; height: 44px; border-radius: 10px; background: #fdf2f8; display: flex; align-items: center; justify-content: center;">👨‍👩‍👧‍👦</span>
        <div>
            <div style="font-weight: 700; font-size: 15px; color: #9d174d;"><?= htmlspecialchars(I18n::t('nav_families')) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                <?= $lang === 'ru' ? '71 семья, союзы и дети' : '71 шаңырақ, некелер және балалар' ?> ➔
            </div>
        </div>
    </a>

    <!-- 4. Есте қаларлық күндер -->
    <a href="/dates" style="display: flex; align-items: center; gap: 14px; background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; color: #0f172a; text-decoration: none; box-shadow: 0 2px 6px rgba(0,0,0,0.03); transition: transform 0.2s ease, border-color 0.2s ease;" onmouseover="this.style.borderColor='#10b981'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.transform='translateY(0)';">
        <span style="font-size: 28px; width: 44px; height: 44px; border-radius: 10px; background: #ecfdf5; display: flex; align-items: center; justify-content: center;">📅</span>
        <div>
            <div style="font-weight: 700; font-size: 15px; color: #065f46;"><?= htmlspecialchars(I18n::t('nav_dates')) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                <?= $lang === 'ru' ? 'Туған күндер, годовщины, Жеті ата' : 'Туған күндер, ас беру, Жеті ата' ?> ➔
            </div>
        </div>
    </a>
</div>

<!-- Ближайшие дни рождения и памятные даты -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start; margin-bottom: 24px;">
    
    <!-- 1. Дни рождения -->
    <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 22px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 12px;">
            <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <span>🎂</span>
                <span><?= htmlspecialchars(I18n::t('upcoming_birthdays')) ?></span>
            </h3>
            <a href="/dates" style="font-size: 12px; color: #2563eb; text-decoration: none; font-weight: 600;">
                <?= $lang === 'ru' ? 'Все даты ➔' : 'Барлығы ➔' ?>
            </a>
        </div>

        <?php if (empty($birthdays)): ?>
            <div style="padding: 24px; text-align: center; color: #94a3b8; font-size: 13px;">
                <?= htmlspecialchars(I18n::t('no_upcoming_birthdays')) ?>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach (array_slice($birthdays, 0, 5) as $b): 
                    $isToday = ($b['days_until'] === 0);
                    $pName = trim(($b['person']['last_name'] ?? '') . ' ' . $b['person']['first_name']);
                ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; border-radius: 8px; background: <?= $isToday ? '#fefce8' : '#f8fafc' ?>; border: 1px solid <?= $isToday ? '#fde047' : '#e2e8f0' ?>;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: #1e3a8a; color: #fff; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <?= mb_substr($b['person']['first_name'] ?? 'Ш', 0, 1) ?>
                            </div>
                            <div>
                                <a href="/person/<?= (int)$b['person']['id'] ?>" style="font-weight: 700; font-size: 13px; color: #1e3a8a; text-decoration: none;">
                                    <?= htmlspecialchars($pName) ?>
                                </a>
                                <div style="font-size: 11px; color: #64748b;">
                                    <?= htmlspecialchars((string)($b['formatted_date_kk'] ?? $b['birth_date'])) ?>
                                    <?php if (!empty($b['age_turning'])): ?>
                                        • <?= (int)$b['age_turning'] ?> <?= $lang === 'ru' ? 'лет' : 'жас' ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div>
                            <?php if ($isToday): ?>
                                <span style="background: #ef4444; color: #fff; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 800;">
                                    🎉 <?= $lang === 'ru' ? 'Сегодня' : 'Бүгін' ?>
                                </span>
                            <?php else: ?>
                                <span style="background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 700;">
                                    <?= $lang === 'ru' ? "Через {$b['days_until']} дн." : "{$b['days_until']} күннен соң" ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 2. Памятные даты предков -->
    <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 22px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 12px;">
            <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <span>✨</span>
                <span><?= htmlspecialchars(I18n::t('memorial_anniversaries')) ?></span>
            </h3>
            <a href="/dates" style="font-size: 12px; color: #2563eb; text-decoration: none; font-weight: 600;">
                <?= $lang === 'ru' ? 'Все даты ➔' : 'Барлығы ➔' ?>
            </a>
        </div>

        <?php if (empty($anniversaries)): ?>
            <div style="padding: 24px; text-align: center; color: #94a3b8; font-size: 13px;">
                <?= htmlspecialchars(I18n::t('no_memorial_dates')) ?>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach (array_slice($anniversaries, 0, 5) as $a): 
                    $pName = trim(($a['person']['last_name'] ?? '') . ' ' . $a['person']['first_name']);
                ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: #334155; color: #d4af37; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid #d4af37;">
                                <?= mb_substr($a['person']['first_name'] ?? 'Ш', 0, 1) ?>
                            </div>
                            <div>
                                <a href="/person/<?= (int)$a['person']['id'] ?>" style="font-weight: 700; font-size: 13px; color: #0f172a; text-decoration: none;">
                                    <?= htmlspecialchars($pName) ?> ✨
                                </a>
                                <div style="font-size: 11px; color: #64748b;">
                                    <?= htmlspecialchars((string)($a['formatted_date_kk'] ?? $a['death_date'])) ?>
                                    <?php if (!empty($a['years_since'])): ?>
                                        • <?= (int)$a['years_since'] ?> <?= $lang === 'ru' ? 'лет со дня ухода' : 'жыл өтті' ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div>
                            <span style="background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 700;">
                                <?= $lang === 'ru' ? "Через {$a['days_until']} дн." : "{$a['days_until']} күннен соң" ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require __DIR__ . '/layout/master_end.php';
?>
