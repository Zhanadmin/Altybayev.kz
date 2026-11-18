<?php

declare(strict_types=1);

namespace App\Core;

class I18n
{
    private static ?string $currentLang = null;

    /** ALT-007: поддерживаемые языки интерфейса. */
    public const LANGS = ['kk', 'ru', 'en'];

    /**
     * Получить текущий язык ('kk', 'ru' или 'en')
     */
    public static function lang(): string
    {
        if (self::$currentLang !== null) {
            return self::$currentLang;
        }

        // 1. Проверяем параметр GET ?lang=
        if (!empty($_GET['lang']) && in_array($_GET['lang'], self::LANGS, true)) {
            self::$currentLang = (string)$_GET['lang'];
            self::setCookieLang(self::$currentLang);
            return self::$currentLang;
        }

        // 2. Проверяем Cookie
        if (!empty($_COOKIE['shezhire_lang']) && in_array($_COOKIE['shezhire_lang'], self::LANGS, true)) {
            self::$currentLang = (string)$_COOKIE['shezhire_lang'];
            return self::$currentLang;
        }

        // 3. Проверяем сессию
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['shezhire_lang']) && in_array($_SESSION['shezhire_lang'], self::LANGS, true)) {
            self::$currentLang = (string)$_SESSION['shezhire_lang'];
            return self::$currentLang;
        }

        // По умолчанию — казахский язык
        self::$currentLang = 'kk';
        return self::$currentLang;
    }

    /**
     * Установить язык приложения
     */
    public static function setLang(string $lang): void
    {
        $lang = in_array($lang, self::LANGS, true) ? $lang : 'kk';
        self::$currentLang = $lang;
        self::setCookieLang($lang);
    }

    private static function setCookieLang(string $lang): void
    {
        $_COOKIE['shezhire_lang'] = $lang;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['shezhire_lang'] = $lang;
        }
        if (!headers_sent()) {
            setcookie('shezhire_lang', $lang, [
                'expires'  => time() + (365 * 86400),
                'path'     => '/',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
    }

    /**
     * Название сайта в соответствии с требованиями Жандоса (13.11):
     * Қаз: «Шежіре», Рус: «Родословная» (без фамилии!)
     */
    public static function siteTitle(): string
    {
        return match (self::lang()) {
            'ru' => 'Родословная',
            'en' => 'Family Tree',
            default => 'Шежіре',
        };
    }

    /** ALT-007: удобные проверки языка в шаблонах и сервисах. */
    public static function isEn(): bool
    {
        return self::lang() === 'en';
    }

    public static function isRu(): bool
    {
        return self::lang() === 'ru';
    }

    /**
     * Полный перевод строки по ключу
     */
    public static function t(string $key, array $params = []): string
    {
        $lang = self::lang();
        $dict = self::getDictionary();

        if ($lang === 'en') {
            $text = $dict[$key]['en'] ?? self::translateText((string)($dict[$key]['ru'] ?? $dict[$key]['kk'] ?? $key));
        } else {
            $text = $dict[$key][$lang] ?? $dict[$key]['kk'] ?? $key;
        }

        if (!empty($params)) {
            foreach ($params as $k => $v) {
                $text = str_replace('{' . $k . '}', (string)$v, $text);
            }
        }

        return $text;
    }

    /**
     * Склонение казахского предиката 2-го лица вежливого обращения («Сіз — ...сыз / ...сіз»)
     * на основе гармонии гласных (сингармонизм) и конечного звука
     */
    public static function kazakhYouAre(string $name): string
    {
        if (trim($name) === '') {
            return 'Сіз';
        }

        $lastChar = mb_substr($name, -1);
        $vowelsHard = ['а', 'о', 'ұ', 'ы', 'я'];
        $vowelsSoft = ['ә', 'ө', 'ү', 'і', 'е', 'э', 'и', 'ю'];

        $isSoft = false;
        $len = mb_strlen($name);
        for ($i = $len - 1; $i >= 0; $i--) {
            $ch = mb_strtolower(mb_substr($name, $i, 1));
            if (in_array($ch, $vowelsSoft, true)) {
                $isSoft = true;
                break;
            }
            if (in_array($ch, $vowelsHard, true)) {
                $isSoft = false;
                break;
            }
        }

        $suffix = $isSoft ? 'сіз' : 'сыз';
        return "Сіз — {$name}{$suffix}";
    }

    /**
     * Қазақ тілінде ілік септік жалғауларын дұрыс жалғау (Ілік септік: кімнің? ненің?)
     * Мысалы: Ғабит -> Ғабиттің, Жандос -> Жандостың, Кенен -> Кененнің, Алтыбай -> Алтыбайдың
     */
    public static function kazakhGenitive(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $lastChar = mb_strtolower(mb_substr($name, -1));
        $vowelsHard = ['а', 'о', 'ұ', 'ы', 'я'];
        $vowelsSoft = ['ә', 'ө', 'ү', 'і', 'е', 'э', 'и', 'ю'];

        $isSoft = false;
        $len = mb_strlen($name);
        for ($i = $len - 1; $i >= 0; $i--) {
            $ch = mb_strtolower(mb_substr($name, $i, 1));
            if (in_array($ch, $vowelsSoft, true)) {
                $isSoft = true;
                break;
            }
            if (in_array($ch, $vowelsHard, true)) {
                $isSoft = false;
                break;
            }
        }

        $deaf = ['к', 'қ', 'п', 'с', 'т', 'ш', 'щ', 'ф', 'х', 'ц', 'ч', 'h'];
        $sonor = ['б', 'в', 'г', 'ғ', 'д', 'ж', 'з', 'л', 'р', 'й', 'у'];

        if (in_array($lastChar, $deaf, true)) {
            return $name . ($isSoft ? 'тің' : 'тың');
        }
        if (in_array($lastChar, $sonor, true)) {
            return $name . ($isSoft ? 'дің' : 'дың');
        }
        return $name . ($isSoft ? 'нің' : 'ның');
    }

    /**
     * Форматирование термина родства:
     * На казахском — чистый казахский термин.
     * На русском — казахский термин с русским переводом в скобках, например: «Әкесі (Отец)».
     */
    public static function formatKinship(string $termKk, ?string $termRu = null): string
    {
        $termKk = trim($termKk);
        $termRu = trim((string)$termRu);

        if (self::lang() === 'kk') {
            return $termKk;
        }

        // ALT-007: английский — переводим русскую подпись, казахский термин оставляем в скобках,
        // если он был частью term_ru (например, «Әке (Отец)» → «Әке (Father)»).
        if (self::lang() === 'en') {
            $base = $termRu !== '' ? $termRu : $termKk;
            return self::translateText($base);
        }

        // TASK-020: term_ru уже может начинаться с казахского термина — без дубля «Іні (Іні (…))»
        if ($termRu !== '' && mb_stripos($termRu, $termKk) === 0 && $termKk !== '') {
            return $termRu;
        }

        if ($termRu !== '' && mb_strtolower($termRu) !== mb_strtolower($termKk)) {
            return "{$termKk} ({$termRu})";
        }

        return $termKk;
    }

    /**
     * Жалпы күндерді тілге сай пішімдеу (сервердің setlocale параметрлеріне тәуелсіз)
     * Форматирование дат по языку:
     * Рус — «13 ноября 1985»
     * Қаз — «1985 жылғы 13 қараша»
     */
    public static function formatDate(mixed $dateVal, ?string $lang = null): string
    {
        if (empty($dateVal)) {
            return match (self::lang()) {
                'ru' => 'не указана',
                'en' => 'not specified',
                default => 'көрсетілмеген',
            };
        }

        $lang = $lang ?? self::lang();
        $raw = trim((string)$dateVal);

        // Если передан только год (например "1985")
        if (preg_match('/^[0-9]{4}$/', $raw)) {
            return match ($lang) {
                'ru' => "{$raw} г.",
                'en' => (string)$raw,
                default => "{$raw} жыл",
            };
        }

        $monthsRu = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
        ];

        $monthsKk = [
            1 => 'қаңтар', 2 => 'ақпан', 3 => 'наурыз', 4 => 'сәуір',
            5 => 'мамыр', 6 => 'маусым', 7 => 'шілде', 8 => 'тамыз',
            9 => 'қыркүйек', 10 => 'қазан', 11 => 'қараша', 12 => 'желтоқсан'
        ];

        $monthsEn = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];

        $year = null;
        $month = null;
        $day = null;

        if (preg_match('/^([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/', $raw, $m)) {
            $year = (int)$m[1];
            $month = (int)$m[2];
            $day = (int)$m[3];
        } elseif (preg_match('/^([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{4})/', $raw, $m)) {
            $day = (int)$m[1];
            $month = (int)$m[2];
            $year = (int)$m[3];
        } else {
            $ts = strtotime($raw);
            if ($ts !== false && $ts > 0) {
                $year = (int)date('Y', $ts);
                $month = (int)date('n', $ts);
                $day = (int)date('j', $ts);
            }
        }

        if ($year && $month && $day && isset($monthsRu[$month])) {
            if ($lang === 'ru') {
                return "{$day} {$monthsRu[$month]} {$year}";
            }
            if ($lang === 'en') {
                return "{$day} {$monthsEn[$month]} {$year}";
            }
            return "{$year} жылғы {$day} {$monthsKk[$month]}";
        }

        if ($year && $month && isset($monthsRu[$month])) {
            return match ($lang) {
                'ru' => "{$monthsRu[$month]} {$year} г.",
                'en' => "{$monthsEn[$month]} {$year}",
                default => "{$year} жылғы {$monthsKk[$month]}",
            };
        }

        if ($year) {
            return match ($lang) {
                'ru' => "{$year} г.",
                'en' => (string)$year,
                default => "{$year} жыл",
            };
        }

        return $raw;
    }

    /**
     * TASK-013 (шаг 10): разбор даты рождения/смерти в [год, месяц, день] (месяц/день — null, если неизвестны).
     */
    private static function lifeParts(mixed $date, mixed $year): ?array
    {
        $raw = trim((string)($date ?? ''));
        if ($raw !== '' && $raw !== '0000-00-00') {
            if (preg_match('/^([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/', $raw, $m) && (int)$m[2] > 0 && (int)$m[3] > 0) {
                return [(int)$m[1], (int)$m[2], (int)$m[3]];
            }
            if (preg_match('/^([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{4})/', $raw, $m)) {
                return [(int)$m[3], (int)$m[2], (int)$m[1]];
            }
            if (preg_match('/^([0-9]{4})/', $raw, $m)) {
                return [(int)$m[1], null, null];
            }
            // TASK-023: текстовые даты («8 марта 1979года», «7 июл 2007», «23 MAY 1998»)
            if (preg_match('/(?<![0-9])([0-9]{4})(?![0-9])/u', $raw, $ym)) {
                $yy = (int)$ym[1];
                $low = mb_strtolower($raw, 'UTF-8');
                $prefixes = [
                    1 => ['янв', 'jan', 'қаң'], 2 => ['фев', 'feb', 'ақп'], 3 => ['мар', 'mar', 'нау'],
                    4 => ['апр', 'apr', 'сәу'], 5 => ['мая', 'май', 'may', 'мам'], 6 => ['июн', 'jun', 'мау'],
                    7 => ['июл', 'jul', 'шіл'], 8 => ['авг', 'aug', 'там'], 9 => ['сен', 'sep', 'қыр'],
                    10 => ['окт', 'oct', 'қаз'], 11 => ['ноя', 'nov', 'қар'], 12 => ['дек', 'dec', 'жел'],
                ];
                $month = null;
                if (preg_match('/(?<![0-9])([0-9]{1,2})\s+([^\s0-9.,]+)/u', $low, $dm)) {
                    $dd = (int)$dm[1];
                    foreach ($prefixes as $mi => $list) {
                        foreach ($list as $pf) {
                            if (str_starts_with($dm[2], $pf)) { $month = $mi; break 2; }
                        }
                    }
                    if ($month !== null && $dd >= 1 && $dd <= 31) {
                        return [$yy, $month, $dd];
                    }
                }
                return [$yy, null, null];
            }
        }
        $y = (int)($year ?? 0);
        return $y > 0 ? [$y, null, null] : null;
    }

    /** Полных лет между двумя датами; если день/месяц неизвестны — по годам (приблизительно). */
    private static function yearsBetween(array $from, array $to): int
    {
        $n = $to[0] - $from[0];
        if ($from[1] !== null && $to[1] !== null) {
            $fd = (int)$from[2];
            // TASK-023: 29 февраля в невисокосный год отмечается 28 февраля (как в MemorialService)
            if ($from[1] === 2 && $fd === 29 && !checkdate(2, 29, (int)$to[0])) {
                $fd = 28;
            }
            if ($to[1] < $from[1] || ($to[1] === $from[1] && (int)$to[2] < $fd)) {
                $n--;
            }
        }
        return $n;
    }

    /** TASK-023: «сегодня» как [год, месяц, день] в часовом поясе Asia/Almaty (или из заданной строки даты). */
    private static function todayParts(?string $today = null): array
    {
        $tz = new \DateTimeZone('Asia/Almaty');
        try {
            $d = $today !== null ? new \DateTimeImmutable($today, $tz) : new \DateTimeImmutable('now', $tz);
        } catch (\Throwable $e) {
            $d = new \DateTimeImmutable('now', $tz);
        }
        return [(int)$d->format('Y'), (int)$d->format('n'), (int)$d->format('j')];
    }

    /** TASK-023: ранняя дата смерти среди супругов (строка для togetherLabel) или null. */
    public static function earliestDeath(?array $a, ?array $b): ?string
    {
        $best = null;
        $bestKey = null;
        foreach ([$a, $b] as $p) {
            if (!$p) continue;
            $pt = self::lifeParts($p['death_date'] ?? null, $p['death_year'] ?? null);
            if (!$pt) continue;
            $key = $pt[0] * 10000 + ($pt[1] ?? 0) * 100 + (int)($pt[2] ?? 0);
            if ($bestKey === null || $key < $bestKey) {
                $bestKey = $key;
                $best = $pt[1] !== null ? sprintf('%04d-%02d-%02d', $pt[0], $pt[1], (int)$pt[2]) : (string)$pt[0];
            }
        }
        return $best;
    }

    /** Русское склонение: 1 год, 2 года, 5 лет. */
    public static function ruYears(int $n): string
    {
        $m10 = $n % 10;
        $m100 = $n % 100;
        if ($m10 === 1 && $m100 !== 11) return "{$n} год";
        if ($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14)) return "{$n} года";
        return "{$n} лет";
    }

    /**
     * TASK-013 (шаг 10): короткая подпись «возраст / годы жизни» для карточек, лент и таблиц.
     * Живой: «38 жас» / «38 лет» (если известен только год — «≈38 жас»).
     * Ушедший: «1932–2005 · 73 жыл ғұмыр» / «1932–2005 · прожил 73 года».
     * Возвращает ['short' => подпись, 'title' => полная дата для подсказки]; пустые строки, если данных нет.
     */
    public static function lifeLabel(array $p, ?string $lang = null, ?string $today = null): array
    {
        $lang = $lang ?? self::lang();
        $ru = ($lang === 'ru');
        $en = ($lang === 'en');
        $birth = self::lifeParts($p['birth_date'] ?? null, $p['birth_year'] ?? null);
        $death = self::lifeParts($p['death_date'] ?? null, $p['death_year'] ?? null);
        $deceased = !empty($p['is_deceased']) || $death !== null;

        // Подсказка строится из разобранных частей (а не из сырой строки — «0000-00-00» не попадёт)
        $partsDate = static fn(array $pt): string => $pt[1] !== null
            ? sprintf('%04d-%02d-%02d', $pt[0], $pt[1], (int)$pt[2])
            : (string)$pt[0];
        $titleParts = [];
        if ($birth) {
            $titleParts[] = ($en ? 'Date of birth: ' : ($ru ? 'Дата рождения: ' : 'Туған күні: ')) . self::formatDate($partsDate($birth), $lang);
        }
        if ($deceased && $death) {
            $titleParts[] = ($en ? 'Date of death: ' : ($ru ? 'Дата ухода: ' : 'Қайтыс болған күні: ')) . self::formatDate($partsDate($death), $lang);
        }
        $title = implode(' · ', $titleParts);

        if ($deceased) {
            if (!$birth && !$death) return ['short' => '', 'title' => '', 'deceased' => true];
            $span = ($birth ? $birth[0] : '?') . '–' . ($death ? $death[0] : '?');
            if ($birth && $death) {
                $n = self::yearsBetween($birth, $death);
                if ($n >= 0 && $n <= 130) {
                    // TASK-023: если у одной из дат нет месяца/дня — возраст приблизительный («≈»)
                    $apx = ($birth[1] === null || $death[1] === null) ? '≈' : '';
                    if ($en) {
                        $span .= " · lived {$apx}{$n} " . ($n === 1 ? 'year' : 'years');
                    } elseif ($ru) {
                        $g = $p['gender'] ?? '';
                        $verb = $g === 'F' ? 'прожила' : ($g === 'M' ? 'прожил' : 'прожил(а)');
                        $span .= " · {$verb} {$apx}" . self::ruYears($n);
                    } else {
                        $span .= " · {$apx}{$n} жыл ғұмыр";
                    }
                }
            }
            return ['short' => $span, 'title' => $title, 'deceased' => true];
        }

        if (!$birth) return ['short' => '', 'title' => '', 'deceased' => false];
        $now = self::todayParts($today);
        $n = self::yearsBetween($birth, $now);
        if ($n < 0 || $n > 130) {
            return ['short' => (string)$birth[0], 'title' => $title, 'deceased' => false];
        }
        $approx = $birth[1] === null ? '≈' : '';
        $short = $approx . ($en ? "{$n} years" : ($ru ? self::ruYears($n) : "{$n} жас"));
        return ['short' => $short, 'title' => $title, 'deceased' => false];
    }

    /**
     * TASK-020: «47 лет вместе» / «47 жыл бірге» — полных лет от даты брака до сегодня
     * (или до развода/ухода). Пустая строка, если год брака неизвестен или союз прекращён.
     */
    public static function togetherLabel(mixed $start, mixed $end = null, ?string $lang = null, ?string $today = null): string
    {
        $lang = $lang ?? self::lang();
        $from = self::lifeParts($start, null);
        if (!$from) return '';
        $to = self::lifeParts($end, null) ?? self::todayParts($today);
        $n = self::yearsBetween($from, $to);
        if ($n < 0 || $n > 100) return '';
        $apx = ($from[1] === null || ($end && ($to[1] ?? null) === null)) ? '≈' : '';
        if ($lang === 'ru') {
            return $apx . self::ruYears($n);
        }
        if ($lang === 'en') {
            return $apx . $n . ' ' . ($n === 1 ? 'year' : 'years') . ' together';
        }
        return $apx . $n . ' жыл';
    }

    /** TASK-020: дата брака для карточки: «8 марта 1979 года» / «1979 ж. 8 наурыз» (месяц — на языке интерфейса). */
    public static function marriageDateLabel(mixed $date, ?string $lang = null): string
    {
        $lang = $lang ?? self::lang();
        $raw = trim((string)($date ?? ''));
        if ($raw === '') return '';
        $s = self::formatDate($raw, $lang);
        if ($lang === 'ru') {
            return preg_replace('/ г\.$/u', ' года', preg_replace('/^(\d{1,2} \S+ \d{4})$/u', '$1 года', $s));
        }
        if ($lang === 'en') {
            return $s;
        }
        return str_replace(' жылғы ', ' ж. ', $s);
    }

    /**
     * ALT-007: словарь русских подписей интерфейса → английский.
     */
    private static ?array $enPhrases = null;

    private static function enPhrases(): array
    {
        if (self::$enPhrases === null) {
            self::$enPhrases = require __DIR__ . '/../Config/EnglishPhrases.php';
        }
        return self::$enPhrases;
    }

    private static function enYears(int $n): string
    {
        return $n === 1 ? 'year' : 'years';
    }

    private static function enDays(int $n): string
    {
        return $n === 1 ? 'day' : 'days';
    }

    /**
     * ALT-007 (замечание 21:29): транслитерация имён из кириллицы в латиницу.
     * Схема повторяет принятые на сайте написания: Ж→Zh, Ш→Sh, Қ→K, Ғ→G,
     * Ң→N, Ә→A, Ө→O, Ұ→U, Ү→U, І→I, Ы→Y, Й→Y, Х→Kh и т.д.
     */
    private const LATIN_MAP = [
        'А' => 'A', 'Ә' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Ғ' => 'G',
        'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
        'Й' => 'Y', 'К' => 'K', 'Қ' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
        'Ң' => 'N', 'О' => 'O', 'Ө' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S',
        'Т' => 'T', 'У' => 'U', 'Ұ' => 'U', 'Ү' => 'U', 'Ф' => 'F', 'Х' => 'Kh',
        'Һ' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch', 'Ъ' => '',
        'Ы' => 'Y', 'І' => 'I', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
        'а' => 'a', 'ә' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'ғ' => 'g',
        'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'қ' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'ң' => 'n', 'о' => 'o', 'ө' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's',
        'т' => 't', 'у' => 'u', 'ұ' => 'u', 'ү' => 'u', 'ф' => 'f', 'х' => 'kh',
        'һ' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ъ' => '',
        'ы' => 'y', 'і' => 'i', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    public static function latinize(string $text): string
    {
        if ($text === '') {
            return '';
        }
        return strtr($text, self::LATIN_MAP);
    }

    /**
     * ALT-007: перевод одной подписи/фразы (без пользовательских имён).
     * $latinizeNames — для en транслитерировать имена людей в латиницу
     * (замечание владельца 21:29). Выключено для значений форм (value).
     */
    public static function translateText(string $text, bool $latinizeNames = true): string
    {
        if ($text === '') {
            return '';
        }

        preg_match('/^\s*/u', $text, $leadMatch);
        preg_match('/\s*$/u', $text, $trailMatch);
        $lead = $leadMatch[0] ?? '';
        $trail = $trailMatch[0] ?? '';
        $core = trim($text);
        if ($core === '') {
            return $text;
        }

        $map = self::enPhrases();
        if (isset($map[$core])) {
            return $lead . $map[$core] . $trail;
        }

        // Месяцы внутри дат: «18 ноября 2026» → «18 November 2026».
        $months = [
            'января' => 'January', 'февраля' => 'February', 'марта' => 'March', 'апреля' => 'April',
            'мая' => 'May', 'июня' => 'June', 'июля' => 'July', 'августа' => 'August',
            'сентября' => 'September', 'октября' => 'October', 'ноября' => 'November', 'декабря' => 'December',
            'янв' => 'Jan', 'фев' => 'Feb', 'мар' => 'Mar', 'апр' => 'Apr', 'июн' => 'Jun',
            'июл' => 'Jul', 'авг' => 'Aug', 'сен' => 'Sep', 'окт' => 'Oct', 'ноя' => 'Nov', 'дек' => 'Dec',
        ];
        $out = strtr($core, $months);

        // Динамические подписи: числа, возрасты, даты, счётчики.
        $dynamic = [
            '/^(\d{4})\s*г\.?$/u' => fn(array $m): string => $m[1],
            '/^(\d{4})\s*года?$/u' => fn(array $m): string => $m[1],
            '/^≈?\s*(\d+)\s*(год|года|лет)$/u' => fn(array $m): string => (str_starts_with($m[0], '≈') ? '≈' : '') . $m[1] . ' ' . self::enYears((int)$m[1]),
            '/^(\d+)\s*(дн\.?|день|дня|дней)$/u' => fn(array $m): string => $m[1] . ' ' . self::enDays((int)$m[1]),
            '/^Через\s+(\d+)\s*дн\.?$/u' => fn(array $m): string => 'In ' . $m[1] . ' ' . self::enDays((int)$m[1]),
            '/^(\d+)\s*(человек|чел\.|персон[аы]?|союзов|детей|ребёнка|ребенка)$/u' => function (array $m): string {
                $n = (int)$m[1];
                $word = preg_match('/союзов/u', $m[2]) ? ($n === 1 ? 'union' : 'unions')
                    : (preg_match('/детей|ребёнка|ребенка/u', $m[2]) ? ($n === 1 ? 'child' : 'children') : ($n === 1 ? 'person' : 'people'));
                return $m[1] . ' ' . $word;
            },
            '/^Дети \((\d+)\)$/u' => fn(array $m): string => 'Children (' . $m[1] . ')',
            '/^Семьи детей \((\d+)\)$/u' => fn(array $m): string => "Children's families (" . $m[1] . ')',
            '/^(\d+)-е поколение$/u' => fn(array $m): string => 'Generation ' . $m[1],
            '/^Отображено персон:\s*(\d+)$/u' => fn(array $m): string => 'People shown: ' . $m[1],
            '/^из\s+(\d+)$/u' => fn(array $m): string => 'of ' . $m[1],
            '/^Дата рождения:\s*(.+)$/u' => fn(array $m): string => 'Date of birth: ' . self::translateText($m[1]),
            '/^Дата ухода:\s*(.+)$/u' => fn(array $m): string => 'Date of death: ' . self::translateText($m[1]),
            '/^Дата брака:\s*(.+)$/u' => fn(array $m): string => 'Marriage date: ' . self::translateText($m[1]),
            '/^(.+?)\s*·\s*прожил(а)?\s+(≈?)(\d+)\s*(год|года|лет)$/u' => fn(array $m): string => self::translateText($m[1]) . ' · lived ' . $m[3] . $m[4] . ' ' . self::enYears((int)$m[4]),
            '/^(.*·\s*)(\d+)\s*(год|года|лет)$/u' => fn(array $m): string => self::translateText($m[1]) . $m[2] . ' ' . self::enYears((int)$m[2]),
            '/^прожил(а)?\s+(≈?)(\d+)\s*(год|года|лет)$/u' => fn(array $m): string => 'lived ' . $m[2] . $m[3] . ' ' . self::enYears((int)$m[3]),
            '/^(\d+)\s*лет со дня ухода$/u' => fn(array $m): string => $m[1] . ' years since passing',
            '/^(\d+)\s*лет исполняется$/u' => fn(array $m): string => $m[1] . ' years',
            '/^Вы — (.+)$/u' => fn(array $m): string => 'You are ' . self::translateText($m[1]),
            '/^Добро пожаловать!,\s*(.+)$/u' => fn(array $m): string => 'Welcome, ' . self::translateText($m[1]),
            '/^⚭\s*замужем — (.+)$/u' => fn(array $m): string => '⚭ married — ' . self::translateText($m[1]),
            '/^замужем — (.+)$/u' => fn(array $m): string => 'married — ' . self::translateText($m[1]),
            '/^\(урожд\.\s*(.+)\)$/u' => fn(array $m): string => '(née ' . self::translateText($m[1]) . ')',
            '/^На ближайшие\s+(\d+)\s+дней событий не найдено$/u' => fn(array $m): string => 'No events found for the next ' . $m[1] . ' days',
            '/^Ближайшие дни рождения, поминальные годовщины и юбилеи свадеб на\s+(\d+)\s+дней вперед$/u' => fn(array $m): string => 'Upcoming birthdays, memorial anniversaries and wedding anniversaries for the next ' . $m[1] . ' days',
            '/^(\d+)\s*год(?:а)?$/u' => fn(array $m): string => $m[1] . ' ' . self::enYears((int)$m[1]),
            '/^(\d+)\s*жас$/u' => fn(array $m): string => $m[1] . ' years',
            '/^(\d+)\s*жыл$/u' => fn(array $m): string => $m[1] . ' years',
            '/^(Сын|Дочь|Брат|Сестра)\s*·\s*(.+)$/u' => function (array $m): string {
                $word = ['Сын' => 'Son', 'Дочь' => 'Daughter', 'Брат' => 'Brother', 'Сестра' => 'Sister'][$m[1]] ?? $m[1];
                return $word . ' · ' . self::translateText($m[2]);
            },
            '/^Фото и видео, где отмечен\(а\)\s*·\s*(\d+)$/u' => fn(array $m): string => 'Photos and videos tagged · ' . $m[1],
            '/^(.+),\s*(\d+)-е колено по отцу$/u' => fn(array $m): string => self::translateText($m[1]) . ', paternal generation ' . $m[2],
            '/^(.+),\s*(\d+)-е колено по матери$/u' => fn(array $m): string => self::translateText($m[1]) . ', maternal generation ' . $m[2],
            '/^(.+)\s+по роду Керей$/u' => fn(array $m): string => self::translateText($m[1]) . ' in the Kerey clan',
            '/^(.+)\s+по отцу$/u' => fn(array $m): string => 'Paternal ' . lcfirst(self::translateText($m[1])),
            '/^(.+)\s+по матери$/u' => fn(array $m): string => 'Maternal ' . lcfirst(self::translateText($m[1])),
            '/^(\d+)-я годовщина \(День памяти\)$/u' => fn(array $m): string => $m[1] . 'th anniversary (Memorial day)',
            '/^(\d+)-я годовщина$/u' => fn(array $m): string => $m[1] . 'th anniversary',
            '/^Шежіре-плакат:\s*«(.+)»\s*ұрпақтары$/u' => fn(array $m): string => 'Shezhire poster: descendants of «' . self::translateText($m[1]) . '»',
            '/^Плакат шежире:\s*потомки\s*«(.+)»$/u' => fn(array $m): string => 'Shezhire poster: descendants of «' . self::translateText($m[1]) . '»',
            '/^Плакат шежире:\s*потомки\s*«(.+)»\s*—\s*Altybayev\.kz$/u' => fn(array $m): string => 'Shezhire poster: descendants of «' . self::translateText($m[1]) . '» — Altybayev.kz',
            '/^(\d+)-буын$/u' => fn(array $m): string => 'Generation ' . $m[1],
            '/^(\d+)-е колено$/u' => fn(array $m): string => 'Generation ' . $m[1],
        ];
        foreach ($dynamic as $pattern => $fn) {
            if (preg_match($pattern, $out, $m)) {
                return $lead . $fn($m) . $trail;
            }
        }

        // Имена собственные (люди, места, роды): для en транслитерируем в латиницу.
        // Шаблон: 1–4 слова, каждое с заглавной кириллической буквы, только буквы и дефисы.
        $looksLikeName = (bool)preg_match(
            '/^[А-ЯЁӘҒҚҢӨҰҮҺІ][а-яёәғқңөұүһі-]*(?:\s+[А-ЯЁӘҒҚҢӨҰҮҺІ][а-яёәғқңөұүһі-]*){0,3}$/u',
            $out
        );
        if ($looksLikeName) {
            if ($latinizeNames) {
                return $lead . self::latinize($out) . $trail;
            }
            return $lead . $out . $trail;
        }

        // Заменяем длинные устойчивые подписи.
        if (preg_match('/[А-Яа-яЁёӘәҒғҚқҢңӨөҰұҮүҺһІі]/u', $out)) {
            $replace = [];
            foreach ($map as $k => $v) {
                if (mb_strlen($k, 'UTF-8') >= 5) {
                    $replace[$k] = $v;
                }
            }
            $out = strtr($out, $replace);
        }

        return $lead . $out . $trail;
    }

    /**
     * ALT-007: перевод готового HTML всех страниц для английского языка.
     * Переводим текстовые узлы и подписи атрибутов; скрипты и данные не трогаем.
     */
    public static function translateHtml(string $html): string
    {
        if (!self::isEn() || $html === '') {
            return $html;
        }

        // Скрипты и стили не трогаем: там лежат словари и код переключения языка.
        $parts = preg_split(
            '/(<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>)/is',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );
        if ($parts === false) {
            return $html;
        }

        foreach ($parts as $i => $part) {
            if (preg_match('/^<(script|style)\b/i', $part)) {
                continue;
            }
            $part = preg_replace_callback(
                '/\b(title|placeholder|aria-label|data-tip|alt|value)="([^"]*)"/u',
                static function (array $m): string {
                    $decoded = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    // Значения полей формы (value) — это данные, а не подписи: имена там не транслитерируем.
                    $translated = self::translateText($decoded, $m[1] !== 'value');
                    return $m[1] . '="' . htmlspecialchars($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"';
                },
                $part
            ) ?? $part;

            $part = preg_replace_callback(
                '/>([^<>]+)</u',
                static function (array $m): string {
                    return '>' . self::translateText($m[1]) . '<';
                },
                $part
            ) ?? $part;

            $parts[$i] = $part;
        }

        return implode('', $parts);
    }

    /**
     * Словарь локализации интерфейса
     */
    public static function getDictionary(): array
    {
        static $dict = null;
        if ($dict !== null) {
            return $dict;
        }

        $dict = [
            // Общие элементы шапки и навигации
            'site_title' => [
                'kk' => 'Шежіре',
                'ru' => 'Родословная',
            ],
            'nav_home' => [
                'kk' => 'Басты',
                'ru' => 'Главная',
            ],
            'nav_persons' => [
                'kk' => 'Адамдар',
                'ru' => 'Люди',
            ],
            'nav_families' => [
                'kk' => 'Отбасылар',
                'ru' => 'Семьи',
            ],
            'nav_tree' => [
                'kk' => 'Шежіре',
                'ru' => 'Древо',
            ],
            'nav_dates' => [
                'kk' => 'Есте қаларлық күндер',
                'ru' => 'Памятные даты',
            ],
            'nav_history' => [
                'kk' => 'Тарих',
                'ru' => 'История правок',
            ],
            'nav_admin' => [
                'kk' => 'Әкімші',
                'ru' => 'Админка',
            ],
            'nav_logout' => [
                'kk' => 'Шығу',
                'ru' => 'Выход',
            ],
            'nav_login' => [
                'kk' => 'Кіру',
                'ru' => 'Вход',
            ],

            // Роли
            'role_admin' => [
                'kk' => 'Әкімші',
                'ru' => 'Администратор',
            ],
            'role_editor' => [
                'kk' => 'Редактор',
                'ru' => 'Редактор',
            ],
            'role_viewer' => [
                'kk' => 'Оқырман',
                'ru' => 'Читатель',
            ],

            // Главная страница
            'welcome_heading' => [
                'kk' => 'Қош келдіңіз!',
                'ru' => 'Добро пожаловать!',
            ],
            'connected_person_badge' => [
                'kk' => 'Байланысты тұлға',
                'ru' => 'Связанная персона',
            ],
            'quick_links' => [
                'kk' => 'Жылдам сілтемелер',
                'ru' => 'Быстрые ссылки',
            ],
            'stat_total_persons' => [
                'kk' => 'Барлық тұлғалар',
                'ru' => 'Всего персон',
            ],
            'stat_total_families' => [
                'kk' => 'Шаңырақтар',
                'ru' => 'Семей',
            ],
            'stat_total_generations' => [
                'kk' => 'Буындар',
                'ru' => 'Поколений',
            ],
            'upcoming_birthdays' => [
                'kk' => 'Жақында болатын туған күндер',
                'ru' => 'Ближайшие дни рождения',
            ],
            'memorial_anniversaries' => [
                'kk' => 'Ата-бабаларды еске алу күндері',
                'ru' => 'Дни памяти предков',
            ],
            'no_upcoming_birthdays' => [
                'kk' => 'Алдағы 45 күнде туған күндер жоқ',
                'ru' => 'В ближайшие 45 дней дней рождения нет',
            ],
            'no_memorial_dates' => [
                'kk' => 'Алдағы 45 күнде атаулы еске алу күндері жоқ',
                'ru' => 'В ближайшие 45 дней памятных дат нет',
            ],

            // Раздел «Адамдар»
            'persons_title' => [
                'kk' => 'Адамдар тізімі',
                'ru' => 'Список людей',
            ],
            'persons_subtitle' => [
                'kk' => 'Әулет шежіресіндегі барлық тұлғалар, іздеу және өңдеу',
                'ru' => 'Все персоны родословной, поиск и редактирование',
            ],
            'search_placeholder' => [
                'kk' => 'Аты-жөнін, туған жылын немесе жерін іздеу...',
                'ru' => 'Поиск по имени, году или месту рождения...',
            ],
            'filter_all_clans' => [
                'kk' => 'Барлық рулар',
                'ru' => 'Все роды',
            ],
            'filter_gender_all' => [
                'kk' => 'Жынысы: Барлығы',
                'ru' => 'Пол: Все',
            ],
            'filter_gender_m' => [
                'kk' => 'Ер',
                'ru' => 'Мужской',
            ],
            'filter_gender_f' => [
                'kk' => 'Әйел',
                'ru' => 'Женский',
            ],
            'filter_status_all' => [
                'kk' => 'Күйі: Барлығы',
                'ru' => 'Статус: Все',
            ],
            'filter_status_living' => [
                'kk' => 'Тірі ұрпақтар',
                'ru' => 'Живые',
            ],
            'filter_status_deceased' => [
                'kk' => 'Уақыттан озғандар',
                'ru' => 'Ушедшие предки',
            ],
            'sort_name_asc' => [
                'kk' => 'Аты-жөні (А-Я)',
                'ru' => 'По имени (А-Я)',
            ],
            'sort_name_desc' => [
                'kk' => 'Аты-жөні (Я-А)',
                'ru' => 'По имени (Я-А)',
            ],
            'sort_birth_asc' => [
                'kk' => 'Туған жылы (өсу ретімен)',
                'ru' => 'По году рождения (возр.)',
            ],
            'sort_birth_desc' => [
                'kk' => 'Туған жылы (кему ретімен)',
                'ru' => 'По году рождения (убыв.)',
            ],
            'sort_generation' => [
                'kk' => 'Ұрпақтар бойынша',
                'ru' => 'По поколениям',
            ],

            // Поля карточки и биографии
            'field_birth_date' => ['kk' => 'Туған күні', 'ru' => 'Дата рождения'],
            'field_birth_place' => ['kk' => 'Туған жері', 'ru' => 'Место рождения'],
            'field_death_date' => ['kk' => 'Уақыттан озған күні', 'ru' => 'Дата ухода'],
            'field_death_place' => ['kk' => 'Мәңгілік мекені', 'ru' => 'Место упокоения'],
            'biography_title' => ['kk' => 'Өмірбаяны', 'ru' => 'Биография'],
            'notes_title' => ['kk' => 'Жазбалар мен естеліктер', 'ru' => 'Заметки и воспоминания'],
            'parents_title' => ['kk' => 'Ата-анасы', 'ru' => 'Родители'],
            'spouses_title' => ['kk' => 'Жұбайлары', 'ru' => 'Супруги'],
            'children_title' => ['kk' => 'Балалары', 'ru' => 'Дети'],
            'kinship_to_you' => ['kk' => 'Сізге туыстығы', 'ru' => 'Родство к вам'],
            'btn_read_more' => ['kk' => 'Толығырақ', 'ru' => 'Подробнее'],
            'btn_collapse' => ['kk' => 'Жасыру', 'ru' => 'Свернуть'],
            'technical_details' => ['kk' => 'Техникалық мәліметтер', 'ru' => 'Служебные данные'],
            'btn_you_are_here' => ['kk' => 'Сіз осындасыз', 'ru' => 'Вы здесь'],
            'btn_art_concept' => ['kk' => 'Шежіре суреті', 'ru' => 'Иллюстрация древа'],
            'btn_kinship_dict' => ['kk' => 'Туыстық сөздігі', 'ru' => 'Словарь родства'],
            'btn_view_in_tree' => ['kk' => 'Шежіреде көрсету', 'ru' => 'Показать в древе'],
            'btn_focus_here' => ['kk' => 'Фокус орнату', 'ru' => 'Фокусировать'],
            'ush_zhurt_title' => ['kk' => 'Үш жұрт', 'ru' => 'Три родственных круга (Үш жұрт)'],
            'oz_zhurt' => ['kk' => 'Өз жұрты', 'ru' => 'Өз жұрты (Родня по отцу)'],
            'torkin' => ['kk' => 'Төркіні', 'ru' => 'Төркіні (Семья по отцу)'],
            'nagashy_zhurt' => ['kk' => 'Нағашы жұрты', 'ru' => 'Нағашы жұрты (Родня по матери)'],
            'kaiyn_zhurt' => ['kk' => 'Қайын жұрты', 'ru' => 'Қайын жұрты (Родня жены)'],
            'kuyeui_zhagy' => ['kk' => 'Күйеуінің жағы', 'ru' => 'Күйеуінің жағы (Семья мужа)'],

            // Таблица
            'th_num' => ['kk' => '#', 'ru' => '#'],
            'th_person' => ['kk' => 'Тұлға', 'ru' => 'Персона'],
            'th_gender' => ['kk' => 'Жынысы', 'ru' => 'Пол'],
            'th_clan' => ['kk' => 'Руы', 'ru' => 'Род'],
            'th_years' => ['kk' => 'Өмір сүрген жылдары', 'ru' => 'Годы жизни'],
            'th_parents' => ['kk' => 'Ата-анасы', 'ru' => 'Родители'],
            'th_spouses' => ['kk' => 'Жұбайы', 'ru' => 'Супруг(а)'],
            'th_kinship' => ['kk' => 'Үш жұрт / Туыстығы', 'ru' => 'Родство к вам'],
            'th_actions' => ['kk' => 'Әрекеттер', 'ru' => 'Действия'],

            // Действия
            'btn_edit' => ['kk' => 'Өзгерту', 'ru' => 'Редактировать'],
            'btn_view_profile' => ['kk' => 'Парақшасы', 'ru' => 'Карточка'],
            'btn_show_in_tree' => ['kk' => 'Шежіреде көрсету', 'ru' => 'Показать в древе'],
            'btn_add_relative' => ['kk' => '+ Туыс қосу', 'ru' => '+ Добавить родню'],
            'btn_add_son' => ['kk' => '+ Ұл', 'ru' => '+ Сын'],
            'btn_add_daughter' => ['kk' => '+ Қыз', 'ru' => '+ Дочь'],
            'btn_save' => ['kk' => 'Сақтау', 'ru' => 'Сохранить'],
            'btn_cancel' => ['kk' => 'Бас тарту', 'ru' => 'Отмена'],
            'btn_delete' => ['kk' => 'Өшіру', 'ru' => 'Удалить'],
            'btn_upload_photo' => ['kk' => 'Сурет жүктеу', 'ru' => 'Загрузить фото'],

            // TASK-022: контакты и приглашение
            'contacts_title' => ['kk' => 'Байланыс', 'ru' => 'Контакты'],
            'contacts_phone' => ['kk' => 'Телефон', 'ru' => 'Телефон'],
            'contacts_phones' => ['kk' => 'Телефондар', 'ru' => 'Телефоны'],
            'contacts_email' => ['kk' => 'Email', 'ru' => 'Email'],
            'socials_title' => ['kk' => 'Әлеуметтік желілер', 'ru' => 'Соцсети'],
            'socials_add' => ['kk' => '+ Желі қосу', 'ru' => '+ Добавить соцсеть'],
            'socials_placeholder' => ['kk' => 'Сілтеме немесе @ник', 'ru' => 'Ссылка или @ник'],
            'socials_remove' => ['kk' => 'Жою', 'ru' => 'Удалить'],
            'socials_other' => ['kk' => 'Басқа', 'ru' => 'Другое'],
            'contacts_empty' => ['kk' => 'Байланыс деректері көрсетілмеген', 'ru' => 'Контакты не указаны'],
            'contacts_add_phone' => ['kk' => '+ Тағы нөмір', 'ru' => '+ Ещё номер'],
            'contacts_whatsapp' => ['kk' => 'WhatsApp-қа жазу', 'ru' => 'Написать в WhatsApp'],
            'contacts_hint' => ['kk' => 'Байланыс деректерін тек сайтқа кірген туыстар көреді', 'ru' => 'Контакты видят только родственники, вошедшие на сайт'],
            'lbl_remove_phone' => ['kk' => 'Нөмірді өшіру', 'ru' => 'Удалить номер'],
            // TASK-022: фото и обложка в окне «Өңдеу»
            'pme_change_avatar' => ['kk' => 'Аватарды ауыстыру', 'ru' => 'Сменить аватарку'],
            'pme_change_cover' => ['kk' => 'Мұқабаны ауыстыру', 'ru' => 'Сменить обложку'],
            'pme_change' =>['kk' => 'Өзгерту', 'ru' => 'Изменить'],
            'pme_more_opts' => ['kk' => 'Фонды баптау', 'ru' => 'Настроить фон'],
            'pme_title' => ['kk' => 'Фото және мұқаба', 'ru' => 'Фото и обложка'],
            'pme_avatar' => ['kk' => 'Портрет (аватар)', 'ru' => 'Портрет (аватар)'],
            'pme_cover' => ['kk' => 'Мұқаба (карточка фоны)', 'ru' => 'Обложка (фон карточки)'],
            'pme_cover_default' => ['kk' => 'Стандартты фон', 'ru' => 'Стандартный фон'],
            'pme_upload' => ['kk' => 'Жүктеу', 'ru' => 'Загрузить'],
            'pme_from_album' => ['kk' => 'Альбомнан таңдау', 'ru' => 'Выбрать из альбомов'],
            'pme_remove' => ['kk' => 'Жою', 'ru' => 'Удалить'],
            'pme_presets' => ['kk' => 'Дайын фондар', 'ru' => 'Готовые фоны'],
            'pme_preset_ornament' => ['kk' => 'Өрнек', 'ru' => 'Орнамент'],
            'pme_preset_steppe' => ['kk' => 'Дала', 'ru' => 'Степь'],
            'pme_preset_mountains' => ['kk' => 'Тау', 'ru' => 'Горы'],
            'pme_pos' => ['kk' => 'Орны (жоғары–төмен)', 'ru' => 'Положение (верх–низ)'],
            'pme_dim' => ['kk' => 'Қараңғылау', 'ru' => 'Затемнение'],
            'pme_zoom' => ['kk' => 'Масштаб', 'ru' => 'Масштаб'],
            'pme_apply' => ['kk' => 'Қолдану', 'ru' => 'Применить'],
            'pme_cancel' => ['kk' => 'Бас тарту', 'ru' => 'Отмена'],
            'pme_more' => ['kk' => 'Тағы', 'ru' => 'Ещё'],
            'pme_empty_albums' => ['kk' => 'Альбомдарда фото жоқ', 'ru' => 'В альбомах нет фото'],
            'pme_saved' => ['kk' => 'Сақталды', 'ru' => 'Сохранено'],
            'pme_no_migration' => ['kk' => 'Мұқаба үшін деректер қорын жаңарту керек (миграция)', 'ru' => 'Для обложки нужно обновить БД (миграция)'],
            'pme_crop_hint' => ['kk' => 'Суретті сүйреп, шаршы аймақты таңдаңыз', 'ru' => 'Перетащите фото и выберите квадрат'],
            'pme_pick_avatar' => ['kk' => 'Портретке фото таңдаңыз', 'ru' => 'Выберите фото для портрета'],
            'pme_pick_cover' => ['kk' => 'Мұқабаға фото таңдаңыз', 'ru' => 'Выберите фото для обложки'],
            'pme_img_error' => ['kk' => 'Суретті ашу мүмкін болмады', 'ru' => 'Не удалось открыть изображение'],
            'invite_send' => ['kk' => 'Шақыру жіберу', 'ru' => 'Отправить приглашение'],
            'invite_role' => ['kk' => 'Рөлі', 'ru' => 'Роль'],
            'invite_invited' => ['kk' => 'Шақырылды', 'ru' => 'Приглашён'],
            'invite_accepted' => ['kk' => 'Қабылданды', 'ru' => 'Принято'],
            'role_viewer' => ['kk' => 'Көрермен (viewer)', 'ru' => 'Зритель (viewer)'],
            'role_editor' => ['kk' => 'Редактор (editor)', 'ru' => 'Редактор (editor)'],
            'role_admin' => ['kk' => 'Әкімші (admin)', 'ru' => 'Администратор (admin)'],

            // Карточка персоны
            'person_details_title' => ['kk' => 'Тұлға парақшасы', 'ru' => 'Карточка персоны'],
            'personal_info' => ['kk' => 'Жеке мәліметтер', 'ru' => 'Личные данные'],
            'kinship_info' => ['kk' => 'Туыстық байланыстар', 'ru' => 'Родственные связи'],
            'father_label' => ['kk' => 'Әкесі', 'ru' => 'Отец'],
            'mother_label' => ['kk' => 'Анасы', 'ru' => 'Мать'],
            'spouses_label' => ['kk' => 'Жұбайлары', 'ru' => 'Супруги'],
            'children_label' => ['kk' => 'Балалары', 'ru' => 'Дети'],
            'brothers_sisters' => ['kk' => 'Бауырлары', 'ru' => 'Братья и сестры'],
            'biography_and_memories' => ['kk' => 'Өмірбаяны мен естеліктер', 'ru' => 'Биография и воспоминания'],
            'audio_and_photos' => ['kk' => 'Дыбыс жазбалары мен архивтік суреттер', 'ru' => 'Аудиозаписи и фотографии'],
            'no_media_yet' => ['kk' => 'Әзірге фотосуреттер мен естеліктер жүктелмеген', 'ru' => 'Пока нет загруженных фото и воспоминаний'],

            // Раздел «Отбасылар»
            'families_title' => ['kk' => 'Отбасылар мен шаңырақтар', 'ru' => 'Семьи и союзы'],
            'families_subtitle' => ['kk' => 'Барлық некелер, ерлі-зайыптылар мен олардың ұрпақтары', 'ru' => 'Все браки, супружеские союзы и их потомство'],

            // Раздел «Тарих»
            'history_title' => ['kk' => 'Өзгерістер тарихы', 'ru' => 'История изменений'],
            'history_subtitle' => ['kk' => 'Шежіреге енгізілген барлық түзетулер мен толықтырулар журналы', 'ru' => 'Журнал всех правок и обновлений родословной'],

            // Раздел «Админка»
            'admin_title' => ['kk' => 'Әкімшілік басқару', 'ru' => 'Панель администратора'],
            'admin_subtitle' => ['kk' => 'Пайдаланушылар, рөлдер, шақырулар және деректер импорты', 'ru' => 'Пользователи, роли, приглашения и импорт данных'],

            // Ошибки и доступ
            'access_denied' => ['kk' => 'Қолжетімділік шектелген', 'ru' => 'Доступ ограничен'],
            'access_denied_desc' => ['kk' => 'Бұл бетті көру үшін сәйкес рұқсат деңгейі қажет', 'ru' => 'Для просмотра этой страницы требуются соответствующие права доступа'],
            'login_required' => ['kk' => 'Жүйеге кіру қажет', 'ru' => 'Необходима авторизация'],
            'login_google' => ['kk' => 'Google арқылы кіру', 'ru' => 'Войти через Google'],
        ];

        // ALT-007: гарантируем наличие английского варианта у каждого ключа.
        foreach ($dict as $key => $row) {
            if (!isset($row['en'])) {
                $dict[$key]['en'] = self::translateText((string)($row['ru'] ?? $row['kk'] ?? ''));
            }
        }

        return $dict;
    }
}
