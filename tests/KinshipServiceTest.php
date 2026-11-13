<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\KinshipService;

/**
 * Тестирование казахской системы терминов родства (KinshipService)
 */
class KinshipServiceTest
{
    protected KinshipService $service;
    protected array $samplePersons;
    protected array $sampleMarriages;
    protected array $personsById;
    protected int $passCount = 0;
    protected int $failCount = 0;

    public function __construct()
    {
        $this->service = new KinshipService();
        $this->setupData();
    }

    protected function setupData(): void
    {
        // 1: Керей (корень, ок. 1380)
        // 2: Самай (ствол, ок. 1620)
        // 3: Алтыбай (ру басы, ок. 1795)
        // 4: Сейітқали (1898)
        // 5: Бибінұр (1904, Жағалбайлы)
        // 6: Қасым (1955)
        // 7: Жандос (1985, M)
        // 8: Әлия (1987, F, Жағалбайлы)
        // 9: Айнұр (1989, F, қарындас)
        // 10: Арнұр (2014, M, ұлы)
        // 11: Медина (2019, F, қызы)
        // 12: Дәулет (1987, M, күйеу бала)
        $this->samplePersons = [
            ['id' => 1, 'first_name' => 'Керей', 'gender' => 'M', 'birth_year' => '1380', 'father_id' => null, 'mother_id' => null, 'clan_id' => 1],
            ['id' => 2, 'first_name' => 'Самай', 'gender' => 'M', 'birth_year' => '1620', 'father_id' => 1, 'mother_id' => null, 'clan_id' => 1],
            ['id' => 3, 'first_name' => 'Алтыбай', 'gender' => 'M', 'birth_year' => '1795', 'father_id' => 2, 'mother_id' => null, 'clan_id' => 1],
            ['id' => 4, 'first_name' => 'Сейітқали', 'gender' => 'M', 'birth_year' => '1898', 'father_id' => 3, 'mother_id' => null, 'clan_id' => 1],
            ['id' => 5, 'first_name' => 'Бибінұр', 'gender' => 'F', 'birth_year' => '1904', 'father_id' => null, 'mother_id' => null, 'clan_id' => 2],
            ['id' => 6, 'first_name' => 'Қасым', 'gender' => 'M', 'birth_year' => '1955', 'father_id' => 4, 'mother_id' => 5, 'clan_id' => 1],
            ['id' => 7, 'first_name' => 'Жандос', 'gender' => 'M', 'birth_year' => '1985', 'father_id' => 6, 'mother_id' => null, 'clan_id' => 1],
            ['id' => 8, 'first_name' => 'Әлия', 'gender' => 'F', 'birth_year' => '1987', 'father_id' => null, 'mother_id' => null, 'clan_id' => 2],
            ['id' => 9, 'first_name' => 'Айнұр', 'gender' => 'F', 'birth_year' => '1989', 'father_id' => 6, 'mother_id' => null, 'clan_id' => 1],
            ['id' => 10, 'first_name' => 'Арнұр', 'gender' => 'M', 'birth_year' => '2014', 'father_id' => 7, 'mother_id' => 8, 'clan_id' => 1],
            ['id' => 11, 'first_name' => 'Медина', 'gender' => 'F', 'birth_year' => '2019', 'father_id' => 7, 'mother_id' => 8, 'clan_id' => 1],
            ['id' => 12, 'first_name' => 'Дәулет', 'gender' => 'M', 'birth_year' => '1987', 'father_id' => null, 'mother_id' => null, 'clan_id' => 3],
        ];

        $this->sampleMarriages = [
            ['id' => 1, 'husband_id' => 4, 'wife_id' => 5],
            ['id' => 2, 'husband_id' => 7, 'wife_id' => 8],
            ['id' => 3, 'husband_id' => 12, 'wife_id' => 9],
        ];

        $this->personsById = array_column($this->samplePersons, null, 'id');
    }

    public function run(): void
    {
        echo "=======================================================\n";
        echo "  Шежіре: Тестирование калькулятора родства (TASK-003) \n";
        echo "=======================================================\n\n";

        $this->testDirectRelations();
        $this->testSiblingsWithGenderAndSeniority();
        $this->testGrandparentsAndAncestors();
        $this->testGrandchildren();
        $this->testInLaws();
        $this->testBulkKinshipCalculation();

        echo "\n-------------------------------------------------------\n";
        echo "Результат: {$this->passCount} из " . ($this->passCount + $this->failCount) . " проверок пройдено успешно!\n";
        if ($this->failCount === 0) {
            echo "ВСЕ ТЕСТЫ РОДСТВА ПРОЙДЕНЫ [SUCCESS]\n";
        } else {
            echo "ЕСТЬ ОШИБКИ: {$this->failCount} проверок провалено!\n";
            exit(1);
        }
        echo "-------------------------------------------------------\n";
    }

