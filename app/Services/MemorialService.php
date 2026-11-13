<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use PDO;

/**
 * Туған күндер мен ата-бабаларды еске алу, ас беру, жылдық күндерінің сервисі
 * (Сервис дней рождения и памятных дат, годовщин ушедших, поминальных асов)
 */
class MemorialService
{
    protected ?KinshipService $kinshipService = null;

    /**
     * @var array<int, array>|null
     */
    protected ?array $allPersonsMapCache = null;

    /**
     * @var array<int, array>|null
     */
    protected ?array $marriagesMapCache = null;

    public function __construct(?KinshipService $kinshipService = null)
    {
        $this->kinshipService = $kinshipService;
    }

    /**
     * Загрузка общего справочника персон и браков для определения родителей/супругов
     */
    protected function loadGlobalPersonsAndMarriages(): void
    {
        if ($this->allPersonsMapCache !== null) {
            return;
        }

        $pdo = Database::getConnection();
        $this->allPersonsMapCache = [];
        $stmtAll = $pdo->query("SELECT id, first_name, last_name, middle_name, maiden_name, gender, birth_date, birth_year, death_date, death_year, is_deceased, clan_ru, avatar_path, father_id, mother_id FROM persons WHERE is_deleted = 0");
        foreach ($stmtAll->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $this->allPersonsMapCache[(int)$row['id']] = $row;
        }

        $this->marriagesMapCache = [];
        $stmtM = $pdo->query("SHOW TABLES LIKE 'marriages'");
        if ($stmtM && $stmtM->rowCount() > 0) {
            $mRows = $pdo->query("SELECT * FROM marriages ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($mRows as $m) {
                $h = (int)($m['husband_id'] ?? 0);
                $w = (int)($m['wife_id'] ?? 0);
                if ($h && $w) {
                    $this->marriagesMapCache[$w][] = $this->allPersonsMapCache[$h] ?? ['id' => $h];
                    $this->marriagesMapCache[$h][] = $this->allPersonsMapCache[$w] ?? ['id' => $w];
                }
            }
        }
    }

    /**
     * Определение родительской принадлежности (чей он ребенок или чья жена)
     *
     * @param array $p
     * @return array{kk: string, ru: string}
     */
    public function determineParentAffiliation(array $p): array
    {
        $this->loadGlobalPersonsAndMarriages();
        $gender = $p['gender'] ?? 'M';
        $pId = (int)($p['id'] ?? 0);

        // Для женщин в браке: «Жандостың әйелі / жары»
        if ($gender === 'F' && !empty($this->marriagesMapCache[$pId])) {
            $spouse = $this->marriagesMapCache[$pId][0] ?? null;
            if ($spouse && !empty($spouse['first_name'])) {
                $hName = trim($spouse['first_name']);
                $hGen = \App\Core\I18n::kazakhGenitive($hName);
                $extraKk = '';
                $extraRu = '';
                if (!empty($p['maiden_name'])) {
                    $extraKk = ", қыз кезінде: {$p['maiden_name']}";
                    $extraRu = ", урожд. {$p['maiden_name']}";
                } elseif (!empty($p['clan_ru']) && $p['clan_ru'] !== 'белгісіз') {
                    $extraKk = ", {$p['clan_ru']} қызы";
                    $extraRu = ", род {$p['clan_ru']}";
                }
                return [
                    'kk' => "{$hGen} әйелі{$extraKk}",
                    'ru' => "жена {$hName}{$extraRu}",
                ];
            }
        }

        // Если известен отец
        if (!empty($p['father_id']) && isset($this->allPersonsMapCache[$p['father_id']])) {
            $f = $this->allPersonsMapCache[$p['father_id']];
            $fName = trim($f['first_name'] ?? '');
            if ($fName !== '') {
                $fGen = \App\Core\I18n::kazakhGenitive($fName);
                return [
                    'kk' => ($gender === 'F') ? "{$fGen} қызы" : "{$fGen} ұлы",
                    'ru' => ($gender === 'F') ? "дочь {$fName}" : "сын {$fName}",
                ];
            }
        }

        // Если известна мать
        if (!empty($p['mother_id']) && isset($this->allPersonsMapCache[$p['mother_id']])) {
            $m = $this->allPersonsMapCache[$p['mother_id']];
            $mName = trim($m['first_name'] ?? '');
            if ($mName !== '') {
                $mGen = \App\Core\I18n::kazakhGenitive($mName);
                return [
                    'kk' => ($gender === 'F') ? "{$mGen} қызы" : "{$mGen} ұлы",
                    'ru' => ($gender === 'F') ? "дочь {$mName}" : "сын {$mName}",
                ];
            }
        }

        if (!empty($p['maiden_name'])) {
            return [
                'kk' => "Қыз кезіндегі тегі: {$p['maiden_name']}",
                'ru' => "Урожд. {$p['maiden_name']}",
            ];
        }

        if (!empty($p['clan_ru']) && $p['clan_ru'] !== 'белгісіз') {
            return [
                'kk' => "Руы: {$p['clan_ru']}",
                'ru' => "Род: {$p['clan_ru']}",
            ];
        }

        return ['kk' => '', 'ru' => ''];
    }

