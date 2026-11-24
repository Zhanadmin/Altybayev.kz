<?php
declare(strict_types=1);
// ALT-022: имена латиницей в en/es + строка «Son of / Hijo de». Запуск: php tests\Alt022TranslitTest.php
require_once __DIR__ . '/../app/Core/I18n.php';
require_once __DIR__ . '/../app/Services/PatronymicService.php';

use App\Core\I18n;
use App\Services\PatronymicService as P;

$fails = 0;
function eq(string $got, string $exp, string $m): void
{
    global $fails;
    $ok = $got === $exp;
    echo ($ok ? 'OK   ' : 'FAIL ') . $m . ' => «' . $got . '»' . ($ok ? '' : ' (ждали «' . $exp . '»)') . "\n";
    if (!$ok) { $fails++; }
}

// [кириллица, en, es]
$names = [
    ['Жандос Алтыбаев',   'Zhandos Altybayev',   'Zhandos Altybayev'],
    ['Ерлан Нұрлан',      'Erlan Nurlan',        'Erlan Nurlan'],
    ['Қайрат Ғабит',      'Kayrat Gabit',        'Kayrat Gabit'],
    ['Бейбарыс Сәрсенбай','Beybarys Sarsenbay',  'Beybarys Sarsenbay'],
    ['Хасен Шәкәрім',     'Khasen Shakarim',     'Jasen Shakarim'],
    ['Ақтөре Өтеген',     'Aktore Otegen',       'Aktore Oteguen'],
    ['Жанғали Сеңгір',    'Zhangali Senggir',    'Zhangali Sengguir'],
    ['Әділет Қоңыр',      'Adilet Kongyr',       'Adilet Kongyr'],
    ['Һадиша Ілияс',      'Hadisha Iliyas',      'Jadisha Iliyas'],
    ['Георгий Щербаков',  'Georgiy Shcherbakov', 'Gueorguiy Sherbakov'],
];
foreach ($names as [$src, $en, $es]) {
    eq(I18n::latinize($src, 'en'), $en, "en: $src");
    eq(I18n::latinize($src, 'es'), $es, "es: $src");
}

// «Son of / Daughter of», «Hijo de / Hija de»
eq(P::parentLabel(['gender' => 'M', 'middle_name' => 'Ерлан ұлы'], null, 'en'), 'Son of Erlan', 'ручное, M, en');
eq(P::parentLabel(['gender' => 'F', 'middle_name' => 'Ерлан қызы'], null, 'en'), 'Daughter of Erlan', 'ручное, F, en');
eq(P::parentLabel(['gender' => 'M'], ['first_name' => 'Хасен'], 'es'), 'Hijo de Jasen', 'по отцу, M, es');
eq(P::parentLabel(['gender' => 'F'], ['first_name' => 'Хасен'], 'es'), 'Hija de Jasen', 'по отцу, F, es');
eq(P::parentLabel(['gender' => 'M'], null, 'en'), '', 'отец неизвестен — пусто');
eq(P::parentLabel(['gender' => 'M'], ['first_name' => 'Ерлан'], 'kk'), '', 'kk — пусто');
// в строке имени отчества нет
eq(P::resolve(['gender' => 'M', 'middle_name' => 'Ерлан ұлы'], null, 'en'), '', 'resolve en — пусто');
eq(P::resolve(['gender' => 'M'], ['first_name' => 'Ерлан'], 'es'), '', 'resolve es — пусто');
// kk/ru не сломаны
eq(P::resolve(['gender' => 'M'], ['first_name' => 'Ерлан'], 'ru'), 'Ерланович', 'ru без изменений');
eq(P::resolve(['gender' => 'M'], ['first_name' => 'Ерлан'], 'kk'), 'Ерлан-ұлы', 'kk: тире внутри отчества');

echo $fails === 0 ? "ALL OK\n" : "FAILS: $fails\n";
exit($fails === 0 ? 0 : 1);
