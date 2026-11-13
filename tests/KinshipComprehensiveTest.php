<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\KinshipService;

echo "=======================================================\n";
echo "  Шежіре: Расширенное тестирование родства (TASK-003)  \n";
echo "=======================================================\n\n";

$service = new KinshipService();

$passed = 0;
$total = 0;

function assertCheck(bool $condition, string $label): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [OK] {$label}\n";
    } else {
        echo "  [FAIL] {$label}\n";
    }
}

// Построим тестовое окружение семьи
// Семья 1: Сестры Асила (101) и Алия (102). Их родители: Отец (100)
// Жандос (1) женат на Асиле (101).
// Муж Алии (102) — Бауржан (200). Жандос и Бауржан — БАЖА!
// Младший брат Асилы — Серик (103, 1995 г.р.) — для Жандоса БАЛДЫЗ!
// Старший брат Асилы — Марат (104, 1980 г.р.) — для Жандоса ҚАЙЫН АҒА!
// Младшая сестра Асилы — Динара (105, 1996 г.р.) — для Жандоса БАЛДЫЗ!
//
// Семья 2: Братья Жандос (1) и Ерлан (2). Жандос (1985), Ерлан (1990).
// У Ерлана (2) есть сын Айбек (3, 2015 г.р.) и дочь Аружан (4, 2017 г.р.).
// Для Жандоса Айбек (сын брата) — ІНІ (братишка / сын брата)!
// Для Жандоса Аружан (дочь брата) — ҚАРЫНДАС (сестрёнка / дочь брата)!
// А обратно для Айбека Жандос (брат отца) — АҒА (дядя по отцу)!
//
// Сын Жандоса — Арнур (5, 2014 г.р.).
// Айбек (3, 2015) и Арнур (5, 2014) — сыновья братьев — друг другу:
// Для Айбека Арнур старше — АҒА (двоюродный брат по отцу)!
// Для Арнура Айбек младше — ІНІ (двоюродный брат по отцу)!
//
// Жены братьев: Асила (101, жена Жандоса) и Гульнар (106, жена Ерлана) — АБЫСЫН!
//
// Сестра Жандоса — Айнур (6). У нее сын Данияр (7).
// Для Жандоса Данияр (сын сестры) — ЖИЕН (племянник по сестре)!
// У Жандоса есть дочь Медина (8). У Медины родился сын Самат (9).
// Для Жандоса Самат (сын дочери) — ЖИЕН (внук по дочери)!
//
// Сваты: Дочь Жандоса Медина (8) вышла замуж за Нурлана (300).
// Отец Нурлана — Канат (301) — для Жандоса ҚҰДА!
// Мать Нурлана — Сара (302) — для Жандоса ҚҰДАҒИ!
// Сестра Нурлана — Гульмира (303) — для Жандоса ҚҰДАША!
//
// Бөле: Сестра матери Жандоса (400) — Назира (401). У Назиры сын Мурат (402).
// Для Жандоса Мурат — БӨЛЕ!

