<?php

declare(strict_types=1);

/**
 * TASK-032: права обычного родственника (viewer) на карточки и браки.
 * Самодостаточный: SQLite в памяти, а при его отсутствии — временные таблицы тестовой БД.
 */

require_once __DIR__ . '/../app/Services/PersonAccessService.php';
// Auth нужен только при вызове без $user — в тесте всегда передаём пользователя явно.
if (!class_exists('App\Core\Auth')) {
    eval('namespace App\Core; class Auth { public static function user(): ?array { return null; } }');
}

use App\Services\PersonAccessService;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    // На этой машине pdo_sqlite нет. Работаем на тестовой БД проекта во ВРЕМЕННЫХ таблицах:
    // они живут только в этом соединении и не затрагивают реальные persons/marriages.
    require_once __DIR__ . '/../app/bootstrap.php';
    $db = \App\Core\Database::getConnection();
    $db->exec('DROP TEMPORARY TABLE IF EXISTS `marriages`');
    $db->exec('DROP TEMPORARY TABLE IF EXISTS `persons`');
    $db->exec('CREATE TEMPORARY TABLE `persons` (`id` INT PRIMARY KEY, `father_id` INT NULL, `mother_id` INT NULL, `is_deleted` INT DEFAULT 0, `created_by` INT NULL) ENGINE=InnoDB');
    $db->exec('CREATE TEMPORARY TABLE `marriages` (`id` INT PRIMARY KEY, `husband_id` INT NULL, `wife_id` INT NULL) ENGINE=InnoDB');
} else {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE persons (id INTEGER PRIMARY KEY, father_id INT, mother_id INT, is_deleted INT DEFAULT 0, created_by INT)');
    $db->exec('CREATE TABLE marriages (id INTEGER PRIMARY KEY, husband_id INT, wife_id INT)');
}

// 1 дед, 2 бабушка | 3 отец(я=viewer user 10), 4 жена | 5 сын, 6 дочь | 7 чужой брат отца | 8 добавлен мной
$db->exec("INSERT INTO persons (id, father_id, mother_id, created_by) VALUES
  (1,NULL,NULL,NULL),(2,NULL,NULL,NULL),(3,1,2,NULL),(4,NULL,NULL,NULL),(5,3,4,NULL),(6,3,4,NULL),(7,1,2,NULL),(8,NULL,NULL,10)");
$db->exec('INSERT INTO marriages (id, husband_id, wife_id) VALUES (1,1,2),(2,3,4),(3,7,4)');

$svc = new PersonAccessService($db);
$me = ['id' => 10, 'role' => 'viewer', 'person_id' => 3];
$editor = ['id' => 11, 'role' => 'editor', 'person_id' => null];
$guest = ['id' => 12, 'role' => 'viewer', 'person_id' => null];

$fail = 0;
$n = 0;
function check(string $name, bool $cond): void
{
    global $fail, $n;
    $n++;
    if (!$cond) {
        $fail++;
        echo "FAIL: $name\n";
    }
}

check('сам себя', $svc->canEditPerson(3, $me));
check('супруга', $svc->canEditPerson(4, $me));
check('сын', $svc->canEditPerson(5, $me));
check('дочь', $svc->canEditPerson(6, $me));
check('добавленный мной', $svc->canEditPerson(8, $me));
check('отец (чужой) нельзя', !$svc->canEditPerson(1, $me));
check('мать (чужая) нельзя', !$svc->canEditPerson(2, $me));
check('брат отца нельзя', !$svc->canEditPerson(7, $me));
check('без привязки — никого, кроме добавленных', !$svc->canEditPerson(3, $guest) && !$svc->canEditPerson(5, $guest));
check('несуществующий id', !$svc->canEditPerson(999, $me));
check('editor правит любого', $svc->canEditPerson(1, $editor) && $svc->canEditPerson(7, $editor));
check('без пользователя — нет', !$svc->canEditPerson(3, ['id' => 0, 'role' => 'viewer']));

check('мой брак правлю', $svc->canEditMarriage(2, $me));
check('брак родителей нельзя', !$svc->canEditMarriage(1, $me));
check('чужой брак нельзя', !$svc->canEditMarriage(3, $me));
check('editor правит любой брак', $svc->canEditMarriage(1, $editor));

$t3 = ['id' => 3, 'father_id' => 1, 'mother_id' => 2];
$t8 = ['id' => 8, 'father_id' => null, 'mother_id' => null];
$t7 = ['id' => 7, 'father_id' => 1, 'mother_id' => 2];
$t1 = ['id' => 1, 'father_id' => null, 'mother_id' => null];
check('добавить сына чужому — можно', $svc->canAddRelativeTo($t7, 'son', $me));
check('добавить супруга чужому — можно', $svc->canAddRelativeTo($t7, 'spouse', $me));
check('добавить отца в пустое место — можно', $svc->canAddRelativeTo($t1, 'father', $me));
check('заменить отца у чужого — нельзя', !$svc->canAddRelativeTo($t7, 'father', $me));
check('заменить мать у чужого — нельзя', !$svc->canAddRelativeTo($t7, 'mother', $me));
check('заменить отца у себя — можно', $svc->canAddRelativeTo($t3, 'father', $me));
check('неизвестный тип — нельзя', !$svc->canAddRelativeTo($t7, 'hack', $me));
check('editor заменяет отца', $svc->canAddRelativeTo($t7, 'father', $editor));

$circle = $svc->circleOf(3);
sort($circle);
check('круг = 3,4,5,6', $circle === [3, 4, 5, 6]);

echo $fail === 0 ? "OK: {$n}/{$n}\n" : "FAILED: {$fail} из {$n}\n";
exit($fail === 0 ? 0 : 1);
