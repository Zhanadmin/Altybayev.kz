<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\I18n;

/**
 * TASK-014: «Құпиялылық саясаты / Политика конфиденциальности» и «Шарттар / Условия».
 * Публичные страницы (без входа) — их проверяет Google при публикации OAuth-приложения.
 *
 * @var string $page privacy|terms
 */
$lang = I18n::lang();
$ru = (($lang === 'ru' || $lang === 'en' || $lang === 'es'));
$siteTitle = I18n::siteTitle();
$isPrivacy = (($page ?? 'privacy') === 'privacy');
$path = $isPrivacy ? '/privacy' : '/terms';
$contactEmail = 'zhandos.alt06@gmail.com';
$updated = '15.11.2026';

$heading = $isPrivacy
    ? ($lang === 'en' ? 'Privacy Policy' : ($ru ? 'Политика конфиденциальности' : 'Құпиялылық саясаты'))
    : ($lang === 'en' ? 'Terms of Use' : ($ru ? 'Условия использования' : 'Пайдалану шарттары'));
$pageTitle = "{$siteTitle} — {$heading}";
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= $e($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $e($pageTitle) ?></title>
    <script>
        (function () {
            try {
                var t = localStorage.getItem('shezhire_theme');
                if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500;1,600&family=PT+Sans:wght@400;700&display=swap&subset=cyrillic,cyrillic-ext">
    <link rel="stylesheet" href="/assets/css/design-system.css?v=15">
    <link rel="stylesheet" href="/assets/css/book.css?v=6">
    <style>
        /* TASK-014: лист «книги» шире карточки входа, текст — слева */
        .au-card.lg-card { max-width: 760px; text-align: left; }
        .lg-card h1 { text-align: center; }
        .lg-card .au-sub { text-align: center; }
        .lg-card h2 { font-family: var(--font-book, serif); font-size: 22px; font-weight: 600; margin: var(--sp-5, 20px) 0 var(--sp-2, 8px); }
        .lg-card p, .lg-card li { line-height: 1.6; }
        .lg-card ul { padding-left: 1.3em; margin: var(--sp-2, 8px) 0; }
        .lg-card .lg-en { margin-top: var(--sp-6, 24px); padding-top: var(--sp-4, 16px); border-top: 1px solid var(--c-border, rgba(0,0,0,.12)); font-size: 14px; }
        .lg-links { display: flex; flex-wrap: wrap; gap: 6px 14px; justify-content: center; margin-top: var(--sp-5, 20px); }
        .lg-links a { text-decoration: underline; text-underline-offset: 3px; }
    </style>
</head>
<body class="au-body">
    <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
        <symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></symbol>
        <symbol id="i-moon" viewBox="0 0 24 24"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></symbol>
        <symbol id="i-tamga" viewBox="0 0 24 24"><path d="M12 3v18"/><path d="M6 7c0 3 2.7 5 6 5s6-2 6-5"/><path d="M8 21h8"/></symbol>
    </svg>

    <div class="au-top">
        <div class="seg" role="group" aria-label="<?= $ru ? 'Язык' : 'Тіл' ?>">
            <a href="/lang/kk?ref=<?= $e($path) ?>" class="<?= $lang === 'kk' ? 'active' : '' ?>">ҚАЗ</a>
            <a href="/lang/ru?ref=<?= $e($path) ?>" class="<?= $lang === 'ru' ? 'active' : '' ?>">РУС</a>
            <a href="/lang/en?ref=<?= $e($path) ?>" class="<?= $lang === 'en' ? 'active' : '' ?>">ENG</a>
            <a href="/lang/es?ref=<?= $e($path) ?>" class="<?= $lang === 'es' ? 'active' : '' ?>">ESP</a>
        </div>
        <button type="button" class="btn btn-ghost btn-icon theme-toggle" onclick="toggleAuthTheme()"
                aria-label="<?= $ru ? 'Светлая / тёмная тема' : 'Жарық / қараңғы тақырып' ?>">
            <svg class="ic ic-sun"><use href="#i-sun"/></svg><svg class="ic ic-moon"><use href="#i-moon"/></svg>
        </button>
    </div>

    <main class="island au-card lg-card">
        <div class="au-mark" aria-hidden="true"><svg class="ic"><use href="#i-tamga"/></svg></div>
        <h1><?= $e($heading) ?></h1>
        <hr class="oyu-rule">
        <p class="au-sub"><?= $e($siteTitle) ?> · altybayev.kz · <?= $ru ? 'обновлено' : 'жаңартылған' ?> <?= $e($updated) ?></p>

        <?php if ($isPrivacy): ?>
            <?php if ($lang === 'en'): ?>
                <h2>What this site is</h2>
                <p>altybayev.kz is a private family website with the genealogy (shezhire) of the Altybayev family. Only relatives invited by the administrator can sign in.</p>

                <h2>What data we receive when you sign in with Google</h2>
                <ul>
                    <li>your name;</li>
                    <li>your e-mail address;</li>
                    <li>your Google profile picture.</li>
                </ul>
                <p>Nothing else: we do not get access to your mail, contacts, files or Google password.</p>

                <h2>Why we need it</h2>
                <p>Only to recognise you when you sign in and grant the required rights (viewing or editing the family tree). Your name and photo are shown in the site header; the administrator sees your e-mail in the user list.</p>

                <h2>Who can see relatives' data</h2>
                <p>Information about family members (names, dates, photos, stories) is visible only to invited relatives after signing in. Search engines and outsiders cannot see it.</p>

                <h2>Data sharing</h2>
                <p>We do not share or sell your data and do not use it for advertising. The data is stored on the site's server.</p>

                <h2>How to delete your data</h2>
                <p>Write to <a href="mailto:<?= $e($contactEmail) ?>"><?= $e($contactEmail) ?></a> — we will delete your account and sign-in data. If you want your information removed from the family tree, please say so in the letter.</p>
            <?php elseif ($ru): ?>
                <h2>Что это за сайт</h2>
                <p>altybayev.kz — закрытый семейный сайт с родословной (шежіре) семьи Алтыбаевых. Войти могут только родственники, которых пригласил администратор.</p>

                <h2>Какие данные мы получаем при входе через Google</h2>
                <ul>
                    <li>имя;</li>
                    <li>адрес электронной почты (e-mail);</li>
                    <li>фото профиля Google.</li>
                </ul>
                <p>Больше ничего: мы не получаем доступ к вашей почте, контактам, файлам и паролю Google.</p>

                <h2>Зачем</h2>
                <p>Только чтобы вас узнать при входе и дать нужные права (просмотр или редактирование родословной). Имя и фото показываются в шапке сайта, e-mail видит администратор в списке пользователей.</p>

                <h2>Кто видит данные родственников</h2>
                <p>Сведения о членах семьи (имена, даты, фото, истории) видят только приглашённые родственники после входа. Поисковые системы и посторонние их не видят.</p>

                <h2>Передача данных</h2>
                <p>Мы никому не передаём и не продаём ваши данные и не используем их для рекламы. Данные хранятся на сервере сайта.</p>

                <h2>Как удалить свои данные</h2>
                <p>Напишите на <a href="mailto:<?= $e($contactEmail) ?>"><?= $e($contactEmail) ?></a> — удалим вашу учётную запись и данные входа. Если хотите убрать сведения о себе из родословной, укажите это в письме.</p>
            <?php else: ?>
                <h2>Бұл қандай сайт</h2>
                <p>altybayev.kz — Алтыбаевтар әулетінің шежіресі бар жабық отбасылық сайт. Оған тек әкімші шақырған туыстар кіре алады.</p>

                <h2>Google арқылы кіргенде қандай деректер алынады</h2>
                <ul>
                    <li>аты-жөніңіз;</li>
                    <li>электрондық пошта (e-mail) мекенжайыңыз;</li>
                    <li>Google профиліңіздің суреті.</li>
                </ul>
                <p>Басқа ештеңе алынбайды: поштаңызға, контактілеріңізге, файлдарыңызға және Google құпиясөзіңізге қол жеткізбейміз.</p>

                <h2>Не үшін</h2>
                <p>Тек кіргеніңізде сізді тану және тиісті құқық беру үшін (шежірені қарау немесе өңдеу). Аты мен суретіңіз сайттың жоғарғы жағында көрінеді, e-mail-ді пайдаланушылар тізімінде тек әкімші көреді.</p>

                <h2>Туыстардың деректерін кім көреді</h2>
                <p>Отбасы мүшелері туралы мәліметтерді (аттары, күндер, суреттер, естеліктер) тек шақырылған туыстар кіргеннен кейін көреді. Іздеу жүйелері мен бөгде адамдар оларды көрмейді.</p>

                <h2>Деректерді беру</h2>
                <p>Деректеріңізді ешкімге бермейміз, сатпаймыз және жарнамаға пайдаланбаймыз. Деректер сайттың серверінде сақталады.</p>

                <h2>Деректеріңізді қалай жоюға болады</h2>
                <p><a href="mailto:<?= $e($contactEmail) ?>"><?= $e($contactEmail) ?></a> мекенжайына хат жазыңыз — аккаунтыңыз бен кіру деректеріңізді жоямыз. Шежіреден өзіңіз туралы мәліметті алып тастағыңыз келсе, оны хатта көрсетіңіз.</p>
            <?php endif; ?>

            <?php if ($lang !== 'en' && $lang !== 'es'): ?>
            <div class="lg-en" lang="en">
                <strong>In English (summary).</strong>
                altybayev.kz is a private family genealogy website, available only to invited relatives.
                When you sign in with Google we receive only your name, e-mail address and profile picture,
                and use them solely to identify you and assign your access role. Family data is visible only to
                invited, signed-in relatives. We do not share, sell or use your data for advertising.
                To delete your data, write to <a href="mailto:<?= $e($contactEmail) ?>"><?= $e($contactEmail) ?></a>.
            </div>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($lang === 'en'): ?>
                <h2>In short</h2>
                <ul>
                    <li>altybayev.kz is a private family website. It can be used only by relatives invited by the administrator.</li>
                    <li>Sign-in is via your Google account. Do not give access to outsiders.</li>
                    <li>Information about relatives (names, dates, photos, stories) is the family's memory. Do not publish or forward it outside the family without those people's consent.</li>
                    <li>Editors add and correct only reliable information. All changes are saved in history and can be rolled back.</li>
                    <li>The administrator may change a role or revoke access.</li>
                    <li>The site is non-commercial, provided "as is", free of charge.</li>
                    <li>How your data is processed is described in the <a href="/privacy">Privacy Policy</a>. Questions: <a href="mailto:<?= $e($contactEmail) ?>"><?= $e($contactEmail) ?></a>.</li>
                </ul>
            <?php elseif ($ru): ?>
                <h2>Коротко</h2>
                <ul>
                    <li>altybayev.kz — закрытый семейный сайт. Пользоваться им могут только родственники, приглашённые администратором.</li>
                    <li>Вход — через ваш аккаунт Google. Не передавайте доступ посторонним.</li>
                    <li>Сведения о родственниках (имена, даты, фото, истории) — семейная память. Не публикуйте и не пересылайте их вне семьи без согласия этих людей.</li>
                    <li>Редакторы добавляют и исправляют только достоверные сведения. Все изменения сохраняются в истории, их можно отменить.</li>
                    <li>Администратор может изменить роль или отозвать доступ.</li>
                    <li>Сайт некоммерческий, предоставляется «как есть», без платы.</li>
                    <li>Как обрабатываются ваши данные — в <a href="/privacy">Политике конфиденциальности</a>. Вопросы: <a href="mailto:<?= $e($contactEmail) ?>"><?= $e($contactEmail) ?></a>.</li>
                </ul>
            <?php else: ?>
                <h2>Қысқаша</h2>
                <ul>
                    <li>altybayev.kz — жабық отбасылық сайт. Оны тек әкімші шақырған туыстар пайдалана алады.</li>
                    <li>Кіру — Google аккаунтыңыз арқылы. Кіру мүмкіндігін бөгде адамдарға бермеңіз.</li>
                    <li>Туыстар туралы мәліметтер (аттары, күндер, суреттер, естеліктер) — отбасының жады. Оларды сол адамдардың келісімінсіз отбасынан тыс жарияламаңыз және жібермеңіз.</li>
                    <li>Редакторлар тек нақты мәліметтерді қосады және түзетеді. Барлық өзгерістер тарихта сақталады, оларды қайтаруға болады.</li>
                    <li>Әкімші рөлді өзгертуі немесе кіру құқығын қайтарып алуы мүмкін.</li>
                    <li>Сайт коммерциялық емес, тегін, «бар күйінде» ұсынылады.</li>
                    <li>Деректеріңіз қалай өңделетіні — <a href="/privacy">Құпиялылық саясатында</a>. Сұрақтар: <a href="mailto:<?= $e($contactEmail) ?>"><?= $e($contactEmail) ?></a>.</li>
                </ul>
            <?php endif; ?>

            <?php if ($lang !== 'en' && $lang !== 'es'): ?>
            <div class="lg-en" lang="en">
                <strong>In English (summary).</strong>
                altybayev.kz is a private, non-commercial family website for invited relatives only, provided free of charge and "as is".
                Sign-in is via Google. Do not share access or publish relatives' information outside the family without their consent.
                The administrator may change roles or revoke access. See the <a href="/privacy">Privacy Policy</a>;
                contact: <a href="mailto:<?= $e($contactEmail) ?>"><?= $e($contactEmail) ?></a>.
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <nav class="lg-links" aria-label="<?= $ru ? 'Ссылки' : 'Сілтемелер' ?>">
            <a href="/"><?= Auth::check() ? ($ru ? 'На главную' : 'Басты бетке') : ($ru ? 'Войти' : 'Кіру') ?></a>
            <?php if ($isPrivacy): ?>
                <a href="/terms"><?= $ru ? 'Условия использования' : 'Пайдалану шарттары' ?></a>
            <?php else: ?>
                <a href="/privacy"><?= $ru ? 'Политика конфиденциальности' : 'Құпиялылық саясаты' ?></a>
            <?php endif; ?>
        </nav>

        <div class="au-foot">altybayev.kz &bull; 2026</div>
    </main>

    <script>
        function toggleAuthTheme() {
            const root = document.documentElement;
            const current = root.getAttribute('data-theme')
                || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            const next = current === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('shezhire_theme', next); } catch (e) {}
            document.cookie = `shezhire_theme=${next}; path=/; max-age=31536000; SameSite=Lax`;
        }
        (function () {
            const root = document.documentElement;
            if (!root.getAttribute('data-theme') && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                root.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</body>
</html>
