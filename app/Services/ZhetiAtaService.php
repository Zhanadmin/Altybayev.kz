<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Қазақтың «Жеті ата» салтын тексеру сервисі
 * (Сервис проверки традиционного казахского правила семи поколений — «Жеті ата»)
 */
class ZhetiAtaService
{
    /**
     * Қазақтың 7 ата атаулары (жеті буын аталары)
     */
    public const GENERATIONS_ANCESTORS = [
        1 => ['kk' => 'Өзі / Баласы', 'ru' => 'Персона'],
        2 => ['kk' => 'Әке', 'ru' => 'Отец'],
        3 => ['kk' => 'Ата', 'ru' => 'Дедушка'],
        4 => ['kk' => 'Баба', 'ru' => 'Прадедушка'],
        5 => ['kk' => 'Түп ата', 'ru' => 'Прапрадедушка (5-буын)'],
        6 => ['kk' => 'Дүние ата', 'ru' => 'Прапрапрадедушка (6-буын)'],
        7 => ['kk' => 'Қаймана ата', 'ru' => 'Седьмой предок (7-буын)'],
    ];

    /**
     * 7 буын ұрпақ атаулары (потомки по мужской линии)
     */
    public const GENERATIONS_DESCENDANTS = [
        1 => ['kk' => 'Өзі', 'ru' => 'Сам человек'],
        2 => ['kk' => 'Баласы (Ұл / Қыз)', 'ru' => 'Сын / Дочь'],
        3 => ['kk' => 'Немере', 'ru' => 'Внук / Внучка'],
        4 => ['kk' => 'Шөбере', 'ru' => 'Правнук / Правнучка'],
        5 => ['kk' => 'Шөпшек', 'ru' => 'Праправнук'],
        6 => ['kk' => 'Немене', 'ru' => 'Прапраправнук'],
        7 => ['kk' => 'Туажат', 'ru' => 'Потомок 7-го колена'],
        8 => ['kk' => 'Жүрежат', 'ru' => 'Потомок 8-го колена'],
        9 => ['kk' => 'Жекжат', 'ru' => 'Потомок 9-го колена'],
        10 => ['kk' => 'Жұрағат', 'ru' => 'Потомок 10-го колена'],
    ];

    /**
     * @var array<int, array>
     */
    protected array $personsCache = [];

    public function __construct(?array $persons = null)
    {
        if ($persons !== null) {
            foreach ($persons as $p) {
                $this->personsCache[(int)$p['id']] = $p;
            }
        }
    }

    /**
     * Загрузка всех персон в кэш сервиса
     */
    protected function loadAllPersons(): void
    {
        if (!empty($this->personsCache)) {
            return;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT id, gedcom_id, first_name, last_name, middle_name, maiden_name, gender, father_id, mother_id, birth_date, birth_year, death_date, death_year, is_deceased, clan_ru, clan_subru, avatar_path FROM persons WHERE is_deleted = 0");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->personsCache[(int)$row['id']] = $row;
        }
    }

    /**
     * Получить персону по ID
     */
    public function getPerson(int $id): ?array
    {
        $this->loadAllPersons();
        return $this->personsCache[$id] ?? null;
    }

    /**
     * Получить цепочку прямых предков по отцовской линии
     *
     * @param int $personId
     * @param int $maxGenerations Максимальная глубина (по умолчанию 15)
     * @return array<int, array{generation: int, title_kk: string, title_ru: string, person: array}>
     */
    public function getPaternalChain(int $personId, int $maxGenerations = 15): array
    {
        $this->loadAllPersons();

        $chain = [];
        $currId = $personId;
        $gen = 1;

        while ($currId && $gen <= $maxGenerations) {
            $person = $this->personsCache[$currId] ?? null;
            if (!$person) {
                break;
            }

            $titleInfo = self::GENERATIONS_ANCESTORS[$gen] ?? [
                'kk' => "{$gen}-буын баба",
                'ru' => "Предок {$gen}-го поколения"
            ];

            $chain[] = [
                'generation' => $gen,
                'title_kk'   => $titleInfo['kk'],
                'title_ru'   => $titleInfo['ru'],
                'person'     => $person,
            ];

            $fatherId = (int)($person['father_id'] ?? 0);
            if ($fatherId <= 0 || $fatherId === $currId) {
                break;
            }

            $currId = $fatherId;
            $gen++;
        }

        return $chain;
    }

