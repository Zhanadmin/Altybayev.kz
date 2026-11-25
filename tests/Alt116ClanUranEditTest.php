<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Controllers\ClanController;
use App\Controllers\ZhetiAtaController;
use App\Services\ClanService;

$fails = 0;
function chk(bool $ok, string $m): void {
    global $fails;
    echo ($ok ? 'OK   ' : 'FAIL ') . $m . "\n";
    if (!$ok) $fails++;
}

$db = Database::getConnection();
$svc = new ClanService($db);

// 1. Создание и редактирование через ClanService
$testName = 'ТестРод_' . bin2hex(random_bytes(3));
$id = $svc->save(0, [
    'name' => $testName,
    'name_ru' => 'ТестРодРус',
    'zhuz' => 'middle',
    'uran' => 'Алаш!',
    'summary' => 'Краткое описание / el.kz',
    'description' => 'Подробное историческое описание рода',
]);

chk($id > 0, "Создан тестовый род ID $id");

$clan = $svc->find($id);
chk($clan['name'] === $testName, "Имя сохранено: {$clan['name']}");
chk($clan['name_ru'] === 'ТестРодРус', "Имя рус сохранено: {$clan['name_ru']}");
chk($clan['zhuz'] === 'middle', "Жуз сохранён: {$clan['zhuz']}");
chk($clan['uran'] === 'Алаш!', "Ұран сохранён: {$clan['uran']}");
chk($clan['summary'] === 'Краткое описание / el.kz', "Summary сохранено: {$clan['summary']}");
chk($clan['description'] === 'Подробное историческое описание рода', "Description сохранено: {$clan['description']}");

// 2. Обновление ұрана и сведений
$svc->save($id, [
    'name' => $testName,
    'name_ru' => 'ТестРодРусОбновлен',
    'zhuz' => 'middle',
    'uran' => 'Қарабура!',
    'summary' => 'Обновлённый источник',
    'description' => 'Обновлённая история',
]);

$clanUpdated = $svc->find($id);
chk($clanUpdated['uran'] === 'Қарабура!', "Ұран обновлён: {$clanUpdated['uran']}");
chk($clanUpdated['name_ru'] === 'ТестРодРусОбновлен', "name_ru обновлён: {$clanUpdated['name_ru']}");
chk($clanUpdated['summary'] === 'Обновлённый источник', "summary обновлён: {$clanUpdated['summary']}");
chk($clanUpdated['description'] === 'Обновлённая история', "description обновлён: {$clanUpdated['description']}");

// 3. Сохранение таңба не сбрасывается при сохранении текстов
$db->prepare("UPDATE clans SET tamga_file = 'clan_test.svg' WHERE id = ?")->execute([$id]);
$svc->save($id, [
    'name' => $testName,
    'name_ru' => 'ТестРодРусОбновлен',
    'zhuz' => 'middle',
    'uran' => 'Аруақ!',
    'summary' => 'Источник',
    'description' => 'История',
]);
$clanWithTamga = $svc->find($id);
chk($clanWithTamga['tamga_file'] === 'clan_test.svg', "Таңба сохранена: {$clanWithTamga['tamga_file']}");
chk($clanWithTamga['uran'] === 'Аруақ!', "Ұран изменён при сохранении таңба: {$clanWithTamga['uran']}");

// 4. Интеграция с ZhetiAtaController (lookupClan возвращает новый ұран)
$zctrl = new class extends ZhetiAtaController {
    public function testLookup(string $name): ?array {
        return $this->lookupClan($name);
    }
};

$zaData = $zctrl->testLookup($testName);
chk($zaData !== null, "ZhetiAtaController находит род");
chk($zaData['uran'] === 'Аруақ!', "ZhetiAtaController возвращает правильный ұран: {$zaData['uran']}");

// 5. Проверка прав доступа в Auth
@Auth::logout();
chk(!Auth::check(), "Без сессии Auth::check() false");
chk(!Auth::isEditor(), "Без сессии Auth::isEditor() false");

// Зритель / обычный пользователь
@Auth::login(['id' => 9999, 'email' => 'viewer@test.com', 'name' => 'Viewer', 'role' => 'viewer']);
chk(Auth::check(), "С сессией читателя Auth::check() true");
chk(!Auth::isEditor(), "У читателя Auth::isEditor() false");

// Редактор
@Auth::login(['id' => 9998, 'email' => 'editor@test.com', 'name' => 'Editor', 'role' => 'editor']);
chk(Auth::check() && Auth::isEditor(), "У редактора Auth::isEditor() true");

// Администратор
@Auth::login(['id' => 9997, 'email' => 'admin@test.com', 'name' => 'Admin', 'role' => 'admin']);
chk(Auth::check() && Auth::isEditor() && Auth::isAdmin(), "У админа Auth::isEditor() и isAdmin() true");

// 6. Проверка рендеринга шаблона views/admin/clans.php для читателя и редактора
function renderView(string $viewPath, array $data = []): string {
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/../views/' . ltrim($viewPath, '/') . '.php';
    return (string)ob_get_clean();
}

$sampleClans = [$clanWithTamga];

// 6a. Читатель: сессия role = viewer
@Auth::login(['id' => 9999, 'email' => 'viewer@test.com', 'name' => 'Viewer', 'role' => 'viewer']);
$htmlReader = renderView('admin/clans', [
    'clans' => $sampleClans,
    'registryReady' => true,
]);
chk(str_contains($htmlReader, 'class="fit cl-uran"'), "Шаблон читателя содержит колонку Ұран");
chk(str_contains($htmlReader, 'Аруақ!'), "Шаблон читателя отображает значение ұрана");
chk(!str_contains($htmlReader, 'id="clDlg"'), "Шаблон читателя НЕ содержит диалог редактирования");
chk(!str_contains($htmlReader, 'data-act="edit"'), "Шаблон читателя НЕ содержит кнопку редактирования");

// 6b. Редактор: сессия role = editor
@Auth::login(['id' => 9998, 'email' => 'editor@test.com', 'name' => 'Editor', 'role' => 'editor']);
$htmlEditor = renderView('admin/clans', [
    'clans' => $sampleClans,
    'registryReady' => true,
]);
chk(str_contains($htmlEditor, 'id="clDlg"'), "Шаблон редактора содержит диалог редактирования clDlg");
chk(str_contains($htmlEditor, 'data-act="edit"'), "Шаблон редактора содержит кнопку редактирования");
chk(str_contains($htmlEditor, 'name="uran"'), "Форма редактора содержит поле ввода uran");
chk(str_contains($htmlEditor, 'name="description"'), "Форма редактора содержит поле ввода description");
chk(str_contains($htmlEditor, 'name="summary"'), "Форма редактора содержит поле ввода summary");

@Auth::logout();

// 7. Очистка тестового рода
$db->prepare("DELETE FROM clans WHERE id = ?")->execute([$id]);
chk($svc->find($id) === null, "Тестовый род удалён");

echo $fails === 0 ? "\nALT-116: Все проверки успешно пройдены!\n" : "\nALT-116: Провалено проверок: $fails\n";
exit($fails === 0 ? 0 : 1);
