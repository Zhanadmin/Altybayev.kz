<?php
declare(strict_types=1);

use App\Core\I18n;

/**
 * @var string|null $error
 * @var bool|null $isHome  TASK-014: страница показана гостю на главной «/»
 */
$lang = I18n::lang();
$langRef = !empty($isHome) ? '/' : '/login';
$siteTitle = I18n::siteTitle();
$pageTitle = (($lang === 'ru' || $lang === 'en' || $lang === 'es')) ? "{$siteTitle} — Вход в систему" : "{$siteTitle} — Жүйеге кіру";
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <script>
        /* TASK-008: тема — сохранённый выбор, иначе как в системе */
        (function () {
            try {
                var t = localStorage.getItem('shezhire_theme');
                if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- TASK-012: шрифты с казахскими буквами и слой «Шежіре-кітап» -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500;1,600&family=PT+Sans:wght@400;700&display=swap&subset=cyrillic,cyrillic-ext">
    <link rel="stylesheet" href="/assets/css/design-system.css?v=15">
    <link rel="stylesheet" href="/assets/css/book.css?v=6">
    <style>
        .au-legal { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-top: var(--sp-4, 16px); font-size: 14px; }
        .au-legal a { text-decoration: underline; text-underline-offset: 3px; }

        /* TASK-027: приветственная страница */
        .wl-body { display: block; padding: 64px var(--sp-4) var(--sp-8); }
        .wl { max-width: 980px; margin: 0 auto; }
        .wl-hero { text-align: center; padding: var(--sp-8) var(--sp-4) var(--sp-6); }
        .wl-hero .au-mark { margin-bottom: var(--sp-5); }
        .wl-hero h1 { font-family: 'Cormorant Garamond', Georgia, serif; font-size: clamp(34px, 7vw, 56px); line-height: 1.1; letter-spacing: -0.01em; }
        .wl-lead { max-width: 640px; margin: var(--sp-4) auto 0; font-size: 17px; line-height: 1.65; color: var(--c-text-2); }
        .wl-chips { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; max-width: 760px; margin: var(--sp-5) auto 0; }
        .wl-chip { padding: 5px 13px; border-radius: 999px; background: var(--c-accent-soft); color: var(--c-accent-text); font-size: 14px; font-weight: 600; }
        .wl-hero .alert { max-width: 460px; margin: var(--sp-5) auto 0; text-align: left; }
        .wl h2 { font-family: 'Cormorant Garamond', Georgia, serif; text-align: center; font-size: clamp(26px, 5vw, 36px); margin: var(--sp-8) 0 var(--sp-2); }
        .wl-h2-sub { text-align: center; color: var(--c-text-2); margin-bottom: var(--sp-6); }
        .wl-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--sp-4); }
        .wl-card { padding: 0; overflow: hidden; border-radius: var(--r-lg); display: flex; flex-direction: column; }
        .wl-prev { background: var(--c-island-2); border-bottom: 1px solid var(--c-border); padding: var(--sp-3); height: 172px; display: grid; place-items: center; }
        .wl-prev svg { width: 100%; height: 100%; display: block; }
        .wl-txt { padding: var(--sp-4) var(--sp-5) var(--sp-5); }
        .wl-txt h3 { font-family: 'Cormorant Garamond', Georgia, serif; font-size: 22px; margin-bottom: 6px; }
        .wl-txt p { font-size: 15px; line-height: 1.55; color: var(--c-text-2); margin: 0; }
        .wl-demo { text-align: center; font-size: 13px; color: var(--c-text-3); margin-top: var(--sp-3); }
        .wl-enter { margin: var(--sp-8) auto 0; }
        .wl-enter.au-card { max-width: 440px; }
        .wl-s-line { stroke: var(--c-border-strong); fill: none; stroke-width: 1.5; }
        .wl-s-box { fill: var(--c-island); stroke: var(--c-border-strong); stroke-width: 1.2; }
        .wl-s-m { fill: var(--c-male-soft); stroke: var(--c-male); stroke-width: 1.2; }
        .wl-s-f { fill: var(--c-female-soft); stroke: var(--c-female); stroke-width: 1.2; }
        .wl-s-acc { fill: var(--c-accent-soft); stroke: var(--c-accent); stroke-width: 1.2; }
        .wl-s-txt { fill: var(--c-text-3); }
        .wl-s-dark { fill: var(--c-text-2); }
        .wl-s-green { fill: var(--c-success-soft); stroke: var(--c-success); stroke-width: 1.2; }
        @media (max-width: 480px) { .wl-body { padding-top: 60px; } .wl-prev { height: 150px; } }
    </style>
</head>
<body class="au-body wl-body">
    <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
        <symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></symbol>
        <symbol id="i-moon" viewBox="0 0 24 24"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></symbol>
        <symbol id="i-tamga" viewBox="0 0 24 24"><path d="M12 3v18"/><path d="M6 7c0 3 2.7 5 6 5s6-2 6-5"/><path d="M8 21h8"/></symbol>
        <symbol id="i-alert" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.01"/></symbol>
        <symbol id="i-lock" viewBox="0 0 24 24"><rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/></symbol>
    </svg>

    <!-- Язык и тема -->
    <div class="au-top">
        <div class="seg" role="group" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Язык' : 'Тіл' ?>">
            <a href="/lang/kk?ref=<?= $langRef ?>" class="<?= $lang === 'kk' ? 'active' : '' ?>">ҚАЗ</a>
            <a href="/lang/ru?ref=<?= $langRef ?>" class="<?= $lang === 'ru' ? 'active' : '' ?>">РУС</a>
            <a href="/lang/en?ref=<?= $langRef ?>" class="<?= $lang === 'en' ? 'active' : '' ?>">ENG</a>
            <a href="/lang/es?ref=<?= $langRef ?>" class="<?= $lang === 'es' ? 'active' : '' ?>">ESP</a>
        </div>
        <button type="button" class="btn btn-ghost btn-icon theme-toggle" onclick="toggleAuthTheme()"
                aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Светлая / тёмная тема' : 'Жарық / қараңғы тақырып' ?>">
            <svg class="ic ic-sun"><use href="#i-sun"/></svg><svg class="ic ic-moon"><use href="#i-moon"/></svg>
        </button>
    </div>

    <main class="wl">
    <header class="wl-hero">
        <div class="au-mark" aria-hidden="true">
            <svg class="ic"><use href="#i-tamga"/></svg>
        </div>

        <h1><?= htmlspecialchars($siteTitle) ?></h1>
        <hr class="oyu-rule">
        <p class="wl-lead">
            <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es')
                ? 'Здесь живёт шежіре семьи Алтыбаевых: кто от кого пошёл, где чьи корни, как выросли наши семьи. Это тёплый семейный дом в интернете — чтобы дети знали своих предков, а родные всегда были рядом.'
                : 'Мұнда Алтыбаевтар әулетінің шежіресі сақталады: кім кімнен тарады, тамыр қайда, отбасылар қалай өсті. Бұл — интернеттегі жылы отбасылық үй: балалар ата-бабасын білсін, жақындар үнемі қасымызда болсын.' ?>
        </p>
        <div class="wl-chips" aria-hidden="true">
            <?php foreach (($lang === 'ru' || $lang === 'en' || $lang === 'es')
                ? ['Древо рода', 'Жеті ата', 'Үш жұрт', 'Семьи и дети', 'Дни рождения', 'Фотоальбомы', '3D-дубрава', 'Контакты родных']
                : ['Ру ағашы', 'Жеті ата', 'Үш жұрт', 'Отбасылар мен балалар', 'Туған күндер', 'Фотоальбомдар', '3D емен орманы', 'Туыстар байланысы'] as $chip): ?>
                <span class="wl-chip"><?= htmlspecialchars($chip) ?></span>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert error" role="alert">
                <svg class="ic" aria-hidden="true"><use href="#i-alert"/></svg>
                <div>
                    <?php if ($error === 'auth_failed'): ?>
                        <strong><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Ошибка входа:' : 'Кіру сәтсіз аяқталды:' ?></strong><br>
                        <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'При авторизации через Google произошла ошибка. Попробуйте снова.' : 'Google арқылы кіру кезінде қате орын алды. Қайталап көріңіз.' ?>
                    <?php elseif ($error === 'invalid_state'): ?>
                        <strong><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Ошибка сессии:' : 'Қауіпсіздік қатесі:' ?></strong><br>
                        <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Сессия устарела. Обновите страницу и попробуйте снова.' : 'Сессия мерзімі өтіп кетті. Бетті жаңартып, қайта кіріңіз.' ?>
                    <?php elseif ($error === 'forbidden'): ?>
                        <strong><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Доступ запрещен:' : 'Рұқсат жоқ:' ?></strong><br>
                        <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Недостаточно прав для доступа к этому разделу.' : 'Бұл бөлімге кіруге құқығыңыз жеткіліксіз.' ?>
                    <?php elseif ($error === 'admin_only'): ?>
                        <strong><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Только для администратора:' : 'Тек әкімшіге:' ?></strong><br>
                        <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Этот раздел доступен только администратору.' : 'Бұл бет тек әкімшіге арналған.' ?>
                    <?php else: ?>
                        <strong><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Ошибка:' : 'Қате:' ?></strong> <?= htmlspecialchars($error) ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </header>

    <section aria-labelledby="wl-what">
        <h2 id="wl-what"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Что здесь можно делать' : 'Мұнда не істеуге болады' ?></h2>
        <p class="wl-h2-sub"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Несколько картинок-образцов — на них вымышленные имена, не настоящие люди.' : 'Бірнеше үлгі сурет — мұнда ойдан шығарылған есімдер, нақты адамдар емес.' ?></p>
        <div class="wl-grid">

            <article class="island wl-card">
                <div class="wl-prev" role="img" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Схема родового древа' : 'Ру ағашының сызбасы' ?>">
                    <svg viewBox="0 0 300 150" aria-hidden="true">
                        <path class="wl-s-line" d="M150 36v16M150 52H70v14M150 52h80v14M70 94v14M70 108H40v10M70 108h30v10M230 94v14M230 108v10"/>
                        <rect class="wl-s-m" x="110" y="10" width="80" height="26" rx="8"/>
                        <rect class="wl-s-m" x="30" y="66" width="80" height="28" rx="8"/>
                        <rect class="wl-s-f" x="190" y="66" width="80" height="28" rx="8"/>
                        <rect class="wl-s-m" x="12" y="118" width="56" height="24" rx="7"/>
                        <rect class="wl-s-f" x="72" y="118" width="56" height="24" rx="7"/>
                        <rect class="wl-s-m" x="202" y="118" width="56" height="24" rx="7"/>
                        <rect class="wl-s-dark" x="126" y="20" width="48" height="6" rx="3"/>
                        <rect class="wl-s-dark" x="46" y="77" width="48" height="6" rx="3"/>
                        <rect class="wl-s-dark" x="206" y="77" width="48" height="6" rx="3"/>
                    </svg>
                </div>
                <div class="wl-txt">
                    <h3><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Древо рода' : 'Ру ағашы' ?></h3>
                    <p><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Весь род на одном экране: от предков до внуков. Листайте, приближайте, смотрите жеті ата и Үш жұрт.' : 'Бүкіл ру бір экранда: ата-бабадан немерелерге дейін. Жылжытыңыз, үлкейтіңіз, жеті ата мен Үш жұртты қараңыз.' ?></p>
                </div>
            </article>

            <article class="island wl-card">
                <div class="wl-prev" role="img" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Семья с раскрывающимся списком детей' : 'Балалар тізімі ашылатын отбасы' ?>">
                    <svg viewBox="0 0 300 150" aria-hidden="true">
                        <rect class="wl-s-box" x="20" y="10" width="260" height="54" rx="12"/>
                        <circle class="wl-s-m" cx="52" cy="37" r="14"/>
                        <circle class="wl-s-f" cx="88" cy="37" r="14"/>
                        <rect class="wl-s-dark" x="112" y="26" width="90" height="7" rx="3.5"/>
                        <rect class="wl-s-txt" x="112" y="41" width="60" height="6" rx="3"/>
                        <path class="wl-s-line" d="M246 32l8 8 8-8"/>
                        <circle class="wl-s-m" cx="52" cy="98" r="12"/>
                        <circle class="wl-s-f" cx="88" cy="98" r="12"/>
                        <circle class="wl-s-f" cx="124" cy="98" r="12"/>
                        <circle class="wl-s-m" cx="160" cy="98" r="12"/>
                        <rect class="wl-s-txt" x="32" y="120" width="60" height="6" rx="3"/>
                        <rect class="wl-s-txt" x="100" y="120" width="60" height="6" rx="3"/>
                    </svg>
                </div>
                <div class="wl-txt">
                    <h3><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Семьи и дети' : 'Отбасылар мен балалар' ?></h3>
                    <p><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Каждая семья — отдельная карточка. Нажмите — и раскроются дети, а за ними и их семьи.' : 'Әр отбасы — бөлек карточка. Басыңыз — балалары ашылады, олардың артынан өз отбасылары.' ?></p>
                </div>
            </article>

            <article class="island wl-card">
                <div class="wl-prev" role="img" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Карточка человека' : 'Адам карточкасы' ?>">
                    <svg viewBox="0 0 300 150" aria-hidden="true">
                        <rect class="wl-s-box" x="50" y="8" width="200" height="134" rx="14"/>
                        <circle class="wl-s-acc" cx="150" cy="44" r="24"/>
                        <path class="wl-s-txt" d="M138 54c2-14 22-14 24 0z"/>
                        <circle class="wl-s-txt" cx="150" cy="38" r="7"/>
                        <rect class="wl-s-dark" x="108" y="78" width="84" height="8" rx="4"/>
                        <rect class="wl-s-txt" x="118" y="93" width="64" height="6" rx="3"/>
                        <rect class="wl-s-acc" x="72" y="108" width="68" height="20" rx="10"/>
                        <rect class="wl-s-green" x="160" y="108" width="68" height="20" rx="10"/>
                    </svg>
                </div>
                <div class="wl-txt">
                    <h3><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Карточка человека' : 'Адам карточкасы' ?></h3>
                    <p><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Фото, даты, родители, супруги и дети, история жизни и, если человек разрешил, — контакты.' : 'Фото, күндер, ата-ана, жұбай мен балалар, өмір баяны және адам рұқсат етсе — байланыс деректері.' ?></p>
                </div>
            </article>

            <article class="island wl-card">
                <div class="wl-prev" role="img" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Календарь дней рождения' : 'Туған күндер күнтізбесі' ?>">
                    <svg viewBox="0 0 300 150" aria-hidden="true">
                        <rect class="wl-s-box" x="60" y="12" width="180" height="126" rx="14"/>
                        <rect class="wl-s-acc" x="60" y="12" width="180" height="30" rx="14"/>
                        <rect class="wl-s-dark" x="108" y="23" width="84" height="8" rx="4"/>
                        <g class="wl-s-txt">
                            <rect x="78" y="56" width="22" height="6" rx="3"/><rect x="112" y="56" width="22" height="6" rx="3"/><rect x="146" y="56" width="22" height="6" rx="3"/><rect x="180" y="56" width="22" height="6" rx="3"/>
                            <rect x="78" y="78" width="22" height="6" rx="3"/><rect x="146" y="78" width="22" height="6" rx="3"/><rect x="180" y="78" width="22" height="6" rx="3"/>
                            <rect x="78" y="100" width="22" height="6" rx="3"/><rect x="112" y="100" width="22" height="6" rx="3"/><rect x="180" y="100" width="22" height="6" rx="3"/>
                        </g>
                        <circle class="wl-s-f" cx="123" cy="81" r="11"/>
                        <circle class="wl-s-m" cx="157" cy="103" r="11"/>
                    </svg>
                </div>
                <div class="wl-txt">
                    <h3><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Дни рождения и памятные даты' : 'Туған күндер мен ескерту күндері' ?></h3>
                    <p><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Сайт напомнит, у кого скоро день рождения, и сохранит памятные даты семьи.' : 'Кімнің туған күні жақындағанын еске салып, отбасының ескерткіш күндерін сақтайды.' ?></p>
                </div>
            </article>

            <article class="island wl-card">
                <div class="wl-prev" role="img" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Фотоальбом' : 'Фотоальбом' ?>">
                    <svg viewBox="0 0 300 150" aria-hidden="true">
                        <rect class="wl-s-box" x="20" y="22" width="120" height="86" rx="10" transform="rotate(-5 80 65)"/>
                        <rect class="wl-s-acc" x="30" y="32" width="100" height="56" rx="6" transform="rotate(-5 80 65)"/>
                        <rect class="wl-s-box" x="160" y="18" width="120" height="86" rx="10" transform="rotate(4 220 61)"/>
                        <rect class="wl-s-green" x="170" y="28" width="100" height="56" rx="6" transform="rotate(4 220 61)"/>
                        <path class="wl-s-txt" d="M170 76l24-22 22 18 14-10 36 14z" transform="rotate(4 220 61)"/>
                        <circle class="wl-s-txt" cx="196" cy="42" r="6" transform="rotate(4 220 61)"/>
                        <rect class="wl-s-dark" x="70" y="122" width="160" height="8" rx="4"/>
                    </svg>
                </div>
                <div class="wl-txt">
                    <h3><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Фотоальбомы' : 'Фотоальбомдар' ?></h3>
                    <p><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Альбомы семей и семейных событий: тои, юбилеи, встречи. Старые снимки больше не потеряются.' : 'Отбасылар мен отбасылық шаралар альбомдары: той, мерейтой, кездесу. Ескі суреттер енді жоғалмайды.' ?></p>
                </div>
            </article>

            <article class="island wl-card">
                <div class="wl-prev" role="img" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? '3D-дубрава' : '3D емен орманы' ?>">
                    <svg viewBox="0 0 300 150" aria-hidden="true">
                        <rect x="0" y="112" width="300" height="38" fill="var(--c-success-soft)"/>
                        <g>
                            <rect class="wl-s-dark" x="146" y="82" width="8" height="40" rx="3"/>
                            <circle class="wl-s-green" cx="150" cy="62" r="34"/>
                            <rect class="wl-s-dark" x="62" y="98" width="6" height="26" rx="3"/>
                            <circle class="wl-s-green" cx="65" cy="84" r="22"/>
                            <rect class="wl-s-dark" x="232" y="98" width="6" height="26" rx="3"/>
                            <circle class="wl-s-green" cx="235" cy="84" r="22"/>
                            <circle class="wl-s-acc" cx="140" cy="56" r="4"/><circle class="wl-s-acc" cx="162" cy="68" r="4"/><circle class="wl-s-acc" cx="152" cy="46" r="4"/>
                        </g>
                    </svg>
                </div>
                <div class="wl-txt">
                    <h3><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? '3D-дубрава' : '3D емен орманы' ?></h3>
                    <p><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Род как роща: каждое дерево — семья. Можно пройтись между ними и увидеть, как всё выросло.' : 'Ру — орман іспетті: әр ағаш — бір отбасы. Арасында серуендеп, бәрінің қалай өскенін көруге болады.' ?></p>
                </div>
            </article>

        </div>
    </section>

    <section class="island au-card wl-enter" aria-label="<?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Вход' : 'Кіру' ?>">
        <h2 style="margin-top:0"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Войти в шежіре' : 'Шежіреге кіру' ?></h2>
        <p class="au-sub" style="margin-bottom:var(--sp-5)"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Вход — через ваш аккаунт Google.' : 'Кіру — Google аккаунтыңыз арқылы.' ?></p>

        <div class="au-actions">
            <a href="/auth/google" class="btn btn-google">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
                <span><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Войти через Google' : 'Google арқылы кіру' ?></span>
            </a>
        </div>

        <div class="alert au-note">
            <svg class="ic" aria-hidden="true"><use href="#i-lock"/></svg>
            <div>
                <strong><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Доступ только по приглашению.' : 'Қолжетімділік тек шақыру бойынша.' ?></strong><br>
                <?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Шежіре закрыто для посторонних: войти могут только приглашённые члены семьи.' : 'Шежіре бөтен адамдарға жабық: тек шақырылған отбасы мүшелері кіре алады.' ?>
            </div>
        </div>

        <!-- TASK-014: ссылки для проверки Google OAuth -->
        <div class="au-legal">
            <a href="/privacy"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Политика конфиденциальности' : 'Құпиялылық саясаты' ?></a>
            <span aria-hidden="true">·</span>
            <a href="/terms"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Условия' : 'Шарттар' ?></a>
        </div>

        <div class="au-foot">altybayev.kz &bull; 2026</div>
    </section>
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
