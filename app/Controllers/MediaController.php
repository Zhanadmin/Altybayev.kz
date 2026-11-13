<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\MediaService;
use Throwable;

/**
 * Контроллер защищенного хранилища медиа, аудиоисторий и архивных фотографий
 */
class MediaController
{
    protected MediaService $service;

    public function __construct()
    {
        $this->service = new MediaService();
    }

    /**
     * API: Получить все медиа персоны
     * GET /api/person/media?person_id=ID
     */
    public function apiList(Request $request): void
    {
        $personId = (int)$request->get('person_id', 0);
        if ($personId <= 0) {
            Response::error('person_id параметрі көрсетілуі тиіс / Требуется person_id.', 400);
            return;
        }

        $user = Auth::user();
        $userId = $user ? (int)$user['id'] : null;
        $isAdmin = Auth::isAdmin();

        $data = $this->service->getPersonMedia($personId, $userId, $isAdmin);
        Response::json([
            'success'   => true,
            'person_id' => $personId,
            'data'      => $data,
        ]);
    }

    /**
     * API: Сохранить текстовую историю / воспоминание
     * POST /api/person/story/save
     */
    public function saveStory(Request $request): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::error('Авторизация қажет / Требуется авторизация.', 401);
            return;
        }

        $body = $request->json() ?: [];
        $personId = (int)($body['person_id'] ?? $request->post('person_id', 0));
        $title = (string)($body['title'] ?? $request->post('title', ''));
        $content = (string)($body['content'] ?? $request->post('content', ''));
        $recordedBy = (string)($body['recorded_by'] ?? $request->post('recorded_by', ''));
        $eventYear = (string)($body['event_year'] ?? $request->post('event_year', ''));

        if ($personId <= 0) {
            Response::error('person_id көрсетілмеген.', 400);
            return;
        }

        try {
            $story = $this->service->saveStory($personId, $title, $content, $recordedBy, $eventYear, (int)$user['id']);
            Response::success($story, 'Естелік сәтті сақталды / История успешно сохранена');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * API: Загрузить аудио или фото
     * POST /api/person/media/upload
     */
    public function upload(Request $request): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::error('Авторизация қажет / Требуется авторизация.', 401);
            return;
        }

        $personId = (int)$request->post('person_id', 0);
        $mediaType = (string)$request->post('media_type', 'audio');
        $title = (string)$request->post('title', '');
        $recordedBy = (string)$request->post('recorded_by', '');
        $eventYear = (string)$request->post('event_year', '');
        $durationSeconds = $request->post('duration_seconds') ? (int)$request->post('duration_seconds') : null;

        $fileData = $_FILES['media_file'] ?? null;
        if (!$fileData) {
            Response::error('Жүктеу үшін файл таңдалмаған / Файл не выбран.', 400);
            return;
        }

        if ($personId <= 0) {
            Response::error('person_id көрсетілмеген.', 400);
            return;
        }

        try {
            $result = $this->service->uploadMediaFile(
                $personId,
                $fileData,
                $mediaType,
                $title,
                $recordedBy,
                $eventYear,
                $durationSeconds,
                (int)$user['id']
            );
            Response::success($result, 'Файл сәтті жүктелді / Файл успешно загружен');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * API: Удалить медиа
     * POST /api/person/media/delete
     */
    public function delete(Request $request): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::error('Авторизация қажет.', 401);
            return;
        }

        $body = $request->json() ?: [];
        $mediaId = (int)($body['media_id'] ?? $request->post('media_id', 0));
        if ($mediaId <= 0) {
            Response::error('media_id көрсетілмеген.', 400);
            return;
        }

        try {
            $this->service->deleteMedia($mediaId, (int)$user['id'], Auth::isAdmin());
            Response::success([], 'Жазба жойылды / Запись успешно удалена');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 403);
        }
    }

    /**
     * Стриминг аудио или отдача защищенного фото
     * GET /media/stream?id=ID[&download=1]
     */
    public function stream(Request $request): void
    {
        $mediaId = (int)$request->get('id', 0);
        $download = (bool)$request->get('download', false);

        if ($mediaId <= 0) {
            http_response_code(400);
            echo 'Қате сұраныс / Некорректный ID.';
            return;
        }

        $user = Auth::user();
        $userId = $user ? (int)$user['id'] : null;
        $isAdmin = Auth::isAdmin();

        $this->service->streamMedia($mediaId, $userId, $isAdmin, $download);
    }
}
