<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * Сервис историй, аудиозаписей аксакалов и архивных фотографий персон
 * (Аудио естеліктер, ақсақалдардың дауысы мен өмірбаяндық хикаялар сервисі)
 */
class MediaService
{
    protected string $storagePath;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? (dirname(__DIR__, 2) . '/storage/uploads/person_media');
        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Получить все медиа, истории и аудиозаписи персоны
     *
     * @param int $personId
     * @param int|null $currentUserId
     * @param bool $isAdmin
     * @return array{audio: list<array>, stories: list<array>, photos: list<array>, total_count: int}
     */
    public function getPersonMedia(int $personId, ?int $currentUserId = null, bool $isAdmin = false): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT m.*, u.name as uploader_name FROM person_media m LEFT JOIN users u ON m.uploaded_by = u.id WHERE m.person_id = ? ORDER BY m.created_at DESC");
        $stmt->execute([$personId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $audio = [];
        $stories = [];
        $photos = [];

        foreach ($rows as $row) {
            $isPrivate = (int)($row['is_private'] ?? 0) === 1;
            $isOwner = $currentUserId && ((int)$row['uploaded_by'] === $currentUserId);
            if ($isPrivate && !$isOwner && !$isAdmin) {
                continue;
            }

            $formatted = [
                'id'               => (int)$row['id'],
                'person_id'        => (int)$row['person_id'],
                'media_type'       => $row['media_type'],
                'title'            => $row['title'],
                'content'          => $row['content'],
                'file_name'        => $row['file_name'],
                'file_size'        => (int)($row['file_size'] ?? 0),
                'file_size_formatted' => $this->formatFileSize((int)($row['file_size'] ?? 0)),
                'mime_type'        => $row['mime_type'],
                'duration_seconds' => (int)($row['duration_seconds'] ?? 0),
                'duration_formatted' => $this->formatDuration((int)($row['duration_seconds'] ?? 0)),
                'recorded_by'      => $row['recorded_by'],
                'event_year'       => $row['event_year'],
                'is_private'       => $isPrivate,
                'uploaded_by'      => (int)$row['uploaded_by'],
                'uploader_name'    => $row['uploader_name'] ?? '',
                'created_at'       => $row['created_at'],
                'stream_url'       => in_array($row['media_type'], ['audio', 'photo'], true) ? "/media/stream?id={$row['id']}" : null,
                'download_url'     => in_array($row['media_type'], ['audio', 'photo'], true) ? "/media/stream?id={$row['id']}&download=1" : null,
            ];

            if ($row['media_type'] === 'audio') {
                $audio[] = $formatted;
            } elseif ($row['media_type'] === 'story') {
                $stories[] = $formatted;
            } elseif ($row['media_type'] === 'photo') {
                $photos[] = $formatted;
            }
        }

        return [
            'audio'       => $audio,
            'stories'     => $stories,
            'photos'      => $photos,
            'total_count' => count($audio) + count($stories) + count($photos),
        ];
    }

    /**
     * Сохранить текстовую историю / воспоминание
     */
    public function saveStory(
        int $personId,
        string $title,
        string $content,
        ?string $recordedBy = null,
        ?string $eventYear = null,
        ?int $userId = null
    ): array {
        $title = trim($title);
        $content = trim($content);

        if ($title === '') {
            throw new RuntimeException('Естелік тақырыбы толтырылмаған / Заголовок истории не может быть пустым.');
        }
        if ($content === '') {
            throw new RuntimeException('Естелік мәтіні толтырылмаған / Текст истории не может быть пустым.');
        }

        $pdo = Database::getConnection();

        // Проверяем существование персоны
        $pStmt = $pdo->prepare("SELECT id, first_name, last_name FROM persons WHERE id = ? AND is_deleted = 0");
        $pStmt->execute([$personId]);
        $person = $pStmt->fetch(PDO::FETCH_ASSOC);
        if (!$person) {
            throw new RuntimeException('Персона табылмады / Персона не найдена.');
        }

        $sql = "INSERT INTO person_media (person_id, media_type, title, content, recorded_by, event_year, uploaded_by, created_at) VALUES (?, 'story', ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $personId,
            $title,
            $content,
            $recordedBy ?: null,
            $eventYear ?: null,
            $userId ?: null,
        ]);

        $newId = (int)$pdo->lastInsertId();

        // Лог аудита
        $this->logAudit('CREATE', 'person_media', $newId, "Қосылған естелік: «{$title}» ({$person['first_name']})", null, [
            'title'       => $title,
            'person_id'   => $personId,
            'recorded_by' => $recordedBy,
        ], $userId);