$testPersons = [
    1 => ['id' => 1, 'first_name' => 'Жандос', 'gender' => 'M', 'birth_year' => '1985', 'father_id' => 50, 'mother_id' => 400, 'clan_ru' => 'Керей'],
    2 => ['id' => 2, 'first_name' => 'Ерлан', 'gender' => 'M', 'birth_year' => '1990', 'father_id' => 50, 'mother_id' => 400, 'clan_ru' => 'Керей'],
    3 => ['id' => 3, 'first_name' => 'Айбек', 'gender' => 'M', 'birth_year' => '2015', 'father_id' => 2, 'mother_id' => 106, 'clan_ru' => 'Керей'],
    4 => ['id' => 4, 'first_name' => 'Аружан', 'gender' => 'F', 'birth_year' => '2017', 'father_id' => 2, 'mother_id' => 106, 'clan_ru' => 'Керей'],
    5 => ['id' => 5, 'first_name' => 'Арнур', 'gender' => 'M', 'birth_year' => '2014', 'father_id' => 1, 'mother_id' => 101, 'clan_ru' => 'Керей'],
    6 => ['id' => 6, 'first_name' => 'Айнур', 'gender' => 'F', 'birth_year' => '1988', 'father_id' => 50, 'mother_id' => 400, 'clan_ru' => 'Керей'],
    7 => ['id' => 7, 'first_name' => 'Данияр', 'gender' => 'M', 'birth_year' => '2016', 'father_id' => 250, 'mother_id' => 6, 'clan_ru' => 'Қоңырат'],
    8 => ['id' => 8, 'first_name' => 'Медина', 'gender' => 'F', 'birth_year' => '2018', 'father_id' => 1, 'mother_id' => 101, 'clan_ru' => 'Керей'],
    9 => ['id' => 9, 'first_name' => 'Самат', 'gender' => 'M', 'birth_year' => '2038', 'father_id' => 300, 'mother_id' => 8, 'clan_ru' => 'Арғын'],
    50 => ['id' => 50, 'first_name' => 'Ғабит', 'gender' => 'M', 'birth_year' => '1956', 'father_id' => 60, 'mother_id' => 61, 'clan_ru' => 'Керей'],

    100 => ['id' => 100, 'first_name' => 'Кубен', 'gender' => 'M', 'birth_year' => '1955', 'father_id' => null, 'mother_id' => null, 'clan_ru' => 'Жағалбайлы'],
    101 => ['id' => 101, 'first_name' => 'Асила', 'gender' => 'F', 'birth_year' => '1985', 'father_id' => 100, 'mother_id' => null, 'clan_ru' => 'Жағалбайлы'],
    102 => ['id' => 102, 'first_name' => 'Алия', 'gender' => 'F', 'birth_year' => '1987', 'father_id' => 100, 'mother_id' => null, 'clan_ru' => 'Жағалбайлы'],
    103 => ['id' => 103, 'first_name' => 'Серик', 'gender' => 'M', 'birth_year' => '1995', 'father_id' => 100, 'mother_id' => null, 'clan_ru' => 'Жағалбайлы'],
    104 => ['id' => 104, 'first_name' => 'Марат', 'gender' => 'M', 'birth_year' => '1980', 'father_id' => 100, 'mother_id' => null, 'clan_ru' => 'Жағалбайлы'],
    105 => ['id' => 105, 'first_name' => 'Динара', 'gender' => 'F', 'birth_year' => '1996', 'father_id' => 100, 'mother_id' => null, 'clan_ru' => 'Жағалбайлы'],
    106 => ['id' => 106, 'first_name' => 'Гульнар', 'gender' => 'F', 'birth_year' => '1992', 'father_id' => null, 'mother_id' => null, 'clan_ru' => 'Найман'],

    200 => ['id' => 200, 'first_name' => 'Бауржан', 'gender' => 'M', 'birth_year' => '1986', 'father_id' => null, 'mother_id' => null, 'clan_ru' => 'Байұлы'],
    300 => ['id' => 300, 'first_name' => 'Нурлан', 'gender' => 'M', 'birth_year' => '2017', 'father_id' => 301, 'mother_id' => 302, 'clan_ru' => 'Арғын'],
    301 => ['id' => 301, 'first_name' => 'Канат', 'gender' => 'M', 'birth_year' => '1990', 'father_id' => null, 'mother_id' => null, 'clan_ru' => 'Арғын'],
    302 => ['id' => 302, 'first_name' => 'Сара', 'gender' => 'F', 'birth_year' => '1992', 'father_id' => null, 'mother_id' => null, 'clan_ru' => 'Арғын'],
    303 => ['id' => 303, 'first_name' => 'Гульмира', 'gender' => 'F', 'birth_year' => '2020', 'father_id' => 301, 'mother_id' => 302, 'clan_ru' => 'Арғын'],

    400 => ['id' => 400, 'first_name' => 'Менслу', 'gender' => 'F', 'birth_year' => '1960', 'father_id' => 500, 'mother_id' => 501, 'clan_ru' => 'Жағалбайлы'],
    401 => ['id' => 401, 'first_name' => 'Назира', 'gender' => 'F', 'birth_year' => '1963', 'father_id' => 500, 'mother_id' => 501, 'clan_ru' => 'Жағалбайлы'],
    402 => ['id' => 402, 'first_name' => 'Мурат', 'gender' => 'M', 'birth_year' => '1986', 'father_id' => 600, 'mother_id' => 401, 'clan_ru' => 'Дулат'],
];

$testMarriages = [
    ['id' => 1, 'husband_id' => 1, 'wife_id' => 101], // Жандос + Асила
    ['id' => 2, 'husband_id' => 200, 'wife_id' => 102], // Бауржан + Алия
    ['id' => 3, 'husband_id' => 2, 'wife_id' => 106], // Ерлан + Гульнар
    ['id' => 4, 'husband_id' => 50, 'wife_id' => 400], // Ғабит + Менслу
    ['id' => 5, 'husband_id' => 300, 'wife_id' => 8], // Нурлан + Медина
];

