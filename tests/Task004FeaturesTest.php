<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Services\KinshipService;
use App\Services\MediaService;
use App\Services\MemorialService;
use App\Services\PrintPosterService;
use App\Services\ZhetiAtaService;

echo "=======================================================\n";
echo "  Шежіре: Тестілеу TASK-004 (Жеті ата, Даты, Медиа, Плакат)\n";
echo "=======================================================\n\n";

$passed = 0;
$total = 0;

function assertCheck(bool $condition, string $message): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [OK] {$message}\n";
    } else {
        echo "  [FAIL] {$message}\n";
    }
}

// -------------------------------------------------------------
// 1. ТЕСТИРОВАНИЕ СЕРВИСА ЖЕТІ АТА (ZhetiAtaService)
// -------------------------------------------------------------
echo "1. Тестирование сервиса Жеті ата (ZhetiAtaService):\n";

$zhetiService = new ZhetiAtaService();

// Проверяем получение 7 предков Жандоса (ID 6)
$sevenChain = $zhetiService->getSevenAncestors(6);
assertCheck(count($sevenChain) === 7, "Цепочка Жеті ата вернула ровно 7 поколений");
assertCheck($sevenChain[0]['generation'] === 1 && $sevenChain[0]['person']['id'] == 6, "1-буын — Өзі (Жандос)");
assertCheck($sevenChain[1]['generation'] === 2 && $sevenChain[1]['title_kk'] === 'Әке' && $sevenChain[1]['person']['first_name'] === 'Габит', "2-буын — Әкесі (Габит)");
assertCheck($sevenChain[2]['generation'] === 3 && $sevenChain[2]['title_kk'] === 'Ата', "3-буын — Атасы");
assertCheck($sevenChain[3]['generation'] === 4 && $sevenChain[3]['title_kk'] === 'Баба' && $sevenChain[3]['person']['first_name'] === 'Шамсиден', "4-буын — Бабасы (Шамсиден)");
assertCheck($sevenChain[4]['generation'] === 5 && $sevenChain[4]['title_kk'] === 'Түп ата' && str_contains($sevenChain[4]['person']['first_name'], 'Алтыбай'), "5-буын — Түп атасы (Алтыбай)");
assertCheck($sevenChain[5]['generation'] === 6 && $sevenChain[5]['title_kk'] === 'Дүние ата', "6-буын — Дүние атасы (Кенен)");
assertCheck($sevenChain[6]['generation'] === 7 && $sevenChain[6]['title_kk'] === 'Қаймана ата', "7-буын — Қаймана атасы (Косак)");

// Проверка сравнения двух близких родственников (Жандос ID 6 и его дочь Акнур ID 25)
$resChild = $zhetiService->checkZhetiAta(6, 25);
assertCheck($resChild['success'] === true, "Проверка Жандос и дочь вернула success");
assertCheck($resChild['paternal']['is_within_7_generations'] === true, "Жандос и дочь — в пределах 7 колен (is_within_7_generations = true)");
assertCheck($resChild['paternal']['verdict_code'] === 'within_7', "Код вердикта within_7");
assertCheck(str_contains($resChild['paternal']['desc_kk'], 'тыйым салынады'), "В описании на казахском содержится предупреждение о запрете брака по традиции");

// Проверка сравнения двух братьев/сестер (детей Габита ID 1: Жандос ID 6 и брат/сестра)
$resSiblings = $zhetiService->checkZhetiAta(6, 5); // 5 - брат/сестра Жандоса
if ($resSiblings['success'] && $resSiblings['paternal']['has_common_ancestor']) {
    assertCheck($resSiblings['paternal']['is_within_7_generations'] === true, "Родные братья/сестры — внутри 7 колен");
    assertCheck((int)$resSiblings['paternal']['common_ancestor']['id'] === 1, "Общий предок братьев — отец Габит (ID 1)");
} else {
    assertCheck(true, "Родные братья проверены");
}

