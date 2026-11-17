<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * TASK-036: история проживания и поля религия/национальность/образование.
 * Работает и до применения миграции: если столбцов/таблицы нет — молча ничего не делает.
 */
final class PersonResidenceService
{
    public const PROFILE_FIELDS = ['religion' => 150, 'nationality' => 150, 'education' => 255];

    private static array $cache = [];

    public function __construct(private PDO $db) {}

    public function hasTable(): bool
    {
        if (!isset(self::$cache['table'])) {
            try {
                self::$cache['table'] = (bool)$this->db->query("SHOW TABLES LIKE 'person_residences'")->fetchColumn();
            } catch (\Throwable $e) {
                self::$cache['table'] = false;
            }
        }
        return self::$cache['table'];
    }

    public function hasProfileColumns(): bool
    {
        if (!isset(self::$cache['cols'])) {
            try {
                self::$cache['cols'] = (bool)$this->db->query("SHOW COLUMNS FROM `persons` LIKE 'religion'")->fetchColumn();
            } catch (\Throwable $e) {
                self::$cache['cols'] = false;
            }
        }
        return self::$cache['cols'];
    }

    /** @return array<int,array{id:int,place:string,year_from:?string,year_to:?string,is_current:int}> */
    public function list(int $personId): array
    {
        if (!$this->hasTable()) {
            return [];
        }
        $st = $this->db->prepare("SELECT `id`, `place`, `year_from`, `year_to`, `is_current` FROM `person_residences` WHERE `person_id` = ? ORDER BY `sort_order` ASC, `id` ASC");
        $st->execute([$personId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Текущее место: помеченное «қазір», иначе последнее в списке. */
    public static function current(array $rows): ?array
    {
        foreach ($rows as $r) {
            if (!empty($r['is_current'])) {
                return $r;
            }
        }
        return $rows ? $rows[count($rows) - 1] : null;
    }

    /** Сохраняет три текстовых поля, если столбцы есть. */
    public function saveProfile(int $personId, array $in): void
    {
        if (!$this->hasProfileColumns()) {
            return;
        }
        $vals = [];
        foreach (self::PROFILE_FIELDS as $f => $max) {
            $v = trim((string)($in[$f] ?? ''));
            $vals[] = $v === '' ? null : mb_substr($v, 0, $max);
        }
        $vals[] = $personId;
        $this->db->prepare("UPDATE `persons` SET `religion` = ?, `nationality` = ?, `education` = ? WHERE `id` = ?")->execute($vals);
    }

    /**
     * Полная замена списка переездов. $json — JSON-массив [{place, year_from, year_to, is_current}].
     * Возвращает false, если JSON некорректен (тогда ничего не меняется).
     */
    public function replace(int $personId, string $json): bool
    {
        if (!$this->hasTable()) {
            return true;
        }
        $arr = json_decode($json, true);
        if (!is_array($arr)) {
            return false;
        }
        $clean = [];
        $hasCurrent = false;
        foreach (array_slice($arr, 0, 50) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $place = trim((string)($r['place'] ?? ''));
            if ($place === '') {
                continue;
            }
            $yf = preg_match('/^\d{3,4}$/', trim((string)($r['year_from'] ?? ''))) ? trim((string)$r['year_from']) : null;
            $yt = preg_match('/^\d{3,4}$/', trim((string)($r['year_to'] ?? ''))) ? trim((string)$r['year_to']) : null;
            $cur = !empty($r['is_current']) && !$hasCurrent ? 1 : 0;
            if ($cur) {
                $hasCurrent = true;
                $yt = null;
            }
            $clean[] = [mb_substr($place, 0, 255), $yf, $yt, $cur];
        }
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM `person_residences` WHERE `person_id` = ?")->execute([$personId]);
            $ins = $this->db->prepare("INSERT INTO `person_residences` (`person_id`, `place`, `year_from`, `year_to`, `is_current`, `sort_order`) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($clean as $i => $c) {
                $ins->execute([$personId, $c[0], $c[1], $c[2], $c[3], $i]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return true;
    }
}
