<?php

declare(strict_types=1);

/**
 * TASK-035: нормализация ссылок соцсетей (без БД).
 * Запуск: C:\PHP\php.exe tests\Task035SocialsTest.php
 */

require_once __DIR__ . '/../app/Services/PersonContactService.php';

use App\Services\PersonContactService as S;

$passed = 0;
$total = 0;
function t35(bool $c, string $l): void
{
    global $passed, $total;
    $total++;
    if ($c) { $passed++; echo "  [OK] {$l}\n"; } else { echo "  [FAIL] {$l}\n"; }
}

t35(S::normalizeSocial('instagram', '@ali.kz') === 'https://instagram.com/ali.kz', 'instagram @ник');
t35(S::normalizeSocial('instagram', 'https://www.instagram.com/ali.kz/') === 'https://www.instagram.com/ali.kz/', 'instagram ссылка');
t35(S::normalizeSocial('instagram', 'instagram.com/ali') === 'https://instagram.com/ali', 'instagram без схемы');
t35(S::normalizeSocial('instagram', 'https://evil.com/ali') === null, 'чужой домен для instagram — отклонён');
t35(S::normalizeSocial('instagram', 'https://instagram.com.evil.com/a') === null, 'поддомен-обманка отклонён');
t35(S::normalizeSocial('instagram', 'https://instagram.com/') === null, 'главная сети — не профиль');
t35(S::normalizeSocial('telegram', '@alty_bay') === 'https://t.me/alty_bay', 'telegram @ник');
t35(S::normalizeSocial('telegram', '@ab') === null, 'telegram слишком короткий ник');
t35(S::normalizeSocial('whatsapp', '8 777 123 45 67') === 'https://wa.me/77771234567', 'whatsapp по номеру');
t35(S::normalizeSocial('tiktok', 'ali') === 'https://www.tiktok.com/@ali', 'tiktok ник');
t35(S::normalizeSocial('vk', 'id12345') === 'https://vk.com/id12345', 'vk ник');
t35(S::normalizeSocial('other', 'https://example.org/me') === 'https://example.org/me', 'другое: ссылка');
t35(S::normalizeSocial('other', 'example.org/me') === 'https://example.org/me', 'другое: без схемы');
t35(S::normalizeSocial('other', 'ali') === null, 'другое: ник не принимается');
t35(S::normalizeSocial('other', 'javascript:alert(1)') === null, 'javascript: отклонён');
t35(S::normalizeSocial('other', 'ftp://example.org/x') === null, 'ftp отклонён');
t35(S::normalizeSocial('other', 'https://user:pw@example.org/x') === null, 'логин в ссылке отклонён');
t35(S::normalizeSocial('other', 'https://example.org/"onmouseover=x') === null, 'кавычки отклонены');
t35(S::normalizeSocial('nope', 'ali') === null, 'неизвестный тип');
t35(S::decodeContacts('{"phones":["+7"],"socials":[{"type":"instagram","url":"https://instagram.com/a"},{"type":"x","url":"https://a"},{"type":"other","url":"javascript:1"}]}')['socials'] === [['type' => 'instagram', 'url' => 'https://instagram.com/a']], 'decode отбрасывает мусор');

echo "\n{$passed}/{$total}\n";
exit($passed === $total ? 0 : 1);
