<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\GedcomImportService;
use App\Services\GedcomParserService;

/**
 * Контроллер административной панели для импорта GEDCOM и управления откатами
 * 
 * Доступ строго ограничен ролью admin (Жандос)
 */
class AdminImportController
{
    protected GedcomParserService $parser;
    protected GedcomImportService $importer;

    public function __construct()
    {
        $this->parser = new GedcomParserService();
        $this->importer = new GedcomImportService();
    }

    /**
     * Главная страница модуля импорта GEDCOM
     */
    public function index(Request $request): void
    {
        $user = Auth::user();
        $importDir = __DIR__ . '/../../import';
        $availableFiles = [];

        if (is_dir($importDir)) {
            $files = scandir($importDir);
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') {
                    continue;
                }
                if (str_ends_with(strtolower($f), '.ged')) {
                    $fullPath = $importDir . '/' . $f;
                    $availableFiles[] = [
                        'name' => $f,
                        'path' => $fullPath,
                        'size' => filesize($fullPath),
                        'size_formatted' => round(filesize($fullPath) / 1024, 1) . ' KB',
                        'modified_at' => date('Y-m-d H:i:s', filemtime($fullPath)),
                    ];
                }
            }
        }

        $history = $this->importer->getImportHistory(15);
        $cachedPreview = Session::get('gedcom_preview');

        Response::view('admin/import', [
            'user' => $user,
            'availableFiles' => $availableFiles,
            'history' => $history,
            'preview' => $cachedPreview,
            'successMessage' => $request->get('msg'),
            'errorMessage' => $request->get('error'),
        ]);
    }

    /**
     * Формирование предпросмотра GEDCOM (через AJAX или прямой запрос)
     */
    public function preview(Request $request): void
    {
        $user = Auth::user();
        $fileName = $request->get('file');
        $importDir = __DIR__ . '/../../import';

        // 1. Проверяем, передан ли файл из папки import/
        $targetFile = null;
        if ($fileName) {
            // Защита от Directory Traversal
            $cleanName = basename($fileName);
            $candidate = $importDir . '/' . $cleanName;
            if (file_exists($candidate)) {
                $targetFile = $candidate;
            }
        }

        // 2. Проверяем загрузку файла через форму
        if (!$targetFile && isset($_FILES['gedcom_file']) && $_FILES['gedcom_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedTmp = $_FILES['gedcom_file']['tmp_name'];
            $origName = basename($_FILES['gedcom_file']['name']);
            if (!str_ends_with(strtolower($origName), '.ged')) {
                if ($request->isAjax()) {
                    Response::error('Тек .ged файлдарын жүктеуге болады / Разрешены только файлы с расширением .ged', 400);
                }
                Response::redirect('/admin/import?error=invalid_extension');
            }

            if (!is_dir($importDir)) {
                mkdir($importDir, 0755, true);
            }
            $targetFile = $importDir . '/' . $origName;
            move_uploaded_file($uploadedTmp, $targetFile);
        }

        // Если файл не выбран, берем стандартный Altubaiev - 2010-05-27.ged
        if (!$targetFile) {
            $defaultFile = $importDir . '/Altubaiev - 2010-05-27.ged';
            if (file_exists($defaultFile)) {
                $targetFile = $defaultFile;
            }
        }

        if (!$targetFile || !file_exists($targetFile)) {
            if ($request->isAjax()) {
                Response::error('GEDCOM файлы табылмады / Файл GEDCOM не найден', 404);
            }
            Response::redirect('/admin/import?error=file_not_found');
        }

        try {
            $parsed = $this->parser->parseFile($targetFile);
            Session::set('gedcom_preview', $parsed);

            if ($request->isAjax()) {
                Response::json([
                    'success' => true,
                    'preview' => $parsed,
                ]);
            }

            Response::view('admin/import', [
                'user' => $user,
                'availableFiles' => $this->getAvailableFiles($importDir),
                'history' => $this->importer->getImportHistory(15),
                'preview' => $parsed,
                'activeFile' => basename($targetFile),
                'successMessage' => 'Файл сәтті талданды! Предпросмотр дайын. / Файл успешно проанализирован! Предпросмотр готов.',
            ]);
        } catch (\Throwable $e) {
            if ($request->isAjax()) {
                Response::error('Парсинг қатесі / Ошибка парсинга: ' . $e->getMessage(), 500);
            }
            Response::redirect('/admin/import?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Выполнение импорта в базу данных (одной транзакцией)
     * Доступно Жандосу по кнопке «Импортировать»
     */
    public function execute(Request $request): void
    {
        $user = Auth::user();
        $userId = (int)($user['id'] ?? 1);
        $userName = (string)($user['name'] ?? 'Жандос Алтыбаев');
        $userEmail = (string)($user['email'] ?? 'zhandos.alt06@gmail.com');

        $previewData = Session::get('gedcom_preview');
        $fileName = $request->post('file') ?? $request->get('file');

        if (!$previewData) {
            $importDir = __DIR__ . '/../../import';
            $targetFile = $importDir . '/' . ($fileName ? basename($fileName) : 'Altubaiev - 2010-05-27.ged');
            if (file_exists($targetFile)) {
                $previewData = $this->parser->parseFile($targetFile);
            }
        }

        if (!$previewData || empty($previewData['persons'])) {
            if ($request->isAjax()) {
                Response::error('Импорттауға арналған деректер табылмады. Алдымен предпросмотрды ашыңыз. / Нет данных для импорта. Сначала откройте предпросмотр.', 400);
            }
            Response::redirect('/admin/import?error=no_data');
        }

        try {
            $result = $this->importer->executeImport($previewData, $userId, $userName, $userEmail);
            Session::remove('gedcom_preview');

            if ($request->isAjax()) {
                Response::json($result);
            }

            $msg = urlencode($result['message']);
            Response::redirect("/admin/import?msg={$msg}&batch={$result['batch_id']}");
        } catch (\Throwable $e) {
            if ($request->isAjax()) {
                Response::error('Импорт кезінде қате орын алды / Ошибка при импорте: ' . $e->getMessage(), 500);
            }
            Response::redirect('/admin/import?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Откат выполненного импорта (ROLLBACK)
     */
    public function rollback(Request $request): void
    {
        $user = Auth::user();
        $userId = (int)($user['id'] ?? 1);
        $userName = (string)($user['name'] ?? 'Жандос Алтыбаев');
        $userEmail = (string)($user['email'] ?? 'zhandos.alt06@gmail.com');

        $batchId = $request->post('batch_id') ?? $request->get('batch_id');

        if (!$batchId) {
            if ($request->isAjax()) {
                Response::error('Пакет көрсетілмеген / Пакет не указан', 400);
            }
            Response::redirect('/admin/import?error=missing_batch');
        }

        try {
            $res = $this->importer->rollback($batchId, $userId, $userName, $userEmail);

            if ($request->isAjax()) {
                Response::json($res);
            }

            $msg = urlencode($res['message']);
            Response::redirect("/admin/import?msg={$msg}");
        } catch (\Throwable $e) {
            if ($request->isAjax()) {
                Response::error('Откат кезінде қате шықты / Ошибка при откате: ' . $e->getMessage(), 500);
            }
            Response::redirect('/admin/import?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Очистка сессионного кэша предпросмотра
     */
    public function clearPreview(Request $request): void
    {
        Session::remove('gedcom_preview');
        Response::redirect('/admin/import');
    }

    private function getAvailableFiles(string $dir): array
    {
        $list = [];
        if (is_dir($dir)) {
            foreach (scandir($dir) as $f) {
                if (str_ends_with(strtolower($f), '.ged')) {
                    $fullPath = $dir . '/' . $f;
                    $list[] = [
                        'name' => $f,
                        'path' => $fullPath,
                        'size' => filesize($fullPath),
                        'size_formatted' => round(filesize($fullPath) / 1024, 1) . ' KB',
                        'modified_at' => date('Y-m-d H:i:s', filemtime($fullPath)),
                    ];
                }
            }
        }
        return $list;
    }
}
