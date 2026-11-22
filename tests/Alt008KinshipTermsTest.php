<?php
declare(strict_types=1);
// ALT-008: точные термины родства вместо общего «Туысқан»
require_once __DIR__ . '/../app/Config/KinshipDictionary.php';
require_once __DIR__ . '/../app/Services/KinshipService.php';

use App\Services\KinshipService;

$fails = 0;
function chk(bool $ok, string $m): void { global $fails; echo ($ok ? 'OK   ' : 'FAIL ') . $m . "\n"; if (!$ok) $fails++; }

// Я=1 (M,1990); отец 2 (1960), мать 3 (1962); дед 4 (1930)
// брат отца 5 (1955, жена 6 ; её отец 7, мать 8, брат 9 (1958), сестра 10 (1975)); сестра отца 11 (1958, муж 12)
// дочь сестры отца 13 (1985, муж 14); мой старший брат 15 (1985, жена 16; отец жены 17, мать 18, младшая сестра 19 (1995), младший брат 20 (1996), старший брат 21 (1975))
// брат матери 22 (1965); сестра матери 23 (1968), муж 24; сын сестры матери 25 (1992) ; двоюродный (отец отца деда) 26...
$P = [
    ['id'=>1,'gender'=>'M','father_id'=>2,'mother_id'=>3,'birth_year'=>1990],
    ['id'=>2,'gender'=>'M','father_id'=>4,'birth_year'=>1960],
    ['id'=>3,'gender'=>'F','father_id'=>30,'mother_id'=>31,'birth_year'=>1962],
    ['id'=>4,'gender'=>'M','birth_year'=>1930],
    ['id'=>5,'gender'=>'M','father_id'=>4,'birth_year'=>1955],
    ['id'=>6,'gender'=>'F','father_id'=>7,'mother_id'=>8,'birth_year'=>1957],
    ['id'=>7,'gender'=>'M','birth_year'=>1930],
    ['id'=>8,'gender'=>'F','birth_year'=>1932],
    ['id'=>9,'gender'=>'M','father_id'=>7,'mother_id'=>8,'birth_year'=>1958],
    ['id'=>10,'gender'=>'F','father_id'=>7,'mother_id'=>8,'birth_year'=>1975],
    ['id'=>11,'gender'=>'F','father_id'=>4,'birth_year'=>1958],
    ['id'=>12,'gender'=>'M','birth_year'=>1955],
    // дочь тёти МЛАДШЕ меня (1995) → муж 14 — күйеу бала; дочь тёти СТАРШЕ (1982) → муж 81 — жезде
    ['id'=>13,'gender'=>'F','father_id'=>12,'mother_id'=>11,'birth_year'=>1995],
    ['id'=>14,'gender'=>'M','birth_year'=>1993],
    ['id'=>80,'gender'=>'F','father_id'=>12,'mother_id'=>11,'birth_year'=>1982],
    ['id'=>81,'gender'=>'M','birth_year'=>1980],
    // дочери сестры матери (бөле): 82 старше (1985, муж 83), 84 младше (1996, муж 85)
    ['id'=>82,'gender'=>'F','father_id'=>24,'mother_id'=>23,'birth_year'=>1985],
    ['id'=>83,'gender'=>'M','birth_year'=>1984],
    ['id'=>84,'gender'=>'F','father_id'=>24,'mother_id'=>23,'birth_year'=>1996],
    ['id'=>85,'gender'=>'M','birth_year'=>1994],
    // сестра (старшая) 50, её сын 51 (жиен), жена жиена 53, её родители 54/55, ребёнок жиена 56 (жиеншар)
    ['id'=>50,'gender'=>'F','father_id'=>2,'mother_id'=>3,'birth_year'=>1988],
    ['id'=>52,'gender'=>'M','birth_year'=>1985],
    ['id'=>51,'gender'=>'M','father_id'=>52,'mother_id'=>50,'birth_year'=>2010],
    ['id'=>53,'gender'=>'F','father_id'=>54,'mother_id'=>55,'birth_year'=>2012],
    ['id'=>54,'gender'=>'M','birth_year'=>1985],
    ['id'=>55,'gender'=>'F','birth_year'=>1987],
    ['id'=>56,'gender'=>'M','father_id'=>51,'mother_id'=>53,'birth_year'=>2035],
    // жена Я (60), родня жены: сестра 61 (муж 62), отец 64, тётя 63 (муж 68), дочь тёти 69 (муж 70)
    ['id'=>60,'gender'=>'F','father_id'=>64,'mother_id'=>65,'birth_year'=>1992],
    ['id'=>61,'gender'=>'F','father_id'=>64,'mother_id'=>65,'birth_year'=>1995],
    ['id'=>62,'gender'=>'M','birth_year'=>1993],
    ['id'=>64,'gender'=>'M','father_id'=>66,'mother_id'=>67,'birth_year'=>1965],
    ['id'=>65,'gender'=>'F','birth_year'=>1967],
    ['id'=>66,'gender'=>'M','birth_year'=>1938],
    ['id'=>67,'gender'=>'F','birth_year'=>1940],
    ['id'=>63,'gender'=>'F','father_id'=>66,'mother_id'=>67,'birth_year'=>1968],
    ['id'=>68,'gender'=>'M','birth_year'=>1966],
    ['id'=>69,'gender'=>'F','father_id'=>68,'mother_id'=>63,'birth_year'=>1990],
    ['id'=>70,'gender'=>'M','birth_year'=>1989],
    ['id'=>15,'gender'=>'M','father_id'=>2,'mother_id'=>3,'birth_year'=>1985],
    ['id'=>17,'gender'=>'M','birth_year'=>1960],
    ['id'=>18,'gender'=>'F','birth_year'=>1962],
    ['id'=>19,'gender'=>'F','father_id'=>17,'mother_id'=>18,'birth_year'=>1995],
    ['id'=>20,'gender'=>'M','father_id'=>17,'mother_id'=>18,'birth_year'=>1996],
    ['id'=>21,'gender'=>'M','father_id'=>17,'mother_id'=>18,'birth_year'=>1975],
    ['id'=>16,'gender'=>'F','father_id'=>17,'mother_id'=>18,'birth_year'=>1987],
    ['id'=>30,'gender'=>'M','birth_year'=>1935],
    ['id'=>31,'gender'=>'F','birth_year'=>1938],
    ['id'=>22,'gender'=>'M','father_id'=>30,'mother_id'=>31,'birth_year'=>1965],
    ['id'=>23,'gender'=>'F','father_id'=>30,'mother_id'=>31,'birth_year'=>1968],
    ['id'=>24,'gender'=>'M','birth_year'=>1966],
    // дед по деду отца (баба 40) и его внуки: двоюродный дядя отца и его сын
    ['id'=>40,'gender'=>'M','birth_year'=>1900],
    ['id'=>41,'gender'=>'M','father_id'=>40,'birth_year'=>1928],
    ['id'=>42,'gender'=>'M','father_id'=>41,'birth_year'=>1955],
    ['id'=>43,'gender'=>'M','father_id'=>42,'birth_year'=>1988],
];
// дед 4 — сын 40
foreach ($P as &$p) { if ($p['id'] === 4) { $p['father_id'] = 40; } }
unset($p);
$M = [
    ['husband_id'=>5,'wife_id'=>6], ['husband_id'=>12,'wife_id'=>11], ['husband_id'=>14,'wife_id'=>13],
    ['husband_id'=>15,'wife_id'=>16], ['husband_id'=>7,'wife_id'=>8], ['husband_id'=>17,'wife_id'=>18],
    ['husband_id'=>24,'wife_id'=>23],
    ['husband_id'=>81,'wife_id'=>80], ['husband_id'=>83,'wife_id'=>82], ['husband_id'=>85,'wife_id'=>84],
    ['husband_id'=>52,'wife_id'=>50], ['husband_id'=>51,'wife_id'=>53],
    ['husband_id'=>1,'wife_id'=>60], ['husband_id'=>62,'wife_id'=>61], ['husband_id'=>68,'wife_id'=>63], ['husband_id'=>70,'wife_id'=>69],
];
$svc = new KinshipService();
$k = $svc->calculateAllKinships(1, $P, $M);
$t = fn(int $id): string => $k[$id]['term_kk'] ?? '?';
$ru = fn(int $id): string => $k[$id]['term_ru'] ?? '?';