        return [
            'id'          => $newId,
            'person_id'   => $personId,
            'media_type'  => 'story',
            'title'       => $title,
            'content'     => $content,
            'recorded_by' => $recordedBy,
            'event_year'  => $eventYear,
            'created_at'  => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Загрузить медиафайл (аудио или архивное фото)
     */
    public function uploadMediaFile(
        int $personId,
        array $fileData,
        string $mediaType,
        string $title,
        ?string $recordedBy = null,
        ?string $eventYear = null,
        ?int $durationSeconds = null,
        ?int $userId = null
    ): array {
        if (!in_array($mediaType, ['audio', 'photo'], true)) {
            throw new RuntimeException('Қате медиа түрі / Недопустимый тип медиа.');
        }

        $title = trim($title);
        if ($title === '') {
            $title = pathinfo($fileData['name'] ?? 'Медиа', PATHINFO_FILENAME);
        }

        $tmpName = $fileData['tmp_name'] ?? '';
        $origName = $fileData['name'] ?? 'file';
        $fileSize = (int)($fileData['size'] ?? 0);
        $error = (int)($fileData['error'] ?? UPLOAD_ERR_OK);

        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmpName)) {
            // Для тестов и локального сохранения проверяем существование обычного файла
            if (!file_exists($tmpName)) {
                throw new RuntimeException("Файлды жүктеу қатесі (код: {$error}) / Ошибка загрузки файла.");
            }
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedAudio = ['mp3', 'm4a', 'wav', 'ogg', 'aac', 'webm'];
        $allowedPhotos = ['jpg', 'jpeg', 'png', 'webp'];

        if ($mediaType === 'audio') {
            if (!in_array($ext, $allowedAudio, true)) {
                throw new RuntimeException('Қабылданбайтын аудио форматы. Рұқсат етілген: mp3, m4a, wav, ogg, webm.');
            }
            if ($fileSize > 50 * 1024 * 1024) {
                throw new RuntimeException('Аудио файл өлшемі 50 МБ-тан аспауы тиіс.');
            }
            $mimeType = $fileData['type'] ?? 'audio/mpeg';
        } else {
            if (!in_array($ext, $allowedPhotos, true)) {
                throw new RuntimeException('Қабылданбайтын сурет форматы. Рұқсат етілген: jpg, png, webp.');
            }
            if ($fileSize > 15 * 1024 * 1024) {
                throw new RuntimeException('Сурет файл өлшемі 15 МБ-тан аспауы тиіс.');
            }
            $mimeType = $fileData['type'] ?? 'image/jpeg';
        }

        // Генерируем безопасное уникальное имя файла
        $safeName = sprintf('%s_%s_%s.%s', $mediaType, bin2hex(random_bytes(8)), time(), $ext);
        $destination = $this->storagePath . DIRECTORY_SEPARATOR . $safeName;

        if (is_uploaded_file($tmpName)) {
            if (!move_uploaded_file($tmpName, $destination)) {
                throw new RuntimeException('Файлды сақтау қатесі / Не удалось сохранить файл на диске.');
            }
        } else {
            if (!copy($tmpName, $destination)) {
                throw new RuntimeException('Файлды көшіру қатесі / Не удалось скопировать файл.');
            }
        }

        $relPath = 'person_media/' . $safeName;

        $pdo = Database::getConnection();
        $sql = "INSERT INTO person_media (person_id, media_type, title, file_path, file_name, file_size, mime_type, duration_seconds, recorded_by, event_year, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $personId,
            $mediaType,
            $title,
            $relPath,
            $origName,
            $fileSize,
            $mimeType,
            $durationSeconds,
            $recordedBy ?: null,
            $eventYear ?: null,
            $userId ?: null,
        ]);

        $newId = (int)$pdo->lastInsertId();

        $this->logAudit('CREATE', 'person_media', $newId, "Жүктелген {$mediaType}: «{$title}» ({$origName})", null, [
            'person_id'  => $personId,
            'file_name'  => $origName,
            'file_size'  => $fileSize,
            'media_type' => $mediaType,
        ], $userId);

        return [
            'id'                  => $newId,
            'person_id'           => $personId,
            'media_type'          => $mediaType,
            'title'               => $title,
            'file_name'           => $origName,
            'file_size'           => $fileSize,
            'file_size_formatted' => $this->formatFileSize($fileSize),
            'mime_type'           => $mimeType,
            'duration_seconds'    => $durationSeconds,
            'recorded_by'         => $recordedBy,
            'event_year'          => $eventYear,
            'stream_url'          => "/media/stream?id={$newId}",
            'download_url'        => "/media/stream?id={$newId}&download=1",
            'created_at'          => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Удалить запись медиа (автор или админ/редактор)
     */
    public function deleteMedia(int $mediaId, ?int $userId = null, bool $isAdmin = false): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM person_media WHERE id = ?");
        $stmt->execute([$mediaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        $isOwner = $userId && ((int)$row['uploaded_by'] === $userId);
        if (!$isOwner && !$isAdmin) {
            throw new RuntimeException('Бұл жазбаны жоюға құқығыңыз жоқ / У вас нет прав на удаление этого медиа.');
        }

        // Удаляем физический файл, если есть
        if (!empty($row['file_path'])) {
            $baseStorage = dirname(__DIR__, 2) . '/storage/uploads/';
            $fullPath = $baseStorage . $row['file_path'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        $dStmt = $pdo->prepare("DELETE FROM person_media WHERE id = ?");
        $dStmt->execute([$mediaId]);

        $this->logAudit('DELETE', 'person_media', $mediaId, "Жойылған медиа: «{$row['title']}»", $row, null, $userId);

        return true;
    }

    /**
     * Отдать/стримить медиа файл клиенту с поддержкой HTTP Range (для плеера)
     */
    public function streamMedia(int $mediaId, ?int $currentUserId = null, bool $isAdmin = false, bool $forceDownload = false): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM person_media WHERE id = ?");
        $stmt->execute([$mediaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['file_path'])) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Медиа файл табылмады / Файл не найден.';
            return;
        }

        $isPrivate = (int)($row['is_private'] ?? 0) === 1;
        $isOwner = $currentUserId && ((int)$row['uploaded_by'] === $currentUserId);
        if ($isPrivate && !$isOwner && !$isAdmin) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Бұл файлға қолжетімділік жабық / Доступ к файлу ограничен.';
            return;
        }

        $baseStorage = dirname(__DIR__, 2) . '/storage/uploads/';
        $fullPath = $baseStorage . $row['file_path'];

        if (!file_exists($fullPath)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Дискіден файл табылмады / Файл на диске отсутствует.';
            return;
        }

        $fileSize = filesize($fullPath);
        $mimeType = $row['mime_type'] ?: 'application/octet-stream';
        $fileName = $row['file_name'] ?: basename($fullPath);

        $disposition = $forceDownload ? 'attachment' : 'inline';

        // Поддержка HTTP Range (критично для перемещения по таймлайну аудио HTML5)
        $range = $_SERVER['HTTP_RANGE'] ?? null;
        if ($range && preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
            $start = (int)$matches[1];
            $end = ($matches[2] !== '') ? (int)$matches[2] : ($fileSize - 1);

            if ($start > $end || $start >= $fileSize) {
                http_response_code(416);
                header("Content-Range: bytes */{$fileSize}");
                return;
            }

            $length = $end - $start + 1;

            http_response_code(206);
            header('HTTP/1.1 206 Partial Content');
            header("Content-Type: {$mimeType}");
            header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
            header("Content-Length: {$length}");
            header('Accept-Ranges: bytes');
            header("Content-Disposition: {$disposition}; filename=\"{$fileName}\"");

            $fp = fopen($fullPath, 'rb');
            fseek($fp, $start);
            $bytesSent = 0;
            while (!feof($fp) && $bytesSent < $length) {
                $buffer = fread($fp, min(1024 * 64, $length - $bytesSent));
                echo $buffer;
                flush();
                $bytesSent += strlen($buffer);
            }
            fclose($fp);
            return;
        }

        // Обычная отдача целого файла
        header("Content-Type: {$mimeType}");
        header("Content-Length: {$fileSize}");
        header('Accept-Ranges: bytes');
        header("Content-Disposition: {$disposition}; filename=\"{$fileName}\"");

        readfile($fullPath);
    }

    /**
     * Форматирование размера файла
     */
    protected function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }

    /**
     * Форматирование секунд в ММ:СС
     */
    protected function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) return '—';
        $m = floor($seconds / 60);
        $s = $seconds % 60;
        return sprintf('%02d:%02d', $m, $s);
    }

    /**
     * Запись в журнал аудита
     */
    protected function logAudit(string $action, string $table, int $id, string $summary, ?array $old, ?array $new, ?int $userId = null): void
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_table, entity_id, summary, old_values, new_values, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $userId,
                $action,
                $table,
                $id,
                $summary,
                $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
                $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            // Аудит не должен ломать основную логику
        }
    }
}
