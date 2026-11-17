<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * ALT-002: подробное место упокоения. Работает и до применения миграции:
 * если столбцов нет — молча ничего не делает (остаётся только death_place).
 */
final class PersonBurialService
{
    public const TEXT_FIELDS = [
        'burial_settlement' => 255,
        'burial_cemetery'   => 255,
        'burial_address'    => 255,
        'burial_plot'       => 255,
    ];

    private static ?bool $ready = null;

    public function __construct(private PDO $db) {}

    public function isReady(): bool
    {
        if (self::$ready === null) {
            try {
                self::$ready = (bool)$this->db->query("SHOW COLUMNS FROM `persons` LIKE 'burial_lat'")->fetchColumn();
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }
        return self::$ready;
    }

    /** Координата или null, если значение вне диапазона. */
    public static function cleanCoord(mixed $v, float $limit): ?float
    {
        $s = trim((string)$v);
        if ($s === '' || !is_numeric($s)) {
            return null;
        }
        $f = (float)$s;
        return abs($f) <= $limit ? round($f, 6) : null;
    }

    public function save(int $personId, array $in, bool $isDeceased): void
    {
        if (!$this->isReady()) {
            return;
        }
        $vals = [];
        foreach (self::TEXT_FIELDS as $f => $max) {
            $v = $isDeceased ? trim((string)($in[$f] ?? '')) : '';
            $vals[] = $v === '' ? null : mb_substr($v, 0, $max);
        }
        $lat = $isDeceased ? self::cleanCoord($in['burial_lat'] ?? '', 90.0) : null;
        $lng = $isDeceased ? self::cleanCoord($in['burial_lng'] ?? '', 180.0) : null;
        if ($lat === null || $lng === null) {
            $lat = $lng = null;
        }
        $vals[] = $lat;
        $vals[] = $lng;
        $vals[] = $personId;
        $this->db->prepare(
            "UPDATE `persons` SET `burial_settlement` = ?, `burial_cemetery` = ?, `burial_address` = ?, `burial_plot` = ?, `burial_lat` = ?, `burial_lng` = ? WHERE `id` = ?"
        )->execute($vals);
    }
}
