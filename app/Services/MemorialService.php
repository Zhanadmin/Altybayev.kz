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

    public function __construct(?KinshipService $kinshipService = null)
    {
        $this->kinshipService = $kinshipService;
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
        $today = $currentDate ?? new DateTimeImmutable('today');
        $pdo = Database::getConnection();

        $stmt = $pdo->query("SELECT id, first_name, last_name, middle_name, maiden_name, gender, birth_date, birth_year, is_deceased, clan_ru, avatar_path, father_id, mother_id FROM persons WHERE is_deceased = 0 AND birth_date IS NOT NULL AND is_deleted = 0");
        $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $events = [];

        // Для расчета родства
        $focusPerson = null;
        $allPersonsMap = [];
        if ($focusPersonId) {
            foreach ($persons as $p) {
                $allPersonsMap[(int)$p['id']] = $p;
            }
            if (!isset($allPersonsMap[$focusPersonId])) {
                $fStmt = $pdo->prepare("SELECT * FROM persons WHERE id = ?");
                $fStmt->execute([$focusPersonId]);
                $focusPerson = $fStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($focusPerson) {
                    $allPersonsMap[$focusPersonId] = $focusPerson;
                }
            } else {
                $focusPerson = $allPersonsMap[$focusPersonId];
            }
        }

        foreach ($persons as $p) {
            $birthDateStr = $p['birth_date'];
            if (!$birthDateStr) {
                continue;
            }

            try {
                $birthDate = new DateTimeImmutable($birthDateStr);
            } catch (\Throwable $e) {
                continue;
            }

            $currentYear = (int)$today->format('Y');
            $birthMonth = (int)$birthDate->format('m');
            $birthDay = (int)$birthDate->format('d');

            // Обработка 29 февраля в невисокосные годы
            if ($birthMonth === 2 && $birthDay === 29 && !checkdate(2, 29, $currentYear)) {
                $birthDay = 28;
            }

            $thisYearBday = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $currentYear, $birthMonth, $birthDay));
            if ($thisYearBday < $today) {
                // День рождения в текущем году уже прошел, берем следующий год
                $nextYear = $currentYear + 1;
                $nextBdayDay = ($birthMonth === 2 && (int)$birthDate->format('d') === 29 && !checkdate(2, 29, $nextYear)) ? 28 : (int)$birthDate->format('d');
                $nextBday = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $nextYear, $birthMonth, $nextBdayDay));
            } else {
                $nextBday = $thisYearBday;
            }

            $daysUntil = (int)$today->diff($nextBday)->format('%r%a');
            if ($daysUntil < 0 || $daysUntil > $daysAhead) {
                continue;
            }

            $birthYear = (int)$birthDate->format('Y');
            $turningAge = (int)$nextBday->format('Y') - $birthYear;

            // Расчет родства
            $kinshipLabelKk = '';
            $kinshipLabelRu = '';
            if ($focusPerson && $this->kinshipService) {
                $kin = $this->kinshipService->determineKinship($p, $focusPerson, 'auto', $allPersonsMap);
                $kinshipLabelKk = $kin['term_kk'] ?? '';
                $kinshipLabelRu = $kin['term_ru'] ?? '';
            }

            $fullName = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));

            $events[] = [
                'type'             => 'birthday',
                'person_id'        => (int)$p['id'],
                'full_name'        => $fullName,
                'first_name'       => $p['first_name'],
                'last_name'        => $p['last_name'] ?? '',
                'gender'           => $p['gender'] ?? 'M',
                'avatar_path'      => $p['avatar_path'] ?? null,
                'clan_ru'          => $p['clan_ru'] ?? '',
                'event_date'       => $nextBday->format('Y-m-d'),
                'day_month_formatted' => $this->formatDayMonth((int)$nextBday->format('d'), (int)$nextBday->format('m')),
                'days_until'       => $daysUntil,
                'is_today'         => ($daysUntil === 0),
                'is_tomorrow'      => ($daysUntil === 1),
                'turning_age'      => $turningAge,
                'birth_date'       => $birthDateStr,
                'kinship_kk'       => $kinshipLabelKk,
                'kinship_ru'       => $kinshipLabelRu,
                'title_kk'         => ($daysUntil === 0) ? "Бүгін туған күні! ({$turningAge} жас)" : "Туған күніне {$daysUntil} күн қалды ({$turningAge} жас)",
                'title_ru'         => ($daysUntil === 0) ? "Сегодня день рождения! ({$turningAge} лет)" : "До дня рождения {$daysUntil} дн. ({$turningAge} лет)",
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
        $today = $currentDate ?? new DateTimeImmutable('today');
        $pdo = Database::getConnection();

        $stmt = $pdo->query("SELECT id, first_name, last_name, middle_name, maiden_name, gender, birth_date, birth_year, death_date, death_year, is_deceased, clan_ru, avatar_path, father_id, mother_id FROM persons WHERE is_deceased = 1 AND death_date IS NOT NULL AND is_deleted = 0");
        $deceased = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $events = [];

        // Для расчета родства
        $focusPerson = null;
        $allPersonsMap = [];
        if ($focusPersonId) {
            $allStmt = $pdo->query("SELECT id, first_name, last_name, gender, father_id, mother_id, birth_year, birth_date FROM persons WHERE is_deleted = 0");
            while ($row = $allStmt->fetch(PDO::FETCH_ASSOC)) {
                $allPersonsMap[(int)$row['id']] = $row;
            }
            $focusPerson = $allPersonsMap[$focusPersonId] ?? null;
        }

        foreach ($deceased as $p) {
            $deathDateStr = $p['death_date'];
            if (!$deathDateStr) {
                continue;
            }

            try {
                $deathDate = new DateTimeImmutable($deathDateStr);
            } catch (\Throwable $e) {
                continue;
            }

            $currentYear = (int)$today->format('Y');
            $deathMonth = (int)$deathDate->format('m');
            $deathDay = (int)$deathDate->format('d');

            // 29 февраля
            if ($deathMonth === 2 && $deathDay === 29 && !checkdate(2, 29, $currentYear)) {
                $deathDay = 28;
            }

            $thisYearAnniv = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $currentYear, $deathMonth, $deathDay));
            if ($thisYearAnniv < $today) {
                $nextYear = $currentYear + 1;
                $nextDay = ($deathMonth === 2 && (int)$deathDate->format('d') === 29 && !checkdate(2, 29, $nextYear)) ? 28 : (int)$deathDate->format('d');
                $nextAnniv = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $nextYear, $deathMonth, $nextDay));
            } else {
                $nextAnniv = $thisYearAnniv;
            }

            $daysUntil = (int)$today->diff($nextAnniv)->format('%r%a');
            if ($daysUntil < 0 || $daysUntil > $daysAhead) {
                continue;
            }

            $deathYear = (int)$deathDate->format('Y');
            $yearsPassed = (int)$nextAnniv->format('Y') - $deathYear;
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
                $kin = $this->kinshipService->determineKinship($p, $focusPerson, 'auto', $allPersonsMap);
                $kinshipLabelKk = $kin['term_kk'] ?? '';
                $kinshipLabelRu = $kin['term_ru'] ?? '';
            }

            $fullName = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));

            $events[] = [
                'type'                => 'death_anniversary',
                'person_id'           => (int)$p['id'],
                'full_name'           => $fullName,
                'first_name'          => $p['first_name'],
                'last_name'           => $p['last_name'] ?? '',
                'gender'              => $p['gender'] ?? 'M',
                'avatar_path'         => $p['avatar_path'] ?? null,
                'clan_ru'             => $p['clan_ru'] ?? '',
                'event_date'          => $nextAnniv->format('Y-m-d'),
                'day_month_formatted' => $this->formatDayMonth((int)$nextAnniv->format('d'), (int)$nextAnniv->format('m')),
                'days_until'          => $daysUntil,
                'is_today'            => ($daysUntil === 0),
                'is_tomorrow'         => ($daysUntil === 1),
                'years_passed'        => $yearsPassed,
                'death_date'          => $deathDateStr,
                'birth_date'          => $p['birth_date'] ?? null,
                'is_jyldyq'           => $isJyldyq,
                'is_round'            => $isRoundAnniversary,
                'memorial_label_kk'   => $memorialCategoryKk,
                'memorial_label_ru'   => $memorialCategoryRu,
                'kinship_kk'          => $kinshipLabelKk,
                'kinship_ru'          => $kinshipLabelRu,
                'reminder_kk'         => 'Аруағына құран бағыштау, рухына тағзым ету дәстүрі.',
                'reminder_ru'         => 'Традиция поминовения: посвящение молитвы, дань памяти предкам.',
            ];
        }

        usort($events, fn($a, $b) => $a['days_until'] <=> $b['days_until']);

        return $events;
    }

    /**
     * Сводный отчет по всем ближайшим памятным датам (для виджета на главной и API)
     */
    public function getMemorialSummary(int $daysAhead = 45, ?int $focusPersonId = null): array
    {
        $birthdays = $this->getUpcomingBirthdays($daysAhead, $focusPersonId);
        $anniversaries = $this->getMemorialAnniversaries($daysAhead, $focusPersonId);

        $todayBirthdays = array_filter($birthdays, fn($e) => $e['is_today']);
        $todayAnniversaries = array_filter($anniversaries, fn($e) => $e['is_today']);

        return [
            'success'              => true,
            'days_ahead'           => $daysAhead,
            'birthdays'            => $birthdays,
            'anniversaries'        => $anniversaries,
            'today_events_count'   => count($todayBirthdays) + count($todayAnniversaries),
            'birthdays_count'      => count($birthdays),
            'anniversaries_count'  => count($anniversaries),
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