// 1. ТЕСТ БАЖА
$resBazha = $service->determineKinship($testPersons[200], $testPersons[1], 'auto', $testPersons, $testMarriages);
assertCheck($resBazha['term_kk'] === 'Бажа', "Бауржан для Жандоса — БАЖА (term_kk = 'Бажа')");
assertCheck(str_contains($resBazha['term_ru'], 'Бажа'), "Бауржан для Жандоса term_ru содержит 'Бажа'");
assertCheck(!str_contains(mb_strtolower($resBazha['term_ru']), 'бауыр') && !str_contains(mb_strtolower($resBazha['term_ru']), 'сородич'), "Бажа не подписан как бауыр или сородич");
assertCheck($resBazha['zhurt'] === 'Қайын жұрты', "Бажа относится к Қайын жұрты");

// 2. ТЕСТ БАЛДЫЗ (младший брат и младшая сестра жены)
$resBaldyzM = $service->determineKinship($testPersons[103], $testPersons[1], 'auto', $testPersons, $testMarriages);
assertCheck($resBaldyzM['term_kk'] === 'Балдыз', "Серик для Жандоса — Балдыз (младший брат жены)");
assertCheck(str_contains($resBaldyzM['term_ru'], 'Балдыз'), "term_ru содержит 'Балдыз'");

$resBaldyzF = $service->determineKinship($testPersons[105], $testPersons[1], 'auto', $testPersons, $testMarriages);
assertCheck($resBaldyzF['term_kk'] === 'Балдыз', "Динара для Жандоса — Балдыз (младшая сестра жены)");

// 3. ТЕСТ ҚАЙЫН АҒА
$resKayinAga = $service->determineKinship($testPersons[104], $testPersons[1], 'auto', $testPersons, $testMarriages);
assertCheck($resKayinAga['term_kk'] === 'Қайын аға', "Марат для Жандоса — Қайын аға (старший брат жены)");

// 4. ТЕСТ АБЫСЫН
$resAbysyn = $service->determineKinship($testPersons[106], $testPersons[101], 'auto', $testPersons, $testMarriages);
assertCheck($resAbysyn['term_kk'] === 'Абысын', "Гульнар для Асилы (жены двух братьев) — АБЫСЫН");

// 5. ТЕСТ СЫН БРАТА (ІНІ) И ДОЧЬ БРАТА (ҚАРЫНДАС)
$resNephewM = $service->determineKinship($testPersons[3], $testPersons[1], 'auto', $testPersons, $testMarriages);
assertCheck($resNephewM['term_kk'] === 'Іні', "Айбек (сын брата) для Жандоса — ІНІ");
assertCheck(str_contains($resNephewM['term_ru'], 'сын брата'), "term_ru содержит 'сын брата'");

$resNephewF = $service->determineKinship($testPersons[4], $testPersons[1], 'auto', $testPersons, $testMarriages);
assertCheck($resNephewF['term_kk'] === 'Қарындас', "Аружан (дочь брата) для Жандоса — ҚАРЫНДАС");
assertCheck(str_contains($resNephewF['term_ru'], 'дочь брата'), "term_ru содержит 'дочь брата'");

// 6. ТЕСТ ОБРАТНО: ДЯДЯ ПО ОТЦУ (АҒА)
$resUncle = $service->determineKinship($testPersons[1], $testPersons[3], 'auto', $testPersons, $testMarriages);
assertCheck($resUncle['term_kk'] === 'Аға', "Жандос для Айбека (брат отца) — АҒА");
assertCheck(str_contains($resUncle['term_ru'], 'дядя по отцу'), "term_ru содержит 'дядя по отцу'");

// 7. ТЕСТ ДЕТИ РОДНЫХ БРАТЬЕВ ДРУГ ДРУГУ (Двоюродные братья по отцу)
$resCousin1 = $service->determineKinship($testPersons[5], $testPersons[3], 'auto', $testPersons, $testMarriages);
assertCheck($resCousin1['term_kk'] === 'Аға', "Арнур (2014) для Айбека (2015) — старший двоюродный брат (Аға)");
assertCheck(str_contains($resCousin1['term_ru'], 'немере аға'), "term_ru содержит пояснение 'немере аға'");

$resCousin2 = $service->determineKinship($testPersons[3], $testPersons[5], 'auto', $testPersons, $testMarriages);
assertCheck($resCousin2['term_kk'] === 'Іні', "Айбек (2015) для Арнура (2014) — младший двоюродный брат (Іні)");