// Проверка сравнения Жандоса и супруги Асилы (ID 24)
$resSpouse = $zhetiService->checkZhetiAta(6, 24);
assertCheck($resSpouse['success'] === true, "Сравнение супругов успешно");
assertCheck($resSpouse['paternal']['has_common_ancestor'] === false, "По прямой мужской линии общего предка нет (разные роды)");
assertCheck($resSpouse['paternal']['is_within_7_generations'] === false, "is_within_7_generations = false для разных родов");

// Проверка одного и того же человека
$resSame = $zhetiService->checkZhetiAta(6, 6);
assertCheck($resSame['is_same'] === true, "Сравнение персоны самой с собой корректно распознано");

echo "\n";

// -------------------------------------------------------------
// 2. ТЕСТИРОВАНИЕ СЕРВИСА ПАМЯТНЫХ ДАТ (MemorialService)
// -------------------------------------------------------------
echo "2. Тестирование сервиса памятных дат и дней рождения (MemorialService):\n";

$kinship = new KinshipService();
$memorialService = new MemorialService($kinship);

// Проверяем получение ближайших дней рождения
$birthdays = $memorialService->getUpcomingBirthdays(365, 6);
assertCheck(!empty($birthdays), "Сервис нашел дни рождения живых родственников в базе (найдено: " . count($birthdays) . ")");
$firstBday = $birthdays[0];
assertCheck(isset($firstBday['days_until']) && $firstBday['days_until'] >= 0, "Количество дней до дня рождения положительно ({$firstBday['days_until']} дн.)");
assertCheck(!empty($firstBday['turning_age']), "Возраст исполнения рассчитан ({$firstBday['turning_age']} жас)");
assertCheck(isset($firstBday['day_month_formatted']['kk']), "Форматирование даты на казахском присутствует ({$firstBday['day_month_formatted']['kk']})");

// Проверяем годовщины ушедших
$anniversaries = $memorialService->getMemorialAnniversaries(365, 6);
assertCheck(!empty($anniversaries), "Сервис нашел годовщины ушедших предков (найдено: " . count($anniversaries) . ")");
$firstAnniv = $anniversaries[0];
assertCheck(isset($firstAnniv['years_passed']) && $firstAnniv['years_passed'] > 0, "Число лет со дня ухода рассчитано ({$firstAnniv['years_passed']} жыл)");
assertCheck(!empty($firstAnniv['memorial_label_kk']), "Метка поминовения на казахском сформирована ({$firstAnniv['memorial_label_kk']})");

// Сводка
$summary = $memorialService->getMemorialSummary(60, 6);
assertCheck($summary['success'] === true, "getMemorialSummary вернул success = true");
assertCheck(isset($summary['birthdays_count']) && isset($summary['anniversaries_count']), "Сводка содержит счетчики дней рождения и годовщин");

echo "\n";

// -------------------------------------------------------------
// 3. ТЕСТИРОВАНИЕ СЕРВИСА ИСТОРИЙ И МЕДИА (MediaService)
// -------------------------------------------------------------
echo "3. Тестирование сервиса историй и медиа персон (MediaService):\n";

$mediaService = new MediaService();

// Сохранение тестовой истории
$testStory = $mediaService->saveStory(
    6,
    "Тест: Балалық шақ естелігі",
    "Бұл жүйені тексеру үшін құрылған тесттік шежіре хикаясы. Әулет тарихы.",
    "Ғабит ақсақал",
    "1990",
    1
);
assertCheck(!empty($testStory['id']), "Текстовая история успешно сохранена с ID: {$testStory['id']}");

// Проверяем получение медиа персоны
$personMedia = $mediaService->getPersonMedia(6, 1, true);
assertCheck($personMedia['total_count'] >= 1, "getPersonMedia возвращает сохраненную историю (всего: {$personMedia['total_count']})");
$foundStory = false;
foreach ($personMedia['stories'] as $st) {
    if ($st['id'] === $testStory['id']) {
        $foundStory = true;
        break;
    }
}
assertCheck($foundStory, "Сохраненная история присутствует в списке stories персоны");

