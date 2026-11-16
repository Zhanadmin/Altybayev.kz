<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * TASK-022: контакты человека (телефон, email) и связь приглашений с карточкой.
 * Контакты лежат в persons.private_contacts (JSON: phones[], emails[], addresses[]).
 */
class PersonContactService
{
    public const MAX_PHONES = 5;

    private PDO $db;
    private static ?bool $hasInvitePersonColumn = null;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** Применена ли миграция invitations.person_id */
    public function hasInvitationPersonColumn(): bool
    {
        if (self::$hasInvitePersonColumn === null) {
            try {
                $st = $this->db->query("SHOW COLUMNS FROM `invitations` LIKE 'person_id'");
                self::$hasInvitePersonColumn = $st !== false && $st->rowCount() > 0;
            } catch (\Throwable $e) {
                self::$hasInvitePersonColumn = false;
            }
        }
        return self::$hasInvitePersonColumn;
    }

    /** Телефон → +7XXXXXXXXXX (или +цифры для иностранных); null, если не похож на номер */
    public static function normalizePhone(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $plus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }
        if (!$plus && strlen($digits) === 11 && ($digits[0] === '8' || $digits[0] === '7')) {
            return '+7' . substr($digits, 1);
        }
        if (!$plus && strlen($digits) === 10) {
            return '+7' . $digits;
        }
        if (strlen($digits) >= 8 && strlen($digits) <= 15) {
            return '+' . $digits;
        }
        return null;
    }

    /** +77771234567 → +7 (777) 123-45-67 */
    public static function formatPhone(string $phone): string
    {
        if (preg_match('/^\+7(\d{3})(\d{3})(\d{2})(\d{2})$/', $phone, $m)) {
            return "+7 ({$m[1]}) {$m[2]}-{$m[3]}-{$m[4]}";
        }
        return $phone;
    }

    public static function normalizeEmail(string $raw): ?string
    {
        $email = strtolower(trim($raw));
        if ($email === '' || mb_strlen($email) > 191 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }
        return $email;
    }

    /** TASK-035: соцсети — тип => [название, домены, шаблон ссылки по нику, regex ника] */
    public const SOCIALS = [
        'instagram' => ['Instagram', ['instagram.com'], 'https://instagram.com/%s', '/^[A-Za-z0-9._]{1,30}$/'],
        'facebook'  => ['Facebook', ['facebook.com', 'fb.com', 'fb.me'], 'https://facebook.com/%s', '/^[A-Za-z0-9.]{5,50}$/'],
        'telegram'  => ['Telegram', ['t.me', 'telegram.me', 'telegram.org'], 'https://t.me/%s', '/^[A-Za-z0-9_]{5,32}$/'],
        'whatsapp'  => ['WhatsApp', ['wa.me', 'whatsapp.com'], 'https://wa.me/%s', null],
        'tiktok'    => ['TikTok', ['tiktok.com'], 'https://www.tiktok.com/@%s', '/^[A-Za-z0-9._]{2,24}$/'],
        'youtube'   => ['YouTube', ['youtube.com', 'youtu.be'], 'https://www.youtube.com/@%s', '/^[A-Za-z0-9._-]{3,30}$/'],
        'vk'        => ['VK', ['vk.com', 'vk.ru'], 'https://vk.com/%s', '/^[A-Za-z0-9._]{3,32}$/'],
        'linkedin'  => ['LinkedIn', ['linkedin.com'], 'https://www.linkedin.com/in/%s', '/^[A-Za-z0-9_-]{3,100}$/'],
        'other'     => ['', [], '', null],
    ];
    public const MAX_SOCIALS = 12;

    /**
     * Ссылка или @ник → безопасная https-ссылка; null, если формат не подходит.
     */
    public static function normalizeSocial(string $type, string $raw): ?string
    {
        if (!isset(self::SOCIALS[$type])) {
            return null;
        }
        [, $domains, $tpl, $nickRe] = self::SOCIALS[$type];
        $raw = trim($raw);
        if ($raw === '' || mb_strlen($raw) > 300 || preg_match('/[\x00-\x20\x7f<>"\'`\\\\]/', $raw)) {
            return null;
        }

        if ($type === 'whatsapp' && !preg_match('#^(https?://|wa\.me|(www\.)?whatsapp\.com)#i', $raw)) {
            $n = self::normalizePhone($raw);
            return $n !== null ? 'https://wa.me/' . ltrim($n, '+') : null;
        }

        $looksUrl = (bool)preg_match('#^https?://#i', $raw)
            || (bool)preg_match('#^(www\.)?[a-z0-9-]+(\.[a-z0-9-]+)+(/|$)#i', $raw);
        if ($looksUrl) {
            $url = preg_match('#^https?://#i', $raw) ? $raw : 'https://' . $raw;
            $parts = parse_url($url);
            if ($parts === false || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
                return null;
            }
            $scheme = strtolower((string)($parts['scheme'] ?? ''));
            if ($scheme !== 'http' && $scheme !== 'https') {
                return null;
            }
            $host = strtolower((string)$parts['host']);
            if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $host)) {
                return null;
            }
            if ($domains) {
                $ok = false;
                foreach ($domains as $d) {
                    if ($host === $d || str_ends_with($host, '.' . $d)) {
                        $ok = true;
                        break;
                    }
                }
                if (!$ok) {
                    return null;
                }
            }
            $path = (string)($parts['path'] ?? '');
            // только «безопасные» символы пути/запроса (без javascript:, кавычек и т.п.)
            $query = isset($parts['query']) ? '?' . $parts['query'] : '';
            if (!preg_match('#^[A-Za-z0-9._~%!$&()*+,;=:@/-]*$#', $path) || !preg_match('#^[A-Za-z0-9._~%!$&()*+,;=:@/?-]*$#', $query)) {
                return null;
            }
            if ($domains && ($path === '' || $path === '/') && $query === '') {
                return null; // только главная страница сети — не профиль
            }
            return 'https://' . $host . $path . $query;
        }

        if ($type === 'other') {
            return null; // «Другое» — только ссылка
        }
        $nick = ltrim($raw, '@');
        if ($nickRe === null || !preg_match($nickRe, $nick)) {
            return null;
        }
        return sprintf($tpl, $nick);
    }

    /** @return array<int, array{type: string, url: string}> */
    public static function cleanSocials(mixed $list): array
    {
        $out = [];
        if (!is_array($list)) {
            return $out;
        }
        foreach ($list as $s) {
            if (!is_array($s)) {
                continue;
            }
            $type = (string)($s['type'] ?? '');
            $url = (string)($s['url'] ?? '');
            if (!isset(self::SOCIALS[$type]) || !preg_match('#^https://#', $url) || mb_strlen($url) > 300) {
                continue;
            }
            $out[] = ['type' => $type, 'url' => $url];
        }
        return array_slice($out, 0, self::MAX_SOCIALS);
    }

    /** @return array{phones: string[], emails: string[], addresses: string[], socials: array} */
    public static function decodeContacts(mixed $json): array
    {
        $data = is_string($json) && $json !== '' ? json_decode($json, true) : (is_array($json) ? $json : []);
        if (!is_array($data)) {
            $data = [];
        }
        $clean = static fn($v): array => array_values(array_filter(array_map(
            static fn($x) => is_string($x) ? trim($x) : '',
            is_array($v) ? $v : []
        ), static fn($x) => $x !== ''));
        return [
            'phones'    => $clean($data['phones'] ?? []),
            'emails'    => array_map('strtolower', $clean($data['emails'] ?? [])),
            'addresses' => $clean($data['addresses'] ?? []),
            'socials'   => self::cleanSocials($data['socials'] ?? []),
        ];
    }

    public function getContacts(int $personId): array
    {
        $st = $this->db->prepare('SELECT private_contacts FROM persons WHERE id = ? AND is_deleted = 0 LIMIT 1');
        $st->execute([$personId]);
        return self::decodeContacts($st->fetchColumn() ?: null);
    }

    /**
     * Сохраняет телефоны и email (адреса не трогаем).
     * @param string[] $phones сырые строки
     * @return array{ok: bool, error?: string, contacts?: array}
     */
    public function saveContacts(int $personId, array $phones, string $emailRaw, ?array $socialsRaw = null): array
    {
        // TASK-035: $socialsRaw — список ['type'=>, 'value'=>]; null — соцсети не трогаем
        $cleanSocials = null;
        if ($socialsRaw !== null) {
            $cleanSocials = [];
            foreach ($socialsRaw as $s) {
                $type = (string)($s['type'] ?? '');
                $val = trim((string)($s['value'] ?? ''));
                if ($val === '') {
                    continue;
                }
                $url = self::normalizeSocial($type, $val);
                if ($url === null) {
                    $label = self::SOCIALS[$type][0] ?? '';
                    return ['ok' => false, 'error' => 'Сілтеме қате / Некорректная ссылка' . ($label !== '' ? ' (' . $label . ')' : '') . ': ' . mb_substr($val, 0, 40)];
                }
                $dup = false;
                foreach ($cleanSocials as $c) {
                    if ($c['url'] === $url) {
                        $dup = true;
                    }
                }
                if (!$dup) {
                    $cleanSocials[] = ['type' => $type, 'url' => $url];
                }
            }
            if (count($cleanSocials) > self::MAX_SOCIALS) {
                return ['ok' => false, 'error' => 'Ең көбі ' . self::MAX_SOCIALS . ' сілтеме / Не более ' . self::MAX_SOCIALS . ' ссылок'];
            }
        }

        $cleanPhones = [];
        foreach ($phones as $p) {
            $p = trim((string)$p);
            if ($p === '') {
                continue;
            }
            $n = self::normalizePhone($p);
            if ($n === null) {
                return ['ok' => false, 'error' => 'Телефон қате / Некорректный номер телефона: ' . mb_substr($p, 0, 30)];
            }
            if (!in_array($n, $cleanPhones, true)) {
                $cleanPhones[] = $n;
            }
        }
        if (count($cleanPhones) > self::MAX_PHONES) {
            return ['ok' => false, 'error' => 'Ең көбі ' . self::MAX_PHONES . ' нөмір / Не более ' . self::MAX_PHONES . ' номеров'];
        }

        $email = null;
        if (trim($emailRaw) !== '') {
            $email = self::normalizeEmail($emailRaw);
            if ($email === null) {
                return ['ok' => false, 'error' => 'Email қате / Некорректный e-mail'];
            }
        }

        $old = $this->getContacts($personId);
        $contacts = [
            'phones'    => $cleanPhones,
            'emails'    => $email !== null ? [$email] : [],
            'addresses' => $old['addresses'],
            'socials'   => $cleanSocials ?? $old['socials'],
        ];
        $json = ($contacts['phones'] || $contacts['emails'] || $contacts['addresses'] || $contacts['socials'])
            ? json_encode($contacts, JSON_UNESCAPED_UNICODE)
            : null;

        $st = $this->db->prepare('UPDATE persons SET private_contacts = ?, updated_at = NOW() WHERE id = ? AND is_deleted = 0');
        $st->execute([$json, $personId]);

        if ($email !== null) {
            $this->linkInvitationsByEmail($email, $personId);
        }
        return ['ok' => true, 'contacts' => $contacts, 'old' => $old];
    }

    /**
     * Карточка по email, если он указан ровно у одного человека (иначе null — не гадаем).
     */
    public function resolvePersonIdByEmail(string $email): ?int
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        $found = [];
        $rows = $this->db->query('SELECT id, private_contacts FROM persons WHERE is_deleted = 0 AND private_contacts IS NOT NULL')
            ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            if (in_array($email, self::decodeContacts($r['private_contacts'])['emails'], true)) {
                $found[] = (int)$r['id'];
            }
        }
        return count($found) === 1 ? $found[0] : null;
    }

    /** Привязать приглашение (и учётку, если она уже есть и не привязана) к карточке */
    public function linkInvitationsByEmail(string $email, int $personId): void
    {
        $email = strtolower(trim($email));
        if ($this->hasInvitationPersonColumn()) {
            $this->db->prepare('UPDATE invitations SET person_id = ? WHERE email = ? AND person_id IS NULL')
                ->execute([$personId, $email]);
        }
        // TASK-024: не привязываем карточку, если ею уже владеет другая учётка
        $this->db->prepare(
            'UPDATE users SET person_id = ?, updated_at = NOW()
             WHERE email = ? AND person_id IS NULL
               AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM users WHERE person_id = ? AND email <> ?) x)'
        )->execute([$personId, $email, $personId, $email]);
    }

    /** TASK-024: есть ли у карточки другая учётка (кроме $exceptUserId) */
    public function personHasOtherUser(int $personId, int $exceptUserId): bool
    {
        $st = $this->db->prepare('SELECT 1 FROM users WHERE person_id = ? AND id <> ? LIMIT 1');
        $st->execute([$personId, $exceptUserId]);
        return (bool)$st->fetchColumn();
    }

    /** Привязка по email из админки (совпадение email с контактом карточки) */
    public function linkByEmailMatch(string $email): ?int
    {
        $pid = $this->resolvePersonIdByEmail($email);
        if ($pid !== null) {
            $this->linkInvitationsByEmail($email, $pid);
        }
        return $pid;
    }

    /**
     * Статус приглашения для карточки: none | pending | accepted.
     * Смотрим привязку по person_id и совпадение email.
     * @param string[] $emails
     * @return array{state: string, invitation?: array}
     */
    public function inviteStatus(int $personId, array $emails): array
    {
        // Вошёл на сайт: учётка привязана к карточке или её email совпал
        $st = $this->db->prepare('SELECT id, is_active FROM users WHERE person_id = ? AND is_active = 1 LIMIT 1');
        $st->execute([$personId]);
        if ($st->fetch()) {
            return ['state' => 'accepted'];
        }
        $inv = null;
        if ($this->hasInvitationPersonColumn()) {
            $st = $this->db->prepare("SELECT * FROM invitations WHERE person_id = ? AND status != 'revoked' ORDER BY id DESC LIMIT 1");
            $st->execute([$personId]);
            $inv = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        foreach ($emails as $em) {
            if ($inv !== null) {
                break;
            }
            $st = $this->db->prepare("SELECT * FROM invitations WHERE email = ? AND status != 'revoked' LIMIT 1");
            $st->execute([$em]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            // чужая привязка (приглашение уже у другой карточки) не считается
            if ($row && (empty($row['person_id']) || (int)$row['person_id'] === $personId)) {
                $inv = $row;
            }
            if ($inv === null) {
                $su = $this->db->prepare('SELECT id, person_id FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
                $su->execute([$em]);
                $u = $su->fetch(PDO::FETCH_ASSOC);
                if ($u && (empty($u['person_id']) || (int)$u['person_id'] === $personId)) {
                    return ['state' => 'accepted'];
                }
            }
        }
        if ($inv === null) {
            return ['state' => 'none'];
        }
        return ['state' => $inv['status'] === 'accepted' ? 'accepted' : 'pending', 'invitation' => $inv];
    }
}
