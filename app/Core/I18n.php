<?php

declare(strict_types=1);

namespace App\Core;

class I18n
{
    private static ?string $currentLang = null;

    /** ALT-007: поддерживаемые языки интерфейса. */
    public const LANGS = ['kk', 'ru', 'en', 'es'];

    /**
     * Получить текущий язык ('kk', 'ru', 'en' или 'es')
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
            'es' => 'Árbol Genealógico',
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

    public static function isEs(): bool
    {
        return self::lang() === 'es';
    }

    /**
     * Полный перевод строки по ключу
     */
    public static function t(string $key, array $params = []): string
    {
        $lang = self::lang();
        $dict = self::getDictionary();

        if ($lang === 'en') {
            $text = $dict[$key]['en'] ?? self::translateText((string)($dict[$key]['ru'] ?? $dict[$key]['kk'] ?? $key), true, 'en');
        } elseif ($lang === 'es') {
            $text = $dict[$key]['es'] ?? self::translateText((string)($dict[$key]['ru'] ?? $dict[$key]['kk'] ?? $key), true, 'es');
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
     * ALT-083: подпись семьи («Жандостың отбасы» / «Семья Жандоса» / «Zhandos's family» / «Familia de Zhandos»).
     * Те же склонения (KinshipService) и шаблоны «tree_family_label», что в древе. Пустое имя — пустая строка.
     */
    public static function familyLabel(string $firstName, ?string $lang = null): string
    {
        $first = preg_split('/\s+/u', trim($firstName), -1, PREG_SPLIT_NO_EMPTY)[0] ?? '';
        if ($first === '') {
            return '';
        }
        $lang = $lang ?? self::lang();
        if ($lang === 'ru') {
            $name = \App\Services\KinshipService::ruGenitive($first);
        } elseif ($lang === 'en' || $lang === 'es') {
            $name = self::latinize($first, $lang);
        } else {
            $name = \App\Services\KinshipService::kkGenitive($first);
        }
        $tpl = self::getDictionary()['tree_family_label'][$lang] ?? self::getDictionary()['tree_family_label']['kk'];
        return str_replace('{name}', $name, $tpl);
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

        // ALT-007: английский/испанский — переводим русскую подпись, казахский термин оставляем в скобках,
        // если он был частью term_ru (например, «Әке (Отец)» → «Әке (Father)» / «Әке (Padre)»).
        if (self::lang() === 'en' || self::lang() === 'es') {
            // ALT-013: сначала термин из глоссария родства (испанский/английский термин вместо казахского)
            $glossary = \App\Config\KinshipGlossary::label($termKk, $termRu, self::lang());
            if ($glossary !== null && $glossary !== '') {
                return $glossary;
            }
            $base = $termRu !== '' ? $termRu : $termKk;
            return self::translateText($base, true, self::lang());
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
     * ALT-013: термин родства с подсказкой-расшифровкой (HTML, уже экранирован).
     * Пояснение берётся из KinshipGlossary на текущем языке; тап/наведение показывает его (JS: .kin-hint).
     */
    public static function kinshipHtml(string $termKk, ?string $termRu = null): string
    {
        $label = htmlspecialchars(self::formatKinship($termKk, $termRu), ENT_QUOTES, 'UTF-8');
        $hint = \App\Config\KinshipGlossary::hint($termKk, $termRu, self::lang());
        if ($hint === null || $hint === '') {
            return $label;
        }
        $h = htmlspecialchars($hint, ENT_QUOTES, 'UTF-8');
        return '<span class="kin-term">' . $label . ' <button type="button" class="kin-hint" aria-label="' . $h . '" data-hint="' . $h . '">ⓘ</button></span>';
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
                'es' => 'no especificada',
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
                'es' => (string)$raw,
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

        $monthsEs = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
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
                return "{$day} {$monthsRu[$month]} {$year} г.";
            }
            if ($lang === 'en') {
                return "{$monthsEn[$month]} {$day}, {$year}";
            }
            if ($lang === 'es') {
                return "{$day} de {$monthsEs[$month]} de {$year}";
            }
            return "{$year} жылғы {$day} {$monthsKk[$month]}";
        }

        if ($year && $month && isset($monthsRu[$month])) {
            return match ($lang) {
                'ru' => "{$monthsRu[$month]} {$year} г.",
                'en' => "{$monthsEn[$month]} {$year}",
                'es' => "{$monthsEs[$month]} de {$year}",
                default => "{$year} жылғы {$monthsKk[$month]}",
            };
        }

        if ($year) {
            return match ($lang) {
                'ru' => "{$year} г.",
                'en' => (string)$year,
                'es' => (string)$year,
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
        $es = ($lang === 'es');
        $birth = self::lifeParts($p['birth_date'] ?? null, $p['birth_year'] ?? null);
        $death = self::lifeParts($p['death_date'] ?? null, $p['death_year'] ?? null);
        $deceased = !empty($p['is_deceased']) || $death !== null;

        // Подсказка строится из разобранных частей (а не из сырой строки — «0000-00-00» не попадёт)
        $partsDate = static fn(array $pt): string => $pt[1] !== null
            ? sprintf('%04d-%02d-%02d', $pt[0], $pt[1], (int)$pt[2])
            : (string)$pt[0];
        $titleParts = [];
        if ($birth) {
            $titleParts[] = ($en ? 'Date of birth: ' : ($es ? 'Fecha de nacimiento: ' : ($ru ? 'Дата рождения: ' : 'Туған күні: '))) . self::formatDate($partsDate($birth), $lang);
        }
        if ($deceased && $death) {
            $titleParts[] = ($en ? 'Date of death: ' : ($es ? 'Fecha de fallecimiento: ' : ($ru ? 'Дата ухода: ' : 'Қайтыс болған күні: '))) . self::formatDate($partsDate($death), $lang);
        }
        $title = implode(' · ', $titleParts);

        if ($deceased) {
            if (!$birth && !$death) return ['short' => '', 'title' => '', 'deceased' => true];
            $span = ($birth ? $birth[0] : '?') . '–' . ($death ? $death[0] : '?');
            // ALT-022: в en/es при одном известном годе — «b. 1952» / «d. 2010» («n.» / «m.» по-испански)
            if (($en || $es) && (!$birth || !$death)) {
                $span = $birth
                    ? ($en ? 'b. ' : 'n. ') . $birth[0]
                    : ($en ? 'd. ' : 'm. ') . $death[0];
            }
            if ($birth && $death) {
                $n = self::yearsBetween($birth, $death);
                if ($n >= 0 && $n <= 130) {
                    // TASK-023: если у одной из дат нет месяца/дня — возраст приблизительный («≈»)
                    $apx = ($birth[1] === null || $death[1] === null) ? '≈' : '';
                    if ($en) {
                        $span .= " · lived {$apx}{$n} " . ($n === 1 ? 'year' : 'years');
                    } elseif ($es) {
                        $span .= " · vivió {$apx}{$n} " . ($n === 1 ? 'año' : 'años');
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
        $short = $approx . ($en ? "{$n} years" : ($es ? "{$n} años" : ($ru ? self::ruYears($n) : "{$n} жас")));
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
        if ($lang === 'es') {
            return $apx . $n . ' ' . ($n === 1 ? 'año' : 'años') . ' juntos';
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
        if ($lang === 'es') {
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

    /**
     * ALT-007: словарь русских подписей интерфейса → испанский.
     */
    private static ?array $esPhrases = null;

    private static function esPhrases(): array
    {
        if (self::$esPhrases === null) {
            self::$esPhrases = require __DIR__ . '/../Config/SpanishPhrases.php';
        }
        return self::$esPhrases;
    }

    /**
     * ALT-022: месяцы заменяются только целыми словами и только в тексте с цифрами (датах),
     * иначе «сен», «мар», «дек» ломали имена (Хасен → ХаSep).
     */
    private static function translateMonths(string $core, array $months): string
    {
        if (!preg_match('/\d/', $core)) {
            return $core;
        }
        return preg_replace_callback(
            '/(?<![\p{L}])(?:' . implode('|', array_map(static fn($k) => preg_quote((string)$k, '/'), array_keys($months))) . ')(?![\p{L}])/u',
            static fn(array $m): string => $months[$m[0]] ?? $m[0],
            $core
        ) ?? $core;
    }

    private static function enYears(int $n): string
    {
        return $n === 1 ? 'year' : 'years';
    }

    private static function enDays(int $n): string
    {
        return $n === 1 ? 'day' : 'days';
    }

    private static function esYears(int $n): string
    {
        return $n === 1 ? 'año' : 'años';
    }

    private static function esDays(int $n): string
    {
        return $n === 1 ? 'día' : 'días';
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

    /**
     * ALT-022: схема по языку. en — как в загранпаспортах и англоязычной прессе
     * (Ң→Ng, Х→Kh); es — по испанскому чтению (Х/Һ→J, Щ→Sh, Г перед е/и→Gu).
     * Ударения не ставятся, казахская латиница 2021 не используется.
     */
    private const LATIN_MAP_ES = [
        'Х' => 'J', 'х' => 'j', 'Һ' => 'J', 'һ' => 'j', 'Щ' => 'Sh', 'щ' => 'sh',
    ];

    public static function latinize(string $text, ?string $lang = null): string
    {
        if ($text === '') {
            return '';
        }
        $lang = $lang ?? self::lang();
        $map = ['Ң' => 'Ng', 'ң' => 'ng'] + self::LATIN_MAP;
        if ($lang === 'es') {
            $map = self::LATIN_MAP_ES + $map;
            // ge/gi по-испански читается как «хе/хи», поэтому Ге/Ги → Gue/Gui
            $text = preg_replace_callback(
                '/([ГгҒғ])(?=[еиэіЕИЭІ])/u',
                static fn(array $m): string => ($m[1] === 'Г' || $m[1] === 'Ғ') ? 'Gu' : 'gu',
                $text
            ) ?? $text;
        }
        // «Алтыбаев» → Altybayev: е после гласной читается как «ye».
        $text = preg_replace_callback(
            '/([аеёиоуыэюяәіөұүАЕЁИОУЫЭЮЯӘІӨҰҮ])([Ее])/u',
            static fn(array $m): string => $m[1] . ($m[2] === 'Е' ? (mb_strtoupper($m[1]) === $m[1] ? 'YE' : 'Ye') : 'ye'),
            $text
        ) ?? $text;
        return strtr($text, $map);
    }

    /**
     * ALT-007: перевод одной подписи/фразы (без пользовательских имён).
     * $latinizeNames — для en/es транслитерировать имена людей в латиницу
     * (замечание владельца 21:29). Выключено для значений форм (value).
     * $to — целевой язык ('en' или 'es'); по умолчанию — текущий.
     */
    public static function translateText(string $text, bool $latinizeNames = true, ?string $to = null): string
    {
        $to = $to ?? self::lang();
        if ($to !== 'en' && $to !== 'es') {
            return $text;
        }
        // ALT-022 (скорость): текст без кириллицы переводить нечего; одинаковые подписи на странице
        // (кнопки, годы, имена) переводятся один раз за запрос — раньше каждый узел шёл через ~35 регулярок.
        static $cache = [];
        $key = $to . ($latinizeNames ? '1' : '0') . $text;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        if (!preg_match('/[\p{Cyrillic}]/u', $text) && !isset(($to === 'es' ? self::esPhrases() : self::enPhrases())[trim($text)])) {
            return $cache[$key] = $text;
        }
        if (count($cache) > 20000) {
            $cache = [];
        }
        return $cache[$key] = self::translateTextUncached($text, $latinizeNames, $to);
    }

    private static function translateTextUncached(string $text, bool $latinizeNames, string $to): string
    {
        if ($to === 'es') {
            return self::translateTextEs($text, $latinizeNames);
        }
        if ($to !== 'en') {
            return $text;
        }

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
        $out = self::translateMonths($core, $months);

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
            '/^Дата рождения:\s*(.+)$/u' => fn(array $m): string => 'Date of birth: ' . self::translateText($m[1], true, $to),
            '/^Дата ухода:\s*(.+)$/u' => fn(array $m): string => 'Date of death: ' . self::translateText($m[1], true, $to),
            '/^Дата брака:\s*(.+)$/u' => fn(array $m): string => 'Marriage date: ' . self::translateText($m[1], true, $to),
            '/^(.+?)\s*·\s*прожил(а)?\s+(≈?)(\d+)\s*(год|года|лет)$/u' => fn(array $m): string => self::translateText($m[1], true, $to) . ' · lived ' . $m[3] . $m[4] . ' ' . self::enYears((int)$m[4]),
            '/^(.*·\s*)(\d+)\s*(год|года|лет)$/u' => fn(array $m): string => self::translateText($m[1], true, $to) . $m[2] . ' ' . self::enYears((int)$m[2]),
            '/^прожил(а)?\s+(≈?)(\d+)\s*(год|года|лет)$/u' => fn(array $m): string => 'lived ' . $m[2] . $m[3] . ' ' . self::enYears((int)$m[3]),
            '/^(\d+)\s*лет со дня ухода$/u' => fn(array $m): string => $m[1] . ' years since passing',
            '/^(\d+)\s*лет исполняется$/u' => fn(array $m): string => $m[1] . ' years',
            '/^Вы — (.+)$/u' => fn(array $m): string => 'You are ' . self::translateText($m[1], true, $to),
            '/^Добро пожаловать!,\s*(.+)$/u' => fn(array $m): string => 'Welcome, ' . self::translateText($m[1], true, $to),
            '/^⚭\s*замужем — (.+)$/u' => fn(array $m): string => '⚭ married — ' . self::translateText($m[1], true, $to),
            '/^замужем — (.+)$/u' => fn(array $m): string => 'married — ' . self::translateText($m[1], true, $to),
            '/^\(урожд\.\s*(.+)\)$/u' => fn(array $m): string => '(née ' . self::translateText($m[1], true, $to) . ')',
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
            '/^(.+),\s*(\d+)-е колено по отцу$/u' => fn(array $m): string => self::translateText($m[1], true, $to) . ', paternal generation ' . $m[2],
            '/^(.+),\s*(\d+)-е колено по матери$/u' => fn(array $m): string => self::translateText($m[1], true, $to) . ', maternal generation ' . $m[2],
            '/^(.+)\s+по роду Керей$/u' => fn(array $m): string => self::translateText($m[1], true, $to) . ' in the Kerey clan',
            '/^(.+)\s+по отцу$/u' => fn(array $m): string => 'Paternal ' . lcfirst(self::translateText($m[1], true, $to)),
            '/^(.+)\s+по матери$/u' => fn(array $m): string => 'Maternal ' . lcfirst(self::translateText($m[1], true, $to)),
            '/^(\d+)-я годовщина \(День памяти\)$/u' => fn(array $m): string => $m[1] . 'th anniversary (Memorial day)',
            '/^(\d+)-я годовщина$/u' => fn(array $m): string => $m[1] . 'th anniversary',
            '/^Шежіре-плакат:\s*«(.+)»\s*ұрпақтары$/u' => fn(array $m): string => 'Shezhire poster: descendants of «' . self::translateText($m[1], true, $to) . '»',
            '/^Плакат шежире:\s*потомки\s*«(.+)»$/u' => fn(array $m): string => 'Shezhire poster: descendants of «' . self::translateText($m[1], true, $to) . '»',
            '/^Плакат шежире:\s*потомки\s*«(.+)»\s*—\s*Altybayev\.kz$/u' => fn(array $m): string => 'Shezhire poster: descendants of «' . self::translateText($m[1], true, $to) . '» — Altybayev.kz',
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
                return $lead . self::latinize($out, 'en') . $trail;
            }
            return $lead . $out . $trail;
        }

        // Заменяем длинные устойчивые подписи.
        $out = self::finishCyrillic($out, $map, $latinizeNames, 'en');

        return $lead . $out . $trail;
    }

    /**
     * ALT-007: перевод одной подписи/фразы на испанский (параллельно английскому слою).
     */
    private static function translateTextEs(string $text, bool $latinizeNames = true): string
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

        $map = self::esPhrases();
        if (isset($map[$core])) {
            return $lead . $map[$core] . $trail;
        }

        // Месяцы внутри дат: «18 ноября 2026» → «18 de noviembre de 2026».
        $months = [
            'января' => 'enero', 'февраля' => 'febrero', 'марта' => 'marzo', 'апреля' => 'abril',
            'мая' => 'mayo', 'июня' => 'junio', 'июля' => 'julio', 'августа' => 'agosto',
            'сентября' => 'septiembre', 'октября' => 'octubre', 'ноября' => 'noviembre', 'декабря' => 'diciembre',
            'янв' => 'ene', 'фев' => 'feb', 'мар' => 'mar', 'апр' => 'abr', 'июн' => 'jun',
            'июл' => 'jul', 'авг' => 'ago', 'сен' => 'sep', 'окт' => 'oct', 'ноя' => 'nov', 'дек' => 'dic',
        ];
        $out = self::translateMonths($core, $months);

        // Динамические подписи: числа, возрасты, даты, счётчики.
        $dynamic = [
            '/^(\d{4})\s*г\.?$/u' => fn(array $m): string => $m[1],
            '/^(\d{4})\s*года?$/u' => fn(array $m): string => $m[1],
            '/^≈?\s*(\d+)\s*(год|года|лет)$/u' => fn(array $m): string => (str_starts_with($m[0], '≈') ? '≈' : '') . $m[1] . ' ' . self::esYears((int)$m[1]),
            '/^(\d+)\s*(дн\.?|день|дня|дней)$/u' => fn(array $m): string => $m[1] . ' ' . self::esDays((int)$m[1]),
            '/^Через\s+(\d+)\s*дн\.?$/u' => fn(array $m): string => 'En ' . $m[1] . ' ' . self::esDays((int)$m[1]),
            '/^(\d+)\s*(человек|чел\.|персон[аы]?|союзов|детей|ребёнка|ребенка)$/u' => function (array $m): string {
                $n = (int)$m[1];
                $word = preg_match('/союзов/u', $m[2]) ? ($n === 1 ? 'unión' : 'uniones')
                    : (preg_match('/детей|ребёнка|ребенка/u', $m[2]) ? ($n === 1 ? 'hijo' : 'hijos') : ($n === 1 ? 'persona' : 'personas'));
                return $m[1] . ' ' . $word;
            },
            '/^Дети \((\d+)\)$/u' => fn(array $m): string => 'Hijos (' . $m[1] . ')',
            '/^Семьи детей \((\d+)\)$/u' => fn(array $m): string => 'Familias de los hijos (' . $m[1] . ')',
            '/^(\d+)-е поколение$/u' => fn(array $m): string => 'Generación ' . $m[1],
            '/^Отображено персон:\s*(\d+)$/u' => fn(array $m): string => 'Personas mostradas: ' . $m[1],
            '/^из\s+(\d+)$/u' => fn(array $m): string => 'de ' . $m[1],
            '/^Дата рождения:\s*(.+)$/u' => fn(array $m): string => 'Fecha de nacimiento: ' . self::translateTextEs($m[1], $latinizeNames),
            '/^Дата ухода:\s*(.+)$/u' => fn(array $m): string => 'Fecha de fallecimiento: ' . self::translateTextEs($m[1], $latinizeNames),
            '/^Дата брака:\s*(.+)$/u' => fn(array $m): string => 'Fecha de matrimonio: ' . self::translateTextEs($m[1], $latinizeNames),
            '/^(.+?)\s*·\s*прожил(а)?\s+(≈?)(\d+)\s*(год|года|лет)$/u' => fn(array $m): string => self::translateTextEs($m[1], $latinizeNames) . ' · vivió ' . $m[3] . $m[4] . ' ' . self::esYears((int)$m[4]),
            '/^(.*·\s*)(\d+)\s*(год|года|лет)$/u' => fn(array $m): string => self::translateTextEs($m[1], $latinizeNames) . $m[2] . ' ' . self::esYears((int)$m[2]),
            '/^прожил(а)?\s+(≈?)(\d+)\s*(год|года|лет)$/u' => fn(array $m): string => 'vivió ' . $m[2] . $m[3] . ' ' . self::esYears((int)$m[3]),
            '/^(\d+)\s*лет со дня ухода$/u' => fn(array $m): string => $m[1] . ' años desde su fallecimiento',
            '/^(\d+)\s*лет исполняется$/u' => fn(array $m): string => $m[1] . ' años',
            '/^Вы — (.+)$/u' => fn(array $m): string => 'Usted es ' . self::translateTextEs($m[1], $latinizeNames),
            '/^Добро пожаловать!,\s*(.+)$/u' => fn(array $m): string => 'Bienvenido, ' . self::translateTextEs($m[1], $latinizeNames),
            '/^⚭\s*замужем — (.+)$/u' => fn(array $m): string => '⚭ casada — ' . self::translateTextEs($m[1], $latinizeNames),
            '/^замужем — (.+)$/u' => fn(array $m): string => 'casada — ' . self::translateTextEs($m[1], $latinizeNames),
            '/^\(урожд\.\s*(.+)\)$/u' => fn(array $m): string => '(de soltera ' . self::translateTextEs($m[1], $latinizeNames) . ')',
            '/^На ближайшие\s+(\d+)\s+дней событий не найдено$/u' => fn(array $m): string => 'No se encontraron eventos en los próximos ' . $m[1] . ' días',
            '/^Ближайшие дни рождения, поминальные годовщины и юбилеи свадеб на\s+(\d+)\s+дней вперед$/u' => fn(array $m): string => 'Próximos cumpleaños, aniversarios conmemorativos y aniversarios de boda en los próximos ' . $m[1] . ' días',
            '/^(\d+)\s*год(?:а)?$/u' => fn(array $m): string => $m[1] . ' ' . self::esYears((int)$m[1]),
            '/^(\d+)\s*жас$/u' => fn(array $m): string => $m[1] . ' años',
            '/^(\d+)\s*жыл$/u' => fn(array $m): string => $m[1] . ' años',
            '/^(Сын|Дочь|Брат|Сестра)\s*·\s*(.+)$/u' => function (array $m) use ($latinizeNames): string {
                $word = ['Сын' => 'Hijo', 'Дочь' => 'Hija', 'Брат' => 'Hermano', 'Сестра' => 'Hermana'][$m[1]] ?? $m[1];
                return $word . ' · ' . self::translateTextEs($m[2], $latinizeNames);
            },
            '/^Фото и видео, где отмечен\(а\)\s*·\s*(\d+)$/u' => fn(array $m): string => 'Fotos y vídeos etiquetados · ' . $m[1],
            '/^(.+),\s*(\d+)-е колено по отцу$/u' => fn(array $m): string => self::translateTextEs($m[1], $latinizeNames) . ', generación paterna ' . $m[2],
            '/^(.+),\s*(\d+)-е колено по матери$/u' => fn(array $m): string => self::translateTextEs($m[1], $latinizeNames) . ', generación materna ' . $m[2],
            '/^(.+)\s+по роду Керей$/u' => fn(array $m): string => self::translateTextEs($m[1], $latinizeNames) . ' del clan Kerey',
            '/^(.+)\s+по отцу$/u' => fn(array $m): string => 'Paterno ' . lcfirst(self::translateTextEs($m[1], $latinizeNames)),
            '/^(.+)\s+по матери$/u' => fn(array $m): string => 'Materno ' . lcfirst(self::translateTextEs($m[1], $latinizeNames)),
            '/^(\d+)-я годовщина \(День памяти\)$/u' => fn(array $m): string => $m[1] . '.º aniversario (Día conmemorativo)',
            '/^(\d+)-я годовщина$/u' => fn(array $m): string => $m[1] . '.º aniversario',
            '/^Шежіре-плакат:\s*«(.+)»\s*ұрпақтары$/u' => fn(array $m): string => 'Póster de shezhire: descendientes de «' . self::translateTextEs($m[1], $latinizeNames) . '»',
            '/^Плакат шежире:\s*потомки\s*«(.+)»$/u' => fn(array $m): string => 'Póster de shezhire: descendientes de «' . self::translateTextEs($m[1], $latinizeNames) . '»',
            '/^Плакат шежире:\s*потомки\s*«(.+)»\s*—\s*Altybayev\.kz$/u' => fn(array $m): string => 'Póster de shezhire: descendientes de «' . self::translateTextEs($m[1], $latinizeNames) . '» — Altybayev.kz',
            '/^(\d+)-буын$/u' => fn(array $m): string => 'Generación ' . $m[1],
            '/^(\d+)-е колено$/u' => fn(array $m): string => 'Generación ' . $m[1],
        ];
        foreach ($dynamic as $pattern => $fn) {
            if (preg_match($pattern, $out, $m)) {
                return $lead . $fn($m) . $trail;
            }
        }

        // Имена собственные (люди, места, роды): для es транслитерируем в латиницу.
        $looksLikeName = (bool)preg_match(
            '/^[А-ЯЁӘҒҚҢӨҰҮҺІ][а-яёәғқңөұүһі-]*(?:\s+[А-ЯЁӘҒҚҢӨҰҮҺІ][а-яёәғқңөұүһі-]*){0,3}$/u',
            $out
        );
        if ($looksLikeName) {
            if ($latinizeNames) {
                return $lead . self::latinize($out, 'es') . $trail;
            }
            return $lead . $out . $trail;
        }

        $out = self::finishCyrillic($out, $map, $latinizeNames, 'es');

        return $lead . $out . $trail;
    }

    /**
     * ALT-022: смешанный текст — заменяем целые слова из словаря, остаток кириллицы
     * транслитерируем (en/es), чтобы на этих языках кириллица не оставалась.
     */
    private static function finishCyrillic(string $out, array $map, bool $latinizeNames, string $lang): string
    {
        $cyr = '/[А-Яа-яЁёӘәҒғҚқҢңӨөҰұҮүҺһІі]/u';
        if (!preg_match($cyr, $out)) {
            return $out;
        }
        $keys = [];
        foreach ($map as $k => $v) {
            $k = (string)$k;
            if (mb_strlen($k, 'UTF-8') >= 5 && str_contains($out, $k)) {
                $keys[$k] = (string)$v;
            }
        }
        // длинные фразы первыми; границы слов, чтобы «не указан» не ломал «не указана»
        uksort($keys, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($keys as $k => $v) {
            $out = preg_replace_callback(
                '/(?<![\p{L}])' . preg_quote($k, '/') . '(?![\p{L}])/u',
                static fn(array $m): string => $v,
                $out
            ) ?? $out;
        }
        if ($latinizeNames && preg_match($cyr, $out)) {
            // «Алга РК» → «Alga, Kazakhstan» / «Alga, Kazajistán»: страна словом нужного языка
            $out = preg_replace(
                '/^(.+?)[,\s]+(?:РК|Республика Казахстан|Казахстан|Қазақстан)$/u',
                '$1, ' . ($lang === 'es' ? 'Kazajistán' : 'Kazakhstan'),
                $out
            ) ?? $out;
            $out = preg_replace_callback(
                '/[\p{Cyrillic}]+(?:-[\p{Cyrillic}]+)*/u',
                static fn(array $m): string => self::latinize($m[0], $lang),
                $out
            ) ?? $out;
        }
        return $out;
    }

    /**
     * ALT-007: перевод готового HTML всех страниц для английского/испанского языка.
     * Переводим текстовые узлы и подписи атрибутов; скрипты и данные не трогаем.
     */
    public static function translateHtml(string $html): string
    {
        if ((!self::isEn() && !self::isEs()) || $html === '') {
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
            // TASK-027: приветственная страница — окна возможностей и обратная связь
            'wl_invite' => ['kk' => 'Қолжетімділік тек шақыру бойынша.', 'ru' => 'Доступ только по приглашению.', 'en' => 'Access by invitation only.', 'es' => 'Acceso solo por invitación.'],
            'wl_invite_google' => ['kk' => 'Кіру — Google аккаунты арқылы.', 'ru' => 'Вход — через аккаунт Google.', 'en' => 'Sign in with your Google account.', 'es' => 'Inicia sesión con tu cuenta de Google.'],
            'wl_close' => ['kk' => 'Жабу', 'ru' => 'Закрыть', 'en' => 'Close', 'es' => 'Cerrar'],
            'wl_feat_tree' => ['kk' => 'Бүкіл ру көрінеді: адамды басып, оның отбасын ашыңыз. «Жеті ата» (жеті буын) және «Үш жұрт» режимдері бар.', 'ru' => 'Род виден целиком: нажимайте на человека, чтобы увидеть его семью. Есть режимы «жеті ата» (семь поколений предков) и «Үш жұрт» (три родственные линии).', 'en' => 'The whole clan at a glance: tap a person to see their family. There are "Jeti ata" (seven generations of ancestors) and "Üsh jurt" (three kin lines) views.', 'es' => 'Todo el linaje de un vistazo: toca a una persona para ver su familia. Hay vistas «Jeti ata» (siete generaciones de antepasados) y «Üsh jurt» (tres líneas de parentesco).'],
            'wl_feat_families' => ['kk' => 'Отбасын басыңыз — балалары ашылады; әр баланың өз отбасын ашып, буындар бойынша әрі қарай жүруге болады.', 'ru' => 'Нажмите на семью — раскроется список детей; у каждого ребёнка можно раскрыть его собственную семью и идти дальше по поколениям.', 'en' => 'Tap a family to reveal its children; each child can reveal their own family, and so on down the generations.', 'es' => 'Toca una familia para ver a sus hijos; cada hijo puede mostrar su propia familia y así de generación en generación.'],
            'wl_feat_person' => ['kk' => 'Карточкада — фото, өмір күндері, ата-анасы, жұбайы, балалары және адам туралы әңгіме. Байланыс деректері адам рұқсат етсе ғана көрінеді.', 'ru' => 'В карточке — фото, даты жизни, родители, супруги, дети и рассказ о человеке. Контакты видны только если человек сам разрешил их показывать.', 'en' => 'A card holds the photo, life dates, parents, spouses, children and the person\'s story. Contacts are shown only if the person allows it.', 'es' => 'La ficha reúne la foto, fechas de vida, padres, cónyuges, hijos y la historia de la persona. Los contactos solo se muestran si la persona lo permite.'],
            // ALT-081: подпись над семьёй на дереве; {name} уже стоит в нужном падеже (kk/ru) или в латинице (en/es)
            'tree_family_label' => ['kk' => '{name} отбасы', 'ru' => 'Семья {name}', 'en' => '{name}\'s family', 'es' => 'Familia de {name}'],
            'wl_feat_birthdays' => ['kk' => 'Жақындардың таяудағы туған күндері мен отбасының ескерткіш күндері бір күнтізбеде.', 'ru' => 'Ближайшие дни рождения родных и памятные даты семьи собраны в одном календаре.', 'en' => 'Upcoming birthdays of relatives and the family\'s memorable dates are gathered in one calendar.', 'es' => 'Los próximos cumpleaños de los familiares y las fechas memorables de la familia, en un solo calendario.'],
            'wl_feat_albums' => ['kk' => 'Альбомдарды отбасы және шара бойынша жүргізуге болады: той, мерейтой, кездесу. Ескі суреттер бір жерде сақталады.', 'ru' => 'Альбомы можно вести по семьям и по событиям: тои, юбилеи, встречи. Старые снимки хранятся в одном месте.', 'en' => 'Albums can be kept by family and by event: weddings, anniversaries, gatherings. Old photos live in one place.', 'es' => 'Los álbumes se organizan por familia y por evento: bodas, aniversarios, reuniones. Las fotos antiguas quedan en un solo lugar.'],
            'wl_feat_grove' => ['kk' => 'Емен орманындағы әр ағаш — бір отбасы. Орманда серуендеп, туыстардың қалай өскенін көруге болады.', 'ru' => 'Каждое дерево в дубраве — семья. Можно «пройтись» по роще и увидеть, как выросла наша родня.', 'en' => 'Every tree in the oak grove is a family. Walk through the grove and see how our kin has grown.', 'es' => 'Cada árbol del robledal es una familia. Pasea por el bosque y mira cómo ha crecido nuestra familia.'],
            'fb_open' => ['kk' => 'Байланысу', 'ru' => 'Связаться', 'en' => 'Contact us', 'es' => 'Contactar'],
            'fb_title' => ['kk' => 'Қосылғыңыз келе ме немесе сұрағыңыз бар ма?', 'ru' => 'Хотите присоединиться или есть вопрос?', 'en' => 'Want to join, or have a question?', 'es' => '¿Quieres unirte o tienes una pregunta?'],
            'fb_owner_default' => ['kk' => 'сайт иесі', 'ru' => 'владельцем сайта', 'en' => 'the site owner', 'es' => 'el propietario del sitio'],
            'fb_lead' => ['kk' => 'Байланысыңыз: {owner} — жауап береміз, шежіреге қосамыз.', 'ru' => 'Свяжитесь с {owner} — ответим и добавим в шежіре.', 'en' => 'Get in touch with {owner} — we will reply and add you to the family tree.', 'es' => 'Contacta con {owner}: responderemos y te añadiremos al árbol familiar.'],
            'fb_mail_btn' => ['kk' => 'Поштаға жазу', 'ru' => 'Написать на почту', 'en' => 'Send an email', 'es' => 'Enviar un correo'],
            'fb_name' => ['kk' => 'Атыңыз', 'ru' => 'Ваше имя', 'en' => 'Your name', 'es' => 'Tu nombre'],
            'fb_contact' => ['kk' => 'Телефон немесе e-mail', 'ru' => 'Телефон или e-mail', 'en' => 'Phone or e-mail', 'es' => 'Teléfono o e-mail'],
            'fb_message' => ['kk' => 'Хабарлама', 'ru' => 'Сообщение', 'en' => 'Message', 'es' => 'Mensaje'],
            'fb_send' => ['kk' => 'Жіберу', 'ru' => 'Отправить', 'en' => 'Send', 'es' => 'Enviar'],
            'fb_fill_all' => ['kk' => 'Барлық өрісті толтырыңыз.', 'ru' => 'Заполните все поля.', 'en' => 'Please fill in all fields.', 'es' => 'Rellena todos los campos.'],
            'fb_ok' => ['kk' => 'Рақмет! Хабарлама жіберілді.', 'ru' => 'Спасибо! Сообщение отправлено.', 'en' => 'Thank you! Your message has been sent.', 'es' => '¡Gracias! Tu mensaje ha sido enviado.'],
            'fb_fail' => ['kk' => 'Жіберу мүмкін болмады.', 'ru' => 'Не удалось отправить.', 'en' => 'Could not send the message.', 'es' => 'No se pudo enviar el mensaje.'],
            'fb_offline' => ['kk' => 'Байланыс жоқ, кейінірек көріңіз.', 'ru' => 'Нет связи, попробуйте позже.', 'en' => 'No connection, please try again later.', 'es' => 'Sin conexión, inténtalo más tarde.'],
            'fb_err_csrf' => ['kk' => 'Сессия ескірді, бетті жаңартыңыз.', 'ru' => 'Сессия устарела, обновите страницу.', 'en' => 'Session expired, please refresh the page.', 'es' => 'La sesión ha caducado, actualiza la página.'],
            'fb_err_fast' => ['kk' => 'Тым жылдам, қайталап көріңіз.', 'ru' => 'Слишком быстро, попробуйте ещё раз.', 'en' => 'Too fast, please try again.', 'es' => 'Demasiado rápido, inténtalo de nuevo.'],
            'fb_err_name' => ['kk' => 'Атыңызды жазыңыз.', 'ru' => 'Укажите имя.', 'en' => 'Please enter your name.', 'es' => 'Indica tu nombre.'],
            'fb_err_contact' => ['kk' => 'Телефон немесе e-mail жазыңыз.', 'ru' => 'Укажите телефон или e-mail.', 'en' => 'Please enter a phone or e-mail.', 'es' => 'Indica un teléfono o e-mail.'],
            'fb_err_message' => ['kk' => 'Хабарлама: 5–2000 таңба.', 'ru' => 'Сообщение: от 5 до 2000 символов.', 'en' => 'Message: 5 to 2000 characters.', 'es' => 'Mensaje: de 5 a 2000 caracteres.'],
            'fb_err_limit' => ['kk' => 'Тым көп өтініш, кейінірек көріңіз.', 'ru' => 'Слишком много обращений, попробуйте позже.', 'en' => 'Too many messages, please try again later.', 'es' => 'Demasiados mensajes, inténtalo más tarde.'],
            'fb_err_send' => ['kk' => 'Жіберу мүмкін болмады, кейінірек көріңіз.', 'ru' => 'Не удалось отправить, попробуйте позже.', 'en' => 'Could not send, please try again later.', 'es' => 'No se pudo enviar, inténtalo más tarde.'],
            // TASK-027 п.8: все тексты приветственной страницы на 4 языках
            'wl_page_title' => ['kk' => 'Жүйеге кіру', 'ru' => 'Вход в систему', 'en' => 'Sign in', 'es' => 'Iniciar sesión'],
            'wl_login' => ['kk' => 'Кіру / Войти', 'ru' => 'Кіру / Войти', 'en' => 'Sign in', 'es' => 'Entrar'],
            // ALT-015: первый экран приветственной страницы
            'wl_hero_eyebrow' => ['kk' => 'Әулеттің тірі ағашы', 'ru' => 'Живое древо рода', 'en' => 'The living family tree', 'es' => 'El árbol vivo de la familia'],
            'wl_hero_tag' => ['kk' => 'Алтыбаевтар әулетінің жабық отбасылық шежіресі: ата-баба, отбасылар, суреттер мен маңызды күндер бір жерде.', 'ru' => 'Закрытое семейное древо рода Алтыбаевых: предки, семьи, фото и памятные даты — в одном месте.', 'en' => 'The private family tree of the Altybayev clan: ancestors, families, photos and key dates in one place.', 'es' => 'El árbol genealógico privado del clan Altybáyev: antepasados, familias, fotos y fechas clave en un solo lugar.'],
            'wl_demo_note' => ['kk' => 'Үлгі: ойдан шығарылған есімдер', 'ru' => 'Образец: вымышленные имена', 'en' => 'Sample: fictional names', 'es' => 'Muestra: nombres ficticios'],
            'wl_scroll' => ['kk' => 'Төменге қарай жылжыңыз', 'ru' => 'Листайте вниз', 'en' => 'Scroll down', 'es' => 'Desliza hacia abajo'],
            'wl_pt_1' => ['kk' => 'Ұрпақтар', 'ru' => 'Поколения', 'en' => 'Generations', 'es' => 'Generaciones'],
            'wl_pt_1s' => ['kk' => 'жеті атадан бүгінге дейін', 'ru' => 'от седьмого колена до наших дней', 'en' => 'from the seventh ancestor to today', 'es' => 'del séptimo ancestro a hoy'],
            'wl_pt_2' => ['kk' => 'Отбасылар', 'ru' => 'Семьи', 'en' => 'Families', 'es' => 'Familias'],
            'wl_pt_2s' => ['kk' => 'шаңырақтар мен балалар', 'ru' => 'семьи и дети', 'en' => 'households and children', 'es' => 'hogares e hijos'],
            'wl_pt_3' => ['kk' => 'Альбомдар', 'ru' => 'Альбомы', 'en' => 'Albums', 'es' => 'Álbumes'],
            'wl_pt_3s' => ['kk' => 'суреттер мен естеліктер', 'ru' => 'фото и воспоминания', 'en' => 'photos and memories', 'es' => 'fotos y recuerdos'],
            'wl_pt_4' => ['kk' => 'Күндер', 'ru' => 'Даты', 'en' => 'Dates', 'es' => 'Fechas'],
            'wl_pt_4s' => ['kk' => 'туған күндер мен еске алу', 'ru' => 'дни рождения и памяти', 'en' => 'birthdays and remembrance', 'es' => 'cumpleaños y recuerdos'],
            'wl_lang_label' => ['kk' => 'Тіл', 'ru' => 'Язык', 'en' => 'Language', 'es' => 'Idioma'],
            'wl_theme_label' => ['kk' => 'Жарық / қараңғы тақырып', 'ru' => 'Светлая / тёмная тема', 'en' => 'Light / dark theme', 'es' => 'Tema claro / oscuro'],
            'wl_lead' => ['kk' => 'Мұнда Алтыбаевтар әулетінің шежіресі сақталады: кім кімнен тарады, тамыр қайда, отбасылар қалай өсті. Бұл — интернеттегі жылы отбасылық үй: балалар ата-бабасын білсін, жақындар үнемі қасымызда болсын.', 'ru' => 'Здесь живёт шежіре семьи Алтыбаевых: кто от кого пошёл, где чьи корни, как выросли наши семьи. Это тёплый семейный дом в интернете — чтобы дети знали своих предков, а родные всегда были рядом.', 'en' => 'This is the shezhire — the family chronicle — of the Altybayev family: who descends from whom, where our roots lie, how our families have grown. It is a warm family home on the internet, so that children know their ancestors and loved ones are always close.', 'es' => 'Aquí vive el shezhire —la crónica familiar— de los Altybáyev: quién desciende de quién, dónde están nuestras raíces, cómo han crecido nuestras familias. Es un cálido hogar familiar en internet, para que los niños conozcan a sus antepasados y los seres queridos estén siempre cerca.'],
            'wl_chip_1' => ['kk' => 'Ру ағашы', 'ru' => 'Древо рода', 'en' => 'Family tree', 'es' => 'Árbol familiar'],
            'wl_chip_2' => ['kk' => 'Жеті ата', 'ru' => 'Жеті ата', 'en' => 'Seven generations (Zheti ata)', 'es' => 'Siete generaciones (Zheti ata)'],
            'wl_chip_3' => ['kk' => 'Үш жұрт', 'ru' => 'Үш жұрт', 'en' => 'Three kin lines (Üsh zhurt)', 'es' => 'Tres linajes (Üsh zhurt)'],
            'wl_chip_4' => ['kk' => 'Отбасылар мен балалар', 'ru' => 'Семьи и дети', 'en' => 'Families and children', 'es' => 'Familias e hijos'],
            'wl_chip_5' => ['kk' => 'Туған күндер', 'ru' => 'Дни рождения', 'en' => 'Birthdays', 'es' => 'Cumpleaños'],
            'wl_chip_6' => ['kk' => 'Фотоальбомдар', 'ru' => 'Фотоальбомы', 'en' => 'Photo albums', 'es' => 'Álbumes de fotos'],
            'wl_chip_7' => ['kk' => '3D емен орманы', 'ru' => '3D-дубрава', 'en' => '3D oak grove', 'es' => 'Robledal en 3D'],
            'wl_chip_8' => ['kk' => 'Туыстар байланысы', 'ru' => 'Контакты родных', 'en' => 'Relatives\' contacts', 'es' => 'Contactos de familiares'],
            'wl_what' => ['kk' => 'Мұнда не істеуге болады', 'ru' => 'Что здесь можно делать', 'en' => 'What you can do here', 'es' => 'Qué puedes hacer aquí'],
            'wl_what_sub' => ['kk' => 'Бірнеше үлгі сурет — мұнда ойдан шығарылған есімдер, нақты адамдар емес.', 'ru' => 'Несколько картинок-образцов — на них вымышленные имена, не настоящие люди.', 'en' => 'A few sample pictures — the names are fictional, not real people.', 'es' => 'Unas imágenes de muestra: los nombres son ficticios, no personas reales.'],
            'wl_a_tree' => ['kk' => 'Ру ағашының сызбасы', 'ru' => 'Схема родового древа', 'en' => 'Family tree diagram', 'es' => 'Esquema del árbol genealógico'],
            'wl_t_tree' => ['kk' => 'Ру ағашы', 'ru' => 'Древо рода', 'en' => 'Family tree', 'es' => 'Árbol familiar'],
            'wl_d_tree' => ['kk' => 'Бүкіл ру бір экранда: ата-бабадан немерелерге дейін. Жылжытыңыз, үлкейтіңіз, жеті ата мен Үш жұртты қараңыз.', 'ru' => 'Весь род на одном экране: от предков до внуков. Листайте, приближайте, смотрите жеті ата и Үш жұрт.', 'en' => 'The whole clan on one screen: from ancestors to grandchildren. Scroll, zoom in, explore the seven generations and the Üsh zhurt.', 'es' => 'Todo el linaje en una pantalla: de los antepasados a los nietos. Desplázate, acerca y explora las siete generaciones y el Üsh zhurt.'],
            'wl_a_families' => ['kk' => 'Балалар тізімі ашылатын отбасы', 'ru' => 'Семья с раскрывающимся списком детей', 'en' => 'A family with an expandable list of children', 'es' => 'Familia con lista de hijos desplegable'],
            'wl_t_families' => ['kk' => 'Отбасылар мен балалар', 'ru' => 'Семьи и дети', 'en' => 'Families and children', 'es' => 'Familias e hijos'],
            'wl_d_families' => ['kk' => 'Әр отбасы — бөлек карточка. Басыңыз — балалары ашылады, олардың артынан өз отбасылары.', 'ru' => 'Каждая семья — отдельная карточка. Нажмите — и раскроются дети, а за ними и их семьи.', 'en' => 'Every family is its own card. Tap it and the children unfold, followed by their own families.', 'es' => 'Cada familia es una tarjeta. Pulsa y se despliegan los hijos, y tras ellos sus propias familias.'],
            'wl_a_person' => ['kk' => 'Адам карточкасы', 'ru' => 'Карточка человека', 'en' => 'Person card', 'es' => 'Ficha de persona'],
            'wl_t_person' => ['kk' => 'Адам карточкасы', 'ru' => 'Карточка человека', 'en' => 'Person card', 'es' => 'Ficha de persona'],
            'wl_d_person' => ['kk' => 'Фото, күндер, ата-ана, жұбай мен балалар, өмір баяны және адам рұқсат етсе — байланыс деректері.', 'ru' => 'Фото, даты, родители, супруги и дети, история жизни и, если человек разрешил, — контакты.', 'en' => 'Photo, dates, parents, spouses and children, life story and — if the person allows it — contact details.', 'es' => 'Foto, fechas, padres, cónyuges e hijos, historia de vida y, si la persona lo permite, datos de contacto.'],
            'wl_a_birthdays' => ['kk' => 'Туған күндер күнтізбесі', 'ru' => 'Календарь дней рождения', 'en' => 'Birthday calendar', 'es' => 'Calendario de cumpleaños'],
            'wl_t_birthdays' => ['kk' => 'Туған күндер мен ескерту күндері', 'ru' => 'Дни рождения и памятные даты', 'en' => 'Birthdays and memorable dates', 'es' => 'Cumpleaños y fechas memorables'],
            'wl_d_birthdays' => ['kk' => 'Кімнің туған күні жақындағанын еске салып, отбасының ескерткіш күндерін сақтайды.', 'ru' => 'Сайт напомнит, у кого скоро день рождения, и сохранит памятные даты семьи.', 'en' => 'The site reminds you whose birthday is coming up and keeps the family\'s memorable dates.', 'es' => 'El sitio te recuerda quién cumple años pronto y guarda las fechas memorables de la familia.'],
            'wl_a_albums' => ['kk' => 'Фотоальбом', 'ru' => 'Фотоальбом', 'en' => 'Photo album', 'es' => 'Álbum de fotos'],
            'wl_t_albums' => ['kk' => 'Фотоальбомдар', 'ru' => 'Фотоальбомы', 'en' => 'Photo albums', 'es' => 'Álbumes de fotos'],
            'wl_d_albums' => ['kk' => 'Отбасылар мен отбасылық шаралар альбомдары: той, мерейтой, кездесу. Ескі суреттер енді жоғалмайды.', 'ru' => 'Альбомы семей и семейных событий: тои, юбилеи, встречи. Старые снимки больше не потеряются.', 'en' => 'Albums of families and family events: weddings, anniversaries, gatherings. Old photos will never be lost again.', 'es' => 'Álbumes de familias y de eventos familiares: bodas, aniversarios, reuniones. Las fotos antiguas ya no se perderán.'],
            'wl_a_grove' => ['kk' => '3D емен орманы', 'ru' => '3D-дубрава', 'en' => '3D oak grove', 'es' => 'Robledal en 3D'],
            'wl_t_grove' => ['kk' => '3D емен орманы', 'ru' => '3D-дубрава', 'en' => '3D oak grove', 'es' => 'Robledal en 3D'],
            'wl_d_grove' => ['kk' => 'Ру — орман іспетті: әр ағаш — бір отбасы. Арасында серуендеп, бәрінің қалай өскенін көруге болады.', 'ru' => 'Род как роща: каждое дерево — семья. Можно пройтись между ними и увидеть, как всё выросло.', 'en' => 'The clan as a grove: every tree is a family. Walk between them and see how it all grew.', 'es' => 'El linaje como un bosque: cada árbol es una familia. Pasea entre ellos y mira cómo todo ha crecido.'],
            'wl_privacy' => ['kk' => 'Құпиялылық саясаты', 'ru' => 'Политика конфиденциальности', 'en' => 'Privacy policy', 'es' => 'Política de privacidad'],
            'wl_terms' => ['kk' => 'Шарттар', 'ru' => 'Условия', 'en' => 'Terms', 'es' => 'Condiciones'],
            'wl_info' => ['kk' => 'Мәліметтер', 'ru' => 'Сведения', 'en' => 'Information', 'es' => 'Información'],
            'wl_err_auth_t' => ['kk' => 'Кіру сәтсіз аяқталды:', 'ru' => 'Ошибка входа:', 'en' => 'Sign-in failed:', 'es' => 'Error de inicio de sesión:'],
            'wl_err_auth' => ['kk' => 'Google арқылы кіру кезінде қате орын алды. Қайталап көріңіз.', 'ru' => 'При авторизации через Google произошла ошибка. Попробуйте снова.', 'en' => 'An error occurred while signing in with Google. Please try again.', 'es' => 'Se produjo un error al iniciar sesión con Google. Inténtalo de nuevo.'],
            'wl_err_state_t' => ['kk' => 'Қауіпсіздік қатесі:', 'ru' => 'Ошибка сессии:', 'en' => 'Session error:', 'es' => 'Error de sesión:'],
            'wl_err_state' => ['kk' => 'Сессия мерзімі өтіп кетті. Бетті жаңартып, қайта кіріңіз.', 'ru' => 'Сессия устарела. Обновите страницу и попробуйте снова.', 'en' => 'The session has expired. Refresh the page and try again.', 'es' => 'La sesión ha caducado. Actualiza la página e inténtalo de nuevo.'],
            'wl_err_forbidden_t' => ['kk' => 'Рұқсат жоқ:', 'ru' => 'Доступ запрещен:', 'en' => 'Access denied:', 'es' => 'Acceso denegado:'],
            'wl_err_forbidden' => ['kk' => 'Бұл бөлімге кіруге құқығыңыз жеткіліксіз.', 'ru' => 'Недостаточно прав для доступа к этому разделу.', 'en' => 'You do not have enough rights to access this section.', 'es' => 'No tienes permisos suficientes para acceder a esta sección.'],
            'wl_err_admin_t' => ['kk' => 'Тек әкімшіге:', 'ru' => 'Только для администратора:', 'en' => 'Administrators only:', 'es' => 'Solo administradores:'],
            'wl_err_admin' => ['kk' => 'Бұл бет тек әкімшіге арналған.', 'ru' => 'Этот раздел доступен только администратору.', 'en' => 'This section is available to administrators only.', 'es' => 'Esta sección está disponible solo para administradores.'],
            'wl_err_other_t' => ['kk' => 'Қате:', 'ru' => 'Ошибка:', 'en' => 'Error:', 'es' => 'Error:'],
            'wl_music_on' => ['kk' => 'Музыканы қосу', 'ru' => 'Включить музыку', 'en' => 'Turn music on', 'es' => 'Activar la música'],
            'wl_music_off' => ['kk' => 'Музыканы өшіру', 'ru' => 'Выключить музыку', 'en' => 'Turn music off', 'es' => 'Desactivar la música'],

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
            'pme_upload_photo' => ['kk' => 'Фото жүктеу', 'ru' => 'Загрузить фото'],
            'pme_replace_photo' => ['kk' => 'Фотоны ауыстыру', 'ru' => 'Заменить фото'],
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
            'pme_reframe' => ['kk' => 'Қайта кесу', 'ru' => 'Перекадрировать'],
            'pme_preview' => ['kk' => 'Аватар осылай көрінеді', 'ru' => 'Так будет в аватарке'],
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

        // ALT-007: гарантируем наличие английского и испанского варианта у каждого ключа.
        foreach ($dict as $key => $row) {
            if (!isset($row['en'])) {
                $dict[$key]['en'] = self::translateText((string)($row['ru'] ?? $row['kk'] ?? ''), true, 'en');
            }
            if (!isset($row['es'])) {
                $dict[$key]['es'] = self::translateText((string)($row['ru'] ?? $row['kk'] ?? ''), true, 'es');
            }
        }

        return $dict;
    }
}
