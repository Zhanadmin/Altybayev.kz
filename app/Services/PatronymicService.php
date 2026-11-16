<?php
declare(strict_types=1);

namespace App\Services;

/**
 * TASK-028: отчество (әкесінің аты) автоматически по имени отца.
 * Ручное значение (persons.middle_name) всегда имеет приоритет и не перезаписывается.
 */
class PatronymicService
{
    /** Казахские буквы -> русские (для русской формы отчества). */
    private const KK_TO_RU = [
        'Ғ' => 'Г', 'ғ' => 'г', 'Қ' => 'К', 'қ' => 'к', 'Ң' => 'Н', 'ң' => 'н',
        'Ә' => 'А', 'ә' => 'а', 'Ө' => 'О', 'ө' => 'о', 'Ү' => 'У', 'ү' => 'у',
        'Ұ' => 'У', 'ұ' => 'у', 'Һ' => 'Х', 'һ' => 'х', 'І' => 'И', 'і' => 'и',
    ];

    /** Словарь исключений: имя отца (нижний регистр, по-русски) => [муж., жен.]. */
    private const RU_EXCEPTIONS = [
        'илья'   => ['Ильич', 'Ильинична'],
        'лука'   => ['Лукич', 'Лукинична'],
        'фома'   => ['Фомич', 'Фоминична'],
        'никита' => ['Никитич', 'Никитична'],
        'кузьма' => ['Кузьмич', 'Кузьминична'],
        'лев'    => ['Львович', 'Львовна'],
        'павел'  => ['Павлович', 'Павловна'],
    ];

    /**
     * Отчество по имени отца. '' — если отца/имени нет.
     * $gender: 'M' | 'F' (иное — считается мужским? нет: пустая строка).
     */
    public static function generate(string $fatherFirstName, string $gender, string $lang): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $fatherFirstName) ?? '');
        if ($name === '') {
            return '';
        }
        $g = strtoupper($gender);
        if ($g !== 'M' && $g !== 'F') {
            return '';
        }

        if ($lang === 'ru') {
            $ru = self::russian($name, $g === 'M');
            if ($ru !== null) {
                return $ru;
            }
        }
        return $name . ($g === 'M' ? ' ұлы' : ' қызы');
    }

    /**
     * Итоговое отчество человека: ручное значение, иначе авто по отцу, иначе ''.
     * @param array<string,mixed> $person
     * @param array<string,mixed>|null $father
     */
    public static function resolve(array $person, ?array $father, string $lang): string
    {
        $manual = trim((string)($person['middle_name'] ?? ''));
        if ($manual !== '') {
            return $manual;
        }
        if (!$father || trim((string)($father['first_name'] ?? '')) === '') {
            return '';
        }
        return self::generate((string)$father['first_name'], (string)($person['gender'] ?? ''), $lang);
    }

    /** Русская форма или null, если по-русски неестественно (имя на гласную и т.п.). */
    private static function russian(string $name, bool $male): ?string
    {
        $words = explode(' ', strtr($name, self::KK_TO_RU));
        $last = array_pop($words);
        $lower = mb_strtolower($last);

        if (isset(self::RU_EXCEPTIONS[$lower])) {
            $suffix = self::RU_EXCEPTIONS[$lower][$male ? 0 : 1];
            return trim(implode(' ', array_merge($words, [$suffix])));
        }

        $end = mb_substr($lower, -1);
        if (!preg_match('/^[а-яё\-]+$/u', $lower)) {
            return null; // латиница, цифры и т.п. — показываем казахскую форму
        }
        if (mb_strpos('аяоеиыуэю', $end) !== false) {
            return null; // на гласную: «Нурлана-ович» неестественно
        }

        if ($end === 'й' || $end === 'ь') {
            $stem = mb_substr($last, 0, -1);
            $suffix = $male ? 'евич' : 'евна';
            // Сарсенбай -> Сарсенбаевич (й сохраняется как «е» после гласной: Сарсенба+евич)
            $word = $stem . $suffix;
        } elseif (mb_strpos('жшчщц', $end) !== false) {
            $word = $last . ($male ? 'евич' : 'евна');
        } else {
            $word = $last . ($male ? 'ович' : 'овна');
        }
        return trim(implode(' ', array_merge($words, [$word])));
    }
}