chk($t(12) === 'Жезде', 'муж сестры отца: жезде (получено ' . $t(12) . ')');
chk($t(14) === 'Күйеу бала', 'муж дочери тёти: күйеу бала (получено ' . $t(14) . ')');
chk($t(6) === 'Жеңге', 'жена брата отца: жеңге (получено ' . $t(6) . ')');
chk($t(16) === 'Жеңге', 'жена старшего брата: жеңге');
chk($t(17) === 'Құда', 'отец жены брата: құда (получено ' . $t(17) . ')');
chk($t(18) === 'Құдағи', 'мать жены брата: құдағи (получено ' . $t(18) . ')');
chk($t(21) === 'Құда', 'старший брат жены брата: құда (получено ' . $t(21) . ')');
chk($t(19) === 'Құдаша', 'младшая сестра жены брата: құдаша (получено ' . $t(19) . ')');
chk($t(20) === 'Құда бала', 'младший брат жены брата: құда бала (получено ' . $t(20) . ')');
chk($t(24) === 'Нағашы жезде', 'муж сестры матери: нағашы жезде (получено ' . $t(24) . ')');
chk($ru(17) !== 'Родственник' && $ru(12) !== 'Родственник', 'русский перевод не «Родственник»');
chk($t(41) !== 'Туысқан' && $t(42) !== 'Туысқан' && $t(43) !== 'Туысқан', 'дальняя кровная родня не «Туысқан»: ' . $t(41) . ' / ' . $t(42) . ' / ' . $t(43));
foreach ($k as $id => $kin) {
    if ($id === 1) continue;
    chk(($kin['term_kk'] ?? '') !== 'Туысқан', "id=$id: " . ($kin['term_kk'] ?? '') . ' — ' . ($kin['term_ru'] ?? ''));
}

