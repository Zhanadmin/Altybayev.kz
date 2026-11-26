<?php
declare(strict_types=1);

/**
 * ALT-117: единый компонент карточки ру с медальоном.
 *
 * Использование:
 *   <?= clan_tamga_card($clan, ['can_edit' => $canEdit]) ?>
 * Один раз на странице подключить стили:
 *   <style><?= clan_tamga_medallion_styles() ?></style>
 *
 * Данные берутся только из переданной записи clans; компонент ничего не
 * придумывает и не подставляет вместо пустых полей. Таңба выводится как
 * исходное изображение (SVG/PNG) с сохранением пропорций.
 */

if (!function_exists('clan_tamga_medallion_styles')) {
    function clan_tamga_medallion_styles(): string
    {
        return <<<'CSS'
/* ALT-117: медальон и карточки «Ру таңбалары» */
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
    --ctm-gold-deep: #826432;
    --ctm-ink: #f4ead8;
    --ctm-muted: #bdae95;
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 13px;
    padding: 22px 20px 18px;
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
.ctm-card::before { content: ""; position: absolute; inset: 7px; border: 1px solid rgba(201, 168, 106, .22); border-radius: 12px; pointer-events: none; }
.ctm-corner { position: absolute; width: 13px; height: 13px; border: 0 solid rgba(236, 213, 161, .62); pointer-events: none; }
.ctm-corner.tl { top: 12px; left: 12px; border-top-width: 1px; border-left-width: 1px; }
.ctm-corner.tr { top: 12px; right: 12px; border-top-width: 1px; border-right-width: 1px; }
.ctm-corner.bl { bottom: 12px; left: 12px; border-bottom-width: 1px; border-left-width: 1px; }
.ctm-corner.br { bottom: 12px; right: 12px; border-bottom-width: 1px; border-right-width: 1px; }

.ctm-head { position: relative; z-index: 1; text-align: center; min-height: 52px; display: flex; flex-direction: column; justify-content: flex-end; gap: 3px; }
.ctm-name { margin: 0; font-family: var(--font-sans, Georgia, serif); font-size: clamp(20px, 2.1vw, 25px); font-weight: 700; line-height: 1.16; letter-spacing: .015em; color: #f8efdd; text-wrap: balance; overflow-wrap: anywhere; }
.ctm-name-ru { font-size: 12px; line-height: 1.35; letter-spacing: .09em; text-transform: uppercase; color: #b7a17a; overflow-wrap: anywhere; }

.ctm-medal-wrap { position: relative; z-index: 1; display: grid; place-items: center; margin: 1px 0 0; }
.ctm-medal { display: block; width: min(100%, 282px); height: auto; aspect-ratio: 1 / 1; filter: drop-shadow(0 12px 18px rgba(0, 0, 0, .38)); }
.ctm-sign-img { mix-blend-mode: multiply; }
.ctm-sign-empty { fill: none; stroke: #9f8d6b; stroke-width: 1.2; stroke-dasharray: 3 4; opacity: .8; }
.ctm-sign-empty-mark { fill: #7d6c50; font-family: Georgia, serif; font-size: 34px; text-anchor: middle; dominant-baseline: middle; }

.ctm-card > .cl-uran { position: relative; z-index: 1; }
.ctm-uran {
    display: flex; flex-direction: column; align-items: center; gap: 1px;
    padding: 8px 14px 9px; border-radius: 10px; text-align: center;
    border: 1px solid rgba(201, 168, 106, .34);
    background: linear-gradient(180deg, rgba(236, 213, 161, .10), rgba(236, 213, 161, .025));
}
.ctm-uran-label { font-size: 9.5px; line-height: 1.2; letter-spacing: .22em; text-transform: uppercase; color: #ad9770; }
.ctm-uran-value { font-size: clamp(15px, 1.7vw, 18px); line-height: 1.3; font-weight: 600; color: #f0d8a6; overflow-wrap: anywhere; }
.ctm-uran-value.is-empty { color: #8f887b; font-weight: 400; }

.ctm-meta { position: relative; z-index: 1; display: flex; flex-wrap: wrap; justify-content: center; gap: 5px 6px; }
.ctm-chip { display: inline-flex; align-items: center; gap: 4px; max-width: 100%; padding: 3px 9px; border-radius: 999px; border: 1px solid rgba(201, 168, 106, .24); background: rgba(0, 0, 0, .18); color: #c6b696; font-size: 11.5px; line-height: 1.3; overflow-wrap: anywhere; }
.ctm-chip b { font-weight: 600; color: #ddcba7; }

.ctm-summary { position: relative; z-index: 1; display: block; margin: 0; font-size: 13px; line-height: 1.5; color: #b6ab98; overflow-wrap: anywhere; }
.ctm-more { position: relative; z-index: 1; font-size: 12.5px; color: #bfa877; }
.ctm-more summary { cursor: pointer; width: fit-content; margin: 0 auto; padding: 2px 4px; color: #cdb27c; }
.ctm-more summary:hover { color: #ecd5a1; }
.ctm-more p { margin: 6px 0 0; font-size: 12.5px; line-height: 1.5; color: #b6ab98; overflow-wrap: anywhere; }
.ctm-more .ctm-src { color: #9f9482; }

.ctm-actions { position: relative; z-index: 1; display: flex; gap: 8px; margin-top: auto; padding-top: 2px; }
.ctm-actions .btn { flex: 1 1 0; height: 36px; padding: 0 10px; font-family: inherit; font-size: 13.5px; border-color: rgba(201, 168, 106, .32); background: rgba(255, 255, 255, .035); color: #e7d9bd; }
.ctm-actions .btn:hover { background: rgba(201, 168, 106, .14); border-color: rgba(236, 213, 161, .6); }
.ctm-actions .btn[data-act=edit] { background: var(--ctm-gold); border-color: transparent; color: #221d15; font-weight: 600; }
.ctm-actions .btn[data-act=edit]:hover { background: var(--ctm-gold-bright); }
.ctm-actions .btn[data-act=delete]:hover { border-color: #a75d4e; color: #efb8aa; }

.ctm-no-sign { color: #9e8f77; font-size: 11.5px; letter-spacing: .04em; }

@media (max-width: 640px) {
    .cl-grid { grid-template-columns: 1fr; gap: 14px; }
    .ctm-card { padding: 20px 16px 16px; border-radius: 16px; }
    .ctm-medal { width: min(100%, 258px); }
    .ctm-name { font-size: 22px; }
}
@media (prefers-reduced-motion: reduce) {
    .ctm-card, .ctm-card:hover { transition: none; transform: none; }
}
CSS;
    }
}

if (!function_exists('clan_tamga_medallion')) {
    /**
     * Круглый медальон: исходная таңба в центре, двойная рамка и орнамент.
     *
     * @param array<string,mixed> $clan
     * @param array<string,mixed> $opts
     */
    function clan_tamga_medallion(array $clan, array $opts = []): string
    {
        static $seq = 0;
        $seq++;
        $uid = 'ctm' . $seq;

        $e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $name = trim((string)($clan['name'] ?? ''));
        $tamgaUrl = trim((string)($clan['tamga_url'] ?? ''));
        $label = $opts['aria_label'] ?? ('Таңба: ' . $name);

        $sign = '';
        if ($tamgaUrl !== '') {
            // href (SVG2) + xlink:href для старых движков; геометрия не меняется.
            $sign = '<image class="ctm-sign-img" x="104" y="104" width="112" height="112"'
                . ' preserveAspectRatio="xMidYMid meet"'
                . ' href="' . $e($tamgaUrl) . '"'
                . ' xlink:href="' . $e($tamgaUrl) . '"></image>';
        } else {
            $sign = '<circle class="ctm-sign-empty" cx="160" cy="160" r="54"></circle>'
                . '<text class="ctm-sign-empty-mark" x="160" y="161" aria-hidden="true">—</text>';
        }

        // Ветвь «қошқар мүйіз» — четыре симметричных завитка в кольце.
        $horn = '<path d="M160 23 C145 23 134 34 134 47 C134 58 143 66 154 66 C162 66 168 60 168 52 C168 45 163 40 156 40 C151 40 147 44 147 49"/>'
            . '<path d="M160 23 C175 23 186 34 186 47 C186 58 177 66 166 66 C158 66 152 60 152 52 C152 45 157 40 164 40 C169 40 173 44 173 49"/>';

        return '<svg class="ctm-medal" viewBox="0 0 320 320" role="img" aria-label="' . $e($label) . '" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
            . '<defs>'
            . '<radialGradient id="' . $uid . '-gold" cx="34%" cy="26%" r="82%">'
            . '<stop offset="0" stop-color="#f6e3b4"/><stop offset=".4" stop-color="#d2b074"/>'
            . '<stop offset=".74" stop-color="#8e6c37"/><stop offset="1" stop-color="#5c431e"/>'
            . '</radialGradient>'
            . '<radialGradient id="' . $uid . '-dark" cx="42%" cy="34%" r="78%">'
            . '<stop offset="0" stop-color="#28251f"/><stop offset=".62" stop-color="#151310"/><stop offset="1" stop-color="#0a0908"/>'
            . '</radialGradient>'
            . '<radialGradient id="' . $uid . '-ivory" cx="40%" cy="30%" r="82%">'
            . '<stop offset="0" stop-color="#fbf5e9"/><stop offset=".66" stop-color="#eadfc8"/><stop offset="1" stop-color="#cfc0a2"/>'
            . '</radialGradient>'
            . '<linearGradient id="' . $uid . '-ring" x1="0" y1="0" x2="1" y2="1">'
            . '<stop offset="0" stop-color="#f8e8bf"/><stop offset=".28" stop-color="#cfae70"/>'
            . '<stop offset=".52" stop-color="#7e5f2e"/><stop offset=".76" stop-color="#e4c78f"/><stop offset="1" stop-color="#9a763e"/>'
            . '</linearGradient>'
            . '</defs>'
            . '<circle cx="160" cy="160" r="154" fill="#070706" opacity=".55"/>'
            . '<circle cx="160" cy="160" r="150" fill="url(#' . $uid . '-gold)"/>'
            . '<circle cx="160" cy="160" r="141" fill="url(#' . $uid . '-dark)"/>'
            . '<circle cx="160" cy="160" r="138" fill="none" stroke="url(#' . $uid . '-ring)" stroke-width="1.4"/>'
            . '<circle cx="160" cy="160" r="128" fill="none" stroke="#c9a86a" stroke-width="1" opacity=".55"/>'
            . '<g fill="none" stroke="url(#' . $uid . '-ring)" stroke-width="2" stroke-linecap="round" opacity=".88">'
            . '<g>' . $horn . '</g>'
            . '<g transform="rotate(90 160 160)">' . $horn . '</g>'
            . '<g transform="rotate(180 160 160)">' . $horn . '</g>'
            . '<g transform="rotate(270 160 160)">' . $horn . '</g>'
            . '</g>'
            . '<g fill="#e3c68d" opacity=".9">'
            . '<circle cx="239" cy="239" r="2.1"/><circle cx="81" cy="239" r="2.1"/>'
            . '<circle cx="239" cy="81" r="2.1"/><circle cx="81" cy="81" r="2.1"/>'
            . '</g>'
            . '<circle cx="160" cy="160" r="93" fill="#0b0a09" opacity=".9"/>'
            . '<circle cx="160" cy="160" r="86" fill="url(#' . $uid . '-ivory)" stroke="url(#' . $uid . '-ring)" stroke-width="2.4"/>'
            . '<circle cx="160" cy="160" r="79" fill="none" stroke="#8b6a36" stroke-width=".8" opacity=".45"/>'
            . $sign
            . '</svg>';
    }
}

if (!function_exists('clan_tamga_card')) {
    /**
     * Карточка ру: название, медальон, ұран, факты и действия.
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
        $zhuz = (string)($clan['zhuz'] ?? 'other');
        $zhuz = in_array($zhuz, ['senior', 'middle', 'junior'], true) ? $zhuz : 'other';
        $uran = trim((string)($clan['uran'] ?? ''));
        $summary = trim((string)($clan['summary'] ?? ''));
        $description = trim((string)($clan['description'] ?? ''));
        $parent = trim((string)($clan['parent_name'] ?? ''));
        $persons = (int)($clan['persons_count'] ?? 0);
        $tamgaUrl = trim((string)($clan['tamga_url'] ?? ''));
        // ALT-117: не показываем «битую» картинку, если файла таңба нет на диске
        // (например, старая запись ссылается на удалённый файл).
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

        $out .= '<header class="ctm-head"><h3 class="ctm-name" data-clan-coat="' . $e($name) . '" title="' . ($ru ? 'Показать герб' : 'Елтаңбаны көрсету') . '">' . $e($name) . '</h3>';
        if ($nameRu !== '') {
            $out .= '<div class="ctm-name-ru">' . $e($nameRu) . '</div>';
        }
        $out .= '</header>';

        $out .= '<div class="ctm-medal-wrap" data-clan-coat="' . $e($name) . '" title="' . ($ru ? 'Показать герб' : 'Елтаңбаны көрсету') . '" style="cursor:pointer">' . clan_tamga_medallion($clan, ['aria_label' => ($ru ? 'Таңба ру ' : 'Ру таңбасы: ') . $name]) . '</div>';

        // ALT-116: класс fit cl-uran сохраняется для обратной совместимости тестов
        // и семантики «поле ұрана»; стили — на вложенном .ctm-uran.
        $out .= '<div class="fit cl-uran"><div class="ctm-uran">'
            . '<span class="ctm-uran-label">Ұран</span>';
        if ($uran !== '') {
            $out .= '<span class="ctm-uran-value">' . $e($uran) . '</span>';
        } else {
            $out .= '<span class="ctm-uran-value is-empty" title="' . ($ru ? 'Ұран не указан' : 'Ұран көрсетілмеген') . '">—</span>';
        }
        $out .= '</div></div>';

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
