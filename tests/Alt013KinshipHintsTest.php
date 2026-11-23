<?php
declare(strict_types=1);
// ALT-013: термины родства на es/en и подсказка-расшифровка для всех терминов словаря (kk/ru/en/es)
require_once __DIR__ . '/../app/Config/KinshipDictionary.php';
require_once __DIR__ . '/../app/Config/KinshipGlossary.php';

use App\Config\KinshipDictionary;
use App\Config\KinshipGlossary;

$fails = 0;
function chk(bool $ok, string $m): void { global $fails; echo ($ok ? 'OK   ' : 'FAIL ') . $m . "\n"; if (!$ok) $fails++; }

// 1) Все термины словаря: есть название и пояснение на всех четырёх языках
foreach (KinshipDictionary::getTerms() as $code => $t) {
    $kk = (string)($t['kk'] ?? '');
    $ru = (string)($t['ru'] ?? '');
    chk(KinshipGlossary::label($kk, $ru, 'kk') !== null, "$code: kk-термин");
    foreach (['en', 'es'] as $l) {
        $lab = KinshipGlossary::label($kk, $ru, $l);
        chk($lab !== null && $lab !== '', "$code: термин $l (" . ($lab ?? 'нет') . ')');
    }
    foreach (['kk', 'ru', 'en', 'es'] as $l) {
        $h = KinshipGlossary::hint($kk, $ru, $l);
        chk($h !== null && $h !== '', "$code: подсказка $l");
    }
    chk(KinshipGlossary::conceptId($kk, $ru) !== null, "$code: опознан ($kk / $ru)");
}

// 2) Все понятия глоссария заполнены на всех языках
foreach (KinshipGlossary::concepts() as $id => $c) {
    chk(!empty($c['en'][0]) && !empty($c['es'][0]), "понятие $id: en/es название");
    foreach (['kk', 'ru', 'en', 'es'] as $l) {
        chk(!empty($c['hint'][$l]), "понятие $id: подсказка $l");
    }
}

// 3) Конкретные испанские термины из ТЗ
$cases = [
    ['Бажа', 'Бажа (муж сестры жены / свояк)', 'es', 'Concuñado'],
    ['Құда', 'Құда (сват)', 'es', 'Consuegro'],
    ['Құдағи', 'Құдағи (сватья)', 'es', 'Consuegra'],
    ['Келін', 'Келін (сноха / невестка)', 'es', 'Nuera'],
    ['Күйеу бала', 'Күйеу бала (зять)', 'es', 'Yerno'],
    ['Қайын ата', 'Тесть (Қайын ата)', 'es', 'Suegro'],
    ['Қайын ене', 'Свекровь (Қайын ене)', 'es', 'Suegra'],
    ['Бөле', 'Бөле (двоюродный брат/сестра по матери)', 'es', 'Primo/a por parte de madre'],
    ['Жиен', 'Жиен (племянница по сестре)', 'es', 'Sobrina'],
    ['Баба (Үлкен ата)', 'Прадедушка', 'es', 'Bisabuelo'],
    ['Әпке', 'Двоюродная сестра по отцу', 'es', 'Prima por parte de padre'],
    ['Аға', 'Аға (дядя по отцу)', 'es', 'Tío paterno'],
    ['Бажа', 'Бажа (муж сестры жены / свояк)', 'en', 'Co-brother-in-law'],
];
foreach ($cases as [$kk, $ru, $l, $must]) {
    $lab = (string)KinshipGlossary::label($kk, $ru, $l);
    chk(str_starts_with($lab, $must), "$kk → [$l] «$lab» начинается с «$must»");
}

// 4) Примеры из ТЗ в подсказках
chk(mb_strpos((string)KinshipGlossary::hint('Бөле', 'Бөле (двоюродный брат/сестра по матери)', 'ru'), 'дети двух сестёр') !== false, 'бөле ru: дети двух сестёр');
chk(mb_strpos((string)KinshipGlossary::hint('Бажа', 'Бажа (муж сестры жены / свояк)', 'ru'), 'мужья сестёр жены') !== false, 'бажа ru: мужья сестёр жены');
chk(mb_strpos((string)KinshipGlossary::hint('Құда', 'Құда (сват)', 'es'), 'consuegros') !== false, 'құда es: по-испански');

