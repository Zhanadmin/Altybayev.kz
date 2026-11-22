<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use PDO;

/**
 * TASK-027: обратная связь с владельцем сайта (приветственная страница) и контакты из настроек админки.
 * Контакты — в site_settings (ключи contact_owner_name / contact_email / contact_whatsapp).
 * Письмо уходит через SMTP, только если SMTP_HOST (+ SMTP_FROM или SMTP_USER) заданы в окружении; иначе — только админка.
 */
class FeedbackService
{
    private const MAX_PER_HOUR = 3;
    private const MAX_PER_DAY = 8;

    public function __construct(private PDO $db)
    {
    }

    /** Таблицы создаются при первом обращении (только добавление, данные не затрагиваются). */
    public function ensureSchema(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS `site_settings` (
              `setting_key` VARCHAR(64) NOT NULL,
              `setting_value` TEXT DEFAULT NULL,
              `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS `feedback_messages` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `name` VARCHAR(120) NOT NULL,
              `contact` VARCHAR(190) NOT NULL,
              `message` TEXT NOT NULL,
              `ip_hash` CHAR(64) NOT NULL,
              `is_read` TINYINT(1) NOT NULL DEFAULT 0,
              `mail_sent` TINYINT(1) NOT NULL DEFAULT 0,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_fb_ip_created` (`ip_hash`, `created_at`),
              KEY `idx_fb_read` (`is_read`, `id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @return array{name:string,email:string,whatsapp:string} */
    public static function publicContacts(PDO $db): array
    {
        $out = ['name' => '', 'email' => '', 'whatsapp' => ''];
        try {
            $st = $db->query(
                "SELECT setting_key, setting_value FROM site_settings
                 WHERE setting_key IN ('contact_owner_name','contact_email','contact_whatsapp')"
            );
            foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
                if ($k === 'contact_owner_name') {
                    $out['name'] = (string)$v;
                } elseif ($k === 'contact_email') {
                    $out['email'] = (string)$v;
                } elseif ($k === 'contact_whatsapp') {
                    $out['whatsapp'] = (string)$v;
                }
            }
        } catch (\Throwable $e) {
            // таблицы настроек ещё нет — контактов нет
        }
        return $out;
    }

    public static function normalizeWhatsapp(string $raw): string
    {
        return preg_replace('/\D+/', '', $raw) ?? '';
    }

    /** @param array{name:string,email:string,whatsapp:string} $c */
    public function saveContacts(array $c): void
    {
        $this->ensureSchema();
        $st = $this->db->prepare(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $st->execute(['contact_owner_name', $c['name']]);
        $st->execute(['contact_email', $c['email']]);
        $st->execute(['contact_whatsapp', $c['whatsapp']]);
    }

    public static function ipHash(): string
    {
        return hash('sha256', 'fb|' . ($_SERVER['REMOTE_ADDR'] ?? '0'));
    }

    /** Лимит частоты: true — можно отправлять. */
    public function underLimit(string $ipHash): bool
    {
        $st = $this->db->prepare(
            'SELECT COALESCE(SUM(created_at >= (NOW() - INTERVAL 1 HOUR)), 0) AS h, COUNT(*) AS d
             FROM feedback_messages WHERE ip_hash = ? AND created_at >= (NOW() - INTERVAL 1 DAY)'
        );
        $st->execute([$ipHash]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return (int)($r['h'] ?? 0) < self::MAX_PER_HOUR && (int)($r['d'] ?? 0) < self::MAX_PER_DAY;
    }

    public function save(string $name, string $contact, string $message, string $ipHash): int
    {
        $st = $this->db->prepare(
            'INSERT INTO feedback_messages (name, contact, message, ip_hash) VALUES (?, ?, ?, ?)'
        );
        $st->execute([$name, $contact, $message, $ipHash]);
        return (int)$this->db->lastInsertId();
    }

    public function markMailSent(int $id): void
    {
        $this->db->prepare('UPDATE feedback_messages SET mail_sent = 1 WHERE id = ?')->execute([$id]);
    }

    /** Список для админки (таблицы может ещё не быть → пусто). @return array{rows:array,unread:int} */
    public static function listForAdmin(PDO $db): array
    {
        try {
            $rows = $db->query('SELECT id, name, contact, message, is_read, mail_sent, created_at FROM feedback_messages ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
            $unread = (int)$db->query('SELECT COUNT(*) FROM feedback_messages WHERE is_read = 0')->fetchColumn();
            return ['rows' => $rows, 'unread' => $unread];
        } catch (\Throwable $e) {
            return ['rows' => [], 'unread' => 0];
        }
    }

    /** id = 0 — все обращения. */
    public function markRead(int $id, bool $read): void
    {
        if ($id === 0) {
            $this->db->prepare('UPDATE feedback_messages SET is_read = ?')->execute([$read ? 1 : 0]);
            return;
        }
        $this->db->prepare('UPDATE feedback_messages SET is_read = ? WHERE id = ?')->execute([$read ? 1 : 0, $id]);
    }

    public static function smtpConfigured(): bool
    {
        return (string)Config::get('SMTP_HOST', '') !== ''
            && ((string)Config::get('SMTP_FROM', '') !== '' || (string)Config::get('SMTP_USER', '') !== '');
    }

    /** Отправка письма владельцу. Любая ошибка → false (обращение уже сохранено в БД). */
    public function sendMail(string $to, string $subject, string $body, string $replyTo = ''): bool
    {
        if (!self::smtpConfigured() || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $host = (string)Config::get('SMTP_HOST', '');
        $port = (int)Config::get('SMTP_PORT', '587');
        $user = (string)Config::get('SMTP_USER', '');
        $pass = (string)Config::get('SMTP_PASS', '');
        $secure = strtolower((string)Config::get('SMTP_SECURE', $port === 465 ? 'ssl' : 'tls'));
        $from = (string)Config::get('SMTP_FROM', $user);
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $clean = static fn(string $s): string => trim(str_replace(["\r", "\n"], ' ', $s));
        $fp = null;
        try {
            $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $en, $es, 10);
            if (!$fp) {
                return false;
            }
            stream_set_timeout($fp, 10);
            $read = function () use ($fp): string {
                $all = '';
                while (($line = fgets($fp, 515)) !== false) {
                    $all .= $line;
                    if (strlen($line) < 4 || $line[3] === ' ') {
                        break;
                    }
                }
                return $all;
            };
            $cmd = function (string $c, string $ok) use ($fp, $read): bool {
                fwrite($fp, $c . "\r\n");
                return str_starts_with($read(), $ok);
            };
            if (!str_starts_with($read(), '220')) {
                return false;
            }
            if (!$cmd('EHLO altybayev.kz', '250')) {
                return false;
            }
            if ($secure === 'tls') {
                if (!$cmd('STARTTLS', '220') || !@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    return false;
                }
                if (!$cmd('EHLO altybayev.kz', '250')) {
                    return false;
                }
            }
            if ($user !== '') {
                if (!$cmd('AUTH LOGIN', '334') || !$cmd(base64_encode($user), '334') || !$cmd(base64_encode($pass), '235')) {
                    return false;
                }
            }
            if (!$cmd('MAIL FROM:<' . $from . '>', '250') || !$cmd('RCPT TO:<' . $to . '>', '25') || !$cmd('DATA', '354')) {
                return false;
            }

            $headers = [
                'From: =?UTF-8?B?' . base64_encode('Шежіре') . '?= <' . $from . '>',
                'To: <' . $to . '>',
                'Subject: =?UTF-8?B?' . base64_encode($clean($subject)) . '?=',
                'Date: ' . date('r'),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];
            if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $headers[] = 'Reply-To: <' . $replyTo . '>';
            }
            fwrite($fp, implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body)) . "\r\n.\r\n");
            $ok = str_starts_with($read(), '250');
            @fwrite($fp, "QUIT\r\n");
            return $ok;
        } catch (\Throwable $e) {
            return false;
        } finally {
            if (is_resource($fp)) {
                @fclose($fp);
            }
        }
    }
}