    /**
     * Получить ровно 7 ата персоны (Жеті атасы)
     */
    public function getSevenAncestors(int $personId): array
    {
        $chain = $this->getPaternalChain($personId, 7);
        $result = [];

        for ($gen = 1; $gen <= 7; $gen++) {
            $entry = null;
            foreach ($chain as $item) {
                if ($item['generation'] === $gen) {
                    $entry = $item;
                    break;
                }
            }

            $titleInfo = self::GENERATIONS_ANCESTORS[$gen];
            if ($entry) {
                $result[] = $entry;
            } else {
                $result[] = [
                    'generation' => $gen,
                    'title_kk'   => $titleInfo['kk'],
                    'title_ru'   => $titleInfo['ru'],
                    'person'     => null,
                ];
            }
        }

        return $result;
    }

    /**
     * Полная проверка «Жеті ата» между двумя персонами
     *
     * @param int $personAId
     * @param int $personBId
     * @return array
     */
    public function checkZhetiAta(int $personAId, int $personBId): array
    {
        $this->loadAllPersons();

        $personA = $this->getPerson($personAId);
        $personB = $this->getPerson($personBId);

        if (!$personA || !$personB) {
            return [
                'success' => false,
                'error'   => 'Тұлғалардың бірі немесе екеуі де табылмады / Одна или обе персоны не найдены.',
            ];
        }

        if ($personAId === $personBId) {
            return [
                'success'   => true,
                'is_same'   => true,
                'person_a'  => $personA,
                'person_b'  => $personB,
                'paternal'  => [
                    'has_common_ancestor'     => true,
                    'is_within_7_generations' => true,
                    'verdict_code'            => 'same_person',
                    'verdict_status'          => 'danger',
                    'title_kk'                => 'Бір тұлға таңдалды',
                    'title_ru'                => 'Выбрана одна и та же персона',
                    'desc_kk'                 => 'Салыстыру үшін екі түрлі адамды таңдаңыз.',
                    'desc_ru'                 => 'Пожалуйста, выберите двух разных родственников для проверки.',
                ],
                'all_lines' => [
                    'has_common_ancestor' => true,
                    'is_maternal'         => false,
                    'path'                => [],
                ],
            ];
        }

        // 1. ПРОВЕРКА ПО ПРЯМОЙ ОТЦОВСКОЙ ЛИНИИ (Қазақтың «Жеті ата» қағидасы)
        $paternalCheck = $this->evaluatePaternalZhetiAta($personA, $personB);

        // 2. СПРАВОЧНАЯ ПРОВЕРКА ПО ВСЕМ ЛИНИЯМ (Материнские и смешанные линии)
        $allLinesCheck = $this->evaluateAllLinesAncestry($personA, $personB, $paternalCheck);

        // 3. 7 ата тізімі для обеих персон
        $sevenAncestorsA = $this->getSevenAncestors($personAId);
        $sevenAncestorsB = $this->getSevenAncestors($personBId);

        return [
            'success'            => true,
            'is_same'            => false,
            'person_a'           => $personA,
            'person_b'           => $personB,
            'paternal'           => $paternalCheck,
            'all_lines'          => $allLinesCheck,
            'seven_ancestors_a'  => $sevenAncestorsA,
            'seven_ancestors_b'  => $sevenAncestorsB,
        ];
    }

