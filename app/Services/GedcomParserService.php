<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Сервис парсинга GEDCOM 5.5.1 (MyHeritage Family Tree Builder и аналоги)
 * Реализует:
 * - Парсинг INDI (персоны), FAM (семьи), связей родителей и супругов
 * - Полную очистку XSS и вредоносных тегов (script, iframe, on* события) в заметках
 * - Пропуск локальных фото с ПК и подсчет пропущенных файлов
 * - Изоляцию контактов (телефон, email, адрес) в закрытые поля (только для админа)
 * - Формирование детальной диагностики и предпросмотра
 */
class GedcomParserService
{
    /**
     * Парсинг файла GEDCOM
     *
     * @param string $filePath Абсолютный или относительный путь к файлу .ged
     * @return array Структурированные данные предпросмотра
     */
    public function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("GEDCOM файлы табылмады / Файл GEDCOM не найден: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Файлды оқу мүмкін болмады / Не удалось прочитать файл: {$filePath}");
        }

        return $this->parseContent($content, basename($filePath));
    }

    /**
     * Парсинг строки содержимого GEDCOM
     */
    public function parseContent(string $content, string $sourceName = 'import.ged'): array
    {
        // 1. Удаление UTF-8 BOM, если присутствует
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // 2. Нормализация переводов строк
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $rawLines = explode("\n", $content);

        // 3. Предварительная обработка: склейка неформатированных строк (где MyHeritage не указал уровень)
        $lines = [];
        foreach ($rawLines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^[0-9]+\s+/', $line)) {
                $lines[] = $line;
            } else {
                // Строка без номера уровня — продолжение предыдущего значения
                if (!empty($lines)) {
                    $lastIdx = count($lines) - 1;
                    $lines[$lastIdx] .= "\n" . $trimmed;
                }
            }
        }

        $persons = [];
        $families = [];
        $skippedPhotos = 0;
        $xssThreats = [];
        $warnings = [];

        $currentRecord = null;
        $currentType = null; // 'INDI', 'FAM', etc.
        $subTagStack = [];

        // Вспомогательные переменные для текущей записи
        $currPerson = null;
        $currFamily = null;
        $activeSubContext = null; // 'BIRT', 'DEAT', 'RESI', 'OBJE', 'NAME', 'MARR', 'DIV', 'NOTE'

        foreach ($lines as $line) {
            if (!preg_match('/^([0-9]+)\s+(@[^@]+@|[A-Za-z0-9_]+)?(?:\s+(.*))?$/s', $line, $m)) {
                continue;
            }

            $level = (int)$m[1];
            $tagOrId = trim($m[2] ?? '');
            $rest = isset($m[3]) ? trim($m[3]) : '';

            // Уровень 0: Новая сущность
            if ($level === 0) {
                // Сохраняем предыдущую запись
                if ($currentType === 'INDI' && $currPerson !== null) {
                    $persons[$currPerson['gedcom_id']] = $this->finalizePerson($currPerson, $xssThreats, $warnings);
                } elseif ($currentType === 'FAM' && $currFamily !== null) {
                    $families[$currFamily['gedcom_id']] = $currFamily;
                }

                $currentRecord = null;
                $currentType = null;
                $activeSubContext = null;

                if ($rest === 'INDI' && str_starts_with($tagOrId, '@')) {
                    $currentType = 'INDI';
                    $currPerson = $this->initPerson($tagOrId);
                } elseif ($rest === 'FAM' && str_starts_with($tagOrId, '@')) {
                    $currentType = 'FAM';
                    $currFamily = $this->initFamily($tagOrId);
                }
                continue;
            }

            // Обработка внутри INDI
            if ($currentType === 'INDI' && $currPerson !== null) {
                $this->processIndiLine($level, $tagOrId, $rest, $currPerson, $activeSubContext, $skippedPhotos);
                continue;
            }

            // Обработка внутри FAM
            if ($currentType === 'FAM' && $currFamily !== null) {
                $this->processFamLine($level, $tagOrId, $rest, $currFamily, $activeSubContext);
                continue;
            }
        }

        // Финализируем последнюю запись
        if ($currentType === 'INDI' && $currPerson !== null) {
            $persons[$currPerson['gedcom_id']] = $this->finalizePerson($currPerson, $xssThreats, $warnings);
        } elseif ($currentType === 'FAM' && $currFamily !== null) {
            $families[$currFamily['gedcom_id']] = $currFamily;
        }

        // 4. Связывание родителей и детей по FAM записям
        $this->resolveFamilyLinks($persons, $families);

        // 5. Диагностика и выявление сомнительных записей
        $securedContactsCount = 0;
        foreach ($persons as $id => &$person) {
            if (!empty($person['private_contacts']['phones']) ||
                !empty($person['private_contacts']['emails']) ||
                !empty($person['private_contacts']['addresses'])) {
                $securedContactsCount++;
            }

            // Проверка на отсутствие даты рождения
            if (empty($person['birth_year']) && empty($person['birth_date'])) {
                $warnings[] = [
                    'type' => 'missing_birth_date',
                    'person_id' => $id,
                    'name' => $person['full_name'],
                    'message' => "Туған жылы көрсетілмеген / Не указан год рождения: {$person['full_name']} ({$id})"
                ];
            }

            // Неизвестный пол
            if ($person['gender'] === 'unknown') {
                $warnings[] = [
                    'type' => 'unknown_gender',
                    'person_id' => $id,
                    'name' => $person['full_name'],
                    'message' => "Жынысы белгісіз / Неизвестный пол: {$person['full_name']} ({$id})"
                ];
            }

            // Изолированная персона (нет родителей, нет супруга, нет детей)
            if (empty($person['father_gedcom_id']) &&
                empty($person['mother_gedcom_id']) &&
                empty($person['spouse_gedcom_ids']) &&
                empty($person['children_gedcom_ids'])) {
                $warnings[] = [
                    'type' => 'isolated_person',
                    'person_id' => $id,
                    'name' => $person['full_name'],
                    'message' => "Байланыссыз жеке жазба / Изолированная запись без связей: {$person['full_name']} ({$id})"
                ];
            }
        }
        unset($person);

        return [
            'file_name' => $sourceName,
            'stats' => [
                'total_persons' => count($persons),
                'total_families' => count($families),
                'photos_skipped' => $skippedPhotos,
                'contacts_secured' => $securedContactsCount,
                'xss_threats_neutralized' => count($xssThreats),
                'warnings_count' => count($warnings),
            ],
            'xss_threats' => $xssThreats,
            'warnings' => $warnings,
            'persons' => $persons,
            'families' => $families,
        ];
    }

    /**
     * Инициализация структуры персоны
     */
    private function initPerson(string $gedcomId): array
    {
        return [
            'gedcom_id' => $gedcomId,
            'first_name' => '',
            'last_name' => '',
            'middle_name' => null,
            'maiden_name' => null,
            'full_name' => '',
            'gender' => 'unknown',
            'birth_date' => null,
            'birth_year' => null,
            'birth_place' => null,
            'is_deceased' => 0,
            'death_date' => null,
            'death_year' => null,
            'death_place' => null,
            'death_cause' => null,
            'clan_id' => 1, // Керей по умолчанию
            'clan_ru' => 'Керей',
            'clan_subru' => null,
            'raw_notes' => [],
            'biography' => null,
            'private_contacts' => [
                'phones' => [],
                'emails' => [],
                'addresses' => []
            ],
            'famc' => null, // семья родителей
            'fams' => [],   // семьи браков
            'father_gedcom_id' => null,
            'mother_gedcom_id' => null,
            'spouse_gedcom_ids' => [],
            'children_gedcom_ids' => [],
            'extra_facts' => [],
        ];
    }

    /**
     * Инициализация структуры семьи
     */
    private function initFamily(string $gedcomId): array
    {
        return [
            'gedcom_id' => $gedcomId,
            'husband_gedcom_id' => null,
            'wife_gedcom_id' => null,
            'children' => [],
            'marriage_date' => null,
            'marriage_place' => null,
            'divorce_date' => null,
            'raw_notes' => [],
            'notes' => null,
        ];
    }

    /**
     * Обработка строки внутри записи INDI
     */
    private function processIndiLine(
        int $level,
        string $tag,
        string $value,
        array &$person,
        ?string &$context,
        int &$skippedPhotos
    ): void {
        if ($level === 1) {
            $context = $tag;
            switch ($tag) {
                case 'NAME':
                    $this->parseNameField($value, $person);
                    break;
                case 'SEX':
                    $sex = strtoupper(trim($value));
                    $person['gender'] = ($sex === 'M' || $sex === 'F') ? $sex : 'unknown';
                    break;
                case 'BIRT':
                    // контекст установлен в BIRT
                    break;
                case 'DEAT':
                    $person['is_deceased'] = 1;
                    if (!empty($value) && strtoupper($value) === 'Y') {
                        $person['is_deceased'] = 1;
                    }
                    break;
                case 'FAMC':
                    $person['famc'] = trim($value);
                    break;
                case 'FAMS':
                    $famsId = trim($value);
                    if ($famsId !== '' && !in_array($famsId, $person['fams'], true)) {
                        $person['fams'][] = $famsId;
                    }
                    break;
                case 'NOTE':
                    $person['raw_notes'][] = $value;
                    break;
                case 'OBJE':
                    // Пропускаем фото локального ПК
                    $skippedPhotos++;
                    break;
                case 'RESI':
                    // Резиденция / адрес / телефон
                    break;
                case 'OCCU':
                    $person['extra_facts'][] = 'Кәсібі / Профессия: ' . $value;
                    break;
                case 'EDUC':
                    $person['extra_facts'][] = 'Білімі / Образование: ' . $value;
                    break;
                case 'RELI':
                    $person['extra_facts'][] = 'Діни көзқарасы / Религия: ' . $value;
                    break;
                case 'NATI':
                    $person['extra_facts'][] = 'Ұлты / Азаматтығы: ' . $value;
                    break;
            }
            return;
        }

        // Уровень 2 и глубже: подуровни
        if ($level >= 2) {
            // Продолжение заметок (CONC / CONT)
            if ($tag === 'CONC') {
                $lastIdx = count($person['raw_notes']) - 1;
                if ($lastIdx >= 0) {
                    $person['raw_notes'][$lastIdx] .= $value;
                }
                return;
            }
            if ($tag === 'CONT') {
                $lastIdx = count($person['raw_notes']) - 1;
                if ($lastIdx >= 0) {
                    $person['raw_notes'][$lastIdx] .= "\n" . $value;
                }
                return;
            }

            // Подуровни имени
            if ($context === 'NAME') {
                if ($tag === 'GIVN' && empty($person['first_name'])) {
                    $person['first_name'] = trim($value);
                } elseif ($tag === 'SURN' && empty($person['last_name'])) {
                    $person['last_name'] = $this->cleanSurname($value);
                } elseif ($tag === '_MARNM') {
                    $person['maiden_name'] = $this->cleanSurname($value);
                }
                return;
            }

            // Подуровни рождения
            if ($context === 'BIRT') {
                if ($tag === 'DATE') {
                    $dateInfo = $this->parseGedcomDate($value);
                    $person['birth_date'] = $dateInfo['date'];
                    $person['birth_year'] = $dateInfo['year'];
                } elseif ($tag === 'PLAC') {
                    $person['birth_place'] = trim($value);
                }
                return;
            }

            // Подуровни смерти
            if ($context === 'DEAT') {
                if ($tag === 'DATE') {
                    $dateInfo = $this->parseGedcomDate($value);
                    $person['death_date'] = $dateInfo['date'];
                    $person['death_year'] = $dateInfo['year'];
                } elseif ($tag === 'PLAC') {
                    $person['death_place'] = trim($value);
                } elseif ($tag === 'CAUS') {
                    $person['death_cause'] = trim($value);
                }
                return;
            }

            // Пропуск вложенных фото
            if ($context === 'OBJE') {
                if ($tag === 'FILE') {
                    // файл фото пропущен
                }
                return;
            }

            // Закрытые контакты (RESI / PHON / EMAIL / ADDR)
            if ($context === 'RESI') {
                if ($tag === 'PHON') {
                    $cleanPhone = trim($value);
                    if ($cleanPhone !== '' && !in_array($cleanPhone, $person['private_contacts']['phones'], true)) {
                        $person['private_contacts']['phones'][] = $cleanPhone;
                    }
                } elseif ($tag === 'EMAIL') {
                    $cleanEmail = trim($value);
                    if ($cleanEmail !== '' && !in_array($cleanEmail, $person['private_contacts']['emails'], true)) {
                        $person['private_contacts']['emails'][] = $cleanEmail;
                    }
                } elseif ($tag === 'ADDR' || $tag === 'ADR1') {
                    $cleanAddr = trim($value);
                    if ($cleanAddr !== '' && !in_array($cleanAddr, $person['private_contacts']['addresses'], true)) {
                        $person['private_contacts']['addresses'][] = $cleanAddr;
                    }
                }
                return;
            }

            // Прямые теги контактов на уровне 2
            if ($tag === 'PHON') {
                $cleanPhone = trim($value);
                if ($cleanPhone !== '' && !in_array($cleanPhone, $person['private_contacts']['phones'], true)) {
                    $person['private_contacts']['phones'][] = $cleanPhone;
                }
            } elseif ($tag === 'EMAIL') {
                $cleanEmail = trim($value);
                if ($cleanEmail !== '' && !in_array($cleanEmail, $person['private_contacts']['emails'], true)) {
                    $person['private_contacts']['emails'][] = $cleanEmail;
                }
            }
        }
    }

    /**
     * Обработка строки внутри записи FAM
     */
    private function processFamLine(
        int $level,
        string $tag,
        string $value,
        array &$family,
        ?string &$context
    ): void {
        if ($level === 1) {
            $context = $tag;
            switch ($tag) {
                case 'HUSB':
                    $family['husband_gedcom_id'] = trim($value);
                    break;
                case 'WIFE':
                    $family['wife_gedcom_id'] = trim($value);
                    break;
                case 'CHIL':
                    $chil = trim($value);
                    if ($chil !== '' && !in_array($chil, $family['children'], true)) {
                        $family['children'][] = $chil;
                    }
                    break;
                case 'MARR':
                    // контекст брака
                    break;
                case 'DIV':
                    // контекст развода
                    break;
                case 'NOTE':
                    $family['raw_notes'][] = $value;
                    break;
            }
            return;
        }

        if ($level >= 2) {
            if ($tag === 'CONC') {
                $lastIdx = count($family['raw_notes']) - 1;
                if ($lastIdx >= 0) {
                    $family['raw_notes'][$lastIdx] .= $value;
                }
                return;
            }
            if ($tag === 'CONT') {
                $lastIdx = count($family['raw_notes']) - 1;
                if ($lastIdx >= 0) {
                    $family['raw_notes'][$lastIdx] .= "\n" . $value;
                }
                return;
            }

            if ($context === 'MARR') {
                if ($tag === 'DATE') {
                    $family['marriage_date'] = trim($value);
                } elseif ($tag === 'PLAC') {
                    $family['marriage_place'] = trim($value);
                }
                return;
            }

            if ($context === 'DIV') {
                if ($tag === 'DATE') {
                    $family['divorce_date'] = trim($value);
                }
                return;
            }
        }
    }

    /**
     * Парсинг строки NAME в имя и фамилию
     */
    private function parseNameField(string $nameField, array &$person): void
    {
        $nameField = trim($nameField);
        if (preg_match('#^(.*?)/(.*?)/(.*)$#', $nameField, $m)) {
            $firstName = trim($m[1]);
            $surname = trim($m[2]);
            $after = trim($m[3]);

            if ($firstName !== '') {
                $person['first_name'] = $firstName;
            }
            if ($surname !== '') {
                $person['last_name'] = $this->cleanSurname($surname);
            }
            if ($after !== '' && empty($person['maiden_name'])) {
                $person['maiden_name'] = $this->cleanSurname($after);
            }
        } else {
            // Формат без слэшей
            $parts = preg_split('/\s+/', $nameField, 2);
            if (!empty($parts[0])) {
                $person['first_name'] = $parts[0];
            }
            if (!empty($parts[1])) {
                $person['last_name'] = $this->cleanSurname($parts[1]);
            }
        }
    }

    /**
     * Очистка фамилии от паразитных символов MyHeritage (запятые, точки, слэши)
     */
    private function cleanSurname(string $surname): string
    {
        $cleaned = trim($surname);
        // Удаляем завершающие запятые, точки, слэши
        $cleaned = rtrim($cleaned, " \t\n\r\0\x0B,./");
        $cleaned = ltrim($cleaned, " \t\n\r\0\x0B/.");
        return $cleaned;
    }

    /**
     * Финализация персоны: сборка биографии, санитизация HTML, сбор полного имени
     */
    private function finalizePerson(array $person, array &$xssThreats, array &$warnings): array
    {
        // 1. Полное имя
        $names = [];
        if (!empty($person['last_name'])) {
            $names[] = $person['last_name'];
        }
        if (!empty($person['first_name'])) {
            $names[] = $person['first_name'];
        }
        if (!empty($person['middle_name'])) {
            $names[] = $person['middle_name'];
        }
        $person['full_name'] = !empty($names) ? implode(' ', $names) : 'Белгісіз / Без имени';

        // 2. Сборка заметок и биографии
        $noteBlocks = [];
        if (!empty($person['raw_notes'])) {
            $rawJoined = implode("\n\n", $person['raw_notes']);
            $cleanNote = $this->sanitizeHtml($rawJoined, $person['gedcom_id'], $person['full_name'], $xssThreats);
            if ($cleanNote !== '') {
                $noteBlocks[] = $cleanNote;
            }
        }

        if (!empty($person['extra_facts'])) {
            $noteBlocks[] = implode("\n", $person['extra_facts']);
        }

        $person['biography'] = !empty($noteBlocks) ? implode("\n\n", $noteBlocks) : null;
        unset($person['raw_notes']);

        return $person;
    }

    /**
     * Строгая санитизация HTML (удаление script, iframe, on* событий, опасных тегов)
     * Требование 2: удалить script, iframe, обработчики событий; оставить только безопасный текст
     */
    public function sanitizeHtml(string $html, string $personId, string $personName, array &$threatsLog): string
    {
        $hasThreat = false;

        // 1. Проверка и удаление тегов <script>...</script>
        if (preg_match('/<script[\s\S]*?<\/script>/i', $html)) {
            $hasThreat = true;
            $threatsLog[] = [
                'type' => 'XSS_SCRIPT',
                'person_id' => $personId,
                'person_name' => $personName,
                'threat' => 'Қауіпті <script> тегі табылып, жойылды / Удален опасный тег <script>',
            ];
            $html = preg_replace('/<script[\s\S]*?<\/script>/i', '', $html);
        }

        // 2. Проверка и удаление тегов <iframe>...</iframe>
        if (preg_match('/<iframe[\s\S]*?(?:<\/iframe>|\/?>)/i', $html)) {
            $hasThreat = true;
            $threatsLog[] = [
                'type' => 'XSS_IFRAME',
                'person_id' => $personId,
                'person_name' => $personName,
                'threat' => 'Жасырын <iframe> тегі табылып, жойылды / Удален скрытый тег <iframe>',
            ];
            $html = preg_replace('/<iframe[\s\S]*?(?:<\/iframe>|\/?>)/i', '', $html);
        }

        // 3. Удаление опасных тегов: object, embed, applet, style, form, input, button
        $dangerousTags = ['object', 'embed', 'applet', 'style', 'link', 'meta', 'form', 'input', 'button', 'svg', 'canvas'];
        foreach ($dangerousTags as $tag) {
            if (preg_match("/<{$tag}[\s\S]*?(?:<\/{$tag}>|\/?>)/i", $html)) {
                $html = preg_replace("/<{$tag}[\s\S]*?(?:<\/{$tag}>|\/?>)/i", '', $html);
            }
        }

        // 4. Удаление javascript: и vbscript: протоколов
        $html = preg_replace('/(href|src)\s*=\s*["\']\s*(?:javascript|vbscript|data):[^"\']*["\']/i', '', $html);

        // 5. Удаление всех атрибутов on* (onload, onerror, onclick, onmouseover...)
        $html = preg_replace('/\s+on[a-z0-9_-]+\s*=\s*(["\'][^"\']*["\']|[^\s>]+)/i', '', $html);

        // 6. Оставляем только безопасные теги: p, br, strong, b, em, i, u, span, ul, ol, li
        $allowedTags = '<p><br><strong><b><em><i><u><span><ul><ol><li>';
        $cleaned = strip_tags($html, $allowedTags);

        // 7. Удаление пустых тегов и лишних неразрывных пробелов
        $cleaned = preg_replace('/<p[^>]*>\s*(?:&nbsp;|\s)*<\/p>/i', '', $cleaned);
        $cleaned = preg_replace('/<span[^>]*>\s*(?:&nbsp;|\s)*<\/span>/i', '', $cleaned);
        $cleaned = trim($cleaned);

        return $cleaned;
    }

    /**
     * Парсинг даты GEDCOM в структурированный вид
     * Возвращает ['date' => 'YYYY-MM-DD'|null, 'year' => 'YYYY'|string|null]
     */
    public function parseGedcomDate(string $rawDate): array
    {
        $rawDate = trim($rawDate);
        if ($rawDate === '') {
            return ['date' => null, 'year' => null];
        }

        $year = null;
        $date = null;

        // Поиск 4-значного года
        if (preg_match('/\b(1[6-9][0-9]{2}|20[0-9]{2})\b/', $rawDate, $ym)) {
            $year = $ym[1];
        }

        // Словарь месяцев GEDCOM (английские и русские варианты)
        $months = [
            'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
            'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
            'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12',
            'ЯНВ' => '01', 'ФЕВ' => '02', 'МАР' => '03', 'АПР' => '04',
            'МАЙ' => '05', 'ИЮН' => '06', 'ИЮЛ' => '07', 'АВГ' => '08',
            'СЕН' => '09', 'ОКТ' => '10', 'НОЯ' => '11', 'ДЕК' => '12',
            'ИЮЛЬ' => '07', 'ИЮНЬ' => '06', 'МАЯ' => '05'
        ];

        // Пробуем распознать полный формат: "3 FEB 1956", "31 AUG 1980", "10 июль 2025"
        if (preg_match('/^(\d{1,2})\s+([A-Za-zА-Яа-яЁё]+)\s+(\d{4})$/u', $rawDate, $dm)) {
            $day = str_pad($dm[1], 2, '0', STR_PAD_LEFT);
            $monthKey = mb_strtoupper($dm[2], 'UTF-8');
            if (isset($months[$monthKey])) {
                $month = $months[$monthKey];
                $yearStr = $dm[3];
                if (checkdate((int)$month, (int)$day, (int)$yearStr)) {
                    $date = "{$yearStr}-{$month}-{$day}";
                }
            }
        }

        // Если не удалось распарсить полный день, но есть год — сохраняем исходную строку в year, если она содержит пояснения
        if ($year === null) {
            $year = $rawDate;
        } elseif (preg_match('/(?:ABT|CAL|EST|ок\.|прим\.)/i', $rawDate)) {
            $year = 'ок. ' . $year;
        }

        return [
            'date' => $date,
            'year' => $year,
        ];
    }

    /**
     * Связывание родителей, детей и супругов по семьям FAM
     */
    private function resolveFamilyLinks(array &$persons, array &$families): void
    {
        foreach ($families as $famId => &$fam) {
            $husbId = $fam['husband_gedcom_id'];
            $wifeId = $fam['wife_gedcom_id'];

            // Супружеские связи
            if ($husbId && isset($persons[$husbId]) && $wifeId && isset($persons[$wifeId])) {
                if (!in_array($wifeId, $persons[$husbId]['spouse_gedcom_ids'], true)) {
                    $persons[$husbId]['spouse_gedcom_ids'][] = $wifeId;
                }
                if (!in_array($husbId, $persons[$wifeId]['spouse_gedcom_ids'], true)) {
                    $persons[$wifeId]['spouse_gedcom_ids'][] = $husbId;
                }
            }

            // Связи родитель - ребенок
            foreach ($fam['children'] as $childId) {
                if (!isset($persons[$childId])) {
                    continue;
                }

                if ($husbId && isset($persons[$husbId])) {
                    $persons[$childId]['father_gedcom_id'] = $husbId;
                    if (!in_array($childId, $persons[$husbId]['children_gedcom_ids'], true)) {
                        $persons[$husbId]['children_gedcom_ids'][] = $childId;
                    }
                }

                if ($wifeId && isset($persons[$wifeId])) {
                    $persons[$childId]['mother_gedcom_id'] = $wifeId;
                    if (!in_array($childId, $persons[$wifeId]['children_gedcom_ids'], true)) {
                        $persons[$wifeId]['children_gedcom_ids'][] = $childId;
                    }
                }
            }
        }
        unset($fam);

        // Дополнительная проверка по FAMC персон
        foreach ($persons as $personId => &$person) {
            if ($person['famc'] && isset($families[$person['famc']])) {
                $fam = $families[$person['famc']];
                if (empty($person['father_gedcom_id']) && $fam['husband_gedcom_id']) {
                    $person['father_gedcom_id'] = $fam['husband_gedcom_id'];
                }
                if (empty($person['mother_gedcom_id']) && $fam['wife_gedcom_id']) {
                    $person['mother_gedcom_id'] = $fam['wife_gedcom_id'];
                }
            }
        }
        unset($person);
    }
}
