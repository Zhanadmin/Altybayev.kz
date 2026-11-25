<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use PDO;

/**
 * ALT-104: вход только по одобрению. Незнакомый Google-аккаунт не попадает внутрь:
 * создаётся (или обновляется) запрос на доступ, админ получает его на сайте и письмом.
 * Одобрение создаёт приглашение (invitations) — дальше работает обычный вход через Google.
 * Таблицы создаются при первом обращении (только добавление, существующие данные не затрагиваются).
 */
class AccessRequestService
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'blocked'];
    private const WINDOW_MINUTES = 10;
    private const MAX_ATTEMPTS_PER_WINDOW = 12;

    public function __construct(private PDO $db)
    {
    }

    public function ensureSchema(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS `access_requests` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `email` VARCHAR(191) NOT NULL,
              `name` VARCHAR(190) NOT NULL DEFAULT '',
              `google_id` VARCHAR(64) DEFAULT NULL,
              `avatar_url` VARCHAR(500) DEFAULT NULL,
              `message` VARCHAR(1000) DEFAULT NULL,
              `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
              `ip` VARCHAR(45) NOT NULL DEFAULT '',
              `place` VARCHAR(190) NOT NULL DEFAULT '',
              `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
              `attempts` INT UNSIGNED NOT NULL DEFAULT 1,
              `mail_sent` TINYINT(1) NOT NULL DEFAULT 0,
              `first_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `last_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `decided_by` INT UNSIGNED DEFAULT NULL,
              `decided_at` DATETIME DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_access_email` (`email`),
              KEY `idx_access_status` (`status`, `last_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS `access_attempts` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `email` VARCHAR(191) NOT NULL DEFAULT '',
              `ip` VARCHAR(45) NOT NULL DEFAULT '',
              `place` VARCHAR(190) NOT NULL DEFAULT '',
              `result` VARCHAR(24) NOT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_att_ip` (`ip`, `created_at`),
              KEY `idx_att_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public static function clientIp(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    /** Примерное место: страна/город из заголовков Cloudflare (если сайт за ним), иначе пусто. */
    public static function place(): string
    {
        $clean = static fn(string $s): string => mb_substr(trim(preg_replace('/[^\p{L}\p{N} ,.\-]/u', '', $s) ?? ''), 0, 80);
        $country = $clean((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''));
        $city = $clean((string)($_SERVER['HTTP_CF_IPCITY'] ?? ''));
        return trim($city . ($city !== '' && $country !== '' ? ', ' : '') . $country);
    }

    /** Лимит частоты попыток входа с одного IP. true — можно продолжать. */
    public function underLimit(string $ip): bool
    {
        $this->ensureSchema();
        $st = $this->db->prepare(
            'SELECT COUNT(*) FROM access_attempts WHERE ip = ? AND created_at >= (NOW() - INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE)'
        );
        $st->execute([$ip]);
        return (int)$st->fetchColumn() < self::MAX_ATTEMPTS_PER_WINDOW;
    }

    public function logAttempt(string $email, string $result): void
    {
        $this->ensureSchema();
        $this->db->prepare('INSERT INTO access_attempts (email, ip, place, result) VALUES (?, ?, ?, ?)')
            ->execute([mb_substr($email, 0, 191), self::clientIp(), self::place(), $result]);
        // журнал не растёт бесконечно
        if (random_int(1, 50) === 1) {
            $this->db->exec('DELETE FROM access_attempts WHERE created_at < (NOW() - INTERVAL 90 DAY)');
        }
    }

    /**
     * Создаёт или обновляет запрос от незнакомого человека.
     * @return array{id:int,status:string,is_new:bool,row:array}
     */
    public function submit(string $email, string $name, ?string $googleId, ?string $avatar): array
    {
        $this->ensureSchema();
        $email = strtolower(trim($email));
        $ip = self::clientIp();
        $place = self::place();
        $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $name = mb_substr($name, 0, 190);
        $avatar = $avatar !== null ? mb_substr($avatar, 0, 500) : null;

        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare('SELECT * FROM access_requests WHERE email = ? LIMIT 1 FOR UPDATE');
            $st->execute([$email]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            $isNew = false;

            if (!$row) {
                $this->db->prepare(
                    'INSERT INTO access_requests (email, name, google_id, avatar_url, status, ip, place, user_agent)
                     VALUES (?, ?, ?, ?, \'pending\', ?, ?, ?)'
                )->execute([$email, $name, $googleId, $avatar, $ip, $place, $ua]);
                $id = (int)$this->db->lastInsertId();
                $status = 'pending';
                $isNew = true;
            } else {
                $id = (int)$row['id'];
                $status = (string)$row['status'];
                // заблокированный: только счётчик и время, без уведомлений; прочее не трогаем
                $newStatus = $status === 'rejected' || $status === 'approved' ? 'pending' : $status;
                if ($status === 'blocked') {
                    $this->db->prepare('UPDATE access_requests SET attempts = attempts + 1, last_at = NOW(), ip = ? WHERE id = ?')
                        ->execute([$ip, $id]);
                } else {
                    // повторная попытка обновляет существующий запрос; после отказа он снова становится «ожидает»
                    $this->db->prepare(
                        'UPDATE access_requests SET name = ?, google_id = ?, avatar_url = ?, status = ?, ip = ?, place = ?,
                                user_agent = ?, attempts = attempts + 1, last_at = NOW() WHERE id = ?'
                    )->execute([$name, $googleId, $avatar, $newStatus, $ip, $place, $ua, $id]);
                    if ($newStatus !== $status) {
                        $isNew = true; // для админа это снова новый запрос — уведомим
                        $this->db->prepare('UPDATE access_requests SET mail_sent = 0, decided_at = NULL, decided_by = NULL WHERE id = ?')->execute([$id]);
                    }
                    $status = $newStatus;
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return ['id' => $id, 'status' => $status, 'is_new' => $isNew, 'row' => $this->find($id) ?? []];
    }

    public function find(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM access_requests WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Сообщение «кто я вам» от самого человека (до 500 символов), только пока запрос не заблокирован. */
    public function saveMessage(int $id, string $message): bool
    {
        $message = mb_substr(trim($message), 0, 500);
        $st = $this->db->prepare("UPDATE access_requests SET message = ?, last_at = NOW() WHERE id = ? AND status IN ('pending','rejected')");
        $st->execute([$message === '' ? null : $message, $id]);
        return $st->rowCount() > 0;
    }

    public static function pendingCount(PDO $db): int
    {
        try {
            return (int)$db->query("SELECT COUNT(*) FROM access_requests WHERE status = 'pending'")->fetchColumn();
        } catch (\Throwable $e) {
            return 0; // таблицы ещё нет
        }
    }

    /** @return array{requests:array,attempts:array} */
    public function listForAdmin(): array
    {
        $this->ensureSchema();
        $requests = $this->db->query(
            "SELECT * FROM access_requests
             ORDER BY (status = 'pending') DESC, last_at DESC LIMIT 300"
        )->fetchAll(PDO::FETCH_ASSOC);
        $attempts = $this->db->query(
            'SELECT email, ip, place, result, created_at FROM access_attempts ORDER BY id DESC LIMIT 100'
        )->fetchAll(PDO::FETCH_ASSOC);
        return ['requests' => $requests, 'attempts' => $attempts];
    }

    /** Адрес админа для уведомлений: контакт владельца из настроек, иначе SUPER_ADMIN_EMAIL. */
    public static function adminMail(PDO $db): string
    {
        $c = FeedbackService::publicContacts($db);
        if ($c['email'] !== '' && filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
            return $c['email'];
        }
        return strtolower(trim((string)Config::get('SUPER_ADMIN_EMAIL', 'zhandos.alt06@gmail.com')));
    }

    /** Письмо админу о новом запросе. Любая ошибка → false (запрос уже сохранён и виден на сайте). */
    public function notifyAdmin(array $row): bool
    {
        try {
            $fb = new FeedbackService($this->db);
            $base = rtrim((string)Config::get('APP_URL', 'https://altybayev.kz'), '/');
            $body = "Новый запрос на доступ к шежіре\n\n"
                . 'Имя: ' . $row['name'] . "\n"
                . 'E-mail: ' . $row['email'] . "\n"
                . 'Время: ' . $row['last_at'] . "\n"
                . 'IP: ' . $row['ip'] . ($row['place'] !== '' ? ' (' . $row['place'] . ')' : '') . "\n"
                . 'Сообщение: ' . ($row['message'] !== null && $row['message'] !== '' ? $row['message'] : '—') . "\n\n"
                . 'Рассмотреть: ' . $base . "/admin/access-requests\n";
            $ok = $fb->sendMail(self::adminMail($this->db), 'Шежіре: запрос на доступ от ' . $row['name'], $body);
            if ($ok) {
                $this->db->prepare('UPDATE access_requests SET mail_sent = 1 WHERE id = ?')->execute([(int)$row['id']]);
            }
            return $ok;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
