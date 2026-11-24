<?php
declare(strict_types=1);
// ALT-022: в en/es не должно оставаться кириллицы в подписях, датах, возрасте, годах жизни.
// Запуск: php tests\Alt022NoCyrillicTest.php
require_once __DIR__ . '/../app/Core/I18n.php';

use App\Core\I18n;

$fails = 0;
function chk(bool $ok, string $m): void
{
    global $fails;
    echo ($ok ? 'OK   ' : 'FAIL ') . $m . "\n";
    if (!$ok) { $fails++; }
}
function hasCyr(string $s): bool
{
    return (bool)preg_match('/[\x{0400}-\x{04FF}]/u', $s);
}

// Фразы, которые реально выводят представления (не указан, дата не указана, места, возраст, родство)
$html = '<dd>не указан</dd><dd>не указано</dd><span>дата не указана</span><span>Дети не указаны</span>'
    . '<span title="Дата рождения: 5 марта 1952">Жандос Алтыбаев</span><span>Сағали ұлы</span>'
    . '<span>Әкесі</span><span>Шешесі</span><span>Ағасы</span><span>Нағашы жұрт</span>'
    . '<span>38 жас</span><span>47 лет</span><span>Қайрат Ғабит, Хасен Шәкәрім</span>';

foreach (['en', 'es'] as $lang) {
    I18n::setLang($lang);
    $out = I18n::translateHtml($html);
    echo "     {$lang}: " . $out . "\n";
    chk(!hasCyr(strip_tags($out)), "$lang: текст без кириллицы");
    chk(!hasCyr($out), "$lang: атрибуты без кириллицы");
    if (hasCyr($out)) { echo "     остаток: " . $out . "\n"; }

    // Годы жизни и возраст
    $dead = I18n::lifeLabel(['birth_year' => 1952, 'death_year' => 2010, 'is_deceased' => 1, 'gender' => 'M'], $lang)['short'];
    $onlyB = I18n::lifeLabel(['birth_year' => 1952, 'is_deceased' => 1], $lang)['short'];
    $onlyD = I18n::lifeLabel(['death_year' => 2010, 'is_deceased' => 1], $lang)['short'];
    $alive = I18n::lifeLabel(['birth_date' => '1990-01-01', 'is_deceased' => 0], $lang, '2026-11-24')['short'];
    foreach (['dead' => $dead, 'onlyB' => $onlyB, 'onlyD' => $onlyD, 'alive' => $alive] as $k => $v) {
        chk(!hasCyr($v) && $v !== '', "$lang $k => «{$v}»");
    }
    chk(str_starts_with($dead, '1952–2010'), "$lang годы жизни 1952–2010");
    chk($onlyB === (($lang === 'en' ? 'b. ' : 'n. ') . '1952'), "$lang только рождение => «{$onlyB}»");
    chk($onlyD === (($lang === 'en' ? 'd. ' : 'm. ') . '2010'), "$lang только смерть => «{$onlyD}»");
    chk(str_contains($alive, $lang === 'en' ? 'years' : 'años'), "$lang возраст");
}

// Форматы дат
I18n::setLang('en');
chk(I18n::formatDate('1952-03-05', 'en') === 'March 5, 1952', 'en дата');
chk(I18n::formatDate('1952-03-05', 'es') === '5 de marzo de 1952', 'es дата');
chk(I18n::formatDate('1952-03-05', 'ru') === '5 марта 1952 г.', 'ru дата');
chk(I18n::formatDate('1952-03-05', 'kk') === '1952 жылғы 5 наурыз', 'kk дата');

echo $fails === 0 ? "ALL OK\n" : "FAILS: $fails\n";
exit($fails === 0 ? 0 : 1);
