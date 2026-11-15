<?php

declare(strict_types=1);

/**
 * TASK-013: Үш жұрт по ПУТИ родства.
 *  - замужняя женщина: Төркіні (родная семья) / Өз жұрты (семья мужа) / Нағашы жұрты, Қайын жұрта нет;
 *  - незамужняя: Төркіні / Өз жұрты (пусто, «появится после замужества») / Нағашы жұрты;
 *  - мужчина: Өз / Қайын / Нағашы;
 *  - замужняя дочь рода в родной семье — след «ұзатылған»;
 *  - двойная связь — ближайший путь + пометка второй.
 *
 * Запуск: C:\PHP\php.exe tests\Task013UshZhurtPathTest.php
 * Часть 1 — синтетическая семья (без БД). Часть 2 — реальные данные (если БД доступна).
 */

require_once __DIR__ . '/../app/Config/KinshipDictionary.php';
require_once __DIR__ . '/../app/Services/KinshipService.php';

use App\Services\KinshipService;

$passed = 0;
$total = 0;

function t13(bool $cond, string $label): void
{
    global $passed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo "  [OK] {$label}\n";
    } else {
        echo "  [FAIL] {$label}\n";
    }
}

function t13ids(array $data, string $key): array
{
    $g = $data['groups'][$key] ?? null;
    return $g ? array_map('intval', array_column($g['items'], 'id')) : [];
}

function t13item(array $data, string $key, int $id): ?array
{
    foreach (($data['groups'][$key]['items'] ?? []) as $it) {
        if ((int)$it['id'] === $id) return $it;
    }
    return null;
}

$P = function (int $id, string $name, string $g, int $y, int $f = 0, int $m = 0): array {
    return ['id' => $id, 'first_name' => $name, 'last_name' => '', 'gender' => $g, 'birth_year' => $y,
        'father_id' => $f ?: null, 'mother_id' => $m ?: null, 'clan_ru' => 'Керей'];
};

// --- Синтетическая семья ---
$persons = [
    $P(1, 'Ғабит', 'M', 1950),
    $P(2, 'Менслу', 'F', 1955, 40, 41),
    $P(3, 'Жандос', 'M', 1979, 1, 2),
    $P(4, 'Бауыр', 'M', 1982, 1, 2),
    $P(5, 'Әпке-сіңлі', 'F', 1985, 1, 2),
    $P(10, 'Кубен', 'M', 1950),
    $P(11, 'Кубен апа', 'F', 1952),
    $P(12, 'Асила', 'F', 1983, 10, 11),
    $P(13, 'Асиланың інісі', 'M', 1986, 10, 11),
    $P(20, 'Жандостың ұлы', 'M', 2008, 3, 12),
    $P(30, 'Бауырдың әйелі', 'F', 1984, 31, 32),
    $P(31, 'Келіннің әкесі', 'M', 1955),
    $P(32, 'Келіннің анасы', 'F', 1957),
    $P(33, 'Келіннің ағасы', 'M', 1980, 31, 32),
    $P(40, 'Нағашы ата', 'M', 1925),
    $P(41, 'Нағашы әже', 'F', 1928),
    $P(42, 'Нағашы аға', 'M', 1958, 40, 41),
    $P(50, 'Жезде', 'M', 1983, 51, 0),
    $P(51, 'Жезденің әкесі', 'M', 1955),
    $P(60, 'Бойжеткен', 'F', 2010, 4, 30),
    $P(71, 'Нағашының ұлы', 'M', 1990, 42, 0),
    $P(72, 'Бауырдың қызы', 'F', 2005, 4, 30),
];
$marriages = [
    ['husband_id' => 1, 'wife_id' => 2],
    ['husband_id' => 10, 'wife_id' => 11],
    ['husband_id' => 3, 'wife_id' => 12],
    ['husband_id' => 4, 'wife_id' => 30],
    ['husband_id' => 31, 'wife_id' => 32],
    ['husband_id' => 40, 'wife_id' => 41],
    ['husband_id' => 50, 'wife_id' => 5],
    ['husband_id' => 71, 'wife_id' => 72],
];

$ks = new KinshipService();