chk($t(81) === 'Жезде', 'муж дочери тёти, которая старше: жезде (получено ' . $t(81) . ')');
chk($t(83) === 'Жезде', 'муж бөле, которая старше: жезде (получено ' . $t(83) . ')');
chk($t(85) === 'Күйеу бала', 'муж бөле, которая младше: күйеу бала (получено ' . $t(85) . ')');
chk($t(51) === 'Жиен', 'сын сестры: жиен (получено ' . $t(51) . ')');
chk($t(53) === 'Келін', 'жена жиена: келін (получено ' . $t(53) . ')');
chk($t(56) === 'Жиеншар', 'ребёнок жиена: жиеншар (получено ' . $t(56) . ')');
chk($t(54) === 'Құда', 'отец келін (жены жиена): құда (получено ' . $t(54) . ')');
chk($t(55) === 'Құдағи', 'мать келін: құдағи (получено ' . $t(55) . ')');
chk($t(62) === 'Бажа', 'муж сестры жены: бажа (получено ' . $t(62) . ')');
chk($t(68) === 'Бажа', 'муж тёти жены: бажа (получено ' . $t(68) . ')');
chk($t(70) === 'Бажа', 'муж двоюродной сестры жены: бажа (получено ' . $t(70) . ')');
chk($ru(62) !== 'Родственник', 'бажа переведён на русский');
chk(mb_strpos($t(24), ' Ж') === false, 'регистр составных терминов: ' . $t(24));

// Женщина-основа: муж сестры отца для женщины, жена старшего брата
$P2 = $P; $P2[0]['gender'] = 'F';
$M2 = array_values(array_filter($M, fn($m) => $m['husband_id'] !== 1));
$k2 = $svc->calculateAllKinships(1, $P2, $M2);
chk($k2[12]['term_kk'] === 'Жезде', 'для женщины: муж сестры отца — жезде');
chk($k2[14]['term_kk'] === 'Күйеу бала', 'для женщины: муж дочери тёти — күйеу бала');

echo $fails ? "FAILED\n" : "ALL OK\n";
exit($fails ? 1 : 0);
