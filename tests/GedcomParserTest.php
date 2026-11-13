<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\GedcomParserService;

/**
 * Комплексный тест парсера GEDCOM, санитизации XSS и изоляции приватных контактов
 * Запуск: C:\PHP\php.exe C:\Projects\Altybayev.kz\tests\GedcomParserTest.php
 */
class GedcomParserTest
{
    private GedcomParserService $parser;
    private int $assertionsCount = 0;
    private int $passedCount = 0;

    public function __construct()
    {
        $this->parser = new GedcomParserService();
    }

    public function run(): void
    {
        echo "=======================================================\n";
        echo "  Шежіре: Тестирование GEDCOM парсера (TASK-005)       \n";
        echo "=======================================================\n\n";

        $this->testRealGedcomFileParsing();
        $this->testXssSanitization();
        $this->testNameCleaning();
        $this->testDateParsing();
        $this->testPrivateContactsIsolation();
        $this->testFamilyRelationships();

        echo "\n-------------------------------------------------------\n";
        echo "Результат: {$this->passedCount} из {$this->assertionsCount} проверок пройдено успешно!\n";
        if ($this->passedCount === $this->assertionsCount) {
            echo "ВСЕ ТЕСТЫ ПРОЙДЕНЫ [SUCCESS]\n";
        } else {
            echo "ЕСТЬ ОШИБКИ [FAIL]\n";
            exit(1);
        }
        echo "-------------------------------------------------------\n";
    }

    private function assert(bool $condition, string $message): void
    {
        $this->assertionsCount++;
        if ($condition) {
            $this->passedCount++;
            echo "  [OK] {$message}\n";
        } else {
            echo "  [FAIL] {$message}\n";
        }
    }

    /**
     * 1. Тест парсинга реального файла Altubaiev - 2010-05-27.ged
     */
    private function testRealGedcomFileParsing(): void
    {
        echo "1. Проверка парсинга файла Altubaiev - 2010-05-27.ged:\n";
        $file = __DIR__ . '/../import/Altubaiev - 2010-05-27.ged';
        $this->assert(file_exists($file), "Файл GEDCOM существует: {$file}");

        $res = $this->parser->parseFile($file);

        $this->assert($res['stats']['total_persons'] === 270, "Количество персон INDI = 270 (~240)");
        $this->assert($res['stats']['total_families'] === 81, "Количество семей FAM = 81 (~85)");
        $this->assert($res['stats']['photos_skipped'] === 1124, "Пропущено 1124 локальных фото отца");
        $this->assert($res['stats']['contacts_secured'] === 12, "Изолировано 12 записей с закрытыми контактами");
        $this->assert($res['stats']['xss_threats_neutralized'] === 2, "Обезврежено 2 XSS угрозы (script + iframe)");

        // Проверяем ключевые персоны шежіре
        $foundZhandos = false;
        $foundGabit = false;
        $foundShamsiden = false;
        foreach ($res['persons'] as $p) {
            if (str_contains($p['full_name'], 'Жандос')) {
                $foundZhandos = true;
            }
            if (str_contains($p['full_name'], 'Габит')) {
                $foundGabit = true;
            }
            if (str_contains($p['full_name'], 'Шамсиден')) {
                $foundShamsiden = true;
                // Проверяем, что в заметке Шамсидена нет скриптов
                $this->assert(!str_contains($p['biography'] ?? '', '<script'), "В биографии Шамсидена отсутствует <script>");
                $this->assert(!str_contains($p['biography'] ?? '', '<iframe'), "В биографии Шамсидена отсутствует <iframe>");
                $this->assert(str_contains($p['biography'] ?? '', 'Жаппасовской школы'), "Полезный текст заметки Шамсидена сохранен");
            }
        }

        $this->assert($foundZhandos, "Персона Жандос найдена в результатах");
        $this->assert($foundGabit, "Персона Габит найдена в результатах");
        $this->assert($foundShamsiden, "Персона Шамсиден найдена в результатах");

        // Проверка корректности маппинга девичьей фамилии и фамилии по мужу (Асила @I24@)
        $asila = array_values(array_filter($res['persons'], fn($p) => ($p['gedcom_id'] ?? '') === '@I24@'))[0] ?? null;
        $this->assert($asila !== null, "Персона Асила (@I24@) найдена в результатах");
        $this->assert(($asila['last_name'] ?? '') === 'Алтыбаева', "Текущая фамилия Асилы (_MARNM) = 'Алтыбаева'");
        $this->assert(($asila['maiden_name'] ?? '') === 'Кубенова', "Девичья фамилия Асилы (SURN) = 'Кубенова'");
    }

