<?php
declare(strict_types=1);

use App\Core\I18n;

/**
 * @var string|null $error
 */
$lang = I18n::lang();
$siteTitle = I18n::siteTitle();
$pageTitle = ($lang === 'ru') ? "{$siteTitle} — Вход в систему" : "{$siteTitle} — Жүйеге кіру";
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
            max-width: 440px;
            width: 100%;
            padding: 40px 32px;
            text-align: center;
        }
        .emblem {
            width: 72px;
            height: 72px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #1b3b6f, #0d1b2a);
            color: #d4af37;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            box-shadow: 0 4px 12px rgba(27, 59, 111, 0.3);
            border: 2px solid #d4af37;
        }
        h1 {
            font-size: 24px;
            font-weight: 700;
            color: #0d1b2a;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }
        .subtitle {
            font-size: 13.5px;
            color: #64748b;
            margin-bottom: 28px;
            line-height: 1.5;
        }
        .error-box {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 13px;
            text-align: left;
            margin-bottom: 22px;
            line-height: 1.4;
        }
        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            background-color: #ffffff;
            color: #1f2937;
            border: 1.5px solid #d1d5db;
            border-radius: 10px;
            padding: 13px 18px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
        }
        .btn-google:hover {
            background-color: #f9fafb;
            border-color: #9ca3af;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            transform: translateY(-1px);
        }
        .btn-google svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        .notice {
            margin-top: 24px;
            font-size: 12px;
            color: #475569;
            line-height: 1.5;
            background: #f8fafc;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }
        .footer {
            margin-top: 24px;
            font-size: 11.5px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <!-- Переключатель языка -->
    <div class="lang-switch-top">
        <a href="/lang/kk?ref=/login" class="lang-btn <?= $lang === 'kk' ? 'active' : '' ?>">ҚАЗ</a>
        <a href="/lang/ru?ref=/login" class="lang-btn <?= $lang === 'ru' ? 'active' : '' ?>">РУС</a>
    </div>

    <div class="card">
        <div class="emblem" aria-hidden="true">
            <span>Ш</span>
        </div>

        <h1><?= htmlspecialchars($siteTitle) ?></h1>
        <p class="subtitle">
            <?= $lang === 'ru' ? 'Семейный генеалогический портал' : 'Отбасылық шежіре порталы' ?>
        </p>

        <?php if (!empty($error)): ?>
            <div class="error-box">
                <?php if ($error === 'auth_failed'): ?>
                    <strong><?= $lang === 'ru' ? 'Ошибка входа:' : 'Кіру сәтсіз аяқталды:' ?></strong><br>
                    <?= $lang === 'ru' ? 'При авторизации через Google произошла ошибка. Попробуйте снова.' : 'Google арқылы кіру кезінде қате орын алды. Қайталап көріңіз.' ?>
                <?php elseif ($error === 'invalid_state'): ?>
                    <strong><?= $lang === 'ru' ? 'Ошибка сессии:' : 'Қауіпсіздік қатесі:' ?></strong><br>
                    <?= $lang === 'ru' ? 'Сессия устарела. Обновите страницу и попробуйте снова.' : 'Сессия мерзімі өтіп кетті. Бетті жаңартып, қайта кіріңіз.' ?>
                <?php elseif ($error === 'forbidden'): ?>
                    <strong><?= $lang === 'ru' ? 'Доступ запрещен:' : 'Рұқсат жоқ:' ?></strong><br>
                    <?= $lang === 'ru' ? 'Недостаточно прав для доступа к этому разделу.' : 'Бұл бөлімге кіруге құқығыңыз жеткіліксіз.' ?>
                <?php elseif ($error === 'admin_only'): ?>
                    <strong><?= $lang === 'ru' ? 'Только для администратора:' : 'Тек әкімшіге:' ?></strong><br>
                    <?= $lang === 'ru' ? 'Этот раздел доступен только администратору.' : 'Бұл бет тек әкімшіге арналған.' ?>
                <?php else: ?>
                    <strong><?= $lang === 'ru' ? 'Ошибка:' : 'Қате:' ?></strong> <?= htmlspecialchars($error) ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <a href="/auth/google" class="btn-google">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
            </svg>
            <span><?= $lang === 'ru' ? 'Войти через Google' : 'Google арқылы кіру' ?></span>
        </a>

        <div class="notice">
            🔒 <strong><?= $lang === 'ru' ? 'Закрытая родословная:' : 'Жабық отбасылық шежіре:' ?></strong><br>
            <?= $lang === 'ru' ? 'Доступ открыт только для приглашенных членов семьи.' : 'Қолжетімділік тек шақырылған отбасы мүшелеріне ашық.' ?>
        </div>

        <div class="footer">
            altybayev.kz &bull; 2026
        </div>
    </div>
</body>
</html>