    protected function testDirectRelations(): void
    {
        echo "1. Тестирование прямых связей (Ата-ана, балалары, жұбайы):\n";

        // Отец: для Жандоса Қасым является Әке
        $res = $this->service->determineKinship($this->personsById[6], $this->personsById[7], 'auto', $this->personsById, $this->sampleMarriages);
        $this->assert($res['code'] === 'father', "Қасым для Жандоса — Әке (code = father)");
        $this->assert($res['term_kk'] === 'Әке', "Қасым для Жандоса term_kk = 'Әке'");

        // Сын: для Қасыма Жандос является Ұл
        $res = $this->service->determineKinship($this->personsById[7], $this->personsById[6], 'auto', $this->personsById, $this->sampleMarriages);
        $this->assert($res['code'] === 'son', "Жандос для Қасыма — Ұл (code = son)");

        // Дочь: для Қасыма Айнұр является Қыз
        $res = $this->service->determineKinship($this->personsById[9], $this->personsById[6], 'auto', $this->personsById, $this->sampleMarriages);
        $this->assert($res['code'] === 'daughter', "Айнұр для Қасыма — Қыз (code = daughter)");

        // Супруга: для Жандоса Әлия является Жұбайы
        $res = $this->service->determineKinship($this->personsById[8], $this->personsById[7], 'auto', $this->personsById, $this->sampleMarriages);
        $this->assert($res['code'] === 'wife', "Әлия для Жандоса — Жұбайы (code = wife)");

        // Сам себя
        $res = $this->service->determineKinship($this->personsById[7], $this->personsById[7], 'auto', $this->personsById, $this->sampleMarriages);
        $this->assert($res['code'] === 'self', "Жандос для самого себя — Өзіңіз (code = self)");
    }

    protected function testSiblingsWithGenderAndSeniority(): void
    {
        echo "\n2. Тестирование бауырлар (Аға, Іні, Әпке, Қарындас, Сіңлі):\n";

        // Младшая сестра для брата: Айнұр (1989) для Жандоса (1985, M) — Қарындас
        $res = $this->service->determineKinship($this->personsById[9], $this->personsById[7], 'auto', $this->personsById, $this->sampleMarriages);
        $this->assert($res['code'] === 'sister_younger_for_male', "Айнұр для Жандоса — Қарындас (sister_younger_for_male)");
        $this->assert($res['term_kk'] === 'Қарындас', "term_kk = 'Қарындас'");

        // Старший брат для сестры: Жандос (1985) для Айнұр (1989, F) — Аға
        $res = $this->service->determineKinship($this->personsById[7], $this->personsById[9], 'auto', $this->personsById, $this->sampleMarriages);
        $this->assert($res['code'] === 'brother_older', "Жандос для Айнұр — Аға (brother_older)");
        $this->assert($res['term_kk'] === 'Аға', "term_kk = 'Аға'");

        // Младшая сестра для сестры (сіңлі)
        $femaleOlder = ['id' => 99, 'gender' => 'F', 'birth_year' => '1986', 'father_id' => 6];
        $res = $this->service->determineKinship($this->personsById[9], $femaleOlder, 'auto', $this->personsById, $this->sampleMarriages);
        $this->assert($res['code'] === 'sister_younger_for_female', "Младшая сестра для сестры — Сіңлі (sister_younger_for_female)");
        $this->assert($res['term_kk'] === 'Сіңлі', "term_kk = 'Сіңлі'");
    }

    protected function testGrandparentsAndAncestors(): void
    {
        echo "\n3. Тестирование аталар мен әжелер (Ата, Әже, Баба):\n";

        // Дедушка по отцу: Сейітқали (отец Қасыма) для Жандоса — Ата
        $res = $this->service->determineKinship($this->personsById[4], $this->personsById[7], 'auto', $this->personsById, $this->sampleMarriages);
        $this->assert($res['code'] === 'grandfather_paternal', "Сейітқали для Жандоса — Ата (grandfather_paternal)");

        // Бабушка по отцу: Бибінұр (мать Қасыма) для Жандоса — Әже
        $res = $this->service->determineKinship($this->personsById[5], $this->personsById[7], 'auto', $this->personsById, $this->sampleMarriages);
        $this->assert($res['code'] === 'grandmother_paternal', "Бибінұр для Жандоса — Әже (grandmother_paternal)");

        // Прадедушка: Алтыбай баба для Жандоса — Баба
        $res = $this->service->determineKinship($this->personsById[3], $this->personsById[7], 'auto', $this->personsById, $this->sampleMarriages);
        $this->assert($res['code'] === 'great_grandfather' || $res['code'] === 'ancestor', "Алтыбай баба для Жандоса — Баба");
    }

