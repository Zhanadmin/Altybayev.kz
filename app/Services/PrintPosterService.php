<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Басып шығаруға және сыйлыққа арналған шежіре-плакат сервисі
 * (Сервис формирования подарочного полиграфического плаката шежире высокого разрешения)
 */
class PrintPosterService
{
    /**
     * @var array<int, array>
     */
    protected array $persons = [];

    /**
     * @var array<int, list<int>>
     */
    protected array $childrenByFather = [];

    /**
     * @var array<int, list<int>>
     */
    protected array $childrenByMother = [];

    /**
     * @var array<int, list<array>>
     */
    protected array $marriagesByPerson = [];

    public function __construct()
    {
        $this->loadData();
    }

    protected function loadData(): void
    {
        $pdo = Database::getConnection();

        $pStmt = $pdo->query("SELECT id, gedcom_id, first_name, last_name, middle_name, maiden_name, gender, father_id, mother_id, birth_date, birth_year, death_date, death_year, is_deceased, clan_ru, clan_subru, biography, avatar_path FROM persons WHERE is_deleted = 0");
        while ($row = $pStmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int)$row['id'];
            $this->persons[$id] = $row;

            $fId = (int)($row['father_id'] ?? 0);
            if ($fId > 0) {
                $this->childrenByFather[$fId][] = $id;
            }

            $mId = (int)($row['mother_id'] ?? 0);
            if ($mId > 0) {
                $this->childrenByMother[$mId][] = $id;
            }
        }