    /**
     * Оценка по прямой мужской линии (әкелер желісі)
     */
    protected function evaluatePaternalZhetiAta(array $personA, array $personB): array
    {
        $chainA = $this->getPaternalChain((int)$personA['id'], 20);
        $chainB = $this->getPaternalChain((int)$personB['id'], 20);

        $mapB = [];
        foreach ($chainB as $idx => $item) {
            $mapB[(int)$item['person']['id']] = [
                'index'      => $idx,
                'generation' => $item['generation'],
                'item'       => $item,
            ];
        }

        $commonAncestor = null;
        $genDistA = 0;
        $genDistB = 0;
        $pathA = [];
        $pathB = [];

        foreach ($chainA as $itemA) {
            $pId = (int)$itemA['person']['id'];
            if (isset($mapB[$pId])) {
                $commonAncestor = $itemA['person'];
                $genDistA = $itemA['generation'];
                $genDistB = $mapB[$pId]['generation'];
                break;
            }
        }

        if ($commonAncestor) {
            // Строим путь от A до предка
            foreach ($chainA as $item) {
                $pathA[] = $item;
                if ((int)$item['person']['id'] === (int)$commonAncestor['id']) {
                    break;
                }
            }

            // Строим путь от B до предка
            foreach ($chainB as $item) {
                $pathB[] = $item;
                if ((int)$item['person']['id'] === (int)$commonAncestor['id']) {
                    break;
                }
            }

            $maxGen = max($genDistA, $genDistB);
            $isWithin7 = ($maxGen <= 7);

            $ancestorName = trim(($commonAncestor['first_name'] ?? '') . ' ' . ($commonAncestor['last_name'] ?? ''));

            if ($isWithin7) {
                return [
                    'has_common_ancestor'     => true,
                    'common_ancestor'         => $commonAncestor,
                    'gen_distance_a'          => $genDistA,
                    'gen_distance_b'          => $genDistB,
                    'max_generation'          => $maxGen,
                    'is_within_7_generations' => true,
                    'verdict_code'            => 'within_7',
                    'verdict_status'          => 'danger',
                    'title_kk'                => 'Жеті атаға тола қоймаған туыстар',
                    'title_ru'                => 'Родственники в пределах 7 колен',
                    'badge_kk'                => "{$maxGen}-буын туыстық",
                    'badge_ru'                => "Связь в пределах {$maxGen} колен",
                    'desc_kk'                 => "Екі тұлғаның арасында ортақ ата бар — «{$ancestorName}». {$personA['first_name']} үшін {$genDistA}-буын, ал {$personB['first_name']} үшін {$genDistB}-буын. Қазақ салты бойынша жеті атаға толмаған ағайын-туыс арасында қыз алысуға, некеге тұруға қатаң тыйым салынады.",
                    'desc_ru'                 => "У обеих персон зафиксирован общий предок по мужской линии — «{$ancestorName}» ({$genDistA}-е колено для {$personA['first_name']}, {$genDistB}-е колено для {$personB['first_name']}). По казахскому адату брак между родственниками до 7 колен строго запрещен.",
                    'path_a'                  => $pathA,
                    'path_b'                  => $pathB,
                ];
            }

            return [
                'has_common_ancestor'     => true,
                'common_ancestor'         => $commonAncestor,
                'gen_distance_a'          => $genDistA,
                'gen_distance_b'          => $genDistB,
                'max_generation'          => $maxGen,
                'is_within_7_generations' => false,
                'verdict_code'            => 'exceeds_7',
                'verdict_status'          => 'success',
                'title_kk'                => 'Жеті атадан асқан (некеге рұқсат)',
                'title_ru'                => 'Более 7 поколений (Жеті атадан асқан)',
                'badge_kk'                => "{$maxGen}-буын (7-ден асқан)",
                'badge_ru'                => "{$maxGen}-е колено (свыше 7)",
                'desc_kk'                 => "Ортақ баба «{$ancestorName}» 7 буыннан кейін кездеседі ({$maxGen}-буын). Қазақ дәстүрі бойынша жеті ата өткеннен кейін ақсақалдардың батасымен қыз алысып, құда болуға жол берілген.",
                'desc_ru'                 => "Общий предок по мужской линии «{$ancestorName}» находится далее 7 поколений ({$maxGen}-е колено). По казахской традиции после семи колен разрешено родниться и вступать в брак.",
                'path_a'                  => $pathA,
                'path_b'                  => $pathB,
            ];
        }

        // Прямого общего предка по мужской линии нет
        return [
            'has_common_ancestor'     => false,
            'common_ancestor'         => null,
            'gen_distance_a'          => 0,
            'gen_distance_b'          => 0,
            'max_generation'          => 0,
            'is_within_7_generations' => false,
            'verdict_code'            => 'no_paternal_common',
            'verdict_status'          => 'neutral',
            'title_kk'                => 'Тікелей әке желісінде ортақ ата табылмады',
            'title_ru'                => 'Прямой общий предок по мужской линии не найден',
            'badge_kk'                => 'Әр түрлі ру немесе ортақ баба тіркелмеген',
            'badge_ru'                => 'Разные роды или ветви',
            'desc_kk'                 => 'Берілген шежіре дерекқорында екі тұлғаның тікелей әкелері желісінде ортақ баба тіркелмеген (әр түрлі ру өкілдері немесе терең байланыс енгізілмеген).',
            'desc_ru'                 => 'В базе данных шежире общий предок по прямой мужской линии не зафиксирован (возможно, представители разных родов или ранняя ветвь не оцифрована).',
            'path_a'                  => $chainA,
            'path_b'                  => $chainB,
        ];
    }

