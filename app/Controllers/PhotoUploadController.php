<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Services\PhotoUploadService;
use PDO;

class PhotoUploadController
{
    private PhotoUploadService $uploadService;

    public function __construct()
    {
        $this->uploadService = new PhotoUploadService();
    }

    /**
     * API загрузки фотографии для персоны
     */
    public function upload(Request $request): void
    {
        $lang = I18n::lang();

        // Проверка прав: только admin и editor
        if (!Auth::canEdit()) {
            Response::json([
                'success' => false,
                'error'   => $lang === 'ru' 
                    ? 'У вас недостаточно прав для загрузки фото. Требуется роль редактора или администратора' 
                    : 'Фото жүктеуге құқығыңыз жеткіліксіз. Редактор немесе әкімші рөлі қажет',
            ], 403);
            return;
        }

        // Проверка наличия файла
        if (empty($_FILES['photo'])) {
            Response::json([
                'success' => false,
                'error'   => $lang === 'ru' ? 'Файл фотографии не передан' : 'Фотосурет файлы берілмеді',
            ], 400);
            return;
        }

        $personId = (int)$request->post('person_id', 0);
        $setAsAvatar = !empty($request->post('set_as_avatar')) || $request->post('type') === 'avatar';

        try {
            $user = Auth::user();
            $userId = !empty($user['id']) ? (int)$user['id'] : null;

            $result = $this->uploadService->processUpload($_FILES['photo'], $personId ?: null, $userId);

            // Если указана персона, привязываем фото
            if ($personId > 0) {
                $db = Database::getConnection();

                if ($setAsAvatar) {
                    $stmt = $db->prepare("UPDATE `persons` SET `avatar_path` = ?, `updated_at` = NOW() WHERE `id` = ?");
                    $stmt->execute([$result['relative_url'], $personId]);
                }

                // Также добавляем запись в person_media
                $stmtM = $db->query("SHOW TABLES LIKE 'person_media'");
                if ($stmtM->rowCount() > 0) {
                    $stmtMedia = $db->prepare("
                        INSERT INTO `person_media` 
                        (`person_id`, `media_type`, `title`, `file_path`, `file_name`, `file_size`, `mime_type`, `uploaded_by`, `created_at`, `updated_at`)
                        VALUES (?, 'photo', ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $title = $setAsAvatar 
                        ? ($lang === 'ru' ? 'Аватар профиля' : 'Негізгі фото') 
                        : ($lang === 'ru' ? 'Семейная фотография' : 'Отбасылық фотосурет');
                    $stmtMedia->execute([
                        $personId,
                        $title,
                        $result['relative_url'],
                        $result['file_name'],
                        $result['file_size'],
                        $result['mime_type'],
                        $userId,
                    ]);
                }
            }

            Response::json([
                'success'      => true,
                'message'      => $lang === 'ru' ? 'Фотография успешно проверена и сохранена' : 'Фотосурет сәтті тексеріліп, сақталды',
                'file_name'    => $result['file_name'],
                'url'          => $result['relative_url'],
                'width'        => $result['width'],
                'height'       => $result['height'],
                'file_size'    => $result['file_size'],
                'avatar_set'   => $setAsAvatar,
            ]);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 422);
        }
    }
}
