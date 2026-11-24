<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AlbumMediaService;
use App\Services\AlbumService;
use App\Services\PhotoUploadService;
use PDO;

/**
 * TASK-022 (замечание 16.11 19:26): фото и обложка человека — прямо в окне «Өңдеу».
 *  - GET  /api/album/photos          — фото из альбомов для выбора (любой вошедший)
 *  - POST /api/person/avatar/upload  — загрузка квадратного аватара (обрезан в браузере)
 *  - POST /api/person/cover/save     — обложка: фото из альбома / загрузка / готовый фон / удалить / положение и затемнение
 * Менять может сам человек, editor и admin (как контакты).
 */
class PersonCoverController
{
    public const PRESETS = ['ornament', 'steppe', 'mountains'];

    private static ?bool $hasCols = null;

    public static function hasCoverColumns(PDO $db): bool
    {
        if (self::$hasCols === null) {
            try {
                self::$hasCols = $db->query("SHOW COLUMNS FROM `persons` LIKE 'cover_path'")->rowCount() > 0;
            } catch (\Throwable $e) {
                self::$hasCols = false;
            }
        }
        return self::$hasCols;
    }

    private function guardPost(Request $request): void
    {
        if (!Auth::check()) {
            Response::json(['success' => false, 'error' => 'Кіру қажет / Нужен вход'], 401);
        }
        if (!Session::validateCsrf((string)$request->post('csrf_token', ''))) {
            Response::json(['success' => false, 'error' => 'Сессия ескірді, бетті жаңартыңыз / Сессия устарела, обновите страницу'], 419);
        }
    }

    private function personForEdit(PDO $db, Request $request): array
    {
        $personId = (int)$request->post('person_id', 0);
        if ($personId <= 0 || !PersonContactController::canEditContacts($personId)) {
            Response::json(['success' => false, 'error' => 'Рұқсат жоқ / Нет прав на изменение'], 403);
        }
        $st = $db->prepare('SELECT * FROM persons WHERE id = ? AND is_deleted = 0 LIMIT 1');
        $st->execute([$personId]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            Response::json(['success' => false, 'error' => 'Тұлға табылмады / Персона не найдена'], 404);
        }
        return $p;
    }

