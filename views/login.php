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
$T = static fn(string $k): string => htmlspecialchars(I18n::t($k));
$pageTitle = $siteTitle . ' — ' . I18n::t('wl_page_title');
// TASK-027 п.7: музыка — кнопка ♪ показывается только если файл лежит на месте
$musicFile = dirname(__DIR__) . '/public/assets/audio/welcome.mp3';
$hasMusic = is_file($musicFile);

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
        /* TASK-027 п.6: мягкая анимация (только transform/opacity/stroke — лёгкая для телефона) */
        .wl-growtree { width: min(300px, 70%); height: auto; display: block; margin: 0 auto var(--sp-3); overflow: visible; }
        .wl-growtree .gt-b { fill: none; stroke: var(--c-border-strong); stroke-width: 3; stroke-linecap: round; stroke-dasharray: 1; stroke-dashoffset: 1; animation: wl-draw 1.6s ease-out forwards; }
        .wl-growtree .gt-b2 { animation-delay: .7s; } .wl-growtree .gt-b3 { animation-delay: 1.2s; }
        .wl-growtree .gt-n { fill: var(--c-accent); opacity: 0; transform-box: fill-box; transform-origin: center; animation: wl-pop .5s ease-out forwards; }
        .wl-growtree .gt-l { fill: var(--c-success); opacity: 0; transform-box: fill-box; transform-origin: 50% 100%; animation: wl-pop .5s ease-out forwards, wl-sway 5s ease-in-out infinite alternate; }
        @keyframes wl-draw { to { stroke-dashoffset: 0; } }
        @keyframes wl-pop { from { opacity: 0; transform: scale(.2); } to { opacity: 1; transform: scale(1); } }
        @keyframes wl-sway { from { transform: rotate(-4deg); } to { transform: rotate(4deg); } }
        .wl-rv { opacity: 0; transform: translateY(18px); transition: opacity .6s ease, transform .6s ease; }
        .wl-rv.in { opacity: 1; transform: none; }
        .wl-grove-tree { transform-box: fill-box; transform-origin: 50% 100%; animation: wl-sway 6s ease-in-out infinite alternate; }
        .wl-music { position: fixed; right: 14px; bottom: 14px; z-index: 900; width: 44px; height: 44px; border-radius: 50%; border: 1px solid var(--c-border-strong); background: var(--c-island, #fff); color: inherit; font-size: 20px; cursor: pointer; box-shadow: 0 4px 14px rgba(0,0,0,.2); }
        .wl-music[aria-pressed="true"] { background: var(--c-accent-soft); border-color: var(--c-accent); }
        .wl-music.off::after { content: ''; position: absolute; left: 10px; right: 10px; top: 50%; border-top: 2px solid currentColor; transform: rotate(-45deg); }
        @media (prefers-reduced-motion: reduce) {
            .wl-growtree .gt-b { animation: none; stroke-dashoffset: 0; }
            .wl-growtree .gt-n, .wl-growtree .gt-l { animation: none; opacity: 1; }
            .wl-rv { opacity: 1; transform: none; transition: none; }
            .wl-grove-tree { animation: none; }
        }
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
        <div class="seg" role="group" aria-label="<?= $T('wl_lang_label') ?>">
            <a href="/lang/kk?ref=<?= $langRef ?>" class="<?= $lang === 'kk' ? 'active' : '' ?>">ҚАЗ</a>
            <a href="/lang/ru?ref=<?= $langRef ?>" class="<?= $lang === 'ru' ? 'active' : '' ?>">РУС</a>
            <a href="/lang/en?ref=<?= $langRef ?>" class="<?= $lang === 'en' ? 'active' : '' ?>">ENG</a>
            <a href="/lang/es?ref=<?= $langRef ?>" class="<?= $lang === 'es' ? 'active' : '' ?>">ESP</a>
        </div>
        <a href="/auth/google" class="btn btn-primary wl-login-top"><?= $T('wl_login') ?></a>
        <button type="button" class="btn btn-ghost btn-icon theme-toggle" onclick="toggleAuthTheme()"
                aria-label="<?= $T('wl_theme_label') ?>">
            <svg class="ic ic-sun"><use href="#i-sun"/></svg><svg class="ic ic-moon"><use href="#i-moon"/></svg>
        </button>
    </div>

    <main class="wl">
    <header class="wl-hero">
        <svg class="wl-growtree" viewBox="0 0 300 150" aria-hidden="true">
            <path class="gt-b" pathLength="1" d="M150 148V90"/>
            <path class="gt-b gt-b2" pathLength="1" d="M150 90C150 70 90 70 70 40M150 90C150 70 210 70 230 40"/>
            <path class="gt-b gt-b3" pathLength="1" d="M70 40C70 28 50 24 40 14M70 40C70 28 90 24 100 14M230 40C230 28 210 24 200 14M230 40C230 28 250 24 260 14"/>
            <circle class="gt-n" style="animation-delay:.6s" cx="150" cy="90" r="7"/>
            <circle class="gt-n" style="animation-delay:1.3s" cx="70" cy="40" r="6"/>
            <circle class="gt-n" style="animation-delay:1.4s" cx="230" cy="40" r="6"/>
            <circle class="gt-l" style="animation-delay:2s,2s" cx="40" cy="14" r="8"/>
            <circle class="gt-l" style="animation-delay:2.1s,2.1s" cx="100" cy="14" r="8"/>
            <circle class="gt-l" style="animation-delay:2.2s,2.2s" cx="200" cy="14" r="8"/>
            <circle class="gt-l" style="animation-delay:2.3s,2.3s" cx="260" cy="14" r="8"/>
        </svg>

        <h1><?= htmlspecialchars($siteTitle) ?></h1>
        <hr class="oyu-rule">
        <p class="wl-lead"><?= $T('wl_lead') ?></p>
        <div class="wl-chips">
            <?php
            // TASK-027: чипы открывают окно соответствующей возможности
            $chipKeys = ['tree', 'tree', 'tree', 'families', 'birthdays', 'albums', 'grove', 'person'];
            foreach ($chipKeys as $i => $ck): ?>
                <button type="button" class="wl-chip" data-feat="<?= $ck ?>"><?= $T('wl_chip_' . ($i + 1)) ?></button>
            <?php endforeach; ?>
        </div>

        <div class="wl-cta">
            <a href="/auth/google" class="btn btn-primary"><?= $T('wl_login') ?></a>
            <p class="wl-invite"><svg class="ic" aria-hidden="true"><use href="#i-lock"/></svg>
                <strong><?= htmlspecialchars(I18n::t('wl_invite')) ?></strong>
                <?= htmlspecialchars(I18n::t('wl_invite_google')) ?></p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert error" role="alert">
                <svg class="ic" aria-hidden="true"><use href="#i-alert"/></svg>
                <div>
                    <?php if ($error === 'auth_failed'): ?>
                        <strong><?= $T('wl_err_auth_t') ?></strong><br><?= $T('wl_err_auth') ?>
                    <?php elseif ($error === 'invalid_state'): ?>
                        <strong><?= $T('wl_err_state_t') ?></strong><br><?= $T('wl_err_state') ?>
                    <?php elseif ($error === 'forbidden'): ?>
                        <strong><?= $T('wl_err_forbidden_t') ?></strong><br><?= $T('wl_err_forbidden') ?>
                    <?php elseif ($error === 'admin_only'): ?>
                        <strong><?= $T('wl_err_admin_t') ?></strong><br><?= $T('wl_err_admin') ?>
                    <?php else: ?>
                        <strong><?= $T('wl_err_other_t') ?></strong> <?= htmlspecialchars($error) ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </header>

    <section aria-labelledby="wl-what">
        <h2 id="wl-what"><?= $T('wl_what') ?></h2>
        <p class="wl-h2-sub"><?= $T('wl_what_sub') ?></p>
        <div class="wl-grid">

            <article class="island wl-card" role="button" tabindex="0" data-feat="tree">
                <div class="wl-prev" role="img" aria-label="<?= $T('wl_a_tree') ?>">
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
                    <h3><?= $T('wl_t_tree') ?></h3>
                    <p><?= $T('wl_d_tree') ?></p>
                </div>
            </article>

            <article class="island wl-card" role="button" tabindex="0" data-feat="families">
                <div class="wl-prev" role="img" aria-label="<?= $T('wl_a_families') ?>">
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
                    <h3><?= $T('wl_t_families') ?></h3>
                    <p><?= $T('wl_d_families') ?></p>
                </div>
            </article>

            <article class="island wl-card" role="button" tabindex="0" data-feat="person">
                <div class="wl-prev" role="img" aria-label="<?= $T('wl_a_person') ?>">
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
                    <h3><?= $T('wl_t_person') ?></h3>
                    <p><?= $T('wl_d_person') ?></p>
                </div>
            </article>

            <article class="island wl-card" role="button" tabindex="0" data-feat="birthdays">
                <div class="wl-prev" role="img" aria-label="<?= $T('wl_a_birthdays') ?>">
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
                    <h3><?= $T('wl_t_birthdays') ?></h3>
                    <p><?= $T('wl_d_birthdays') ?></p>
                </div>
            </article>

            <article class="island wl-card" role="button" tabindex="0" data-feat="albums">
                <div class="wl-prev" role="img" aria-label="<?= $T('wl_a_albums') ?>">
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
                    <h3><?= $T('wl_t_albums') ?></h3>
                    <p><?= $T('wl_d_albums') ?></p>
                </div>
            </article>

            <article class="island wl-card" role="button" tabindex="0" data-feat="grove">
                <div class="wl-prev" role="img" aria-label="<?= $T('wl_a_grove') ?>">
                    <svg viewBox="0 0 300 150" aria-hidden="true">
                        <rect x="0" y="112" width="300" height="38" fill="var(--c-success-soft)"/>
                        <g class="wl-grove-tree">
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
                    <h3><?= $T('wl_t_grove') ?></h3>
                    <p><?= $T('wl_d_grove') ?></p>
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
    <section class="wl-enter" aria-label="<?= $T('wl_info') ?>">
        <!-- TASK-014: ссылки для проверки Google OAuth -->
        <div class="au-legal">
            <a href="/privacy"><?= $T('wl_privacy') ?></a>
            <span aria-hidden="true">·</span>
            <a href="/terms"><?= $T('wl_terms') ?></a>
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

    <?php if ($hasMusic): ?>
    <?php /* ALT-014: кнопка ♪ и музыка — общий скрипт bgm.js (на всех страницах) */ ?>
    <script src="/assets/js/bgm.js?v=3" defer></script>
    <?php endif; ?>
    <script>
        /* TASK-027 п.6: плавное появление карточек при прокрутке */
        (function () {
            const els = document.querySelectorAll('.wl-card, .wl-contact');
            if (!('IntersectionObserver' in window) || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) return;
            const io = new IntersectionObserver(function (es) {
                es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
            }, { threshold: 0.12 });
            els.forEach(function (el, i) { el.classList.add('wl-rv'); el.style.transitionDelay = ((i % 3) * 80) + 'ms'; io.observe(el); });
        })();
        /* TASK-027 п.7: тихая фоновая музыка, по кнопке ♪; выбор хранится в localStorage */
        (function () {
            const b = document.getElementById('wlMusic');
            if (!b) return;
            const L = <?= json_encode(['on' => I18n::t('wl_music_on'), 'off' => I18n::t('wl_music_off')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
            let a = null, fade = null; const VOL = 0.18;
            function ui(on) { b.classList.toggle('off', !on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); b.setAttribute('aria-label', on ? L.off : L.on); }
            function ramp(to, done) {
                clearInterval(fade);
                fade = setInterval(function () {
                    const d = to - a.volume;
                    if (Math.abs(d) < 0.02) { a.volume = to; clearInterval(fade); if (done) done(); return; }
                    a.volume = Math.max(0, Math.min(1, a.volume + (d > 0 ? 0.02 : -0.02)));
                }, 100);
            }
            function play() {
                if (!a) { a = new Audio('/assets/audio/welcome.mp3'); a.loop = true; a.volume = 0; }
                a.play().then(function () { ui(true); ramp(VOL); }).catch(function () { ui(false); });
            }
            function stop() { ui(false); if (a) ramp(0, function () { a.pause(); }); }
            b.addEventListener('click', function () {
                const on = b.getAttribute('aria-pressed') !== 'true';
                try { localStorage.setItem('shezhire_music', on ? '1' : '0'); } catch (e) {}
                on ? play() : stop();
            });
            /* если пользователь ранее включал музыку — пробуем продолжить; при блокировке браузером остаётся выключенной до нажатия */
            try { if (localStorage.getItem('shezhire_music') === '1') play(); } catch (e) {}
        })();
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