        $mStmt = $pdo->query("SELECT * FROM marriages WHERE is_current = 1");
        while ($m = $mStmt->fetch(PDO::FETCH_ASSOC)) {
            $hId = (int)$m['husband_id'];
            $wId = (int)$m['wife_id'];
            $this->marriagesByPerson[$hId][] = $m;
            $this->marriagesByPerson[$wId][] = $m;
        }
    }

    /**
     * Получить список ключевых родоначальников (аталар) для выбора корня плаката
     */
    public function getPotentialPatriarchs(): array
    {
        $candidates = [];

        // Ищем людей, у которых нет отца в базе (корни) или которые имеют много потомков
        foreach ($this->persons as $id => $p) {
            $fId = (int)($p['father_id'] ?? 0);
            $hasChildren = !empty($this->childrenByFather[$id] ?? []);

            if ($p['gender'] === 'M' && ($fId === 0 || in_array($p['first_name'], ['Алтыбай', 'Ғабит', 'Габит', 'Шамсиден', 'Кенен', 'Керей', 'Шортан'], true))) {
                $descendantsCount = $this->countDescendants($id);
                if ($descendantsCount >= 2 || $fId === 0) {
                    $fullName = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
                    $candidates[] = [
                        'id'                => $id,
                        'name'              => $fullName,
                        'birth_year'        => $p['birth_year'] ?? $p['birth_date'] ?? '—',
                        'clan_ru'           => $p['clan_ru'] ?? 'Керей',
                        'descendants_count' => $descendantsCount,
                        'is_root'           => ($fId === 0),
                    ];
                }
            }
        }

        usort($candidates, function($a, $b) {
            if ($a['is_root'] !== $b['is_root']) {
                return $a['is_root'] ? -1 : 1;
            }
            return $b['descendants_count'] <=> $a['descendants_count'];
        });

        return $candidates;
    }

    /**
     * Подсчет количества потомков
     */
    protected function countDescendants(int $personId, array &$visited = []): int
    {
        if (isset($visited[$personId])) {
            return 0;
        }
        $visited[$personId] = true;

        $count = 0;
        $children = $this->childrenByFather[$personId] ?? [];
        foreach ($children as $cId) {
            $count += 1 + $this->countDescendants($cId, $visited);
        }
        return $count;
    }

    /**
     * Построить структуру плаката шежіре
     *
     * @param int $rootId ID родоначальника ветки
     * @param int $maxGenerations Количество колен (по умолчанию 7)
     * @param string $language 'kk'|'ru'
     * @return array
     */
    public function getPosterData(int $rootId, int $maxGenerations = 7, string $language = 'kk'): array
    {
        $root = $this->persons[$rootId] ?? null;
        if (!$root) {
            // Если передан неверный ID, берем первого корня
            $roots = array_filter($this->persons, fn($p) => (int)($p['father_id'] ?? 0) === 0 && $p['gender'] === 'M');
            $root = reset($roots) ?: reset($this->persons);
            $rootId = (int)$root['id'];
        }

        $levels = [];
        for ($i = 1; $i <= $maxGenerations; $i++) {
            $levels[$i] = [];
        }

        $this->collectGenerations($rootId, 1, $maxGenerations, $levels);

        // Убираем пустые уровни в конце
        $levels = array_filter($levels, fn($lvl) => !empty($lvl));

        // Вычисляем общее количество персон на плакате
        $totalPersons = 0;
        foreach ($levels as $lvl) {
            $totalPersons += count($lvl);
        }

        $rootName = trim(($root['first_name'] ?? '') . ' ' . ($root['last_name'] ?? ''));

        return [
            'success'          => true,
            'root'             => $root,
            'root_name'        => $rootName,
            'max_generations'  => count($levels),
            'total_persons'    => $totalPersons,
            'levels'           => $levels,
            'language'         => $language,
            'clan'             => $root['clan_ru'] ?? 'Керей',
            'title_kk'         => "«{$rootName}» ұрпақтарының шежіресі",
            'title_ru'         => "Шежире потомков рода «{$rootName}»",
            'subtitle_kk'      => "Қасиетті аталар сөзі мен асыл ұрпақтар сабақтастығы (" . count($levels) . " буын)",
            'subtitle_ru'      => "Преемственность поколений и родословная (" . count($levels) . " поколений)",
        ];
    }

    /**
     * Рекурсивный сбор поколений для плаката
     */
    protected function collectGenerations(int $personId, int $currentGen, int $maxGen, array &$levels): void
    {
        if ($currentGen > $maxGen || !isset($this->persons[$personId])) {
            return;
        }

        $p = $this->persons[$personId];

        // Получаем супругу (если есть)
        $spouses = [];
        $marriages = $this->marriagesByPerson[$personId] ?? [];
        foreach ($marriages as $m) {
            $spouseId = ((int)$m['husband_id'] === $personId) ? (int)$m['wife_id'] : (int)$m['husband_id'];
            if (isset($this->persons[$spouseId])) {
                $sp = $this->persons[$spouseId];
                $spouses[] = [
                    'id'          => $spouseId,
                    'name'        => trim(($sp['first_name'] ?? '') . ' ' . ($sp['last_name'] ?? '')),
                    'maiden_name' => $sp['maiden_name'] ?? null,
                    'clan'        => $sp['clan_ru'] ?? null,
                    'years'       => $sp['birth_year'] ?? $sp['birth_date'] ?? '',
                ];
            }
        }

        $cardData = [
            'id'          => (int)$p['id'],
            'first_name'  => $p['first_name'],
            'last_name'   => $p['last_name'] ?? '',
            'middle_name' => $p['middle_name'] ?? '',
            'maiden_name' => $p['maiden_name'] ?? null,
            'gender'      => $p['gender'] ?? 'M',
            'generation'  => $currentGen,
            'birth_year'  => $p['birth_year'] ?? ($p['birth_date'] ? substr($p['birth_date'], 0, 4) : ''),
            'death_year'  => $p['death_year'] ?? ($p['death_date'] ? substr($p['death_date'], 0, 4) : ''),
            'is_deceased' => (int)($p['is_deceased'] ?? 0),
            'clan'        => $p['clan_ru'] ?? '',
            'father_id'   => (int)($p['father_id'] ?? 0),
            'mother_id'   => (int)($p['mother_id'] ?? 0),
            'spouses'     => $spouses,
        ];

        $levels[$currentGen][] = $cardData;

        // Дети по мужской линии (и дочери)
        $childrenIds = $this->childrenByFather[$personId] ?? [];
        // Сортировка детей по годам рождения
        usort($childrenIds, function($aId, $bId) {
            $pA = $this->persons[$aId] ?? [];
            $pB = $this->persons[$bId] ?? [];
            $yA = (int)($pA['birth_year'] ?? 9999);
            $yB = (int)($pB['birth_year'] ?? 9999);
            return $yA <=> $yB;
        });

        foreach ($childrenIds as $childId) {
            $this->collectGenerations($childId, $currentGen + 1, $maxGen, $levels);
        }
    }

    /**
     * Генерация чистого векторного SVG-плаката высокой четкости для профессиональной печати
     *
     * @param int $rootId
     * @param int $maxGenerations
     * @param string $style 'gold_scroll'|'forest'|'classic'
     * @param string $language 'kk'|'ru'
     * @return string Полный исходный код SVG
     */
    public function generateSvgPoster(int $rootId, int $maxGenerations = 7, string $style = 'gold_scroll', string $language = 'kk'): string
    {
        $data = $this->getPosterData($rootId, $maxGenerations, $language);
        $levels = $data['levels'];

        $width = 2400; // Разрешение плаката (эквивалент A2/A3 вектор)
        $height = 1700;
        $cardW = 160;
        $cardH = 65;

        $svg = [];
        $svg[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $svg[] = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 {$width} {$height}\" width=\"100%\" height=\"100%\">";
        $svg[] = "<defs>";
        $svg[] = "<linearGradient id=\"bgGrad\" x1=\"0%\" y1=\"0%\" x2=\"100%\" y2=\"100%\">";
        $svg[] = "<stop offset=\"0%\" stop-color=\"#08121e\" />";
        $svg[] = "<stop offset=\"50%\" stop-color=\"#0d1b2a\" />";
        $svg[] = "<stop offset=\"100%\" stop-color=\"#050b14\" />";
        $svg[] = "</linearGradient>";

        $svg[] = "<linearGradient id=\"cardGold\" x1=\"0%\" y1=\"0%\" x2=\"100%\" y2=\"100%\">";
        $svg[] = "<stop offset=\"0%\" stop-color=\"#223249\" />";
        $svg[] = "<stop offset=\"100%\" stop-color=\"#131e2c\" />";
        $svg[] = "</linearGradient>";

        $svg[] = "<filter id=\"glow\" x=\"-20%\" y=\"-20%\" width=\"140%\" height=\"140%\">";
        $svg[] = "<feGaussianBlur stdDeviation=\"4\" result=\"blur\" />";
        $svg[] = "<feComposite in=\"SourceGraphic\" in2=\"blur\" operator=\"over\" />";
        $svg[] = "</filter>";
        $svg[] = "</defs>";

        // Фон плаката
        $svg[] = "<rect width=\"{$width}\" height=\"{$height}\" fill=\"url(#bgGrad)\" />";

        // Декоративная золотая рамка с орнаментом
        $svg[] = "<rect x=\"30\" y=\"30\" width=\"" . ($width - 60) . "\" height=\"" . ($height - 60) . "\" fill=\"none\" stroke=\"#d4af37\" stroke-width=\"3\" opacity=\"0.85\" />";
        $svg[] = "<rect x=\"40\" y=\"40\" width=\"" . ($width - 80) . "\" height=\"" . ($height - 80) . "\" fill=\"none\" stroke=\"#d4af37\" stroke-width=\"1\" stroke-dasharray=\"10 5\" opacity=\"0.5\" />";

        // Заголовок плаката
        $title = ($language === 'ru') ? "ШЕЖИРЕ РОДА АЛТЫБАЕВТАР" : "АЛТЫБАЕВТАР ӘУЛЕТІНІҢ ШЕЖІРЕСІ";
        $svg[] = "<text x=\"" . ($width / 2) . "\" y=\"110\" fill=\"#fef08a\" font-size=\"36\" font-weight=\"bold\" font-family=\"Georgia, serif\" text-anchor=\"middle\" letter-spacing=\"3\" filter=\"url(#glow)\">{$title}</text>";
        
        $subTitle = $data['title_kk'] . " • " . ($data['clan'] ?: 'Керей');
        $svg[] = "<text x=\"" . ($width / 2) . "\" y=\"145\" fill=\"#cbd5e1\" font-size=\"18\" font-family=\"sans-serif\" text-anchor=\"middle\">{$subTitle}</text>";

        // Размещение поколений по вертикали
        $genCount = count($levels);
        $topMargin = 220;
        $availableHeight = $height - $topMargin - 100;
        $rowGap = ($genCount > 1) ? ($availableHeight / $genCount) : 100;

        $coords = []; // [person_id => [x, y]]

        $genIdx = 0;
        foreach ($levels as $genNum => $personsInGen) {
            $y = $topMargin + ($genIdx * $rowGap);
            $pCount = count($personsInGen);
            $xStep = $width / ($pCount + 1);

            // Метка поколения слева
            $genTitle = ($language === 'ru') ? "{$genNum}-е колено" : "{$genNum}-буын";
            $svg[] = "<text x=\"60\" y=\"" . ($y + 35) . "\" fill=\"#d4af37\" font-size=\"14\" font-weight=\"bold\" font-family=\"sans-serif\" opacity=\"0.7\">{$genTitle}</text>";

            foreach ($personsInGen as $pIdx => $p) {
                $x = ($pIdx + 1) * $xStep - ($cardW / 2);
                $coords[$p['id']] = [$x + ($cardW / 2), $y + ($cardH / 2)];

                // Карточка персоны
                $isMale = ($p['gender'] === 'M');
                $borderColor = $isMale ? '#38bdf8' : '#f472b6';
                if ($p['is_deceased']) {
                    $borderColor = '#d4af37';
                }

                $svg[] = "<g id=\"svg-person-{$p['id']}\">";
                $svg[] = "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$cardW}\" height=\"{$cardH}\" rx=\"8\" fill=\"url(#cardGold)\" stroke=\"{$borderColor}\" stroke-width=\"1.8\" />";

                // Имя
                $displayName = htmlspecialchars($p['first_name'] . ' ' . ($p['last_name'] ?? ''));
                $svg[] = "<text x=\"" . ($x + $cardW / 2) . "\" y=\"" . ($y + 26) . "\" fill=\"#ffffff\" font-size=\"13\" font-weight=\"bold\" font-family=\"sans-serif\" text-anchor=\"middle\">{$displayName}</text>";

                // Годы жизни
                $years = $p['birth_year'] ?: '';
                if ($p['is_deceased'] && $p['death_year']) {
                    $years .= ' – ' . $p['death_year'];
                }
                if ($years) {
                    $svg[] = "<text x=\"" . ($x + $cardW / 2) . "\" y=\"" . ($y + 45) . "\" fill=\"#94a3b8\" font-size=\"11\" font-family=\"sans-serif\" text-anchor=\"middle\">{$years}</text>";
                }

                // Супруга (если есть)
                if (!empty($p['spouses'])) {
                    $spName = htmlspecialchars($p['spouses'][0]['name']);
                    $svg[] = "<text x=\"" . ($x + $cardW / 2) . "\" y=\"" . ($y + 58) . "\" fill=\"#fbcfe8\" font-size=\"9\" font-style=\"italic\" font-family=\"sans-serif\" text-anchor=\"middle\">♡ {$spName}</text>";
                }

                $svg[] = "</g>";
            }

            $genIdx++;
        }

        // Соединительные линии между отцами и детьми
        foreach ($levels as $genNum => $personsInGen) {
            foreach ($personsInGen as $p) {
                $fId = $p['father_id'];
                if ($fId && isset($coords[$fId]) && isset($coords[$p['id']])) {
                    $parentPos = $coords[$fId];
                    $childPos = $coords[$p['id']];

                    $x1 = $parentPos[0];
                    $y1 = $parentPos[1] + ($cardH / 2);
                    $x2 = $childPos[0];
                    $y2 = $childPos[1] - ($cardH / 2);
                    $midY = ($y1 + $y2) / 2;

                    // Плавная кривая Безье
                    $pathD = "M {$x1} {$y1} C {$x1} {$midY}, {$x2} {$midY}, {$x2} {$y2}";
                    $svg[] = "<path d=\"{$pathD}\" fill=\"none\" stroke=\"#d4af37\" stroke-width=\"1.4\" opacity=\"0.6\" />";
                }
            }
        }

        // Подвал плаката
        $curDate = date('d.m.Y');
        $footerText = "altybayev.kz • Қазақстан • Басып шығарылған күні: {$curDate}";
        $svg[] = "<text x=\"" . ($width / 2) . "\" y=\"" . ($height - 50) . "\" fill=\"#64748b\" font-size=\"13\" font-family=\"sans-serif\" text-anchor=\"middle\">{$footerText}</text>";

        $svg[] = "</svg>";

        return implode("\n", $svg);
    }
}
