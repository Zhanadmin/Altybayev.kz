<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\PatronymicService as P;

$fails = 0;
function chk(bool $ok, string $m): void { global $fails; echo ($ok ? 'OK   ' : 'FAIL ') . $m . "\n"; if (!$ok) $fails++; }
function eq(string $got, string $exp, string $m): void { chk($got === $exp, $m . ' => «' . $got . '»' . ($got === $exp ? '' : ' (ждали «' . $exp . '»)')); }

// на согласную
eq(P::generate('Ғабит', 'M', 'kk'), 'Ғабит ұлы', 'Ғабит M kk');
eq(P::generate('Ғабит', 'F', 'kk'), 'Ғабит қызы', 'Ғабит F kk');
eq(P::generate('Ғабит', 'M', 'ru'), 'Габитович', 'Ғабит M ru');
eq(P::generate('Ғабит', 'F', 'ru'), 'Габитовна', 'Ғабит F ru');
eq(P::generate('Нұрлан', 'M', 'ru'), 'Нурланович', 'Нұрлан M ru');
eq(P::generate('Қайрат', 'F', 'ru'), 'Кайратовна', 'Қайрат F ru');
// на -й
eq(P::generate('Сарсенбай', 'M', 'ru'), 'Сарсенбаевич', 'Сарсенбай M ru');
eq(P::generate('Сарсенбай', 'F', 'ru'), 'Сарсенбаевна', 'Сарсенбай F ru');
eq(P::generate('Сарсенбай', 'M', 'kk'), 'Сарсенбай ұлы', 'Сарсенбай M kk');
// на -ь
eq(P::generate('Игорь', 'M', 'ru'), 'Игоревич', 'Игорь M ru');
// на шипящую
eq(P::generate('Бейбарыш', 'F', 'ru'), 'Бейбарышевна', 'Бейбарыш F ru');
// на гласную — по-русски неестественно, казахская форма
eq(P::generate('Асқар', 'M', 'ru'), 'Аскарович', 'Асқар M ru');
eq(P::generate('Мұрата', 'M', 'ru'), 'Мұрата ұлы', 'Мұрата (гласная) M ru -> kk-форма');
eq(P::generate('Мұрата', 'F', 'ru'), 'Мұрата қызы', 'Мұрата (гласная) F ru -> kk-форма');
eq(P::generate('Мұрата', 'M', 'kk'), 'Мұрата ұлы', 'Мұрата M kk');
// словарь исключений
eq(P::generate('Илья', 'M', 'ru'), 'Ильич', 'Илья M ru');
eq(P::generate('Илья', 'F', 'ru'), 'Ильинична', 'Илья F ru');
// пол неизвестен / нет имени
eq(P::generate('Ғабит', '', 'kk'), '', 'нет пола');
eq(P::generate('', 'M', 'kk'), '', 'нет имени отца');

// resolve: ручное значение приоритетнее и не затирается
$father = ['first_name' => 'Ғабит'];
eq(P::resolve(['gender' => 'M', 'middle_name' => ''], $father, 'kk'), 'Ғабит ұлы', 'resolve авто kk');
eq(P::resolve(['gender' => 'M', 'middle_name' => null], $father, 'ru'), 'Габитович', 'resolve авто ru');
eq(P::resolve(['gender' => 'F', 'middle_name' => ''], $father, 'ru'), 'Габитовна', 'resolve сестра ru');
eq(P::resolve(['gender' => 'F', 'middle_name' => ''], $father, 'kk'), 'Ғабит қызы', 'resolve сестра kk');
eq(P::resolve(['gender' => 'M', 'middle_name' => 'Ручное'], $father, 'ru'), 'Ручное', 'ручное приоритетнее');
eq(P::resolve(['gender' => 'M', 'middle_name' => ''], null, 'ru'), '', 'нет отца');

echo $fails === 0 ? "ALL OK\n" : "FAILED: $fails\n";
exit($fails === 0 ? 0 : 1);