    /**
     * Поиск общего предка по всем линиям (билатеральный поиск через BFS)
     */
    protected function evaluateAllLinesAncestry(array $personA, array $personB, array $paternalResult): array
    {
        $idA = (int)$personA['id'];
        $idB = (int)$personB['id'];

        // Поиск предков персоны через BFS с сохранением пути
        $ancestorsA = $this->findAncestorsWithPaths($idA);
        $ancestorsB = $this->findAncestorsWithPaths($idB);

        $bestAncestor = null;
        $minTotalDist = 999;
        $bestPathA = [];
        $bestPathB = [];

        foreach ($ancestorsA as $pId => $dataA) {
            if (isset($ancestorsB[$pId])) {
                $totalDist = count($dataA['path']) + count($ancestorsB[$pId]['path']);
                if ($totalDist < $minTotalDist) {
                    $minTotalDist = $totalDist;
                    $bestAncestor = $this->personsCache[$pId] ?? null;
                    $bestPathA = $dataA['path'];
                    $bestPathB = $ancestorsB[$pId]['path'];
                }
            }
        }

        if (!$bestAncestor) {
            return [
                'has_common_ancestor' => false,
                'common_ancestor'     => null,
                'is_maternal'         => false,
                'total_distance'      => 0,
                'summary_kk'          => 'Ешбір желі бойынша ортақ ата-баба табылмады.',
                'summary_ru'          => 'Общих предков по всем линиям не обнаружено.',
                'path_a'              => [],
                'path_b'              => [],
            ];
        }

        // Проверяем, является ли эта связь чисто отцовской или смешанной/материнской
        $isPurePaternal = true;
        foreach ([$bestPathA, $bestPathB] as $path) {
            for ($i = 0; $i < count($path) - 1; $i++) {
                $child = $path[$i];
                $parent = $path[$i + 1];
                if ((int)($child['father_id'] ?? 0) !== (int)$parent['id']) {
                    $isPurePaternal = false;
                    break 2;
                }
            }
        }

        $ancestorName = trim(($bestAncestor['first_name'] ?? '') . ' ' . ($bestAncestor['last_name'] ?? ''));
        $distA = count($bestPathA) - 1;
        $distB = count($bestPathB) - 1;

        $lineTypeKk = $isPurePaternal ? 'Әке желісі бойынша' : 'Ана немесе аралас желі бойынша';
        $lineTypeRu = $isPurePaternal ? 'По отцовской линии' : 'По материнской или смешанной линии';

        return [
            'has_common_ancestor' => true,
            'common_ancestor'     => $bestAncestor,
            'is_maternal'         => !$isPurePaternal,
            'dist_a'              => $distA,
            'dist_b'              => $distB,
            'total_distance'      => $minTotalDist,
            'line_type_kk'        => $lineTypeKk,
            'line_type_ru'        => $lineTypeRu,
            'summary_kk'          => "{$lineTypeKk} ортақ ата/әже: «{$ancestorName}» ({$personA['first_name']} үшін {$distA}-буын, {$personB['first_name']} үшін {$distB}-буын).",
            'summary_ru'          => "{$lineTypeRu} общий предок: «{$ancestorName}» ({$distA}-е колено для {$personA['first_name']}, {$distB}-е колено для {$personB['first_name']}).",
            'path_a'              => $this->formatPathWithKinshipLabels($bestPathA),
            'path_b'              => $this->formatPathWithKinshipLabels($bestPathB),
        ];
    }

