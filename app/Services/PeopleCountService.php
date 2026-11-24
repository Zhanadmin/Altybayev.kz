<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * ALT-026: единственное место, где считается число людей и союзов для меню, главной, древа и т. д.
 * Считаются только неудалённые (persons.is_deleted = 0); союзы — только если оба супруга живые.
 * Результат хранится лишь на время запроса (кэша между запросами нет, поэтому после удаления
 * и восстановления человека сбрасывать нечего; reset() нужен тестам).
 */
final class PeopleCountService
{
    private static ?int $people = null;
    private static ?int $families = null;

    public static function people(): int
    {
        if (self::$people === null) {
            try {
                self::$people = (int)Database::getConnection()
                    ->query('SELECT COUNT(*) FROM `persons` WHERE `is_deleted` = 0')->fetchColumn();
            } catch (\Throwable $e) {
                self::$people = 0;
            }
        }
        return self::$people;
    }

    public static function families(): int
    {
        if (self::$families === null) {
            try {
                self::$families = (int)Database::getConnection()->query(
                    'SELECT COUNT(*) FROM `marriages` m
                       JOIN `persons` ph ON ph.id = m.husband_id AND ph.is_deleted = 0
                       JOIN `persons` pw ON pw.id = m.wife_id AND pw.is_deleted = 0'
                )->fetchColumn();
            } catch (\Throwable $e) {
                self::$families = 0;
            }
        }
        return self::$families;
    }

    public static function reset(): void
    {
        self::$people = null;
        self::$families = null;
    }
}
