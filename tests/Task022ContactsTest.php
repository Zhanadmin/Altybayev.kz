<?php

declare(strict_types=1);

/**
 * TASK-022: нормализация телефона/email и разбор контактов (без БД).
 * Запуск: C:\PHP\php.exe tests\Task022ContactsTest.php
 */

require_once __DIR__ . '/../app/Services/PersonContactService.php';

use App\Services\PersonContactService as S;

$passed = 0;
$total = 0;
function t22(bool $c, string $l): void
{
    global $passed, $total;
    $total++;
    if ($c) { $passed++; echo "  [OK] {$l}\n"; } else { echo "  [FAIL] {$l}\n"; }
}

t22(S::normalizePhone('8 (777) 123-45-67') === '+77771234567', '8 → +7');
t22(S::normalizePhone('+7 777 123 45 67') === '+77771234567', '+7 с пробелами');
t22(S::normalizePhone('7771234567') === '+77771234567', '10 цифр → +7');
t22(S::normalizePhone('+380501234567') === '+380501234567', 'иностранный номер сохраняется');
t22(S::normalizePhone('12') === null, 'слишком короткий — отклонён');
t22(S::normalizePhone('') === null, 'пустой — null');
t22(S::formatPhone('+77771234567') === '+7 (777) 123-45-67', 'формат +7 (xxx) xxx-xx-xx');
t22(S::normalizeEmail('  Ali@Gmail.com ') === 'ali@gmail.com', 'email в нижний регистр');
t22(S::normalizeEmail('not-an-email') === null, 'плохой email — null');
$c = S::decodeContacts('{"phones":["+77771234567"],"emails":["A@b.kz"],"addresses":["Алматы"]}');
t22($c['emails'] === ['a@b.kz'] && $c['phones'] === ['+77771234567'] && $c['addresses'] === ['Алматы'], 'decodeContacts');
$e = S::decodeContacts(null);
t22($e === ['phones' => [], 'emails' => [], 'addresses' => []], 'decodeContacts(null)');

echo "\n{$passed}/{$total}\n";
exit($passed === $total ? 0 : 1);
