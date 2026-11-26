<?php
declare(strict_types=1);

/**
 * ALT-117 (редакция 2): единая карточка ру.
 *
 * Превью — не отдельный круглый медальон, а уменьшенная копия той же
 * квадратной эмблемы, которая открывается по клику и экспортируется в PNG/PDF.
 * Рендерер один: public/assets/js/clan-emblem.js (buildSvg/mount). PHP отдаёт
 * только данные рода; SVG строит JS, поэтому композиция превью и окна совпадает.
 *
 * Использование:
 *   <?= clan_tamga_card($clan, ['can_edit' => $canEdit]) ?>
 * Один раз на странице подключить стили:
 *   <style><?= clan_tamga_medallion_styles() ?></style>
 * и скрипт /assets/js/clan-emblem.js.
 */

if (!function_exists('clan_tamga_medallion_styles')) {
    function clan_tamga_medallion_styles(): string
    {
        return <<<'CSS'
/* ALT-117: галерея и карточка ру. Превью = уменьшенная копия эмблемы. */
.cl-gallery { display: flex; flex-direction: column; gap: 22px; }
.cl-zhuz { margin: 0; }
.cl-zhuz-head { display: flex; align-items: center; gap: 12px; margin: 0 0 12px; }
.cl-zhuz-head h2 { margin: 0; font-size: 13px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: var(--c-text-2); white-space: nowrap; }
.cl-zhuz-head::after { content: ""; flex: 1 1 auto; height: 1px; background: linear-gradient(90deg, var(--c-border, #ddd), transparent); }
.cl-zhuz-n { font-variant-numeric: tabular-nums; color: var(--c-text-3); letter-spacing: 0; }
.cl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(286px, 1fr)); gap: 18px; align-items: stretch; }
.cl-empty { padding: 40px 16px; text-align: center; color: var(--c-text-2); }

.ctm-card {
    --ctm-gold: #c9a86a;
    --ctm-gold-bright: #ecd5a1;
    --ctm-ink: #f4ead8;
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 14px 14px 16px;
    border-radius: 18px;
    border: 1px solid rgba(201, 168, 106, .58);
    color: var(--ctm-ink);
    background-color: #211e19;
    background-image:
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='2' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='140' height='140' filter='url(%23n)' opacity='.055'/%3E%3C/svg%3E"),
        radial-gradient(120% 80% at 18% 0%, rgba(236, 213, 161, .14), transparent 56%),
        radial-gradient(90% 70% at 88% 102%, rgba(130, 100, 50, .16), transparent 62%),
        linear-gradient(155deg, #2c2822 0%, #1b1916 54%, #24201b 100%);
    background-size: 140px 140px, auto, auto, auto;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .06), 0 16px 34px -22px rgba(0, 0, 0, .8);
    transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    overflow: hidden;
}
.ctm-card:hover { transform: translateY(-2px); border-color: rgba(236, 213, 161, .85); box-shadow: inset 0 1px 0 rgba(255, 255, 255, .07), 0 22px 40px -22px rgba(0, 0, 0, .85); }
.ctm-card::before { content: ""; position: absolute; inset: 7px; border: 1px solid rgba(201, 168, 106, .22); border-radius: 12px; pointer-events: none; z-index: 2; }
.ctm-corner { position: absolute; width: 13px; height: 13px; border: 0 solid rgba(236, 213, 161, .62); pointer-events: none; z-index: 3; }
.ctm-corner.tl { top: 12px; left: 12px; border-top-width: 1px; border-left-width: 1px; }
.ctm-corner.tr { top: 12px; right: 12px; border-top-width: 1px; border-right-width: 1px; }
.ctm-corner.bl { bottom: 12px; left: 12px; border-bottom-width: 1px; border-left-width: 1px; }
.ctm-corner.br { bottom: 12px; right: 12px; border-bottom-width: 1px; border-right-width: 1px; }

.ctm-emblem-wrap {
    position: relative;
    z-index: 1;
    display: block;
    width: 100%;
    aspect-ratio: 1 / 1;
    border-radius: 14px;
    overflow: hidden;
    background: #0d0a06;
    box-shadow: 0 12px 24px -16px rgba(0, 0, 0, .9);
    cursor: pointer;
}
.ctm-emblem-wrap:focus-visible { outline: 2px solid var(--ctm-gold-bright); outline-offset: 2px; }
.ctm-emblem { position: absolute; inset: 0; display: block; }
.ctm-emblem svg { display: block; width: 100%; height: 100%; }
.ctm-emblem.is-mounted .ctm-emblem-fallback { display: none; }
.ctm-emblem-fallback {
    position: absolute; inset: 0; display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 10px;
    color: #bfae90; font-size: 12px; letter-spacing: .04em; text-align: center;
    background:
        radial-gradient(60% 50% at 50% 42%, rgba(236, 213, 161, .12), transparent 70%),
        linear-gradient(155deg, #2b2721, #141210 60%, #201c17);
}
.ctm-emblem-fallback img { width: 38%; height: 38%; object-fit: contain; filter: invert(1) sepia(.3); opacity: .85; }

.ctm-name { margin: 0; }
.sr-only {
    position: absolute !important; width: 1px !important; height: 1px !important;
    padding: 0 !important; margin: -1px !important; overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important; white-space: nowrap !important; border: 0 !important;
}
.ctm-card > .cl-uran { position: absolute !important; width: 1px !important; height: 1px !important; overflow: hidden !important; clip: rect(0, 0, 0, 0) !important; white-space: nowrap !important; }

.ctm-meta { position: relative; z-index: 1; display: flex; flex-wrap: wrap; justify-content: center; gap: 5px 6px; }
.ctm-chip { display: inline-flex; align-items: center; gap: 4px; max-width: 100%; padding: 3px 9px; border-radius: 999px; border: 1px solid rgba(201, 168, 106, .24); background: rgba(0, 0, 0, .18); color: #c6b696; font-size: 11.5px; line-height: 1.3; overflow-wrap: anywhere; }
.ctm-chip b { font-weight: 600; color: #ddcba7; }

.ctm-summary { position: relative; z-index: 1; display: block; margin: 0; font-size: 13px; line-height: 1.5; color: #b6ab98; overflow-wrap: anywhere; }
.ctm-more { position: relative; z-index: 1; font-size: 12.5px; color: #bfa877; }
.ctm-more summary { cursor: pointer; width: fit-content; margin: 0 auto; padding: 2px 4px; color: #cdb27c; }
.ctm-more summary:hover { color: #ecd5a1; }
.ctm-more p { margin: 6px 0 0; font-size: 12.5px; line-height: 1.5; color: #b6ab98; overflow-wrap: anywhere; }

.ctm-actions { position: relative; z-index: 1; display: flex; gap: 8px; margin-top: auto; padding-top: 2px; }
.ctm-actions .btn { flex: 1 1 0; height: 36px; padding: 0 10px; font-family: inherit; font-size: 13.5px; border-color: rgba(201, 168, 106, .32); background: rgba(255, 255, 255, .035); color: #e7d9bd; }
.ctm-actions .btn:hover { background: rgba(201, 168, 106, .14); border-color: rgba(236, 213, 161, .6); }
.ctm-actions .btn[data-act=edit] { background: var(--ctm-gold); border-color: transparent; color: #221d15; font-weight: 600; }
.ctm-actions .btn[data-act=edit]:hover { background: var(--ctm-gold-bright); }
.ctm-actions .btn[data-act=delete]:hover { border-color: #a75d4e; color: #efb8aa; }

.ctm-no-sign { color: #9e8f77; font-size: 11.5px; letter-spacing: .04em; text-align: center; }

@media (max-width: 640px) {
    .cl-grid { grid-template-columns: 1fr; gap: 14px; }
    .ctm-card { padding: 12px 12px 14px; border-radius: 16px; }
    .ctm-name { font-size: 22px; }
}
@media (prefers-reduced-motion: reduce) {
    .ctm-card, .ctm-card:hover { transition: none; transform: none; }
}
CSS;
    }
}

if (!function_exists('clan_emblem_preview')) {
    /**
     * Контейнер превью: JS вставляет сюда тот же SVG, что и в модальное окно.
     * Данные рода передаются JSON-ом; ничего не выдумывается.
     *
     * @param array<string,mixed> $clan
     * @param array<string,mixed> $opts
     */
    function clan_emblem_preview(array $clan, array $opts = []): string
    {
        $e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $lang = (string)($opts['lang'] ?? \App\Core\I18n::lang());
        $ru = array_key_exists('ru', $opts) ? (bool)$opts['ru'] : in_array($lang, ['ru', 'en', 'es'], true);

        $name = trim((string)($clan['name'] ?? ''));
        $nameRu = trim((string)($clan['name_ru'] ?? ''));
        $displayName = $ru && $nameRu !== '' ? $nameRu : $name;
        $zhuz = (string)($clan['zhuz'] ?? 'other');
        $zhuz = in_array($zhuz, ['senior', 'middle', 'junior'], true) ? $zhuz : 'other';
        $zhuzLabels = [
            'senior' => ['kk' => 'Ұлы жүз', 'ru' => 'Старший жуз', 'en' => 'Senior Zhuz', 'es' => 'Zhuz Mayor'],
            'middle' => ['kk' => 'Орта жүз', 'ru' => 'Средний жуз', 'en' => 'Middle Zhuz', 'es' => 'Zhuz Medio'],
            'junior' => ['kk' => 'Кіші жүз', 'ru' => 'Младший жуз', 'en' => 'Junior Zhuz', 'es' => 'Zhuz Menor'],
            'other'  => ['kk' => 'Жүзден тыс', 'ru' => 'Вне жузов', 'en' => 'Outside the zhuzes', 'es' => 'Fuera de los zhuz'],
        ];
        $zhuzLabel = $zhuzLabels[$zhuz][$lang] ?? $zhuzLabels[$zhuz]['ru'];
        $uran = trim((string)($clan['uran'] ?? ''));
        $tamgaUrl = trim((string)($clan['tamga_url'] ?? ''));

        // ALT-117: не показываем «битую» картинку, если файла таңба нет на диске.
        if ($tamgaUrl !== '') {
            $path = (string)(parse_url($tamgaUrl, PHP_URL_PATH) ?: '');
            if ($path !== '' && str_starts_with($path, '/uploads/tamga/')) {
                $baseDir = realpath(__DIR__ . '/../../public/uploads/tamga');
                $file = realpath(__DIR__ . '/../../public' . $path);
                if (!$baseDir || !$file || !str_starts_with($file, $baseDir . DIRECTORY_SEPARATOR)) {
                    $tamgaUrl = '';
                }
            }
        }

        $label = $opts['aria_label'] ?? ($ru ? ('Герб рода ' . ($displayName !== '' ? $displayName : $name)) : ('Ру елтаңбасы: ' . $name));
        $payload = json_encode([
            'name' => $displayName,
            'zhuz' => $zhuzLabel,
            'uran' => $uran,
            'tamgaUrl' => $tamgaUrl,
            'ariaLabel' => $label,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if (!is_string($payload)) {
            $payload = '{}';
        }

        $fallback = $tamgaUrl !== ''
            ? '<img src="' . $e($tamgaUrl) . '" alt="" loading="lazy">'
            : '<span>' . ($ru ? 'Таңба не загружена' : 'Таңба жүктелмеген') . '</span>';

        return '<div class="ctm-emblem" data-clan-emblem="' . $e($payload) . '"'
            . ' role="img" aria-label="' . $e($label) . '">'
            . '<div class="ctm-emblem-fallback">' . $fallback . '</div>'
            . '</div>';
    }
}

if (!function_exists('clan_tamga_medallion')) {
    /**
     * Обратная совместимость имени: теперь это не круглый SVG-медальон,
     * а контейнер того же квадратного рендерера.
     *
     * @param array<string,mixed> $clan
     * @param array<string,mixed> $opts
     */
    function clan_tamga_medallion(array $clan, array $opts = []): string
    {
        return clan_emblem_preview($clan, $opts);
    }
}

if (!function_exists('clan_tamga_card')) {
    /**
     * Карточка ру: квадратное превью эмблемы, факты и действия.
     * Название и ұран уже есть внутри эмблемы; для доступности и ALT-116
     * они продублированы скрытыми элементами.
     *
     * @param array<string,mixed> $clan
     * @param array<string,mixed> $opts can_edit, ru, lang
     */
    function clan_tamga_card(array $clan, array $opts = []): string
    {
        $e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

        $lang = (string)($opts['lang'] ?? \App\Core\I18n::lang());
        $ru = array_key_exists('ru', $opts) ? (bool)$opts['ru'] : in_array($lang, ['ru', 'en', 'es'], true);
        $canEdit = !empty($opts['can_edit']);

        $id = (int)($clan['id'] ?? 0);
        $name = (string)($clan['name'] ?? '');
        $nameRu = trim((string)($clan['name_ru'] ?? ''));
        $displayName = $ru && $nameRu !== '' ? $nameRu : $name;
        $zhuz = (string)($clan['zhuz'] ?? 'other');
        $zhuz = in_array($zhuz, ['senior', 'middle', 'junior'], true) ? $zhuz : 'other';
        $uran = trim((string)($clan['uran'] ?? ''));
        $summary = trim((string)($clan['summary'] ?? ''));
        $description = trim((string)($clan['description'] ?? ''));
        $parent = trim((string)($clan['parent_name'] ?? ''));
        $persons = (int)($clan['persons_count'] ?? 0);
        $tamgaUrl = trim((string)($clan['tamga_url'] ?? ''));

        if ($tamgaUrl !== '') {
            $path = (string)(parse_url($tamgaUrl, PHP_URL_PATH) ?: '');
            if ($path !== '' && str_starts_with($path, '/uploads/tamga/')) {
                $baseDir = realpath(__DIR__ . '/../../public/uploads/tamga');
                $file = realpath(__DIR__ . '/../../public' . $path);
                if (!$baseDir || !$file || !str_starts_with($file, $baseDir . DIRECTORY_SEPARATOR)) {
                    $tamgaUrl = '';
                }
            }
        }
        $clan['tamga_url'] = $tamgaUrl;

        $zhuzLabels = [
            'senior' => $ru ? 'Старший жуз' : 'Ұлы жүз',
            'middle' => $ru ? 'Средний жуз' : 'Орта жүз',
            'junior' => $ru ? 'Младший жуз' : 'Кіші жүз',
            'other'  => $ru ? 'Вне жузов' : 'Жүзден тыс',
        ];
        $zhuzLabel = $zhuzLabels[$zhuz];

        $search = mb_strtolower(trim($name . ' ' . $nameRu . ' ' . $uran . ' ' . $summary . ' ' . $description . ' ' . $parent));
        $json = json_encode([
            'id' => $id,
            'name' => $name,
            'name_ru' => $nameRu,
            'zhuz' => $zhuz,
            'uran' => $uran,
            'summary' => $summary,
            'description' => $description,
            'tamga_url' => $tamgaUrl,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

        $lead = $summary !== '' ? $summary : $description;
        $extra = ($description !== '' && $description !== $lead) ? $description : '';

        $out = '<article class="cl-card ctm-card" data-z="' . $e($zhuz) . '" data-id="' . $id . '"'
            . ' data-search="' . $e($search) . '" data-json="' . $e($json) . '">'
            . '<span class="ctm-corner tl" aria-hidden="true"></span><span class="ctm-corner tr" aria-hidden="true"></span>'
            . '<span class="ctm-corner bl" aria-hidden="true"></span><span class="ctm-corner br" aria-hidden="true"></span>';

        $out .= '<div class="ctm-emblem-wrap" data-clan-coat="' . $e($name) . '" tabindex="0" role="button"'
            . ' title="' . ($ru ? 'Показать герб' : 'Елтаңбаны көрсету') . '"'
            . ' aria-label="' . ($ru ? 'Показать герб рода ' : 'Ру елтаңбасын көрсету: ') . $e($displayName) . '">'
            . clan_emblem_preview($clan, ['aria_label' => ($ru ? 'Герб рода ' : 'Ру елтаңбасы: ') . $displayName, 'ru' => $ru, 'lang' => $lang])
            . '</div>';

        // ALT-116: класс fit cl-uran сохраняется для обратной совместимости тестов
        // и семантики «поле ұрана»; визуально данные уже внутри эмблемы.
        $out .= '<h3 class="ctm-name sr-only">' . $e($displayName) . '</h3>';
        $out .= '<div class="fit cl-uran">'
            . '<span class="ctm-uran-label">Ұран</span>';
        if ($uran !== '') {
            $out .= '<span class="ctm-uran-value">' . $e($uran) . '</span>';
        } else {
            $out .= '<span class="ctm-uran-value is-empty" title="' . ($ru ? 'Ұран не указан' : 'Ұран көрсетілмеген') . '">—</span>';
        }
        $out .= '</div>';

        $out .= '<div class="ctm-meta">'
            . '<span class="ctm-chip">' . $e($zhuzLabel) . '</span>';
        if ($parent !== '') {
            $out .= '<span class="ctm-chip" title="' . ($ru ? 'Бас ру / ветвь' : 'Бас ру') . '">→ <b>' . $e($parent) . '</b></span>';
        }
        $out .= '<span class="ctm-chip" title="' . ($ru ? 'Число людей этого рода' : 'Осы рудағы адамдар саны') . '">' . $persons . ' ' . ($ru ? 'чел.' : 'адам') . '</span>';
        $out .= '</div>';

        if ($lead !== '') {
            $out .= '<p class="ctm-summary" title="' . $e($lead) . '">' . $e($lead) . '</p>';
        }
        if ($extra !== '') {
            $out .= '<details class="ctm-more"><summary>' . ($ru ? 'История рода' : 'Ру тарихы') . '</summary>'
                . '<p>' . $e($extra) . '</p></details>';
        }

        if ($tamgaUrl === '') {
            $out .= '<div class="ctm-no-sign">' . ($ru ? 'Таңба не загружена' : 'Таңба жүктелмеген') . '</div>';
        }

        if ($canEdit) {
            $out .= '<div class="ctm-actions">'
                . '<button type="button" class="btn" data-act="edit">' . ($ru ? 'Изменить' : 'Өзгерту') . '</button>'
                . '<button type="button" class="btn" data-act="delete">' . ($ru ? 'Удалить' : 'Өшіру') . '</button>'
                . '</div>';
        }

        $out .= '</article>';
        return $out;
    }
}