echo "1. Замужняя женщина (Асила):\n";
$a = $ks->getUshZhurtTables(12, $persons, $marriages);
t13(($a['order'] ?? []) === ['torkin', 'oz', 'nagashy'], 'Порядок таблиц: Төркіні, Өз жұрты, Нағашы жұрты');
t13(!isset($a['groups']['kaiyn']), 'Таблицы Қайын жұрты у замужней нет');
t13($a['groups']['torkin']['title_kk'] === 'Төркіні', 'Таблица называется «Төркіні»');
$tor = t13ids($a, 'torkin');
$oz = t13ids($a, 'oz');
t13(in_array(10, $tor, true) && in_array(11, $tor, true) && in_array(13, $tor, true), 'Кубеновы (отец, мать, брат) — в Төркіні');
t13(in_array(1, $oz, true) && in_array(2, $oz, true), 'Ғабит и Менслу (свёкор, свекровь) — в Өз жұрты');
t13(in_array(4, $oz, true), 'Брат мужа — в Өз жұрты');
t13(!array_intersect([1, 2, 4, 5, 30], $tor), 'Никого из семьи мужа нет в Төркіні');
t13(!in_array(3, array_merge($tor, $oz, t13ids($a, 'nagashy')), true), 'Муж — вне таблиц');
t13(!in_array(20, array_merge($tor, $oz), true), 'Свой ребёнок — вне таблиц');
t13(!in_array(51, array_merge($tor, $oz, t13ids($a, 'nagashy')), true), 'Семья мужа золовки (51) не попадает никуда');
$sis = t13item($a, 'oz', 5);
t13($sis !== null && (int)($sis['uzatylgan']['husband_id'] ?? 0) === 50, 'Сестра мужа замужем — в Өз жұрты следом «ұзатылған»');

echo "\n2. Жена брата Жандоса:\n";
$k = $ks->getUshZhurtTables(30, $persons, $marriages);
$ktor = t13ids($k, 'torkin');
$koz = t13ids($k, 'oz');
t13(in_array(31, $ktor, true) && in_array(32, $ktor, true) && in_array(33, $ktor, true), 'Её родители и брат — в Төркіні');
t13(in_array(3, $koz, true) && in_array(1, $koz, true) && in_array(2, $koz, true), 'Братья мужа (Жандос) и его родители — в Өз жұрты');
t13(!in_array(3, $ktor, true) && !in_array(5, $ktor, true), 'Братья и сёстры мужа — НЕ в Төркіні');
t13(in_array(12, $koz, true), 'Асила (абысын) — в Өз жұрты');
t13(!in_array(10, array_merge($ktor, $koz), true), 'Родители абысын (Кубеновы) — не в её таблицах');

echo "\n3. Мужчина (Жандос):\n";
$z = $ks->getUshZhurtTables(3, $persons, $marriages);
t13(($z['order'] ?? []) === ['oz', 'kaiyn', 'nagashy'], 'Порядок таблиц: Өз, Қайын, Нағашы');
$zoz = t13ids($z, 'oz');
t13(in_array(1, $zoz, true) && in_array(2, $zoz, true) && in_array(4, $zoz, true) && in_array(5, $zoz, true), 'Родители, брат, сестра — в Өз жұрты');
t13(in_array(30, $zoz, true), 'Жена брата (келін) — в Өз жұрты');
$zk = t13ids($z, 'kaiyn');
t13(in_array(10, $zk, true) && in_array(11, $zk, true) && in_array(13, $zk, true), 'Семья жены — в Қайын жұрты');
$zn = t13ids($z, 'nagashy');
t13(in_array(40, $zn, true) && in_array(41, $zn, true) && in_array(42, $zn, true), 'Родня матери — в Нағашы жұрты');
t13(!in_array(51, array_merge($zoz, $zk, $zn), true), 'Семья жезде (51) — не в таблицах');
$zs = t13item($z, 'oz', 5);
t13($zs !== null && (int)($zs['uzatylgan']['husband_id'] ?? 0) === 50, 'Замужняя сестра — след «ұзатылған»');
t13(t13item($z, 'oz', 30) !== null && (t13item($z, 'oz', 30)['uzatylgan'] ?? null) === null,'Келін — не след, полноправно в Өз жұрты');
$dbl = t13item($z, 'oz', 71);
t13($dbl !== null && ($dbl['zhurt_second_key'] ?? '') === 'nagashy', 'Двойная связь (сын нағашы аға и муж племянницы): ближайший путь Өз, вторая — Нағашы');

echo "\n4. Незамужняя девушка:\n";
$g = $ks->getUshZhurtTables(60, $persons, $marriages);
t13(($g['order'] ?? []) === ['torkin', 'oz', 'nagashy'], 'Порядок: Төркіні, Өз жұрты, Нағашы');
t13(($g['groups']['oz']['total_count'] ?? -1) === 0, 'Өз жұрты пустая');
t13(($g['groups']['oz']['empty_ru'] ?? '') === 'Появится после замужества', 'Пояснение «Появится после замужества»');
$gt = t13ids($g, 'torkin');
t13(in_array(1, $gt, true) && in_array(3, $gt, true), 'Дед по отцу и дядя — в Төркіні');
$gn = t13ids($g, 'nagashy');
t13(in_array(31, $gn, true) && in_array(33, $gn, true), 'Родня матери — в Нағашы');

