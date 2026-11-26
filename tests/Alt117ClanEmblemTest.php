<?php
declare(strict_types=1);

/**
 * ALT-117 (2026-11-26, редакция 2): интеграция единого рендерера.
 * Проверяет, что галерея, редактор рода, модальное окно и экспорт используют
 * один ClanEmblem; круглого медальона и нарисованного пейзажа больше нет;
 * утверждённый растровый фон подключается через отдельный слот.
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
$partialPath = $root . '/views/partials/clan_tamga_medallion.php';
$clanTamgaPath = $root . '/views/partials/clan_tamga.php';

chk(is_file($emblemPath), 'Есть единый генератор public/assets/js/clan-emblem.js');
$emblem = is_file($emblemPath) ? (string)file_get_contents($emblemPath) : '';
$zheti = is_file($zhetiPath) ? (string)file_get_contents($zhetiPath) : '';
$view = is_file($clansView) ? (string)file_get_contents($clansView) : '';
$partial = is_file($partialPath) ? (string)file_get_contents($partialPath) : '';
$clanTamga = is_file($clanTamgaPath) ? (string)file_get_contents($clanTamgaPath) : '';

chk($emblem !== '', 'Генератор не пустой');
chk(str_contains($emblem, 'const W = 1024') && str_contains($emblem, 'viewBox="0 0 '), 'Герб имеет систему 1024×1024');
chk(str_contains($emblem, 'preserveAspectRatio="xMidYMid meet"'), 'Таңба сохраняет пропорции');
chk(str_contains($emblem, 'preserveAspectRatio="xMidYMid slice"'), 'Растровый фон заполняет квадрат');
chk(str_contains($emblem, '/MediaBox [0 0 '), 'PDF имеет MediaBox');
chk(str_contains($emblem, '/Filter /DCTDecode'), 'PDF встраивает изображение без потери кириллицы');
chk(str_contains($emblem, 'buildPdf'), 'Есть единый построитель PDF');
chk(str_contains($emblem, 'toPngDataUrl'), 'Есть растрирование в PNG');
chk(str_contains($emblem, 'function mountAll'), 'Есть монтирование превью mountAll');
chk(str_contains($emblem, 'function mount('), 'Есть монтирование одного превью mount');
chk(str_contains($emblem, 'function loadBackground'), 'Есть загрузчик утверждённого фона');
chk(str_contains($emblem, 'approved-background.jpg'), 'Слот замены фона задан');
chk(str_contains($emblem, '/assets/brand/altybayev-brand/backgrounds/clan-emblem.png'), 'Утверждённый растр бренда — первый кандидат');
chk(str_contains($clanTamga, 'clan-emblem.png'), 'Сервер находит утверждённый растр бренда');
chk(str_contains($emblem, 'neutralScene'), 'Есть нейтральный плейсхолдер без пейзажа');
chk(!str_contains($emblem, 'function sky(') && !str_contains($emblem, 'function mountains('), 'Нет программно нарисованного неба/гор');
chk(!str_contains($emblem, 'function yurt(') && !str_contains($emblem, 'function family(') && !str_contains($emblem, 'function children('), 'Нет юрты, семьи и детей из старого мультяшного герба');
chk(!str_contains($emblem, 'function horses(') && !str_contains($emblem, 'function lake(') && !str_contains($emblem, 'function steppe('), 'Нет лошадей, озера и степи');
chk(!str_contains($emblem, '<textPath'), 'Нет кругового текста: надписи горизонтальные');
chk(!str_contains($emblem, '#5b2018'), 'Нет старого бордового фона');
chk(!str_contains($emblem, 'viewBox="0 0 320 320"'), 'Нет старого круглого медальона 320×320');

chk(str_contains($view, '/assets/js/clan-emblem.js'), 'Страница «Ру таңбалары» подключает генератор');
chk(str_contains($view, '/assets/js/zheti-ata.js'), 'Обработчик клика сохранён');
chk(str_contains($view, 'ClanEmblem.mount('), 'Редактор рода строит живое превью тем же рендерером');
chk(str_contains($view, 'id="clPrev"') && str_contains($view, 'cl-prev-emblem'), 'В редакторе есть квадратное превью');
chk(str_contains($view, 'clan_emblem_background_url()'), 'Галерея проверяет наличие утверждённого фона');
chk(str_contains($clanTamga, 'function clan_emblem_background_url'), 'Есть серверная проверка файла фона');
chk(str_contains($clanTamga, 'window.CLAN_EMBLEM_BG'), 'Путь фона передаётся в JS');

chk(str_contains($partial, 'function clan_emblem_preview'), 'Есть общий контейнер превью');
chk(str_contains($partial, 'data-clan-emblem='), 'Превью отдаёт данные единому рендереру');
chk(str_contains($partial, 'data-clan-coat='), 'Клик по карточке сохранён');
chk(str_contains($partial, 'ctm-emblem'), 'Карточка использует квадратный контейнер');
chk(!str_contains($partial, 'ctm-medal'), 'Круглый медальон удалён из карточки');
chk(!str_contains($partial, 'viewBox="0 0 320 320"'), 'В PHP больше нет отдельного круглого SVG');
chk(!str_contains($partial, 'clan_tamga_medallion($clan'), 'Внутри карточки нет вызова старого медальона');

chk(str_contains($zheti, 'ensureEmblemLib'), 'Обработчик умеет подгрузить генератор');
chk(str_contains($zheti, 'lib.open('), 'Клик открывает новое модальное окно');
$openPos = strpos($zheti, 'async function openClan');
$nextPos = $openPos === false ? false : strpos($zheti, '// клик по любому элементу', $openPos);
$openBody = ($openPos !== false && $nextPos !== false) ? substr($zheti, $openPos, $nextPos - $openPos) : '';
chk($openBody !== '' && !str_contains($openBody, 'coatSvgFull'), 'В новом окне не используется старый герб-звезда');
chk($openBody !== '' && !str_contains($openBody, 'textPath'), 'В новом окне нет круговых надписей');
chk(str_contains($zheti, "'PNG жүктеу'") || str_contains($zheti, "'Скачать PNG'"), 'Есть подпись PNG');
chk(str_contains($zheti, "'PDF жүктеу'") || str_contains($zheti, "'Скачать PDF'"), 'Есть подпись PDF');
chk(str_contains($zheti, 'noBackground'), 'Есть честное сообщение об отсутствии утверждённого фона');

echo $fails === 0 ? "\nALT-117 integration: все проверки пройдены!\n" : "\nALT-117 integration: провалено $fails\n";
exit($fails === 0 ? 0 : 1);