// 5) annotate добавляет поля
$a = KinshipGlossary::annotate(['term_kk' => 'Бөле', 'term_ru' => 'Бөле (двоюродный брат/сестра по матери)']);
chk($a['term_es'] !== '' && $a['term_en'] !== '' && $a['hint_es'] !== '' && $a['hint_kk'] !== '', 'annotate: поля term_*/hint_*');

// 6) Термины, которые строит KinshipService на тестовых данных (если сервис доступен)
require_once __DIR__ . '/../app/Services/KinshipService.php';
$P = [
    ['id'=>1,'gender'=>'M','father_id'=>2,'mother_id'=>3,'birth_year'=>1990],
    ['id'=>2,'gender'=>'M','father_id'=>4,'birth_year'=>1960],
    ['id'=>3,'gender'=>'F','father_id'=>30,'mother_id'=>31,'birth_year'=>1962],
    ['id'=>4,'gender'=>'M','birth_year'=>1930],
    ['id'=>5,'gender'=>'M','father_id'=>4,'birth_year'=>1955],
    ['id'=>11,'gender'=>'F','father_id'=>4,'birth_year'=>1958],
    ['id'=>12,'gender'=>'M','birth_year'=>1955],
    ['id'=>30,'gender'=>'M','birth_year'=>1935],
    ['id'=>31,'gender'=>'F','birth_year'=>1938],
    ['id'=>22,'gender'=>'M','father_id'=>30,'mother_id'=>31,'birth_year'=>1965],
    ['id'=>23,'gender'=>'F','father_id'=>30,'mother_id'=>31,'birth_year'=>1968],
    ['id'=>24,'gender'=>'M','birth_year'=>1966],
    ['id'=>82,'gender'=>'F','father_id'=>24,'mother_id'=>23,'birth_year'=>1985],
    ['id'=>50,'gender'=>'F','father_id'=>2,'mother_id'=>3,'birth_year'=>1988],
    ['id'=>52,'gender'=>'M','birth_year'=>1985],
    ['id'=>51,'gender'=>'M','father_id'=>52,'mother_id'=>50,'birth_year'=>2010],
    ['id'=>60,'gender'=>'F','father_id'=>64,'mother_id'=>65,'birth_year'=>1992],
    ['id'=>61,'gender'=>'F','father_id'=>64,'mother_id'=>65,'birth_year'=>1995],
    ['id'=>62,'gender'=>'M','birth_year'=>1993],
    ['id'=>64,'gender'=>'M','birth_year'=>1965],
    ['id'=>65,'gender'=>'F','birth_year'=>1967],
];
$M = [
    ['husband_id'=>5,'wife_id'=>6], ['husband_id'=>12,'wife_id'=>11], ['husband_id'=>24,'wife_id'=>23],
    ['husband_id'=>52,'wife_id'=>50], ['husband_id'=>1,'wife_id'=>60], ['husband_id'=>62,'wife_id'=>61], ['husband_id'=>64,'wife_id'=>65],
];
$svc = new App\Services\KinshipService();
$k = $svc->calculateAllKinships(1, $P, $M);
foreach ($k as $id => $kin) {
    chk(($kin['term_es'] ?? '') !== '' && ($kin['term_en'] ?? '') !== '', "сервис id=$id «" . ($kin['term_kk'] ?? '') . "» → es: " . ($kin['term_es'] ?? '?'));
    foreach (['kk', 'ru', 'en', 'es'] as $l) {
        chk(($kin['hint_' . $l] ?? '') !== '', "сервис id=$id: подсказка $l");
    }
}

echo $fails ? "FAILED ($fails)\n" : "ALL OK\n";
exit($fails ? 1 : 0);
