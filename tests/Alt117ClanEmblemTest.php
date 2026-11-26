<?php
declare(strict_types=1);

/**
 * ALT-117 (2026-11-26): интеграция нового герба с карточками и модальным окном.
 * Проверяет, что страница «Ру таңбалары» подключает единый генератор,
 * клик data-clan-coat ведёт в новое окно, а экспорт — PNG/PDF, не старый герб.
 */

$root = dirname(__DIR__);
$fails = 0;
function chk(bool $ok, string $m): void {
    global $fails;
    echo ($ok ? 'OK   ' : 'FAIL ') . $m . "\n";
    if (!$ok) $fails++;
}

$emblemPath = $root . '/public/assets/js/clan-emblem.js';
$zhetiPath = $root . '/public/assets/js/zheti-ata.js';
$clansView = $root . '/views/admin/clans.php';

chk(is_file($emblemPath), 'Есть единый генератор public/assets/js/clan-emblem.js');
$emblem = is_file($emblemPath) ? (string)file_get_contents($emblemPath) : '';
$zheti = is_file($zhetiPath) ? (string)file_get_contents($zhetiPath) : '';
$view = is_file($clansView) ? (string)file_get_contents($clansView) : '';

chk($emblem !== '', 'Генератор не пустой');
chk(str_contains($emblem, 'const W = 1024') && str_contains($emblem, 'viewBox="0 0 '), 'Герб имеет систему 1024×1024');
chk(str_contains($emblem, 'preserveAspectRatio="xMidYMid meet"'), 'Таңба сохраняет пропорции');
chk(str_contains($emblem, '/MediaBox [0 0 '), 'PDF имеет MediaBox');
chk(str_contains($emblem, '/Filter /DCTDecode'), 'PDF встраивает изображение без потери кириллицы');
chk(str_contains($emblem, 'buildPdf'), 'Есть единый построитель PDF');
chk(str_contains($emblem, 'toPngDataUrl'), 'Есть растрирование в PNG');
chk(!str_contains($emblem, '<textPath'), 'Нет кругового текста: надписи горизонтальные');
chk(!str_contains($emblem, '#5b2018'), 'Нет старого бордового фона');

chk(str_contains($view, '/assets/js/clan-emblem.js'), 'Страница «Ру таңбалары» подключает генератор');
$medallion = $root . '/views/partials/clan_tamga_medallion.php';
$medallionSrc = is_file($medallion) ? (string)file_get_contents($medallion) : '';
chk(str_contains($medallionSrc, 'data-clan-coat='), 'Клик по карточке сохранён');
chk(str_contains($view, '/assets/js/zheti-ata.js'), 'Обработчик клика сохранён');

chk(str_contains($zheti, 'ensureEmblemLib'), 'Обработчик умеет подгрузить генератор');
chk(str_contains($zheti, 'lib.open('), 'Клик открывает новое модальное окно');
$openPos = strpos($zheti, 'async function openClan');
$nextPos = $openPos === false ? false : strpos($zheti, '// клик по любому элементу', $openPos);
$openBody = ($openPos !== false && $nextPos !== false) ? substr($zheti, $openPos, $nextPos - $openPos) : '';
chk($openBody !== '' && !str_contains($openBody, 'coatSvgFull'), 'В новом окне не используется старый герб-звезда');
chk($openBody !== '' && !str_contains($openBody, 'textPath'), 'В новом окне нет круговых надписей');
chk(str_contains($zheti, "'PNG жүктеу'") || str_contains($zheti, "'Скачать PNG'"), 'Есть подпись PNG');
chk(str_contains($zheti, "'PDF жүктеу'") || str_contains($zheti, "'Скачать PDF'"), 'Есть подпись PDF');

echo $fails === 0 ? "\nALT-117 integration: все проверки пройдены!\n" : "\nALT-117 integration: провалено $fails\n";
exit($fails === 0 ? 0 : 1);