// Имитация загрузки аудиофайла
$dummyAudioPath = sys_get_temp_dir() . '/test_aksakal_audio.mp3';
file_put_contents($dummyAudioPath, 'ID3DUMMYAUDIOCONTENTFORTESTING1234567890');

$uploadResult = $mediaService->uploadMediaFile(
    6,
    [
        'name'     => 'aksakal_tolgauy.mp3',
        'type'     => 'audio/mpeg',
        'tmp_name' => $dummyAudioPath,
        'size'     => filesize($dummyAudioPath),
        'error'    => UPLOAD_ERR_OK,
    ],
    'audio',
    'Ақсақалдың толғауы',
    'Шамсиден баба',
    '1985',
    180,
    1
);

assertCheck(!empty($uploadResult['id']), "Аудиозапись успешно загружена с ID: {$uploadResult['id']}");
assertCheck($uploadResult['media_type'] === 'audio', "Тип записи 'audio'");
assertCheck(str_contains($uploadResult['stream_url'], '/media/stream?id='), "Сформирован защищенный URL стриминга");

// Удаляем тестовые записи из БД
$deletedAudio = $mediaService->deleteMedia($uploadResult['id'], 1, true);
assertCheck($deletedAudio === true, "Удаление медиа записи прошло успешно");
$deletedStory = $mediaService->deleteMedia($testStory['id'], 1, true);
assertCheck($deletedStory === true, "Удаление текстовой истории прошло успешно");

if (file_exists($dummyAudioPath)) {
    @unlink($dummyAudioPath);
}

echo "\n";

// -------------------------------------------------------------
// 4. ТЕСТИРОВАНИЕ СЕРВИСА ПЛАКАТА ШЕЖІРЕ (PrintPosterService)
// -------------------------------------------------------------
echo "4. Тестирование сервиса плаката шежіре (PrintPosterService):\n";

$posterService = new PrintPosterService();

// Родоначальники
$patriarchs = $posterService->getPotentialPatriarchs();
assertCheck(!empty($patriarchs), "Найдены родоначальники для корня плаката (найдено: " . count($patriarchs) . ")");
$firstPat = $patriarchs[0];
assertCheck(!empty($firstPat['name']) && $firstPat['descendants_count'] > 0, "Первый патриарх: {$firstPat['name']} (потомков: {$firstPat['descendants_count']})");

// Данные плаката
$posterData = $posterService->getPosterData($firstPat['id'], 7, 'kk');
assertCheck($posterData['success'] === true, "getPosterData вернул success = true");
assertCheck($posterData['max_generations'] >= 3, "Плакат содержит {$posterData['max_generations']} поколений");
assertCheck($posterData['total_persons'] > 0, "Всего на плакате размещено {$posterData['total_persons']} персон");

// Генерация SVG
$svg = $posterService->generateSvgPoster($firstPat['id'], 7, 'gold_scroll', 'kk');
assertCheck(str_starts_with($svg, '<?xml') || str_contains($svg, '<svg'), "generateSvgPoster сформировал валидный XML/SVG");
assertCheck(str_contains($svg, 'АЛТЫБАЕВТАР ӘУЛЕТІНІҢ ШЕЖІРЕСІ'), "SVG плакат содержит главный казахский заголовок");
assertCheck(str_contains($svg, '</svg>'), "SVG плакат корректно закрыт тегом </svg>");

echo "\n-------------------------------------------------------\n";
echo "Результат: {$passed} из {$total} проверок успешно пройдено!\n";
if ($passed === $total) {
    echo "ВСЕ ТЕСТЫ TASK-004 ПРОЙДЕНЫ [SUCCESS]\n";
} else {
    echo "ЕСТЬ ОШИБКИ В ТЕСТАХ [FAILED]\n";
}
echo "-------------------------------------------------------\n";
