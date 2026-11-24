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
        // ALT-022: в en/es отчества в строке имени нет (только имя и фамилия);
        // отец показывается отдельной строкой parentLabel().
        if ($lang === 'en' || $lang === 'es') {
            return '';
        }
        $manual = trim((string)($person['middle_name'] ?? ''));
        if ($manual !== '') {
            return $lang === 'ru' ? self::localizeManual($manual, (string)($person['gender'] ?? '')) : $manual;
        }
        if (!$father || trim((string)($father['first_name'] ?? '')) === '') {
            return '';
        }
        return self::generate((string)$father['first_name'], (string)($person['gender'] ?? ''), $lang);
    }

    /**
     * Обе формы сразу (клиент сам переключает язык): ручное значение одинаково для обоих.
     * @param array<string,mixed> $person
     * @param array<string,mixed>|null $father
     * @return array{kk:string,ru:string}
     */
    public static function both(array $person, ?array $father): array
    {
        return [
            'kk' => self::resolve($person, $father, 'kk'),
            'ru' => self::resolve($person, $father, 'ru'),
        ];
    }

    /**
     * ALT-021: сохранённое «X ұлы / X қызы» при русском языке -> русское отчество.
     * Данные в БД не меняются. Другие ручные значения возвращаются как есть.
     */
    public static function localizeManual(string $manual, string $gender): string
    {
        if (!preg_match('/^(.+?)\s+(ұлы|улы|қызы|кызы)$/iu', $manual, $m)) {
            return $manual;
        }
        $male = in_array(mb_strtolower($m[2]), ['ұлы', 'улы'], true);
        $g = strtoupper($gender);
        if ($g === 'M' || $g === 'F') {
            $male = $g === 'M';
        }
        $ru = self::russian(trim($m[1]), $male);
        return $ru ?? $manual;
    }

    /**
     * ALT-022: имя отца (по-казахски/по-русски, как в базе) или '' если отец неизвестен.
     * Ручное «X ұлы/қызы» даёт X; иначе имя отца из записи отца. БД не меняется.
     * @param array<string,mixed> $person
     * @param array<string,mixed>|null $father
     */
    public static function fatherName(array $person, ?array $father): string
    {
        $manual = trim((string)($person['middle_name'] ?? ''));
        if ($manual !== '' && preg_match('/^(.+?)\s+(ұлы|улы|қызы|кызы)$/iu', $manual, $m)) {
            return trim($m[1]);
        }
        if ($father) {
            return trim((string)($father['first_name'] ?? ''));
        }
        return '';
    }

    /**
     * ALT-022: строка карточки для en/es: «Son of Erlan» / «Daughter of Erlan»,
     * «Hijo de Erlan» / «Hija de Erlan». '' — если отец неизвестен, пол не задан
     * или язык не en/es. Имя отца — в той же транслитерации, что и имена людей.
     * @param array<string,mixed> $person
     * @param array<string,mixed>|null $father
     */
    public static function parentLabel(array $person, ?array $father, string $lang): string
    {
        if ($lang !== 'en' && $lang !== 'es') {
            return '';
        }
        $g = strtoupper((string)($person['gender'] ?? ''));
        if ($g !== 'M' && $g !== 'F') {
            return '';
        }
        $name = self::fatherName($person, $father);
        if ($name === '') {
            return '';
        }
        $latin = \App\Core\I18n::latinize($name, $lang);
        if ($lang === 'en') {
            return ($g === 'M' ? 'Son of ' : 'Daughter of ') . $latin;
        }
        return ($g === 'M' ? 'Hijo de ' : 'Hija de ') . $latin;
    }

    /** Русская форма или null, если имя не кириллическое (латиница, цифры). */
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
        $suffV = $male ? 'евич' : 'евна';
        $suffC = $male ? 'ович' : 'овна';
        if ($end === 'у' || $end === 'ю') {
            // Ахмету -> Ахметович (казахская форма дательного/усечённая основа)
            $word = mb_substr($last, 0, -1) . $suffC;
            return trim(implode(' ', array_merge($words, [$word])));
        }
        if ($end === 'ы') {
            // Мусы -> Мусаевич
            $word = mb_substr($last, 0, -1) . 'а' . $suffV;
            return trim(implode(' ', array_merge($words, [$word])));
        }
        if (mb_strpos('аяоеиэ', $end) !== false) {
            // на гласную: Мурата -> Муратаевич, Бейсенби -> Бейсенбиевич
            return trim(implode(' ', array_merge($words, [$last . $suffV])));
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