    /**
     * Поиск всех предков с путями через BFS
     *
     * @param int $startPersonId
     * @return array<int, array{depth: int, path: list<array>}>
     */
    protected function findAncestorsWithPaths(int $startPersonId): array
    {
        $startPerson = $this->personsCache[$startPersonId] ?? null;
        if (!$startPerson) {
            return [];
        }

        $ancestors = [];
        $queue = [
            [
                'id'    => $startPersonId,
                'depth' => 0,
                'path'  => [$startPerson],
            ]
        ];
        $visited = [$startPersonId => true];

        while (!empty($queue)) {
            $curr = array_shift($queue);
            $currId = $curr['id'];
            $currPerson = $this->personsCache[$currId] ?? null;
            if (!$currPerson) {
                continue;
            }

            if ($curr['depth'] > 0) {
                $ancestors[$currId] = [
                    'depth' => $curr['depth'],
                    'path'  => $curr['path'],
                ];
            }

            // Ограничение глубины 15 поколений
            if ($curr['depth'] >= 15) {
                continue;
            }

            $fatherId = (int)($currPerson['father_id'] ?? 0);
            if ($fatherId > 0 && !isset($visited[$fatherId]) && isset($this->personsCache[$fatherId])) {
                $visited[$fatherId] = true;
                $newPath = $curr['path'];
                $newPath[] = $this->personsCache[$fatherId];
                $queue[] = [
                    'id'    => $fatherId,
                    'depth' => $curr['depth'] + 1,
                    'path'  => $newPath,
                ];
            }

            $motherId = (int)($currPerson['mother_id'] ?? 0);
            if ($motherId > 0 && !isset($visited[$motherId]) && isset($this->personsCache[$motherId])) {
                $visited[$motherId] = true;
                $newPath = $curr['path'];
                $newPath[] = $this->personsCache[$motherId];
                $queue[] = [
                    'id'    => $motherId,
                    'depth' => $curr['depth'] + 1,
                    'path'  => $newPath,
                ];
            }
        }

        return $ancestors;
    }

    /**
     * Форматирование цепочки пути с понятными казахскими и русскими шагами
     */
    protected function formatPathWithKinshipLabels(array $path): array
    {
        $result = [];
        for ($i = 0; $i < count($path); $i++) {
            $node = $path[$i];
            $stepLabelKk = ($i === 0) ? 'Өзі (Бастапқы)' : 'Ата-баба';
            $stepLabelRu = ($i === 0) ? 'Персона' : 'Предок';

            if ($i > 0) {
                $prev = $path[$i - 1];
                if ((int)($prev['father_id'] ?? 0) === (int)$node['id']) {
                    $stepLabelKk = 'Әкесі';
                    $stepLabelRu = 'Отец';
                } elseif ((int)($prev['mother_id'] ?? 0) === (int)$node['id']) {
                    $stepLabelKk = 'Анасы';
                    $stepLabelRu = 'Мать';
                }
            }

            $result[] = [
                'id'            => (int)$node['id'],
                'first_name'    => $node['first_name'],
                'last_name'     => $node['last_name'] ?? '',
                'gender'        => $node['gender'] ?? 'M',
                'birth_year'    => $node['birth_year'] ?? $node['birth_date'] ?? null,
                'is_deceased'   => (int)($node['is_deceased'] ?? 0),
                'step_label_kk' => $stepLabelKk,
                'step_label_ru' => $stepLabelRu,
                'step_index'    => $i,
            ];
        }

        return $result;
    }
}