    protected function testGrandchildren(): void
    {
        echo "\n4. Тестирование ұрпақтар (Немере, Шөбере):\n";

        // Немере: Арнұр (сын Жандоса) для Қасыма (отца Жандоса) — Немере
        $res = $this->service->determineKinship($this->personsById[10], $this->personsById[6], 'auto', $this->personsById, $this->sampleMarriages);
        $this->assert($res['code'] === 'grandson_son', "Арнұр для Қасыма — Немере (grandson_son)");

        // Шөбере: Арнұр для Сейітқали — Шөбере
        $res = $this->service->determineKinship($this->personsById[10], $this->personsById[4], 'auto', $this->personsById, $this->sampleMarriages);
        $this->assert($res['code'] === 'great_grandson', "Арнұр для Сейітқали — Шөбере (great_grandson)");
    }

    protected function testInLaws(): void
    {
        echo "\n5. Тестирование құда-жекжаттар (Келін, Күйеу бала, Жезде):\n";

        // Келін: Әлия (жена Жандоса) для Қасыма (отца) — Келін
        $res = $this->service->determineKinship($this->personsById[8], $this->personsById[6], 'auto', $this->personsById, $this->sampleMarriages);
        $this->assert($res['code'] === 'kelin', "Әлия для Қасыма — Келін (сноха сына)");

        // Күйеу бала: Дәулет (муж Айнұр) для Қасыма (отца) — Күйеу бала
        $res = $this->service->determineKinship($this->personsById[12], $this->personsById[6], 'auto', $this->personsById, $this->sampleMarriages);
        $this->assert($res['code'] === 'kuyeubala', "Дәулет для Қасыма — Күйеу бала (зять дочери)");

        // Дәулет (муж младшей сестры Айнұр) для Жандоса — Күйеу бала
        $res = $this->service->determineKinship($this->personsById[12], $this->personsById[7], 'auto', $this->personsById, $this->sampleMarriages);
        $this->assert($res['code'] === 'kuyeubala_sister' || $res['code'] === 'zhezde', "Дәулет для Жандоса — Күйеу бала / Жезде (муж сестры)");
    }

    protected function testBulkKinshipCalculation(): void
    {
        echo "\n6. Тестирование массового расчета родства (calculateAllKinships):\n";

        $allKinships = $this->service->calculateAllKinships(7, $this->samplePersons, $this->sampleMarriages);
        $this->assert(count($allKinships) === 12, "Массовый расчет вернул 12 результатов для 12 персон");
        $this->assert(isset($allKinships[6]) && $allKinships[6]['code'] === 'father', "В массовом расчете ID 6 = father");
        $this->assert(isset($allKinships[9]) && $allKinships[9]['code'] === 'sister_younger_for_male', "В массовом расчете ID 9 = sister_younger_for_male");
        $this->assert(isset($allKinships[10]) && $allKinships[10]['code'] === 'son', "В массовом расчете ID 10 = son");
        $this->assert(isset($allKinships[11]) && $allKinships[11]['code'] === 'daughter', "В массовом расчете ID 11 = daughter");

        // Пересчет относительно отца Қасыма (ID 6)
        $allFromKasym = $this->service->calculateAllKinships(6, $this->samplePersons, $this->sampleMarriages);
        $this->assert($allFromKasym[7]['code'] === 'son', "Относительно Қасыма ID 7 (Жандос) = son");
        $this->assert($allFromKasym[8]['code'] === 'kelin', "Относительно Қасыма ID 8 (Әлия) = kelin");
        $this->assert($allFromKasym[10]['code'] === 'grandson_son', "Относительно Қасыма ID 10 (Арнұр) = grandson_son (немере)");
    }

    protected function assert(bool $condition, string $message): void
    {
        if ($condition) {
            echo "  [OK] {$message}\n";
            $this->passCount++;
        } else {
            echo "  [FAIL] {$message}\n";
            $this->failCount++;
        }
    }
}

$test = new KinshipServiceTest();
$test->run();
