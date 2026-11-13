<?php
declare(strict_types=1);

use App\Core\I18n;

/**
 * @var string|null $email
 * @var string|null $reason
 */
$lang = I18n::lang();
$siteTitle = I18n::siteTitle();
$pageTitle = ($lang === 'ru') ? "{$siteTitle} — Доступ ограничен" : "{$siteTitle} — Қолжетімділік шектелген";
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #0d1b2a 0%, #1b263b 50%, #24344d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #333333;
            position: relative;
        }
        .lang-switch-top {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 3px;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .lang-btn {
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            color: #cbd5e1;
            border-radius: 6px;
            transition: all 0.15s ease;
            background: none;
            border: none;
            cursor: pointer;
        }
        .lang-btn.active {
            background-color: #ffffff;
            color: #0d1b2a;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
            max-width: 480px;
            width: 100%;
            padding: 40px 32px;
            text-align: center;
        }
        .icon-wrap {
            width: 72px;
            height: 72px;
            margin: 0 auto 20px;
            background-color: #fef3c7;
            color: #d97706;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            border: 2px solid #fde68a;
        }
        h1 {
            font-size: 22px;
            font-weight: 700;
            color: #0d1b2a;
            margin-bottom: 12px;
        }
        .email-badge {
            display: inline-block;
            background-color: #f3f4f6;
            color: #1f2937;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            word-break: break-all;
            border: 1px solid #e5e7eb;
        }
        .message-box {
            background-color: #f9fafb;
            border-radius: 10px;
            padding: 16px;
            font-size: 13px;
            line-height: 1.6;
            color: #4b5563;
            text-align: left;
            margin-bottom: 24px;
            border: 1px solid #e5e7eb;
        }
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 12px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            text-align: center;
        }
        .btn-primary {
            background-color: #1b3b6f;
            color: #ffffff;
            border: 1px solid #1b3b6f;
        }
        .btn-primary:hover {
            background-color: #0d1b2a;
            border-color: #0d1b2a;
        }
        .btn-secondary {
            background-color: #ffffff;
            color: #4b5563;
            border: 1px solid #d1d5db;
        }
        .btn-secondary:hover {
            background-color: #f3f4f6;
        }
        .footer {
            margin-top: 24px;
            font-size: 12px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <!-- Переключатель языка -->
    <div class="lang-switch-top">
        <a href="/lang/kk?ref=/access-denied" class="lang-btn <?= $lang === 'kk' ? 'active' : '' ?>">ҚАЗ</a>
        <a href="/lang/ru?ref=/access-denied" class="lang-btn <?= $lang === 'ru' ? 'active' : '' ?>">РУС</a>
    </div>

    <div class="card">
        <div class="icon-wrap" aria-hidden="true">
            <?php if (($reason ?? '') === 'account_blocked'): ?>
                ⛔
            <?php else: ?>
                ✉️
            <?php endif; ?>
        </div>

        <?php if (($reason ?? '') === 'account_blocked'): ?>
            <h1><?= $lang === 'ru' ? 'Аккаунт заблокирован' : 'Аккаунт бұғатталған' ?></h1>
        <?php else: ?>
            <h1><?= $lang === 'ru' ? 'Доступ только по приглашению' : 'Тек шақыру бойынша қолжетімді' ?></h1>
        <?php endif; ?>

        <?php if (!empty($email)): ?>
            <div class="email-badge"><?= htmlspecialchars((string)$email) ?></div>
        <?php endif; ?>

        <div class="message-box">
            <?php if (($reason ?? '') === 'account_blocked'): ?>
                <p>
                    <?= $lang === 'ru' 
                        ? 'Ваша учетная запись временно приостановлена. Для восстановления доступа обратитесь к администратору портала.' 
                        : 'Сіздің аккаунтыңыз жүйеде уақытша тоқтатылған. Шежірені көру немесе өңдеу құқығын қалпына келтіру үшін сайт әкімшісіне хабарласыңыз.' ?>
                </p>
            <?php else: ?>
                <p>
                    <?= $lang === 'ru' 
                        ? "Для данного email еще нет активного приглашения. Портал «{$siteTitle}» является закрытым семейным ресурсом. Если вы являетесь членом семьи, свяжитесь с администратором для получения приглашения." 
                        : "Бұл электрондық поштаға шақыру жіберілмеген. «{$siteTitle}» порталы жабық отбасылық дерекқор болып табылады. Егер сіз осы әулеттің мүшесі болсаңыз, әкімшіге хабарласып, шақыру алыңыз." ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="btn-group">
            <a href="/auth/google" class="btn btn-primary">
                <?= $lang === 'ru' ? 'Сменить аккаунт Google' : 'Басқа Google аккаунтымен кіру' ?>
            </a>
            <a href="/logout" class="btn btn-secondary">
                <?= $lang === 'ru' ? 'На главную входа' : 'Басты кіру бетіне оралу' ?>
            </a>
        </div>

        <div class="footer">
            altybayev.kz &bull; 2026
        </div>
    </div>
</body>
</html>