// 8. ТЕСТ ЖИЕН ПО СЕСТРЕ VS ЖИЕН ПО ДОЧЕРИ
$resZhienSister = $service->determineKinship($testPersons[7], $testPersons[1], 'auto', $testPersons, $testMarriages);
assertCheck($resZhienSister['term_kk'] === 'Жиен', "Данияр (сын сестры) — Жиен");
assertCheck(str_contains($resZhienSister['term_ru'], 'по сестре'), "Для сына сестры перевод 'по сестре'");

$resZhienDaughter = $service->determineKinship($testPersons[9], $testPersons[1], 'auto', $testPersons, $testMarriages);
assertCheck($resZhienDaughter['term_kk'] === 'Жиен', "Самат (сын дочери) — Жиен");
assertCheck(str_contains($resZhienDaughter['term_ru'], 'по дочери'), "Для сына дочери перевод 'по дочери'");

// 9. ТЕСТ СВАТОВСТВА (ҚҰДА, ҚҰДАҒИ, ҚҰДАША)
$resKuda = $service->determineKinship($testPersons[301], $testPersons[1], 'auto', $testPersons, $testMarriages);
assertCheck($resKuda['term_kk'] === 'Құда', "Отец зятя — Құда");
assertCheck(str_contains($resKuda['term_ru'], 'Құда'), "term_ru содержит 'Құда'");

$resKudaghy = $service->determineKinship($testPersons[302], $testPersons[1], 'auto', $testPersons, $testMarriages);
assertCheck($resKudaghy['term_kk'] === 'Құдағи', "Мать зятя — Құдағи");

$resKudasha = $service->determineKinship($testPersons[303], $testPersons[1], 'auto', $testPersons, $testMarriages);
assertCheck($resKudasha['term_kk'] === 'Құдаша', "Сестра зятя — Құдаша");

// 10. ТЕСТ БӨЛЕ (дети сестер)
$resBole = $service->determineKinship($testPersons[402], $testPersons[1], 'auto', $testPersons, $testMarriages);
assertCheck($resBole['term_kk'] === 'Бөле', "Мурат для Жандоса — Бөле");
assertCheck($resBole['zhurt'] === 'Нағашы жұрты', "Бөле относится к Нағашы жұрты");

// 11. ПРОВЕРКА ОТСУТСТВИЯ ЗАГЛУШЕК СОРОДИЧ / СОПЛЕМЕННИК ВО ВСЕХ РАСЧЕТАХ
$allKin = $service->calculateAllKinships(1, $testPersons, $testMarriages);
$hasBadWords = false;
foreach ($allKin as $k) {
    if (str_contains(mb_strtolower($k['term_ru']), 'сородич') || str_contains(mb_strtolower($k['term_ru']), 'соплеменник')) {
        $hasBadWords = true;
        break;
    }
}
assertCheck(!$hasBadWords, "Слова 'сородич' и 'соплеменник' ПОЛНОСТЬЮ ОТСУТСТВУЮТ во всех расчетах");

// 12. ТЕСТ СУПРУГ(А): Жұбай / Әйелі НЕ попадает ни в один из трёх жұрт
$resSpouse = $service->determineKinship($testPersons[101], $testPersons[1], 'auto', $testPersons, $testMarriages);
assertCheck($resSpouse['code'] === 'wife', "Асила для Жандоса — Жұбайы (code = wife)");
assertCheck(empty($resSpouse['zhurt']), "Супруга НЕ попадает ни в один из трёх жұрт (zhurt пустой: '{$resSpouse['zhurt']}')");

// Обратно: муж для женщины — Күйеуі, zhurt пустой
$resHusband = $service->determineKinship($testPersons[1], $testPersons[101], 'auto', $testPersons, $testMarriages);
assertCheck($resHusband['code'] === 'husband', "Жандос для Асилы — Күйеуі (code = husband)");
assertCheck(empty($resHusband['zhurt']), "Муж НЕ попадает ни в один из трёх жұрт (zhurt пустой)");

// Дети пары — Өз жұрты для обоих родителей
$resChildFromMother = $service->determineKinship($testPersons[5], $testPersons[101], 'auto', $testPersons, $testMarriages);
assertCheck($resChildFromMother['zhurt'] === 'Өз жұрты', "Сын для матери относится к 'Өз жұрты'");

echo "\n-------------------------------------------------------\n";
echo "Результат: {$passed} из {$total} проверок успешно пройдено!\n";
if ($passed === $total) {
    echo "ВСЕ ТЕСТЫ РАСШИРЕННОГО РОДСТВА ПРОЙДЕНЫ [SUCCESS]\n";
} else {
    echo "ЕСТЬ ОШИБКИ: " . ($total - $passed) . " тестов провалено!\n";
    exit(1);
}
