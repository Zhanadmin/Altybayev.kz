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

// TASK-027: контакты владельца из настроек админки; токен CSRF и метка времени показа формы (защита от ботов)
$ru = ($lang === 'ru' || $lang === 'en' || $lang === 'es');
$fbContacts = ['name' => '', 'email' => '', 'whatsapp' => ''];
try {
    $fbContacts = \App\Services\FeedbackService::publicContacts(\App\Core\Database::getConnection());
} catch (\Throwable $e) {
    // БД недоступна — страница работает без контактов
}
$fbCsrf = \App\Core\Session::csrfToken();
\App\Core\Session::set('fb_form_ts', time());
$fbOwner = $fbContacts['name'] !== '' ? $fbContacts['name'] : I18n::t('fb_owner_default');
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
        .wl-chip { padding: 7px 14px; border-radius: 999px; border: 1px solid transparent; background: var(--c-accent-soft); color: var(--c-accent-text); font: inherit; font-size: 14px; font-weight: 600; cursor: pointer; min-height: 36px; }
        .wl-chip:hover, .wl-chip:focus-visible { border-color: var(--c-accent); }
        .wl-card[data-feat] { cursor: pointer; transition: transform .15s, box-shadow .15s; }
        .wl-card[data-feat]:hover, .wl-card[data-feat]:focus-visible { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.12); }
        .wl-card.wl-flash { outline: 3px solid var(--c-accent); }
        .wl-more { display: inline-block; margin-top: 8px; font-size: 14px; font-weight: 700; color: var(--c-accent-text); }
        .wl-invite { display: flex; gap: 6px; align-items: center; justify-content: center; flex-wrap: wrap; margin: var(--sp-3) 0 0; font-size: 14px; color: var(--c-text-2); }
        .wl-invite .ic { width: 16px; height: 16px; }
        .wl-modal { position: fixed; inset: 0; z-index: 1000; display: none; align-items: center; justify-content: center; padding: 16px; background: rgba(0,0,0,.55); }
        .wl-modal.open { display: flex; }
        .wl-mbox { position: relative; width: 100%; max-width: 560px; max-height: 92vh; overflow: auto; background: var(--c-island, #fff); border-radius: var(--r-lg); padding: var(--sp-5); box-shadow: 0 12px 40px rgba(0,0,0,.35); }
        .wl-mx { position: absolute; top: 8px; right: 8px; width: 40px; height: 40px; border: 0; border-radius: 50%; background: transparent; color: inherit; font-size: 28px; line-height: 1; cursor: pointer; }
        .wl-mprev { background: var(--c-island-2); border: 1px solid var(--c-border); border-radius: var(--r-lg); padding: var(--sp-4); margin: var(--sp-4) 0; }
        .wl-mprev svg { width: 100%; height: auto; display: block; }
        .wl-mbox h3 { font-family: 'Cormorant Garamond', Georgia, serif; font-size: 28px; margin: 0 40px 0 0; }
        .wl-mbox p { line-height: 1.6; color: var(--c-text-2); margin: 0 0 var(--sp-3); }
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
        .wl-login-top { height: 32px; padding: 0 14px; font-size: 14px; font-weight: 700; white-space: nowrap; }
        .wl-cta { margin-top: var(--sp-5); }
        .wl-cta .btn { height: 48px; padding: 0 28px; font-size: 17px; font-weight: 700; }
        .wl-contact { max-width: 620px; margin: var(--sp-8) auto 0; padding: var(--sp-6) var(--sp-5); border-radius: var(--r-lg); }
        .wl-contact h2 { margin-top: 0; }
        .wl-links { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin: var(--sp-4) 0; }
        .wl-links .btn { min-height: 42px; }
        .wl-form { display: grid; gap: var(--sp-3); margin-top: var(--sp-4); }
        .wl-form .textarea { min-height: 110px; }
        .wl-hp { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
        .wl-fmsg { min-height: 1.4em; font-size: 14px; text-align: center; }
        .wl-fmsg.ok { color: var(--c-success); } .wl-fmsg.err { color: var(--c-danger, #b42318); }
        @media (max-width: 480px) { .wl-body { padding-top: 60px; } .wl-prev { height: 150px; } .wl-contact { padding: var(--sp-5) var(--sp-4); } }
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
        <a href="/auth/google" class="btn btn-primary wl-login-top">Кіру / Войти</a>
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
        <div class="wl-chips">
            <?php
            // TASK-027: чипы открывают окно соответствующей возможности
            $chipKeys = ['tree', 'tree', 'tree', 'families', 'birthdays', 'albums', 'grove', 'person'];
            $chipLabels = $ru
                ? ['Древо рода', 'Жеті ата', 'Үш жұрт', 'Семьи и дети', 'Дни рождения', 'Фотоальбомы', '3D-дубрава', 'Контакты родных']
                : ['Ру ағашы', 'Жеті ата', 'Үш жұрт', 'Отбасылар мен балалар', 'Туған күндер', 'Фотоальбомдар', '3D емен орманы', 'Туыстар байланысы'];
            foreach ($chipLabels as $i => $chip): ?>
                <button type="button" class="wl-chip" data-feat="<?= $chipKeys[$i] ?>"><?= htmlspecialchars($chip) ?></button>
            <?php endforeach; ?>
        </div>

        <div class="wl-cta">
            <a href="/auth/google" class="btn btn-primary">Кіру / Войти</a>
            <p class="wl-invite"><svg class="ic" aria-hidden="true"><use href="#i-lock"/></svg>
                <strong><?= htmlspecialchars(I18n::t('wl_invite')) ?></strong>
                <?= htmlspecialchars(I18n::t('wl_invite_google')) ?></p>
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

            <article class="island wl-card" role="button" tabindex="0" data-feat="tree">
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

            <article class="island wl-card" role="button" tabindex="0" data-feat="families">
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

            <article class="island wl-card" role="button" tabindex="0" data-feat="person">
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

            <article class="island wl-card" role="button" tabindex="0" data-feat="birthdays">
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

            <article class="island wl-card" role="button" tabindex="0" data-feat="albums">
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

            <article class="island wl-card" role="button" tabindex="0" data-feat="grove">
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

    <!-- TASK-027: обратная связь с владельцем (контакты — из настроек админки) -->
    <section class="island wl-contact" id="contact" aria-labelledby="wl-contact-h">
        <h2 id="wl-contact-h"><?= htmlspecialchars(I18n::t('fb_title')) ?></h2>
        <p class="wl-h2-sub" style="margin-bottom:0"><?= htmlspecialchars(I18n::t('fb_lead', ['owner' => $fbOwner])) ?></p>
        <div class="wl-links">
            <button type="button" class="btn btn-primary" id="fbOpen"><?= htmlspecialchars(I18n::t('fb_open')) ?></button>
        </div>
    </section>

    <!-- TASK-027: окно обратной связи (форма + WhatsApp + почта) -->
    <div class="wl-modal" id="fbModal" aria-hidden="true">
        <div class="wl-mbox" role="dialog" aria-modal="true" aria-labelledby="fbMTitle">
            <button type="button" class="wl-mx" id="fbMClose" aria-label="<?= htmlspecialchars(I18n::t('wl_close')) ?>">&times;</button>
            <h3 id="fbMTitle"><?= htmlspecialchars(I18n::t('fb_title')) ?></h3>
            <p><?= htmlspecialchars(I18n::t('fb_lead', ['owner' => $fbOwner])) ?></p>
        <?php
        $waDigits = preg_replace('/\D+/', '', $fbContacts['whatsapp']) ?? '';
        if ($waDigits !== '' || $fbContacts['email'] !== ''): ?>
        <div class="wl-links">
            <?php if ($waDigits !== ''): ?>
                <a class="btn" href="https://wa.me/<?= htmlspecialchars($waDigits) ?>" target="_blank" rel="noopener">WhatsApp</a>
            <?php endif; ?>
            <?php if ($fbContacts['email'] !== ''): ?>
                <a class="btn" href="mailto:<?= htmlspecialchars($fbContacts['email']) ?>"><?= htmlspecialchars(I18n::t('fb_mail_btn')) ?></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <form class="wl-form" id="fbForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($fbCsrf) ?>">
            <div class="wl-hp" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>
            <div class="field">
                <label for="fb-name"><?= htmlspecialchars(I18n::t('fb_name')) ?></label>
                <input class="input" id="fb-name" name="name" maxlength="120" required autocomplete="name">
            </div>
            <div class="field">
                <label for="fb-contact"><?= htmlspecialchars(I18n::t('fb_contact')) ?></label>
                <input class="input" id="fb-contact" name="contact" maxlength="190" required autocomplete="email">
            </div>
            <div class="field">
                <label for="fb-message"><?= htmlspecialchars(I18n::t('fb_message')) ?></label>
                <textarea class="textarea" id="fb-message" name="message" maxlength="2000" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary" id="fbSend"><?= htmlspecialchars(I18n::t('fb_send')) ?></button>
            <div class="wl-fmsg" id="fbMsg" role="status" aria-live="polite"></div>
        </form>
        </div>
    </div>

    <!-- TASK-027: единая точка входа — кнопка «Кіру / Войти» вверху и на первом экране; здесь только ссылки и подвал -->
    <section class="wl-enter" aria-label="<?= $ru ? 'Сведения' : 'Мәліметтер' ?>">
        <!-- TASK-014: ссылки для проверки Google OAuth -->
        <div class="au-legal">
            <a href="/privacy"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Политика конфиденциальности' : 'Құпиялылық саясаты' ?></a>
            <span aria-hidden="true">·</span>
            <a href="/terms"><?= ($lang === 'ru' || $lang === 'en' || $lang === 'es') ? 'Условия' : 'Шарттар' ?></a>
        </div>

        <div class="au-foot">altybayev.kz &bull; 2026</div>
    </section>
    </main>

    <!-- TASK-027: окно возможности (демо-превью, вымышленные данные) -->
    <div class="wl-modal" id="wlModal" aria-hidden="true">
        <div class="wl-mbox" role="dialog" aria-modal="true" aria-labelledby="wlMTitle">
            <button type="button" class="wl-mx" id="wlMClose" aria-label="<?= htmlspecialchars(I18n::t('wl_close')) ?>">&times;</button>
            <h3 id="wlMTitle"></h3>
            <div class="wl-mprev" id="wlMPrev"></div>
            <p id="wlMText"></p>
            <p id="wlMExtra"></p>
        </div>
    </div>

    <script>
        (function () {
            const EXTRA = {
                <?php foreach (['tree', 'families', 'person', 'birthdays', 'albums', 'grove'] as $fk): ?>
                <?= $fk ?>: <?= json_encode(I18n::t('wl_feat_' . $fk), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
                <?php endforeach; ?>
            };
            const modal = document.getElementById('wlModal');
            const closeBtn = document.getElementById('wlMClose');
            let lastFocus = null;
            function open(feat) {
                const card = document.querySelector('.wl-card[data-feat="' + feat + '"]');
                if (!card) return;
                document.getElementById('wlMTitle').textContent = card.querySelector('h3').textContent;
                document.getElementById('wlMText').textContent = card.querySelector('.wl-txt p').textContent;
                document.getElementById('wlMExtra').textContent = EXTRA[feat] || '';
                const prev = document.getElementById('wlMPrev');
                prev.innerHTML = '';
                const svg = card.querySelector('.wl-prev svg');
                if (svg) prev.appendChild(svg.cloneNode(true));
                lastFocus = document.activeElement;
                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                closeBtn.focus();
            }
            function close() {
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                if (lastFocus && lastFocus.focus) lastFocus.focus();
            }
            document.querySelectorAll('[data-feat]').forEach(function (el) {
                el.addEventListener('click', function () { open(el.getAttribute('data-feat')); });
                if (el.getAttribute('role') === 'button') {
                    el.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(el.getAttribute('data-feat')); }
                    });
                }
            });
            closeBtn.addEventListener('click', close);
            modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('open')) close(); });
        })();
        function toggleAuthTheme() {
            const root = document.documentElement;
            const current = root.getAttribute('data-theme')
                || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            const next = current === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('shezhire_theme', next); } catch (e) {}
            document.cookie = `shezhire_theme=${next}; path=/; max-age=31536000; SameSite=Lax`;
        }
        // TASK-027: отправка формы обратной связи
        (function () {
            const form = document.getElementById('fbForm');
            if (!form) return;
            const msg = document.getElementById('fbMsg');
            const btn = document.getElementById('fbSend');
            const T = <?= json_encode([
                'fill' => I18n::t('fb_fill_all'), 'ok' => I18n::t('fb_ok'),
                'fail' => I18n::t('fb_fail'), 'off' => I18n::t('fb_offline'),
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
            // окно формы
            const fm = document.getElementById('fbModal');
            const fOpen = document.getElementById('fbOpen');
            function fClose() { fm.classList.remove('open'); fm.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; fOpen.focus(); }
            fOpen.addEventListener('click', function () {
                fm.classList.add('open'); fm.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden';
                document.getElementById('fb-name').focus();
            });
            document.getElementById('fbMClose').addEventListener('click', fClose);
            fm.addEventListener('click', function (e) { if (e.target === fm) fClose(); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && fm.classList.contains('open')) fClose(); });
            form.addEventListener('submit', async function (ev) {
                ev.preventDefault();
                msg.className = 'wl-fmsg';
                msg.textContent = '';
                const fd = new FormData(form);
                if (!String(fd.get('name')).trim() || !String(fd.get('contact')).trim() || !String(fd.get('message')).trim()) {
                    msg.className = 'wl-fmsg err';
                    msg.textContent = T.fill;
                    return;
                }
                btn.disabled = true;
                try {
                    const res = await fetch('/api/feedback', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body: new URLSearchParams(fd)
                    });
                    let data = null;
                    try { data = await res.json(); } catch (e) {}
                    if (res.ok && data && data.success) {
                        form.reset();
                        msg.className = 'wl-fmsg ok';
                        msg.textContent = T.ok;
                    } else {
                        msg.className = 'wl-fmsg err';
                        msg.textContent = (data && data.error) || T.fail;
                    }
                } catch (e) {
                    msg.className = 'wl-fmsg err';
                    msg.textContent = T.off;
                }
                btn.disabled = false;
            });
        })();
        (function () {
            const root = document.documentElement;
            if (!root.getAttribute('data-theme') && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                root.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</body>
</html>