    private function audit(PDO $db, int $personId, string $summary, ?array $old, ?array $new): void
    {
        $u = Auth::user();
        try {
            $db->prepare("
                INSERT INTO audit_logs
                (user_id, user_name, user_email, action, entity_table, entity_id, summary, old_values, new_values, created_at)
                VALUES (?, ?, ?, 'UPDATE', 'persons', ?, ?, ?, ?, NOW())
            ")->execute([
                $u['id'] ?? null,
                $u['name'] ?? '',
                $u['email'] ?? null,
                $personId,
                mb_substr($summary, 0, 255),
                $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
                $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            Logger::error('PersonCover audit: ' . $e->getMessage(), $e);
        }
    }

    /** Состояние обложки для ответа / окна редактирования */
    public static function coverState(array $person): array
    {
        $path = (string)($person['cover_path'] ?? '');
        return [
            'url'   => $path !== '' ? $path : null,
            'pos_y' => isset($person['cover_pos_y']) ? max(0, min(100, (int)$person['cover_pos_y'])) : 50,
            'dim'   => isset($person['cover_dim']) ? max(0, min(70, (int)$person['cover_dim'])) : 30,
        ];
    }

    /** GET /api/album/photos?offset=0 — фото альбомов (постранично по 36) для выбора аватара/обложки */
    public function photos(Request $request): void
    {
        $albums = new AlbumService();
        if (!$albums->isReady()) {
            Response::json(['success' => false, 'error' => 'Альбомдар қосылмаған / Альбомы не включены'], 503);
        }
        $offset = max(0, (int)$request->get('offset', 0));
        $limit = 36;
        $db = Database::getConnection();
        $st = $db->query("SELECT id, caption FROM album_media WHERE media_type = 'photo' ORDER BY id DESC LIMIT " . ($limit + 1) . ' OFFSET ' . $offset);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $more = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $items = array_map(static fn($r) => [
            'id'      => (int)$r['id'],
            'thumb'   => '/album/media?id=' . (int)$r['id'] . '&v=thumb',
            'full'    => '/album/media?id=' . (int)$r['id'] . '&v=full',
            'caption' => (string)($r['caption'] ?? ''),
        ], $rows);
        Response::json(['success' => true, 'items' => $items, 'more' => $more, 'next' => $offset + $limit]);
    }

    /** POST /api/person/avatar/upload: person_id, photo (квадрат, обрезан в браузере), csrf_token */
    public function avatarUpload(Request $request): void
    {
        $this->guardPost($request);
        $db = Database::getConnection();
        $person = $this->personForEdit($db, $request);
        if (empty($_FILES['photo'])) {
            Response::json(['success' => false, 'error' => 'Файл берілмеді / Файл не передан'], 400);
        }
        try {
            $user = Auth::user();
            $res = (new PhotoUploadService())->processUpload($_FILES['photo'], (int)$person['id'], !empty($user['id']) ? (int)$user['id'] : null);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], 422);
        }
        $db->prepare('UPDATE persons SET avatar_path = ?, updated_at = NOW() WHERE id = ?')->execute([$res['relative_url'], (int)$person['id']]);
        $this->audit($db, (int)$person['id'], 'Аватар жаңартылды / Аватар обновлён', ['avatar_path' => $person['avatar_path'] ?? null], ['avatar_path' => $res['relative_url']]);
        Response::json(['success' => true, 'avatar_url' => $res['relative_url']]);
    }

    /**
     * POST /api/person/cover/save: person_id, csrf_token, action = media|upload|preset|remove|adjust,
     *   media_id (media), photo (upload), preset (preset), pos_y 0..100, dim 0..70 (необязательно)
     */
    public function save(Request $request): void
    {
        $this->guardPost($request);
        $db = Database::getConnection();
        if (!self::hasCoverColumns($db)) {
            Response::json(['success' => false, 'error' => 'Миграция қолданылмаған (2026_11_16_add_person_cover) / Не применена миграция БД'], 503);
        }
        $person = $this->personForEdit($db, $request);
        $personId = (int)$person['id'];
        $old = self::coverState($person);

        $action = (string)$request->post('action', 'adjust');
        $posRaw = $request->post('pos_y', null);
        $dimRaw = $request->post('dim', null);
        $pos = is_numeric($posRaw) ? max(0, min(100, (int)$posRaw)) : $old['pos_y'];
        $dim = is_numeric($dimRaw) ? max(0, min(70, (int)$dimRaw)) : $old['dim'];
        $path = $old['url'];

        $publicDir = dirname(__DIR__, 2) . '/public/uploads/photos';
        try {
            if ($action === 'media') {
                $albums = new AlbumService();
                $m = $albums->isReady() ? $albums->getMedia((int)$request->post('media_id', 0)) : null;
                if (!$m || ($m['media_type'] ?? '') !== 'photo') {
                    Response::json(['success' => false, 'error' => 'Фото табылмады / Фото не найдено'], 404);
                }
                $path = (new AlbumMediaService())->makeCover((string)$m['file_path'], $publicDir);
                $pos = 50;
            } elseif ($action === 'upload') {
                if (empty($_FILES['photo'])) {
                    Response::json(['success' => false, 'error' => 'Файл берілмеді / Файл не передан'], 400);
                }
                $user = Auth::user();
                $res = (new PhotoUploadService())->processUpload($_FILES['photo'], $personId, !empty($user['id']) ? (int)$user['id'] : null);
                $path = $res['relative_url'];
                $pos = 50;
            } elseif ($action === 'preset') {
                $key = (string)$request->post('preset', '');
                if (!in_array($key, self::PRESETS, true)) {
                    Response::json(['success' => false, 'error' => 'Фон қате / Неизвестный фон'], 422);
                }
                $path = '/assets/img/covers/' . $key . '.svg';
                $pos = 50;
            } elseif ($action === 'remove') {
                $path = null;
                $pos = 50;
                $dim = 30;
            } elseif ($action !== 'adjust') {
                Response::json(['success' => false, 'error' => 'Әрекет қате / Неизвестное действие'], 422);
            }
        } catch (\Throwable $e) {
            Logger::error('person cover: ' . $e->getMessage(), $e);
            Response::json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        $db->prepare('UPDATE persons SET cover_path = ?, cover_pos_y = ?, cover_dim = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$path, $pos, $dim, $personId]);
        $new = ['url' => $path, 'pos_y' => $pos, 'dim' => $dim];
        if ($new !== $old) {
            $this->audit($db, $personId, 'Мұқаба / Обложка: ' . $action, ['cover' => $old], ['cover' => $new]);
        }
        Response::json(['success' => true, 'cover' => $new]);
    }
}