    /**
     * Парсинг дат различных форматов (ISO, DD.MM.YYYY, русский текст, английский GEDCOM)
     *
     * @param string|null $raw
     * @return array{year: int|null, month: int|null, day: int|null}|null
     */
    public function parseFlexibleDate(?string $raw): ?array
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Только год (без месяца и дня)
        if (preg_match('/^\d{4}$/', $raw)) {
            return ['year' => (int)$raw, 'month' => null, 'day' => null];
        }

        // ISO: YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $m)) {
            return ['year' => (int)$m[1], 'month' => (int)$m[2], 'day' => (int)$m[3]];
        }

        // DD.MM.YYYY
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $raw, $m)) {
            return ['year' => (int)$m[3], 'month' => (int)$m[2], 'day' => (int)$m[1]];
        }

        $monthMap = [
            'янв' => 1, 'фев' => 2, 'мар' => 3, 'апр' => 4, 'май' => 5, 'мая' => 5, 'июн' => 6,
            'июл' => 7, 'авг' => 8, 'сен' => 9, 'окт' => 10, 'ноя' => 11, 'дек' => 12,
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
            'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];

        // "8 марта 1979года", "7 июл 2007", "23 MAY 1998"
        if (preg_match('/^(\d{1,2})\s+([a-zA-Zа-яА-ЯёЁ]+)\s*(\d{4})?/u', $raw, $m)) {
            $day = (int)$m[1];
            $monthStr = mb_strtolower(mb_substr($m[2], 0, 3));
            $year = !empty($m[3]) ? (int)$m[3] : null;
            if (isset($monthMap[$monthStr])) {
                return ['year' => $year, 'month' => $monthMap[$monthStr], 'day' => $day];
            }
        }

        try {
            $dt = new DateTimeImmutable($raw);
            return [
                'year'  => (int)$dt->format('Y'),
                'month' => (int)$dt->format('m'),
                'day'   => (int)$dt->format('d'),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Получить ближайшие дни рождения живых родственников
     *
     * @param int $daysAhead Количество дней вперед (по умолчанию 60)
     * @param int|null $focusPersonId ID персоны пользователя для расчета родства
     * @param DateTimeImmutable|null $currentDate Дата отсчета (по умолчанию сегодня)
     * @return array
     */
    public function getUpcomingBirthdays(int $daysAhead = 60, ?int $focusPersonId = null, ?DateTimeImmutable $currentDate = null): array
    {
        $this->loadGlobalPersonsAndMarriages();
        $today = $currentDate ?? new DateTimeImmutable('today');
        $pdo = Database::getConnection();

        $stmt = $pdo->query("SELECT id, first_name, last_name, middle_name, maiden_name, gender, birth_date, birth_year, is_deceased, clan_ru, avatar_path, father_id, mother_id FROM persons WHERE is_deceased = 0 AND birth_date IS NOT NULL AND is_deleted = 0");
        $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $events = [];

        // Для расчета родства
        $focusPerson = $focusPersonId ? ($this->allPersonsMapCache[$focusPersonId] ?? null) : null;

        foreach ($persons as $p) {
            $birthDateStr = $p['birth_date'];
            if (!$birthDateStr) {
                continue;
            }

            $parsed = $this->parseFlexibleDate($birthDateStr);
            if (!$parsed || empty($parsed['month']) || empty($parsed['day'])) {
                continue;
            }

            $birthMonth = $parsed['month'];
            $birthDay = $parsed['day'];
            $birthYear = $parsed['year'] ?: (int)($p['birth_year'] ?? 0);
            $currentYear = (int)$today->format('Y');

            // Обработка 29 февраля в невисокосные годы
            $checkDay = ($birthMonth === 2 && $birthDay === 29 && !checkdate(2, 29, $currentYear)) ? 28 : $birthDay;

            $thisYearBday = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $currentYear, $birthMonth, $checkDay));
            if ($thisYearBday < $today) {
                // День рождения в текущем году уже прошел, берем следующий год
                $nextYear = $currentYear + 1;
                $nextBdayDay = ($birthMonth === 2 && $birthDay === 29 && !checkdate(2, 29, $nextYear)) ? 28 : $birthDay;
                $nextBday = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $nextYear, $birthMonth, $nextBdayDay));
            } else {
                $nextBday = $thisYearBday;
            }

            $daysUntil = (int)$today->diff($nextBday)->format('%r%a');
            if ($daysUntil < 0 || $daysUntil > $daysAhead) {
                continue;
            }

            $turningAge = $birthYear ? ((int)$nextBday->format('Y') - $birthYear) : 0;

            // Расчет родства
            $kinshipLabelKk = '';
            $kinshipLabelRu = '';
            if ($focusPerson && $this->kinshipService) {
                $kin = $this->kinshipService->determineKinship($p, $focusPerson, 'auto', $this->allPersonsMapCache);
                $kinshipLabelKk = $kin['term_kk'] ?? '';
                $kinshipLabelRu = $kin['term_ru'] ?? '';
            }

            $affiliation = $this->determineParentAffiliation($p);
            $initials = mb_substr((string)($p['first_name'] ?? ''), 0, 1) . mb_substr((string)($p['last_name'] ?? ''), 0, 1);
            $dayMonth = $this->formatDayMonth($birthDay, $birthMonth);
            $fullName = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));

            $events[] = [
                'type'                => 'birthday',
                'event_type'          => 'birthday',
                'event_name_kk'       => 'Туған күн',
                'event_name_ru'       => 'День рождения',
                'event_icon'          => '🎂',
                'person_id'           => (int)$p['id'],
                'full_name'           => $fullName,
                'first_name'          => $p['first_name'],
                'last_name'           => $p['last_name'] ?? '',
                'initials'            => $initials ?: 'Ш',
                'gender'              => $p['gender'] ?? 'M',
                'avatar_path'         => $p['avatar_path'] ?? null,
                'clan_ru'             => $p['clan_ru'] ?? '',
                'event_date'          => $nextBday->format('Y-m-d'),
                'day_month_formatted' => $dayMonth,
                'formatted_date_kk'   => $dayMonth['kk'],
                'formatted_date_ru'   => $dayMonth['ru'],
                'days_until'          => $daysUntil,
                'is_today'            => ($daysUntil === 0),
                'is_tomorrow'         => ($daysUntil === 1),
                'turning_age'         => $turningAge,
                'age_turning'         => $turningAge,
                'birth_date'          => $birthDateStr,
                'affiliation_kk'      => $affiliation['kk'],
                'affiliation_ru'      => $affiliation['ru'],
                'kinship_kk'          => $kinshipLabelKk,
                'kinship_ru'          => $kinshipLabelRu,
                'title_kk'            => ($daysUntil === 0) ? "Бүгін туған күні! ({$turningAge} жас)" : "Туған күніне {$daysUntil} күн қалды ({$turningAge} жас)",
                'title_ru'            => ($daysUntil === 0) ? "Сегодня день рождения! ({$turningAge} лет)" : "До дня рождения {$daysUntil} дн. ({$turningAge} лет)",
                'person'              => $p,
            ];
        }

        // Сортировка по дням до наступления
        usort($events, fn($a, $b) => $a['days_until'] <=> $b['days_until']);

        return $events;
    }

    /**
     * Получить ближайшие памятные даты ушедших предков (годовщины ухода, ас беру күндері)
     *
     * @param int $daysAhead
     * @param int|null $focusPersonId
     * @param DateTimeImmutable|null $currentDate
     * @return array
     */
    public function getMemorialAnniversaries(int $daysAhead = 60, ?int $focusPersonId = null, ?DateTimeImmutable $currentDate = null): array
    {
        $this->loadGlobalPersonsAndMarriages();
        $today = $currentDate ?? new DateTimeImmutable('today');
        $pdo = Database::getConnection();

        $stmt = $pdo->query("SELECT id, first_name, last_name, middle_name, maiden_name, gender, birth_date, birth_year, death_date, death_year, is_deceased, clan_ru, avatar_path, father_id, mother_id FROM persons WHERE is_deceased = 1 AND death_date IS NOT NULL AND is_deleted = 0");
        $deceased = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $events = [];

        // Для расчета родства
        $focusPerson = $focusPersonId ? ($this->allPersonsMapCache[$focusPersonId] ?? null) : null;

        foreach ($deceased as $p) {
            $deathDateStr = $p['death_date'];
            if (!$deathDateStr) {
                continue;
            }

            $parsed = $this->parseFlexibleDate($deathDateStr);
            if (!$parsed || empty($parsed['month']) || empty($parsed['day'])) {
                continue;
            }

            $deathMonth = $parsed['month'];
            $deathDay = $parsed['day'];
            $deathYear = $parsed['year'] ?: (int)($p['death_year'] ?? 0);
            $currentYear = (int)$today->format('Y');

            // 29 февраля
            $checkDay = ($deathMonth === 2 && $deathDay === 29 && !checkdate(2, 29, $currentYear)) ? 28 : $deathDay;

            $thisYearAnniv = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $currentYear, $deathMonth, $checkDay));
            if ($thisYearAnniv < $today) {
                $nextYear = $currentYear + 1;
                $nextDay = ($deathMonth === 2 && $deathDay === 29 && !checkdate(2, 29, $nextYear)) ? 28 : $deathDay;
                $nextAnniv = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $nextYear, $deathMonth, $nextDay));
            } else {
                $nextAnniv = $thisYearAnniv;
            }

            $daysUntil = (int)$today->diff($nextAnniv)->format('%r%a');
            if ($daysUntil < 0 || $daysUntil > $daysAhead) {
                continue;
            }

            $yearsPassed = $deathYear ? ((int)$nextAnniv->format('Y') - $deathYear) : 1;
            if ($yearsPassed <= 0) {
                $yearsPassed = 1;
            }

            // Традиционные казахские категории поминовения
            $memorialCategoryKk = "{$yearsPassed}-жылдығы (Еске алу)";
            $memorialCategoryRu = "{$yearsPassed}-я годовщина (День памяти)";
            $isJyldyq = false;
            $isRoundAnniversary = false;

            if ($yearsPassed === 1) {
                $memorialCategoryKk = "Жылдығы (1 жыл — ас беру дәстүрі)";
                $memorialCategoryRu = "1-я годовщина (Традиционный ас/поминки)";
                $isJyldyq = true;
            } elseif ($yearsPassed % 5 === 0) {
                $memorialCategoryKk = "Мерейтойлық еске алу ({$yearsPassed} жыл толды)";
                $memorialCategoryRu = "Круглая годовщина памяти ({$yearsPassed} лет)";
                $isRoundAnniversary = true;
            }

            // Родство
            $kinshipLabelKk = '';
            $kinshipLabelRu = '';
            if ($focusPerson && $this->kinshipService) {
                $kin = $this->kinshipService->determineKinship($p, $focusPerson, 'auto', $this->allPersonsMapCache);
                $kinshipLabelKk = $kin['term_kk'] ?? '';
                $kinshipLabelRu = $kin['term_ru'] ?? '';
            }

            $affiliation = $this->determineParentAffiliation($p);
            $initials = mb_substr((string)($p['first_name'] ?? ''), 0, 1) . mb_substr((string)($p['last_name'] ?? ''), 0, 1);
            $dayMonth = $this->formatDayMonth($deathDay, $deathMonth);
            $fullName = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));

            $events[] = [
                'type'                => 'death_anniversary',
                'event_type'          => 'death_anniversary',
                'event_name_kk'       => 'Еске алу күні',
                'event_name_ru'       => 'День памяти',
                'event_icon'          => '✨',
                'person_id'           => (int)$p['id'],
                'full_name'           => $fullName,
                'first_name'          => $p['first_name'],
                'last_name'           => $p['last_name'] ?? '',
                'initials'            => $initials ?: 'Ш',
                'gender'              => $p['gender'] ?? 'M',
                'avatar_path'         => $p['avatar_path'] ?? null,
                'clan_ru'             => $p['clan_ru'] ?? '',
                'event_date'          => $nextAnniv->format('Y-m-d'),
                'day_month_formatted' => $dayMonth,
                'formatted_date_kk'   => $dayMonth['kk'],
                'formatted_date_ru'   => $dayMonth['ru'],
                'days_until'          => $daysUntil,
                'is_today'            => ($daysUntil === 0),
                'is_tomorrow'         => ($daysUntil === 1),
                'years_passed'        => $yearsPassed,
                'years_since'         => $yearsPassed,
                'death_date'          => $deathDateStr,
                'birth_date'          => $p['birth_date'] ?? null,
                'is_jyldyq'           => $isJyldyq,
                'is_round'            => $isRoundAnniversary,
                'memorial_label_kk'   => $memorialCategoryKk,
                'memorial_label_ru'   => $memorialCategoryRu,
                'affiliation_kk'      => $affiliation['kk'],
                'affiliation_ru'      => $affiliation['ru'],
                'kinship_kk'          => $kinshipLabelKk,
                'kinship_ru'          => $kinshipLabelRu,
                'reminder_kk'         => 'Аруағына құран бағыштау, рухына тағзым ету дәстүрі.',
                'reminder_ru'         => 'Традиция поминовения: посвящение молитвы, дань памяти предкам.',
                'person'              => $p,
            ];
        }

        usort($events, fn($a, $b) => $a['days_until'] <=> $b['days_until']);

        return $events;
    }

    /**
     * Получить ближайшие годовщины свадеб (үйленген күндері)
     *
     * @param int $daysAhead
     * @param int|null $focusPersonId
     * @param DateTimeImmutable|null $currentDate
     * @return array
     */
    public function getUpcomingWeddings(int $daysAhead = 90, ?int $focusPersonId = null, ?DateTimeImmutable $currentDate = null): array
    {
        $this->loadGlobalPersonsAndMarriages();
        $today = $currentDate ?? new DateTimeImmutable('today');
        $pdo = Database::getConnection();

        $stmt = $pdo->query("SHOW TABLES LIKE 'marriages'");
        if (!$stmt || $stmt->rowCount() === 0) {
            return [];
        }

        $mRows = $pdo->query("SELECT * FROM marriages WHERE (is_current = 1 OR status = 'married' OR status IS NULL) ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

        $events = [];
        $currentYear = (int)$today->format('Y');

        foreach ($mRows as $m) {
            $rawDate = $m['marriage_date'] ?: $m['start_date'];
            if (!$rawDate) {
                continue;
            }

            $parsed = $this->parseFlexibleDate($rawDate);
            if (!$parsed || empty($parsed['month']) || empty($parsed['day'])) {
                continue;
            }

            $hId = (int)($m['husband_id'] ?? 0);
            $wId = (int)($m['wife_id'] ?? 0);
            $husband = $this->allPersonsMapCache[$hId] ?? null;
            $wife = $this->allPersonsMapCache[$wId] ?? null;
            if (!$husband && !$wife) {
                continue;
            }

            $mMonth = $parsed['month'];
            $mDay = $parsed['day'];
            $mYear = $parsed['year'] ?? null;

            $checkDay = ($mMonth === 2 && $mDay === 29 && !checkdate(2, 29, $currentYear)) ? 28 : $mDay;

            $thisYearW = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $currentYear, $mMonth, $checkDay));
            if ($thisYearW < $today) {
                $nextYear = $currentYear + 1;
                $nextDay = ($mMonth === 2 && $mDay === 29 && !checkdate(2, 29, $nextYear)) ? 28 : $mDay;
                $nextW = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $nextYear, $mMonth, $nextDay));
            } else {
                $nextW = $thisYearW;
            }

            $daysUntil = (int)$today->diff($nextW)->format('%r%a');
            if ($daysUntil < 0 || $daysUntil > $daysAhead) {
                continue;
            }

            $yearsMarried = $mYear ? ((int)$nextW->format('Y') - $mYear) : 0;
            $jubilee = $this->getWeddingJubilee($yearsMarried);
            $dayMonth = $this->formatDayMonth($mDay, $mMonth);

            $coupleNameKk = trim(($husband['first_name'] ?? '—') . ' пен ' . ($wife['first_name'] ?? '—'));
            $coupleNameRu = trim(($husband['first_name'] ?? '—') . ' и ' . ($wife['first_name'] ?? '—'));

            // Kinship calculation
            $kinshipKk = '';
            $kinshipRu = '';
            if ($focusPersonId && $this->kinshipService) {
                $focusPerson = $this->allPersonsMapCache[$focusPersonId] ?? null;
                if ($focusPerson) {
                    if ($focusPersonId === $hId || $focusPersonId === $wId) {
                        $kinshipKk = 'Өзіңіздің некеңіз';
                        $kinshipRu = 'Ваш брак';
                    } elseif ($husband && (($focusPerson['father_id'] ?? 0) === $hId || ($focusPerson['mother_id'] ?? 0) === $wId)) {
                        $kinshipKk = 'Ата-анаңыздың некесі';
                        $kinshipRu = 'Брак родителей';
                    } elseif ($husband) {
                        $kin = $this->kinshipService->determineKinship($husband, $focusPerson, 'auto', $this->allPersonsMapCache);
                        if (!empty($kin['term_kk'])) {
                            $kinshipKk = $kin['term_kk'] . ' некесі';
                            $kinshipRu = 'Брак (' . $kin['term_ru'] . ')';
                        }
                    }
                }
            }

            $events[] = [
                'type'                => 'wedding',
                'event_type'          => 'wedding',
                'event_name_kk'       => 'Үйленген күн',
                'event_name_ru'       => 'Годовщина свадьбы',
                'event_icon'          => '💍',
                'marriage_id'         => (int)$m['id'],
                'husband_id'          => $hId,
                'wife_id'             => $wId,
                'husband'             => $husband,
                'wife'                => $wife,
                'full_name'           => $coupleNameRu,
                'couple_name_kk'      => $coupleNameKk,
                'couple_name_ru'      => $coupleNameRu,
                'event_date'          => $nextW->format('Y-m-d'),
                'day_month_formatted' => $dayMonth,
                'formatted_date_kk'   => $dayMonth['kk'],
                'formatted_date_ru'   => $dayMonth['ru'],
                'days_until'          => $daysUntil,
                'is_today'            => ($daysUntil === 0),
                'is_tomorrow'         => ($daysUntil === 1),
                'years_married'       => $yearsMarried,
                'jubilee_kk'          => $jubilee['kk'],
                'jubilee_ru'          => $jubilee['ru'],
                'kinship_kk'          => $kinshipKk,
                'kinship_ru'          => $kinshipRu,
                'raw_marriage_date'   => $rawDate,
                'marriage_place'      => $m['marriage_place'] ?? '',
            ];
        }

        usort($events, fn($a, $b) => $a['days_until'] <=> $b['days_until']);
        return $events;
    }

    /**
     * Сводный список всех ближайших дат (дни рождения, годовщины памяти, свадьбы),
     * отсортированных по дням до наступления
     *
     * @param int $daysAhead
     * @param int|null $focusPersonId
     * @param DateTimeImmutable|null $currentDate
     * @return array
     */
    public function getAllUpcomingEvents(int $daysAhead = 90, ?int $focusPersonId = null, ?DateTimeImmutable $currentDate = null): array
    {
        $birthdays = $this->getUpcomingBirthdays($daysAhead, $focusPersonId, $currentDate);
        $anniversaries = $this->getMemorialAnniversaries($daysAhead, $focusPersonId, $currentDate);
        $weddings = $this->getUpcomingWeddings($daysAhead, $focusPersonId, $currentDate);

        $all = array_merge($birthdays, $anniversaries, $weddings);
        usort($all, fn($a, $b) => $a['days_until'] <=> $b['days_until']);

        return $all;
    }

    /**
     * Названия традиционных свадебных юбилеев (на казахском и русском)
     */
    public function getWeddingJubilee(int $years): array
    {
        $map = [
            1  => ['kk' => 'Шыт той (1 жыл)', 'ru' => 'Ситцевая свадьба (1 год)'],
            5  => ['kk' => 'Ағаш той (5 жыл)', 'ru' => 'Деревянная свадьба (5 лет)'],
            10 => ['kk' => 'Қызғылт той (10 жыл)', 'ru' => 'Розовая свадьба (10 лет)'],
            15 => ['kk' => 'Хрусталь той (15 жыл)', 'ru' => 'Хрустальная свадьба (15 лет)'],
            20 => ['kk' => 'Фарфор той (20 жыл)', 'ru' => 'Фарфоровая свадьба (20 лет)'],
            25 => ['kk' => 'Күміс той (25 жыл)', 'ru' => 'Серебряная свадьба (25 лет)'],
            30 => ['kk' => 'Інжу той (30 жыл)', 'ru' => 'Жемчужная свадьба (30 лет)'],
            35 => ['kk' => 'Маржан той (35 жыл)', 'ru' => 'Коралловая свадьба (35 лет)'],
            40 => ['kk' => 'Лағыл той (40 жыл)', 'ru' => 'Рубиновая свадьба (40 лет)'],
            45 => ['kk' => 'Сапфир той (45 жыл)', 'ru' => 'Сапфировая свадьба (45 лет)'],
            50 => ['kk' => 'Алтын той (50 жыл)', 'ru' => 'Золотая свадьба (50 лет)'],
            55 => ['kk' => 'Зүбәржат той (55 жыл)', 'ru' => 'Изумрудная свадьба (55 лет)'],
            60 => ['kk' => 'Гауһар той (60 жыл)', 'ru' => 'Бриллиантовая свадьба (60 лет)'],
        ];

        if (isset($map[$years])) {
            return $map[$years];
        }

        return [
            'kk' => "{$years}-жылдығы",
            'ru' => "{$years}-я годовщина",
        ];
    }

    /**
     * Сводный отчет по всем ближайшим памятным датам (для виджета на главной и API)
     */
    public function getMemorialSummary(int $daysAhead = 45, ?int $focusPersonId = null): array
    {
        $birthdays = $this->getUpcomingBirthdays($daysAhead, $focusPersonId);
        $anniversaries = $this->getMemorialAnniversaries($daysAhead, $focusPersonId);
        $weddings = $this->getUpcomingWeddings($daysAhead, $focusPersonId);

        $todayBirthdays = array_filter($birthdays, fn($e) => $e['is_today']);
        $todayAnniversaries = array_filter($anniversaries, fn($e) => $e['is_today']);
        $todayWeddings = array_filter($weddings, fn($e) => $e['is_today']);

        return [
            'success'              => true,
            'days_ahead'           => $daysAhead,
            'birthdays'            => $birthdays,
            'anniversaries'        => $anniversaries,
            'weddings'             => $weddings,
            'today_events_count'   => count($todayBirthdays) + count($todayAnniversaries) + count($todayWeddings),
            'birthdays_count'      => count($birthdays),
            'anniversaries_count'  => count($anniversaries),
            'weddings_count'       => count($weddings),
        ];
    }

    /**
     * Форматирование дня и месяца (на казахском и русском)
     */
    protected function formatDayMonth(int $day, int $month): array
    {
        $monthsKk = [
            1 => 'қаңтар', 2 => 'ақпан', 3 => 'наурыз', 4 => 'сәуір',
            5 => 'мамыр', 6 => 'маусым', 7 => 'шілде', 8 => 'тамыз',
            9 => 'қыркүйек', 10 => 'қазан', 11 => 'қараша', 12 => 'желтоқсан'
        ];

        $monthsRu = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
        ];

        return [
            'kk' => "{$day} " . ($monthsKk[$month] ?? ''),
            'ru' => "{$day} " . ($monthsRu[$month] ?? ''),
        ];
    }
}