echo "\n5. Папки (getUshZhurtFamilies) для замужней:\n";
$f = $ks->getUshZhurtFamilies(12, $persons, $marriages);
t13(isset($f['torkin_zhurt'], $f['oz_zhurt'], $f['nagashy_zhurt']) && !isset($f['kaiyn_zhurt']), 'Есть torkin_zhurt, oz_zhurt, nagashy_zhurt; нет kaiyn_zhurt');

// --- Реальные данные (если доступна БД) ---
echo "\n6. Реальные данные:\n";
$real = null;
try {
    require_once __DIR__ . '/../app/bootstrap.php';
    $controller = new App\Controllers\TreeController();
    $real = $controller->getDataset();
} catch (\Throwable $e) {
    $real = null;
}
if (!$real || empty($real['persons'])) {
    echo "  [SKIP] БД недоступна — проверка на реальных данных пропущена\n";
} else {
    $byName = function (string $first, ?string $last = null) use ($real): array {
        $out = [];
        foreach ($real['persons'] as $p) {
            if (mb_stripos((string)$p['first_name'], $first) === 0 && ($last === null || mb_stripos((string)$p['last_name'], $last) !== false)) {
                $out[] = (int)$p['id'];
            }
        }
        return $out;
    };
    $asila = $byName('Асил')[0] ?? 0;
    t13($asila > 0, "Найдена Асила (id {$asila})");
    if ($asila) {
        $ra = $ks->getUshZhurtTables($asila, $real['persons'], $real['marriages']);
        t13(!isset($ra['groups']['kaiyn']), 'У Асилы нет Қайын жұрты');
        $raTor = $ra['groups']['torkin']['items'] ?? [];
        $raOz = $ra['groups']['oz']['items'] ?? [];
        $torLast = array_map(fn($i) => (string)$i['last_name'], $raTor);
        $ozIds = array_map(fn($i) => (int)$i['id'], $raOz);
        $torIds = array_map(fn($i) => (int)$i['id'], $raTor);
        t13((bool)array_filter($torLast, fn($l) => mb_stripos($l, 'Кубен') !== false), 'Кубеновы — в Төркіні');
        foreach (['Ғабит', 'Менслу'] as $nm) {
            $id = $byName($nm)[0] ?? 0;
            t13($id && in_array($id, $ozIds, true) && !in_array($id, $torIds, true), "{$nm} — в Өз жұрты, не в Төркіні");
        }
        $zhandos = 6;
        $zh = $real['persons'][array_search($zhandos, array_map('intval', array_column($real['persons'], 'id')), true)] ?? null;
        $brothers = [];
        if ($zh) {
            foreach ($real['persons'] as $p) {
                if ((int)$p['id'] !== $zhandos && ($p['gender'] ?? '') === 'M' && !empty($zh['father_id']) && (int)$p['father_id'] === (int)$zh['father_id']) {
                    $brothers[] = (int)$p['id'];
                }
            }
        }
        t13($brothers === [] || !array_intersect($brothers, $torIds), 'Братья Жандоса — не в Төркіні Асилы');
        t13($brothers === [] || !array_diff($brothers, $ozIds), 'Братья Жандоса — в Өз жұрты Асилы');

        // Жена брата Жандоса
        foreach ($brothers as $bId) {
            foreach ($real['marriages'] as $m) {
                if ((int)$m['husband_id'] !== $bId) continue;
                $wId = (int)$m['wife_id'];
                $rw = $ks->getUshZhurtTables($wId, $real['persons'], $real['marriages']);
                $wTor = array_map(fn($i) => (int)$i['id'], $rw['groups']['torkin']['items'] ?? []);
                $wOz = array_map(fn($i) => (int)$i['id'], $rw['groups']['oz']['items'] ?? []);
                $husbBrothers = array_values(array_diff(array_merge($brothers, [$zhandos]), [$bId]));
                t13(!array_intersect($husbBrothers, $wTor), "Жена брата (id {$wId}): братья мужа не в Төркіні");
                t13(!array_diff($husbBrothers, $wOz), "Жена брата (id {$wId}): братья мужа в Өз жұрты");
            }
        }
    }
}

echo "\n-------------------------------------------------------\n";
echo "Результат TASK-013: {$passed} из {$total}\n";
echo $passed === $total ? "SUCCESS\n" : "FAIL\n";
exit($passed === $total ? 0 : 1);