    /**
     * 2. Тест строгой санитизации XSS (Требование 2)
     */
    private function testXssSanitization(): void
    {
        echo "\n2. Тестирование санитизации XSS:\n";
        $threats = [];

        // Тест скрипта
        $dirtyScript = "<p>Биография предка</p><script>alert('pwned');</script><p>Конец биографии</p>";
        $clean = $this->parser->sanitizeHtml($dirtyScript, '@I1@', 'Тест', $threats);
        $this->assert(!str_contains($clean, '<script') && !str_contains($clean, 'pwned'), "Скрипт полностью удален");
        $this->assert(count($threats) === 1 && $threats[0]['type'] === 'XSS_SCRIPT', "Угроза XSS_SCRIPT зафиксирована");

        // Тест iframe
        $threats = [];
        $dirtyIframe = "<p>Текст</p><iframe src=\"http://evil.com/hack.html\" style=\"display:none;\"></iframe>";
        $cleanIframe = $this->parser->sanitizeHtml($dirtyIframe, '@I2@', 'Тест 2', $threats);
        $this->assert(!str_contains($cleanIframe, '<iframe') && !str_contains($cleanIframe, 'evil.com'), "Тег iframe полностью удален");
        $this->assert(count($threats) === 1 && $threats[0]['type'] === 'XSS_IFRAME', "Угроза XSS_IFRAME зафиксирована");

        // Тест on-событий
        $threats = [];
        $dirtyEvents = "<img src=\"avatar.jpg\" onload=\"alert(1)\" onerror=\"alert(2)\"><p onclick=\"bad()\">Нажми</p>";
        $cleanEvents = $this->parser->sanitizeHtml($dirtyEvents, '@I3@', 'Тест 3', $threats);
        $this->assert(!str_contains($cleanEvents, 'onload') && !str_contains($cleanEvents, 'onerror') && !str_contains($cleanEvents, 'onclick'), "Обработчики on* событий вычищены");

        // Тест протокола javascript:
        $dirtyJsUri = '<a href="javascript:stealCookie()">Ссылка</a>';
        $cleanJsUri = $this->parser->sanitizeHtml($dirtyJsUri, '@I4@', 'Тест 4', $threats);
        $this->assert(!str_contains($cleanJsUri, 'javascript:'), "Протокол javascript: удален");
    }

    /**
     * 3. Тест парсинга имен и очистки от мусора MyHeritage
     */
    private function testNameCleaning(): void
    {
        echo "\n3. Тестирование очистки имен MyHeritage:\n";
        $rawGedcom = "0 HEAD\n1 GEDC\n2 VERS 5.5.1\n0 @I1@ INDI\n1 NAME Габит /Алтыбаев,/\n1 SEX M\n0 @I2@ INDI\n1 NAME Жанна /Алтыбаева ./\n1 SEX F\n0 @I3@ INDI\n1 NAME Слусия //\n1 SEX F\n0 TRLR";
        $res = $this->parser->parseContent($rawGedcom);

        $this->assert($res['persons']['@I1@']['first_name'] === 'Габит', "Имя Габит распознано");
        $this->assert($res['persons']['@I1@']['last_name'] === 'Алтыбаев', "Фамилия очищена от запятой: 'Алтыбаев'");

        $this->assert($res['persons']['@I2@']['first_name'] === 'Жанна', "Имя Жанна распознано");
        $this->assert($res['persons']['@I2@']['last_name'] === 'Алтыбаева', "Фамилия очищена от точки: 'Алтыбаева'");

        $this->assert($res['persons']['@I3@']['first_name'] === 'Слусия', "Имя Слусия без фамилии распознано");
        $this->assert($res['persons']['@I3@']['last_name'] === '', "Пустая фамилия пуста");
    }

