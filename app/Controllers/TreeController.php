<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\KinshipService;
use PDO;

/**
 * Контроллер двух видов генеалогического древа:
 * 1. MyHeritage (жұмыс режимі, отец Жандоса привык)
 * 2. Шежіре-Орман / Лес (живые деревья, крона с живыми наверху, корень Керей внизу, цветы-девочки)
 */
class TreeController
{
    protected KinshipService $kinshipService;

    public function __construct()
    {
        $this->kinshipService = new KinshipService();
    }

    /**
     * Отображение главной страницы древа
     */
    public function index(Request $request): void
    {
        $user = Auth::user();
        $userPersonId = $user['person_id'] ?? null;

        // Если у пользователя нет жесткой привязки, по умолчанию ассоциируем с ключевой персоной (Жандос)
        if (!$userPersonId) {
            $userPersonId = 7; // Жандос Алтыбаев в демонстрационной структуре
        }

        $allTerms = $this->kinshipService->getAllTerms();

        Response::view('tree', [
            'user' => $user,
            'userPersonId' => $userPersonId,
            'kinshipTerms' => $allTerms,
        ]);
    }

    /**
     * API: Получение полного набора данных древа (персоны, связи, рода)
     */
    public function apiTreeData(Request $request): void
    {
        $db = Database::getInstance();
        $persons = [];
        $marriages = [];
        $clans = [];

        try {
            // Пробуем получить данные из БД
            $stmt = $db->query("SELECT * FROM `persons` WHERE `is_deleted` = 0 ORDER BY `id` ASC");
            $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmtM = $db->query("SELECT * FROM `marriages` ORDER BY `id` ASC");
            $marriages = $stmtM->fetchAll(PDO::FETCH_ASSOC);

            // Рода (если таблица уже создана миграцией)
            $stmtC = $db->query("SHOW TABLES LIKE 'clans'");
            if ($stmtC->rowCount() > 0) {
                $stmtClans = $db->query("SELECT * FROM `clans` ORDER BY `id` ASC");
                $clans = $stmtClans->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (\Throwable $e) {
            // В случае отсутствия записей или процесса настройки — используем эталонный набор
        }

        // Если в БД еще нет персон — отдаем богатый демонстрационный набор шежіре Керей -> Алтыбаевтар
        if (empty($persons)) {
            $demo = $this->getDemoDataset();
            $persons = $demo['persons'];
            $marriages = $demo['marriages'];
            $clans = $demo['clans'];
        }

        Response::json([
            'status' => 'success',
            'clans' => $clans,
            'persons' => $persons,
            'marriages' => $marriages,
            'user_person_id' => Auth::user()['person_id'] ?? 7,
        ]);
    }

    /**
     * API: Расчет степени родства между двумя персонами
     */
    public function apiKinship(Request $request): void
    {
        $targetId = (int)$request->get('target_id', 0);
        $baseId = (int)$request->get('base_id', 0);

        if (!$targetId || !$baseId) {
            Response::json(['error' => 'target_id and base_id are required'], 400);
            return;
        }

        $allData = $this->getDemoDataset()['persons'];
        $personsById = array_column($allData, null, 'id');

        $target = $personsById[$targetId] ?? null;
        $base = $personsById[$baseId] ?? null;

        if (!$target || !$base) {
            Response::json(['error' => 'Person not found'], 404);
            return;
        }

        $kinship = $this->kinshipService->determineKinship($target, $base);
        $phrase = $this->kinshipService->formatUserKinshipPhrase($target, $base);

        Response::json([
            'status' => 'success',
            'target' => $target,
            'base' => $base,
            'kinship' => $kinship,
            'phrase' => $phrase,
        ]);
    }

    /**
     * API: Быстрый поиск персон с подсказками
     */
    public function apiSearch(Request $request): void
    {
        $q = trim((string)$request->get('q', ''));
        if (mb_strlen($q) < 2) {
            Response::json(['results' => []]);
            return;
        }

        $allData = $this->getDemoDataset()['persons'];
        $results = [];

        foreach ($allData as $p) {
            $fullName = trim(($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? ''));
            if (mb_stripos($fullName, $q) !== false || mb_stripos($p['clan_ru'] ?? '', $q) !== false) {
                $results[] = [
                    'id' => $p['id'],
                    'name' => $fullName,
                    'years' => ($p['birth_year'] ?? '') . ($p['death_year'] ? ' — ' . $p['death_year'] : ($p['is_deceased'] ? '' : ' (тірі)')),
                    'clan' => $p['clan_ru'] ?? 'Керей',
                    'gender' => $p['gender'],
                    'avatar' => $p['avatar_path'] ?? null,
                ];
            }
        }

        Response::json(['results' => array_slice($results, 0, 10)]);
    }

    /**
     * Эталонный набор шежіре Керей -> Алтыбаевтар (схема для рабочего вида и леса)
     */
    protected function getDemoDataset(): array
    {
        $clans = [
            [
                'id' => 1,
                'name' => 'Керей',
                'zhuz' => 'middle',
                'tamga' => 'Ашамай (X)',
                'uran' => 'Ошыбай!',
                'tree_color' => '#1b4332',
                'root_ancestor_name' => 'Керей баба',
                'description' => 'Негізгі діңгек: Керей бабадан Алтыбайға дейінгі тікелей ата-баба желісі.',
            ],
            [
                'id' => 2,
                'name' => 'Жағалбайлы',
                'zhuz' => 'junior',
                'tamga' => 'Балға (T)',
                'uran' => 'Малатау!',
                'tree_color' => '#8b2635',
                'root_ancestor_name' => 'Жағалбайлы',
                'description' => 'Кіші жүз. Біздің әулетке келін болып түскен аяулы аналарымыздың руы.',
            ],
            [
                'id' => 3,
                'name' => 'Қоңырат',
                'zhuz' => 'middle',
                'tamga' => 'Босаға (П)',
                'uran' => 'Алатау!',
                'tree_color' => '#2b580c',
                'root_ancestor_name' => 'Қоңырат',
                'description' => 'Орта жүз. Алтыбаевтар әулетінің қыздары ұзатылып барған құрметті құдалар руы.',
            ],
        ];

        // Иерархия:
        // 1: Керей (корень, внизу)
        // 2: Самай баба (вертикальный ствол)
        // 3: Алтыбай баба (негізгі діңгек аяқталып, бұтақтар тарайтын тұлға)
        // 4: Сейітқали Алтыбаев (үлкен ұлы)
        // 5: Бибінұр (Сейітқалидың жұбайы, Жағалбайлы қызы)
        // 6: Қасым Алтыбаев (Жандостың әкесі, бас редактор)
        // 7: Жандос Алтыбаев («Сіз осындасыз», жүйе әкімшісі)
        // 8: Әлия Алтыбаева (Жандостың жұбайы, Жағалбайлы қызы)
        // 9: Айнұр Алтыбаева (Жандостың қарындасы — тұрмысқа шыққан, Қоңыратқа келін)
        // 10: Арнұр Алтыбаев (Жандостың ұлы, крона басындағы жас өркен)
        // 11: Медина Алтыбаева (Жандостың қызы, кронадағы нәзік гүл)
        // 12: Дәулет (Қоңырат күйеу бала — Айнұрдың жұбайы)
        $persons = [
            [
                'id' => 1,
                'clan_id' => 1,
                'first_name' => 'Керей',
                'last_name' => 'Баба',
                'middle_name' => '',
                'gender' => 'M',
                'father_id' => null,
                'mother_id' => null,
                'birth_year' => 'ок. 1380',
                'birth_place' => 'Қазақ даласы',
                'is_deceased' => 1,
                'death_year' => 'ок. 1450',
                'clan_ru' => 'Орта жүз, Керей',
                'is_founder' => 1,
                'is_trunk' => 1,
                'biography' => 'Керей хандығы мен руының түп негізі. Ағаштың ең түпкі тамырында (корень) орналасқан қасиетті баба.',
                'avatar_path' => null,
            ],
            [
                'id' => 2,
                'clan_id' => 1,
                'first_name' => 'Самай',
                'last_name' => 'Би',
                'middle_name' => 'Керейұлы',
                'gender' => 'M',
                'father_id' => 1,
                'mother_id' => null,
                'birth_year' => 'ок. 1620',
                'birth_place' => 'Ертіс бойы',
                'is_deceased' => 1,
                'death_year' => 'ок. 1690',
                'clan_ru' => 'Орта жүз, Керей',
                'is_trunk' => 1,
                'biography' => 'Керейден Алтыбайға дейінгі тікелей көтерілетін біртұтас биік діңгектегі (высокий ствол) тарихи тұлға.',
                'avatar_path' => null,
            ],
            [
                'id' => 3,
                'clan_id' => 1,
                'first_name' => 'Алтыбай',
                'last_name' => 'Баба',
                'middle_name' => 'Самайұлы',
                'gender' => 'M',
                'father_id' => 2,
                'mother_id' => null,
                'birth_year' => 'ок. 1795',
                'birth_place' => 'Шығыс Қазақстан, Зайсан өңірі',
                'is_deceased' => 1,
                'death_year' => 'ок. 1870',
                'clan_ru' => 'Орта жүз, Керей',
                'is_trunk' => 1,
                'biography' => 'Әулетіміздің атын иеленген ұлы бабамыз. Осы кісіден кейін шежіре діңгегі тармақталып, бұтақтар (ветки) жан-жаққа жайылады.',
                'avatar_path' => null,
            ],
            [
                'id' => 4,
                'clan_id' => 1,
                'first_name' => 'Сейітқали',
                'last_name' => 'Алтыбаев',
                'middle_name' => 'Алтыбайұлы',
                'gender' => 'M',
                'father_id' => 3,
                'mother_id' => null,
                'birth_year' => '1898',
                'birth_place' => 'Зайсан',
                'is_deceased' => 1,
                'death_year' => '1972',
                'clan_ru' => 'Орта жүз, Керей',
                'biography' => 'Елге сыйлы ақсақал, шежіреші, шаңырақ тірегі.',
                'avatar_path' => null,
            ],
            [
                'id' => 5,
                'clan_id' => 2,
                'first_name' => 'Бибінұр',
                'last_name' => 'Алтыбаева',
                'maiden_name' => 'Сейітқызы',
                'gender' => 'F',
                'father_id' => null,
                'mother_id' => null,
                'birth_year' => '1904',
                'birth_place' => 'Ақтөбе өңірі',
                'is_deceased' => 1,
                'death_year' => '1984',
                'clan_ru' => 'Кіші жүз, Жағалбайлы',
                'biography' => 'Жағалбайлы руынан келген берекелі анамыз. Күйеуі Сейітқалидің жанындағы гүл түрінде бейнеленеді.',
                'avatar_path' => null,
            ],
            [
                'id' => 6,
                'clan_id' => 1,
                'first_name' => 'Қасым',
                'last_name' => 'Алтыбаев',
                'middle_name' => 'Сейітқалиұлы',
                'gender' => 'M',
                'father_id' => 4,
                'mother_id' => 5,
                'birth_year' => '1955',
                'birth_place' => 'Алматы',
                'is_deceased' => 0,
                'death_year' => null,
                'clan_ru' => 'Орта жүз, Керей',
                'is_father_editor' => 1,
                'biography' => 'Жандостың әкесі, шежіренің басты сақтаушысы әрі редакторы. MyHeritage жүйесіне әбден дағдыланған.',
                'avatar_path' => null,
            ],
            [
                'id' => 7,
                'clan_id' => 1,
                'first_name' => 'Жандос',
                'last_name' => 'Алтыбаев',
                'middle_name' => 'Қасымұлы',
                'gender' => 'M',
                'father_id' => 6,
                'mother_id' => null,
                'birth_year' => '1985',
                'birth_place' => 'Алматы',
                'is_deceased' => 0,
                'death_year' => null,
                'clan_ru' => 'Орта жүз, Керей',
                'is_self' => 1,
                'biography' => 'Жүйе әкімшісі. Древо ашылғанда «Сіз осындасыз» белгісімен алтын реңкпен жарқырап көрінеді.',
                'avatar_path' => null,
            ],
            [
                'id' => 8,
                'clan_id' => 2,
                'first_name' => 'Әлия',
                'last_name' => 'Алтыбаева',
                'maiden_name' => 'Нұрланқызы',
                'gender' => 'F',
                'father_id' => null,
                'mother_id' => null,
                'birth_year' => '1987',
                'birth_place' => 'Астана',
                'is_deceased' => 0,
                'death_year' => null,
                'clan_ru' => 'Кіші жүз, Жағалбайлы',
                'biography' => 'Жандостың өмірлік жары. Жағалбайлы қызы — күйеуінің бұтағындағы гүл, басса Жағалбайлы ағашына ауысады.',
                'avatar_path' => null,
            ],
            [
                'id' => 9,
                'clan_id' => 1,
                'out_married_clan_id' => 3,
                'first_name' => 'Айнұр',
                'last_name' => 'Төлегенова',
                'maiden_name' => 'Алтыбаева',
                'gender' => 'F',
                'father_id' => 6,
                'mother_id' => null,
                'birth_year' => '1989',
                'birth_place' => 'Алматы',
                'is_deceased' => 0,
                'death_year' => null,
                'clan_ru' => 'Орта жүз, Керей (Қоңыратқа келін)',
                'biography' => 'Қасымның қызы, Жандостың қарындасы. Қоңырат руына келін болып түскен. Туған ағашында нәзік із қалып, күйеуінің ағашына гүл болып ауысқан.',
                'avatar_path' => null,
            ],
            [
                'id' => 10,
                'clan_id' => 1,
                'first_name' => 'Арнұр',
                'last_name' => 'Алтыбаев',
                'middle_name' => 'Жандосұлы',
                'gender' => 'M',
                'father_id' => 7,
                'mother_id' => 8,
                'birth_year' => '2014',
                'birth_place' => 'Алматы',
                'is_deceased' => 0,
                'death_year' => null,
                'clan_ru' => 'Орта жүз, Керей',
                'biography' => 'Жандос пен Әлияның ұлы. Кронаның ең жоғарғы қабатындағы жас жапырақ/өркен.',
                'avatar_path' => null,
            ],
            [
                'id' => 11,
                'clan_id' => 1,
                'first_name' => 'Медина',
                'last_name' => 'Алтыбаева',
                'middle_name' => 'Жандосқызы',
                'gender' => 'F',
                'father_id' => 7,
                'mother_id' => 8,
                'birth_year' => '2019',
                'birth_place' => 'Алматы',
                'is_deceased' => 0,
                'death_year' => null,
                'clan_ru' => 'Орта жүз, Керей',
                'biography' => 'Жандостың кішкентай қызы. Әкесінің бұтағындағы жаңа гүлдеген нәзік бүршік-гүл.',
                'avatar_path' => null,
            ],
            [
                'id' => 12,
                'clan_id' => 3,
                'first_name' => 'Дәулет',
                'last_name' => 'Төлегенов',
                'middle_name' => 'Ерланұлы',
                'gender' => 'M',
                'father_id' => null,
                'mother_id' => null,
                'birth_year' => '1987',
                'birth_place' => 'Шымкент',
                'is_deceased' => 0,
                'death_year' => null,
                'clan_ru' => 'Орта жүз, Қоңырат',
                'biography' => 'Айнұрдың жұбайы, Қоңырат азаматы. Шежіре-Ормандағы көрші Қоңырат ағашының өкілі.',
                'avatar_path' => null,
            ],
        ];

        $marriages = [
            ['id' => 1, 'husband_id' => 4, 'wife_id' => 5, 'notes' => 'Сейітқали & Бибінұр'],
            ['id' => 2, 'husband_id' => 7, 'wife_id' => 8, 'notes' => 'Жандос & Әлия'],
            ['id' => 3, 'husband_id' => 12, 'wife_id' => 9, 'notes' => 'Дәулет & Айнұр (Қоңыратқа келін)'],
        ];

        return [
            'clans' => $clans,
            'persons' => $persons,
            'marriages' => $marriages,
        ];
    }
}
