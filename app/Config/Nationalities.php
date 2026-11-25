<?php
declare(strict_types=1);

namespace App\Config;

/**
 * ALT-103: справочник национальностей. В persons.nationality хранится КОД (kazakh, russian…),
 * либо — для «своего варианта» — введённый текст как есть.
 * Метки: [kk, ru (м), ru (ж), en, es].
 */
final class Nationalities
{
    public const KAZAKH = 'kazakh';

    private const LIST = [
        'kazakh'  => ['қазақ',  'казах',   'казашка',   'Kazakh',  'kazajo'],
        'russian' => ['орыс',   'русский', 'русская',   'Russian', 'ruso'],
        'german'  => ['неміс',  'немец',   'немка',     'German',  'alemán'],
        'uzbek'   => ['өзбек',  'узбек',   'узбечка',   'Uzbek',   'uzbeko'],
        'tatar'   => ['татар',  'татарин', 'татарка',   'Tatar',   'tártaro'],
        'spanish' => ['испан',  'испанец', 'испанка',   'Spanish', 'español'],
    ];

    /** Все коды списка. */
    public static function codes(): array
    {
        return array_keys(self::LIST);
    }

    /** Подпись по коду, языку и полу ('M'|'F'). Для неизвестного кода возвращает исходный текст. */
    public static function label(string $value, string $lang, string $gender = 'M'): string
    {
        $value = trim($value);
        $code = self::normalize($value);
        if (!isset(self::LIST[$code])) {
            return $value;
        }
        $l = self::LIST[$code];
        return match ($lang) {
            'ru' => $gender === 'F' ? $l[2] : $l[1],
            'en' => $l[3],
            'es' => $l[4],
            default => $l[0],
        };
    }

    /** Ручной текст («Казах», «русская»…) → код, если совпал с любой подписью; иначе текст без изменений. */
    public static function normalize(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }
        $low = mb_strtolower($v);
        if (isset(self::LIST[$low])) {
            return $low;
        }
        foreach (self::LIST as $code => $labels) {
            foreach ($labels as $l) {
                if (mb_strtolower($l) === $low) {
                    return $code;
                }
            }
        }
        return $v;
    }

    /** Данные для JS: код → [kk, ru_m, ru_f, en, es]. */
    public static function all(): array
    {
        return self::LIST;
    }

    public static function isKazakh(string $value): bool
    {
        return self::normalize($value) === self::KAZAKH;
    }
}
