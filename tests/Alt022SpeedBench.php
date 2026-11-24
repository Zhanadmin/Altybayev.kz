<?php
declare(strict_types=1);
// ALT-022: замер перевода страницы en — без кэша (старый путь) и с кэшем (новый). Только чтение, БД не нужна.
require __DIR__ . '/../app/Core/I18n.php';
use App\Core\I18n;
$_GET['lang'] = 'en';
I18n::setLang('en');
$names = ['Жандос Алтыбаев', 'Ерлан Нұрлан', 'Қайрат Ғабит', 'Хасен Шәкәрім', 'Әділет Қоңыр'];
$html = '';
for ($i = 0; $i < 3000; $i++) {
    $html .= '<span title="Дата рождения: 5 марта 1952">' . $names[$i % 5] . '</span><span>Отец</span><span>38 лет</span><span>Mother</span><div>12</div>';
}
$ref = new ReflectionMethod(I18n::class, 'translateTextUncached');
$ref->setAccessible(true);
$t = microtime(true);
preg_replace_callback('/>([^<>]+)</u', fn($m) => '>' . $ref->invoke(null, $m[1], true, 'en') . '<', $html);
$old = microtime(true) - $t;
$t = microtime(true);
I18n::translateHtml($html);
$new = microtime(true) - $t;
printf("без кэша: %.3f с, с кэшем: %.3f с\n", $old, $new);
