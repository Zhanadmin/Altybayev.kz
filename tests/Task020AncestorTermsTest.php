<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\KinshipService;

$fails = 0;
function chk(bool $ok, string $m): void { global $fails; echo ($ok ? 'OK   ' : 'FAIL ') . $m . "\n"; if (!$ok) $fails++; }

// 1 я (M), мать 2, отец 3; отец отца 4, жена 5; отец отца отца 6, его жена 7;
// отец матери 8, мать матери 9; отец отца матери 10, жена 11
$P = [
    ['id'=>1,'gender'=>'M','father_id'=>3,'mother_id'=>2,'first_name'=>'Я','birth_year'=>1990],
    ['id'=>2,'gender'=>'F','father_id'=>8,'mother_id'=>9,'first_name'=>'Мать','birth_year'=>1965],
    ['id'=>3,'gender'=>'M','father_id'=>4,'mother_id'=>null,'first_name'=>'Отец','birth_year'=>1960],
    ['id'=>4,'gender'=>'M','father_id'=>6,'mother_id'=>null,'first_name'=>'ДедО','birth_year'=>1930],
    ['id'=>5,'gender'=>'F','first_name'=>'БабО','birth_year'=>1935],
    ['id'=>6,'gender'=>'M','first_name'=>'ПрадедО','birth_year'=>1900],
    ['id'=>7,'gender'=>'F','first_name'=>'ПрабабО','birth_year'=>1905],
    ['id'=>8,'gender'=>'M','father_id'=>10,'first_name'=>'ДедМ','birth_year'=>1940],
    ['id'=>9,'gender'=>'F','first_name'=>'БабМ','birth_year'=>1942],
    ['id'=>10,'gender'=>'M','first_name'=>'ПрадедМ','birth_year'=>1901],
    ['id'=>11,'gender'=>'F','first_name'=>'ПрабабМ','birth_year'=>1914],
];
$M = [
    ['husband_id'=>3,'wife_id'=>2], ['husband_id'=>4,'wife_id'=>5], ['husband_id'=>6,'wife_id'=>7],
    ['husband_id'=>8,'wife_id'=>9], ['husband_id'=>10,'wife_id'=>11],
];
$k = (new KinshipService())->calculateAllKinships(1, $P, $M);
chk($k[4]['term_kk'] === 'Ата', 'дед по отцу: ата');
chk($k[5]['term_kk'] === 'Әже', 'жена деда: әже');
chk($k[6]['term_kk'] === 'Үлкен ата', 'прадед по отцу: үлкен ата');
chk($k[7]['term_kk'] === 'Үлкен әже', 'жена прадеда: үлкен әже');
chk($k[8]['term_kk'] === 'Нағашы ата', 'отец матери: нағашы ата');
chk($k[9]['term_kk'] === 'Нағашы әже', 'мать матери: нағашы әже');
chk($k[10]['term_kk'] === 'Нағашы үлкен ата', 'отец деда по матери: нағашы үлкен ата');
chk($k[11]['term_kk'] === 'Нағашы үлкен әже', 'жена: нағашы үлкен әже');
echo $fails ? "FAILED\n" : "ALL OK\n";
exit($fails ? 1 : 0);
