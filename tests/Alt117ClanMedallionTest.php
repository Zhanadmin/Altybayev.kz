<?php
declare(strict_types=1);

/**
 * ALT-117: единый компонент карточек «Ру таңбалары».
 * Проверяет шаблон без записи в БД: данные, экранирование, адаптивность,
 * сохранение полей ALT-116 и режимов читателя/редактора.
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../views/partials/clan_tamga_medallion.php';

use App\Core\Auth;
use App\Core\I18n;

$fails = 0;
function chk(bool $ok, string $m): void {
    global $fails;
    echo ($ok ? 'OK   ' : 'FAIL ') . $m . "\n";
    if (!$ok) $fails++;
}

function card(array $clan, bool $canEdit = true, string $lang = 'ru'): string {
    return clan_tamga_card($clan, ['can_edit' => $canEdit, 'lang' => $lang]);
}

$base = [
    'id' => 7,
    'name' => 'Дулат',
    'name_ru' => 'Дулаты',
    'zhuz' => 'senior',
    'parent_name' => '',
    'uran' => '',
    'summary' => 'Ұлы жуз. Источник: el.kz (по С. Аманжолову, 1959)',
    'description' => 'Историческое описание рода.',
    'tamga_url' => '/uploads/tamga/clan_verified_dulat.svg',
    'persons_count' => 12,
];

// 1. Стили и разметка карточки
$css = clan_tamga_medallion_styles();
chk(str_contains($css, '.ctm-card'), 'Стили компонента содержат .ctm-card');
chk(str_contains($css, '@media (max-width: 640px)'), 'Есть мобильная точка перелома');
chk(!str_contains($css, '-webkit-line-clamp'), 'Описание в карточке не обрезается');

$html = card($base);
chk(str_contains($html, 'class="cl-card ctm-card"'), 'Карточка использует общий класс');
chk(str_contains($html, 'data-id="7"'), 'ID рода сохранён');
chk(str_contains($html, 'data-z="senior"'), 'Жуз сохранён в data-атрибуте');
chk(str_contains($html, 'Дулат'), 'Название рода выведено');
chk(str_contains($html, 'Дулаты'), 'Русское название выведено');
chk(str_contains($html, 'Старший жуз'), 'Жуз выведен по-русски');
chk(str_contains($html, '12 чел.'), 'Число людей выведено');
chk(str_contains($html, 'class="fit cl-uran"'), 'Класс fit cl-uran сохранён (ALT-116)');
chk(str_contains($html, 'Ұран'), 'Подпись ұрана выведена');
chk(str_contains($html, 'is-empty'), 'Пустой ұран показан нейтрально, без выдуманного значения');
chk(str_contains($html, 'href="/uploads/tamga/clan_verified_dulat.svg"'), 'Исходная таңба подключена');
chk(str_contains($html, 'preserveAspectRatio="xMidYMid meet"'), 'Пропорции таңба сохраняются');
chk(str_contains($html, 'data-clan-coat="Дулат"'), 'Сохранён клик по гербу (data-clan-coat)');
chk(str_contains($html, 'data-act="edit"') && str_contains($html, 'data-act="delete"'), 'Редактору доступны действия');

// 2. Ұран и длинные казахские названия
$long = $base;
$long['id'] = 28;
$long['name'] = 'Ашамайлы-Керей';
$long['name_ru'] = 'Ашамайлы-Керей';
$long['zhuz'] = 'middle';
$long['uran'] = 'Аруақ! Қарабура! Бақтияр!';
$long['summary'] = '';
$long['description'] = '';
$longHtml = card($long);
chk(str_contains($longHtml, 'Ашамайлы-Керей'), 'Длинное название выведено целиком');
chk(str_contains($longHtml, 'Аруақ! Қарабура! Бақтияр!'), 'Ұран с казахскими буквами выведен целиком');
chk(substr_count($longHtml, 'class="fit cl-uran"') === 1, 'В карточке ровно один блок ұрана');
chk(!str_contains($longHtml, 'is-empty'), 'Заполненный ұран не помечается пустым');

// 3. Казахские буквы и экранирование
$kk = $base;
$kk['name'] = 'Қоңырат';
$kk['uran'] = 'Алатау!';
$kkHtml = card($kk);
chk(str_contains($kkHtml, 'Қоңырат'), 'Казахские буквы (Қ, ң) не искажены');
chk(str_contains($kkHtml, 'Алатау!'), 'Ұран с қазақскими буквами не искажён');

$xss = $base;
$xss['name'] = '<script>alert(1)</script>';
$xss['name_ru'] = '"><img src=x onerror=alert(2)>';
$xss['uran'] = '<b>Уран</b>';
$xssHtml = card($xss);
chk(!str_contains($xssHtml, '<script>alert(1)</script>'), 'HTML в названии экранирован');
chk(!str_contains($xssHtml, '<img src=x onerror=alert(2)>'), 'HTML в русском названии экранирован');
chk(!str_contains($xssHtml, '<b>Уран</b>'), 'HTML в ұране экранирован');
chk(str_contains($xssHtml, '&lt;script&gt;'), 'Экранированный текст присутствует как текст');

// 4. Род без таңба
$empty = $base;
$empty['id'] = 33;
$empty['name'] = 'Төре';
$empty['zhuz'] = 'other';
$empty['tamga_url'] = '';
$emptyHtml = card($empty);
chk(str_contains($emptyHtml, 'ctm-sign-empty'), 'Для рода без таңба — нейтральный плейсхолдер');
chk(!str_contains($emptyHtml, '<image'), 'Плейсхолдер не подменяет таңба картинкой');
chk(str_contains($emptyHtml, 'Таңба не загружена'), 'Честно указано, что таңба не загружена');

// 5. Режим читателя: только просмотр, ALT-116
$viewerHtml = card($base, false);
chk(!str_contains($viewerHtml, 'data-act="edit"'), 'Читателю не показывается кнопка изменения');
chk(!str_contains($viewerHtml, 'data-act="delete"'), 'Читателю не показывается кнопка удаления');
chk(str_contains($viewerHtml, 'Дулат'), 'Читатель видит данные рода');

// 5a. Казахская локаль интерфейса
$kkUi = card($base, true, 'kk');
chk(str_contains($kkUi, 'Ұлы жүз'), 'Казахская подпись жуза');
chk(str_contains($kkUi, 'Өзгерту') && str_contains($kkUi, 'Өшіру'), 'Казахские подписи действий');

// 6. Полная страница: все переданные роды попали в общий шаблон
$_GET['lang'] = 'ru';
I18n::setLang('ru');

function renderClansPage(array $clans, bool $canEdit): string {
    $registryReady = true;
    ob_start();
    require __DIR__ . '/../views/admin/clans.php';
    return (string)ob_get_clean();
}

@Auth::login(['id' => 1, 'email' => 'admin@test.local', 'name' => 'Admin', 'role' => 'admin']);
$page = renderClansPage([$base, $long, $empty], true);
chk(substr_count($page, 'class="cl-card ctm-card"') === 3, 'Все 3 рода отрисованы одним компонентом');
chk(substr_count($page, 'class="cl-zhuz"') === 3, 'Роды сгруппированы по жузам');
chk(str_contains($page, 'id="clList"'), 'Галерея имеет контейнер clList');
chk(str_contains($page, 'id="clEmpty"'), 'Есть сообщение «ничего не найдено»');
chk(!str_contains($page, 'class="cl-table"'), 'Старая таблица не выводится');
chk(str_contains($page, 'id="clDlg"'), 'Редактору доступен диалог ALT-116');
chk(str_contains($page, 'name="uran"'), 'Поле ұрана ALT-116 сохранено');
chk(str_contains($page, 'data-act="edit"'), 'Кнопка редактирования сохранена');

@Auth::login(['id' => 2, 'email' => 'viewer@test.local', 'name' => 'Viewer', 'role' => 'viewer']);
$pageViewer = renderClansPage([$base], false);
chk(!str_contains($pageViewer, 'id="clDlg"'), 'Читателю не выводится диалог редактирования');
chk(!str_contains($pageViewer, 'data-act="edit"'), 'Читателю не выводятся кнопки действий');
chk(str_contains($pageViewer, 'class="cl-card ctm-card"'), 'Читатель видит карточки');

@Auth::logout();

echo $fails === 0 ? "\nALT-117: Все проверки успешно пройдены!\n" : "\nALT-117: Провалено проверок: $fails\n";
exit($fails === 0 ? 0 : 1);