    /**
     * 4. Тест парсинга дат
     */
    private function testDateParsing(): void
    {
        echo "\n4. Тестирование парсинга дат:\n";
        $d1 = $this->parser->parseGedcomDate('3 FEB 1956');
        $this->assert($d1['date'] === '1956-02-03', "3 FEB 1956 -> 1956-02-03");
        $this->assert($d1['year'] === '1956', "Год 1956");

        $d2 = $this->parser->parseGedcomDate('31 AUG 1980');
        $this->assert($d2['date'] === '1980-08-31', "31 AUG 1980 -> 1980-08-31");

        $d3 = $this->parser->parseGedcomDate('1902');
        $this->assert($d3['date'] === null, "Только год: date = null");
        $this->assert($d3['year'] === '1902', "Только год: year = 1902");

        $d4 = $this->parser->parseGedcomDate('ABT 1925');
        $this->assert($d4['year'] === 'ок. 1925', "Примерная дата: 'ок. 1925'");
    }

    /**
     * 5. Тест изоляции приватных контактов
     */
    private function testPrivateContactsIsolation(): void
    {
        echo "\n5. Тестирование изоляции приватных контактов:\n";
        $rawGedcom = "0 HEAD\n1 GEDC\n2 VERS 5.5.1\n0 @I10@ INDI\n1 NAME Отец /Тест/\n1 SEX M\n1 RESI\n2 TYPE PHON\n2 PHON +77011234567\n1 RESI\n2 TYPE EMAIL\n2 EMAIL test@@altybayev.kz\n1 RESI\n2 TYPE ADDR\n2 ADDR ул. Абая 10\n0 TRLR";
        $res = $this->parser->parseContent($rawGedcom);

        $person = $res['persons']['@I10@'];
        $this->assert(in_array('+77011234567', $person['private_contacts']['phones']), "Телефон в private_contacts");
        $this->assert(in_array('test@altybayev.kz', $person['private_contacts']['emails']) || in_array('test@@altybayev.kz', $person['private_contacts']['emails']), "Email в private_contacts");
        $this->assert(in_array('ул. Абая 10', $person['private_contacts']['addresses']), "Адрес в private_contacts");
    }

    /**
     * 6. Тест связывания родителей и детей
     */
    private function testFamilyRelationships(): void
    {
        echo "\n6. Тестирование связывания семей:\n";
        $rawGedcom = "0 HEAD\n0 @I1@ INDI\n1 NAME Отец /Семьи/\n1 SEX M\n1 FAMS @F1@\n0 @I2@ INDI\n1 NAME Мать /Семьи/\n1 SEX F\n1 FAMS @F1@\n0 @I3@ INDI\n1 NAME Ребенок /Семьи/\n1 SEX M\n1 FAMC @F1@\n0 @F1@ FAM\n1 HUSB @I1@\n1 WIFE @I2@\n1 CHIL @I3@\n0 TRLR";
        $res = $this->parser->parseContent($rawGedcom);

        $child = $res['persons']['@I3@'];
        $father = $res['persons']['@I1@'];
        $mother = $res['persons']['@I2@'];

        $this->assert($child['father_gedcom_id'] === '@I1@', "Отец ребенка привязан к @I1@");
        $this->assert($child['mother_gedcom_id'] === '@I2@', "Мать ребенка привязана к @I2@");
        $this->assert(in_array('@I3@', $father['children_gedcom_ids']), "Ребенок в списке детей отца");
        $this->assert(in_array('@I2@', $father['spouse_gedcom_ids']), "Супруга в списке супругов отца");
    }
}

$test = new GedcomParserTest();
$test->run();
