<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * TASK-019: распознавание лиц в фотоальбомах — серверная часть.
 *
 * Замечание владельца 16.11 20:49: отдельного экрана «Лица» и групп нет.
 * Эталон лица человека — его аватарка. Браузер (face-api.js, модели — локальные файлы сайта)
 * считает отпечаток лица с аватарки и отпечатки лиц на фото альбомов; сервер только хранит
 * рамки и векторы и сравнивает их с эталонами:
 *  - близко (distance < AUTO_DISTANCE) — человек отмечается на фото сам (album_media_people, source='face');
 *  - сомнительно (< ASK_DISTANCE) — на фото вопрос «Это X?», отмечается только после «Да»;
 *  - «Нет» запоминается (rejected_person_id), этому лицу этот человек больше не предлагается.
 * Отпечатки наружу не отдаются (ни в API, ни в экспорт). Приватность: общий переключатель
 * и запрет распознавания конкретного человека (его отпечатки удаляются).
 */
class FaceRecognitionService
{
    private PDO $db;

    public const DESCRIPTOR_LENGTH = 128;
    /** Евклидово расстояние face-api: < 0.47 — уверенно тот же человек */
    public const AUTO_DISTANCE = 0.47;
    /** < 0.58 — похоже, спрашиваем «Это X?» */
    public const ASK_DISTANCE = 0.58;
    private const MAX_FACES_PER_MEDIA = 50;
    private const AVATAR_RE = '#^/uploads/photos/[A-Za-z0-9._-]+$#';

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /** Применены обе миграции (2026_11_16_add_face_recognition + 2026_11_16_face_avatar_reference)? */
    public function isReady(): bool
    {
        try {
            $st = $this->db->query("SHOW TABLES LIKE 'face_references'");
            return $st !== false && $st->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ======================================================= приватность

    public function enabled(): bool
    {
        try {
            return $this->getSetting('face_recognition_enabled', '1') === '1';
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function setEnabled(bool $on): void
    {
        $this->setSetting('face_recognition_enabled', $on ? '1' : '0');
    }

    public function personOptedOut(int $personId): bool
    {
        $st = $this->db->prepare('SELECT face_opt_out FROM persons WHERE id = ?');
        $st->execute([$personId]);
        return (int)$st->fetchColumn() === 1;
    }

    /** Запрет/разрешение распознавания человека; при запрете все его отпечатки удаляются */
    public function setPersonOptOut(int $personId, bool $out): int
    {
        $this->db->prepare('UPDATE persons SET face_opt_out = ? WHERE id = ?')->execute([$out ? 1 : 0, $personId]);
        return $out ? $this->deletePersonData($personId) : 0;
    }

    /**
     * Удалить все отпечатки человека: эталон с аватарки, его лица на фото и отметки, поставленные распознаванием.
     * Ручные отметки не трогаются. Если запрета нет — эталон посчитается заново при следующем открытии альбома.
     */
    public function deletePersonData(int $personId): int
    {
        $st = $this->db->prepare('SELECT DISTINCT media_id FROM face_embeddings WHERE person_id = ?');
        $st->execute([$personId]);
        $mediaIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

        $del = $this->db->prepare('DELETE FROM face_embeddings WHERE person_id = ?');
        $del->execute([$personId]);
        $count = $del->rowCount();

        $ref = $this->db->prepare('DELETE FROM face_references WHERE person_id = ?');
        $ref->execute([$personId]);
        $count += $ref->rowCount();

        $this->db->prepare('DELETE FROM album_media_people WHERE person_id = ? AND source = "face"')->execute([$personId]);
        foreach ($mediaIds as $m) {
            $this->syncMediaTags($m);
        }
        return $count;
    }

    // ======================================================= эталоны (аватарки)

    /**
     * Люди, у которых есть аватар, но нет актуального отпечатка (нет совсем или аватар сменили).
     * @return list<array{person_id:int, avatar:string}>
     */
    public function referencesNeeded(int $limit = 300, ?array $onlyPersonIds = null): array
    {
        $sql = 'SELECT p.id, p.avatar_path FROM persons p
                LEFT JOIN face_references fr ON fr.person_id = p.id
                WHERE p.is_deleted = 0 AND p.face_opt_out = 0
                  AND p.avatar_path IS NOT NULL AND p.avatar_path <> ""
                  AND (fr.person_id IS NULL OR fr.avatar_path <> p.avatar_path)';
        $params = [];
        if ($onlyPersonIds !== null) {
            $onlyPersonIds = array_values(array_filter(array_map('intval', $onlyPersonIds), fn($v) => $v > 0));
            if (!$onlyPersonIds) {
                return [];
            }
            $sql .= ' AND p.id IN (' . implode(',', array_fill(0, count($onlyPersonIds), '?')) . ')';
            $params = $onlyPersonIds;
        }
        $sql .= ' ORDER BY p.id LIMIT ' . max(1, min(1000, $limit));
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $path = (string)$r['avatar_path'];
            if (!preg_match(self::AVATAR_RE, $path)) {
                continue; // чужие/странные пути не отдаём браузеру
            }
            $out[] = ['person_id' => (int)$r['id'], 'avatar' => $path];
        }
        return $out;
    }

    /**
     * Сохранить отпечатки с аватарок. avatar должен совпадать с текущим аватаром человека
     * (иначе отпечаток устарел — пропускаем). descriptor = null — на аватаре лицо не найдено.
     * @param list<array{person_id:int, avatar:string, descriptor:?array}> $refs
     * @param callable(int):bool $mayWrite проверка права на конкретного человека
     * @return array{saved:int, skipped:int}
     */
    public function saveReferences(array $refs, ?int $userId, callable $mayWrite): array
    {
        if (!$this->enabled()) {
            throw new RuntimeException('face_recognition_disabled');
        }
        $saved = 0;
        $skipped = 0;
        $cur = $this->db->prepare('SELECT avatar_path, face_opt_out FROM persons WHERE id = ? AND is_deleted = 0');
        $ins = $this->db->prepare(
            'INSERT INTO face_references (person_id, avatar_path, descriptor, created_by) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE avatar_path = VALUES(avatar_path), descriptor = VALUES(descriptor),
                                     created_by = VALUES(created_by), updated_at = NOW()'
        );
        foreach (array_slice(array_values($refs), 0, 50) as $r) {
            if (!is_array($r)) {
                $skipped++;
                continue;
            }
            $pid = (int)($r['person_id'] ?? 0);
            $avatar = is_string($r['avatar'] ?? null) ? $r['avatar'] : '';
            if ($pid <= 0 || !$mayWrite($pid)) {
                $skipped++;
                continue;
            }
            $cur->execute([$pid]);
            $row = $cur->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int)$row['face_opt_out'] === 1 || (string)$row['avatar_path'] !== $avatar || $avatar === '') {
                $skipped++;
                continue;
            }
            $desc = self::cleanDescriptor($r['descriptor'] ?? null);
            $ins->execute([$pid, $avatar, $desc === null ? null : json_encode($desc), $userId]);
            $saved++;
        }
        if ($saved > 0) {
            $this->rematchAll();
        }
        return ['saved' => $saved, 'skipped' => $skipped];
    }

    // ======================================================= лица на фото

    /**
     * Сохранить найденные лица одного фото (повторный поиск заменяет прежние рамки;
     * ответы «Да»/«Нет» переносятся на ту же рамку). Сразу сравнивает с эталонами.
     * @param array $faces список ['x','y','w','h','descriptor'=>list<float>] (рамка в долях 0..1)
     */
    public function saveFaces(int $mediaId, array $faces, ?int $userId): void
    {
        $media = $this->getMedia($mediaId);
        if (!$media || $media['media_type'] !== 'photo') {
            throw new RuntimeException('media_not_found');
        }
        if (!$this->enabled()) {
            throw new RuntimeException('face_recognition_disabled');
        }
        $faces = $this->sanitizeFaces($faces);

        // прежние ответы людей: подтверждённые и «это не он»
        $st = $this->db->prepare(
            'SELECT person_id, status, rejected_person_id, box_x, box_y, box_w, box_h FROM face_embeddings
             WHERE media_id = ? AND (status = "confirmed" OR rejected_person_id IS NOT NULL)'
        );
        $st->execute([$mediaId]);
        $old = $st->fetchAll(PDO::FETCH_ASSOC);

        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM face_embeddings WHERE media_id = ?')->execute([$mediaId]);
            $ins = $this->db->prepare(
                'INSERT INTO face_embeddings (media_id, cluster_id, person_id, box_x, box_y, box_w, box_h, descriptor, status, rejected_person_id, created_by)
                 VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($faces as $f) {
                $personId = null;
                $status = 'unlabeled';
                $rejected = null;
                foreach ($old as $o) {
                    if (self::iou([$f['x'], $f['y'], $f['w'], $f['h']], [(float)$o['box_x'], (float)$o['box_y'], (float)$o['box_w'], (float)$o['box_h']]) < 0.5) {
                        continue;
                    }
                    if ($o['status'] === 'confirmed' && $o['person_id'] && !$this->personOptedOut((int)$o['person_id'])) {
                        $personId = (int)$o['person_id'];
                        $status = 'confirmed';
                    }
                    if ($o['rejected_person_id']) {
                        $rejected = (int)$o['rejected_person_id'];
                    }
                }
                $ins->execute([
                    $mediaId, $personId, $f['x'], $f['y'], $f['w'], $f['h'],
                    json_encode($f['descriptor']), $status, $rejected, $userId,
                ]);
            }
            $this->db->prepare('UPDATE album_media SET faces_scanned_at = NOW() WHERE id = ?')->execute([$mediaId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->resolveMedia($mediaId, $this->loadReferences());
    }

    /**
     * Рамки лиц на фото для показа: только найденные люди (имя при наведении) и, если можно отвечать, вопросы «Это X?».
     * Векторы не отдаются.
     */
    public function facesForMedia(int $mediaId, bool $withQuestions): array
    {
        $st = $this->db->prepare(
            'SELECT fe.id, fe.person_id, fe.status, fe.box_x, fe.box_y, fe.box_w, fe.box_h, p.first_name, p.last_name
             FROM face_embeddings fe
             JOIN persons p ON p.id = fe.person_id AND p.is_deleted = 0 AND p.face_opt_out = 0
             WHERE fe.media_id = ? AND fe.status IN ("labeled", "confirmed", "suggested")
             ORDER BY fe.box_x'
        );
        $st->execute([$mediaId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($r['status'] === 'suggested' && !$withQuestions) {
                continue;
            }
            $out[] = [
                'id'        => (int)$r['id'],
                'person_id' => (int)$r['person_id'],
                'name'      => AlbumService::personName($r),
                'ask'       => $r['status'] === 'suggested',
                'box'       => [round((float)$r['box_x'], 4), round((float)$r['box_y'], 4), round((float)$r['box_w'], 4), round((float)$r['box_h'], 4)],
            ];
        }
        return $out;
    }

    /** Отметки людей на фото (как в альбоме: id + имя) — чтобы браузер обновил подпись под фото */
    public function mediaPeople(int $mediaId): array
    {
        $st = $this->db->prepare(
            'SELECT p.id, p.first_name, p.last_name FROM album_media_people amp
             JOIN persons p ON p.id = amp.person_id AND p.is_deleted = 0
             WHERE amp.media_id = ? ORDER BY p.first_name'
        );
        $st->execute([$mediaId]);
        return array_map(fn($r) => ['id' => (int)$r['id'], 'name' => AlbumService::personName($r)], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getFace(int $id): ?array
    {
        $st = $this->db->prepare('SELECT id, media_id, person_id, status FROM face_embeddings WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** «Да, это он» — лицо подтверждено, фото попадает в карточку человека */
    public function confirmFace(int $faceId): int
    {
        $face = $this->getFace($faceId);
        if (!$face || !$face['person_id'] || !in_array($face['status'], ['suggested', 'labeled', 'confirmed'], true)) {
            throw new RuntimeException('face_not_found');
        }
        $pid = (int)$face['person_id'];
        if ($this->personOptedOut($pid)) {
            throw new RuntimeException('person_opt_out');
        }
        $mediaId = (int)$face['media_id'];
        // один человек — одно лицо на фото: с других лиц этого фото он снимается
        $this->db->prepare(
            'UPDATE face_embeddings SET person_id = NULL, status = "unlabeled", match_distance = NULL
             WHERE media_id = ? AND person_id = ? AND id <> ?'
        )->execute([$mediaId, $pid, $faceId]);
        $this->db->prepare('UPDATE face_embeddings SET status = "confirmed" WHERE id = ?')->execute([$faceId]);
        $this->resolveMedia($mediaId, $this->loadReferences());
        return $mediaId;
    }

    /** «Нет, это не он» — снять человека с лица; этому лицу он больше не предлагается */
    public function rejectFace(int $faceId): int
    {
        $face = $this->getFace($faceId);
        if (!$face || !$face['person_id']) {
            throw new RuntimeException('face_not_found');
        }
        $this->db->prepare(
            'UPDATE face_embeddings SET rejected_person_id = person_id, person_id = NULL, status = "unlabeled", match_distance = NULL WHERE id = ?'
        )->execute([$faceId]);
        $mediaId = (int)$face['media_id'];
        $this->resolveMedia($mediaId, $this->loadReferences());
        return $mediaId;
    }

    /**
     * Состояние альбома для браузера: какие фото ещё не проверены, у каких есть вопросы.
     * @return array{unscanned:list<int>, questions:list<int>}
     */
    public function albumState(int $albumId): array
    {
        $st = $this->db->prepare('SELECT id FROM album_media WHERE album_id = ? AND media_type = "photo" AND faces_scanned_at IS NULL ORDER BY id');
        $st->execute([$albumId]);
        $unscanned = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

        $q = $this->db->prepare(
            'SELECT DISTINCT fe.media_id FROM face_embeddings fe
             JOIN album_media am ON am.id = fe.media_id
             JOIN persons p ON p.id = fe.person_id AND p.is_deleted = 0 AND p.face_opt_out = 0
             WHERE am.album_id = ? AND fe.status = "suggested" ORDER BY fe.media_id'
        );
        $q->execute([$albumId]);
        return ['unscanned' => $unscanned, 'questions' => array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN))];
    }

    // ======================================================= сравнение с эталонами

    /** Пересравнить все неподтверждённые лица со всеми эталонами (после новых/сменённых аватаров) */
    public function rematchAll(): void
    {
        $refs = $this->loadReferences();
        $st = $this->db->query('SELECT DISTINCT media_id FROM face_embeddings WHERE status IN ("unlabeled", "labeled", "suggested", "confirmed")');
        foreach (array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)) as $mediaId) {
            $this->resolveMedia($mediaId, $refs);
        }
    }

    /** @return array<int, list<float>> person_id => отпечаток (только актуальные аватары, без запрета) */
    private function loadReferences(): array
    {
        $st = $this->db->query(
            'SELECT fr.person_id, fr.descriptor FROM face_references fr
             JOIN persons p ON p.id = fr.person_id
             WHERE p.is_deleted = 0 AND p.face_opt_out = 0 AND fr.descriptor IS NOT NULL AND fr.avatar_path = p.avatar_path'
        );
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $d = self::cleanDescriptor(json_decode((string)$r['descriptor'], true));
            if ($d !== null) {
                $out[(int)$r['person_id']] = $d;
            }
        }
        return $out;
    }

    /** Сравнить лица одного фото с эталонами и обновить отметки фото */
    private function resolveMedia(int $mediaId, array $refs): void
    {
        $st = $this->db->prepare(
            'SELECT id, person_id, status, match_distance, rejected_person_id, descriptor FROM face_embeddings
             WHERE media_id = ? AND status IN ("unlabeled", "labeled", "suggested", "confirmed")'
        );
        $st->execute([$mediaId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $faces = [];
        $taken = [];
        foreach ($rows as $r) {
            if ($r['status'] === 'confirmed' && $r['person_id']) {
                $taken[] = (int)$r['person_id'];
                continue;
            }
            $d = self::cleanDescriptor(json_decode((string)$r['descriptor'], true));
            if ($d === null) {
                continue;
            }
            $faces[(int)$r['id']] = ['d' => $d, 'rej' => $r['rejected_person_id'] ? (int)$r['rejected_person_id'] : null];
        }
        $result = self::assign($faces, $refs, $taken);

        $upd = $this->db->prepare('UPDATE face_embeddings SET person_id = ?, status = ?, match_distance = ? WHERE id = ?');
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            if (!isset($result[$id])) {
                continue;
            }
            $n = $result[$id];
            $same = (int)($r['person_id'] ?? 0) === (int)($n['person'] ?? 0) && $r['status'] === $n['status']
                && abs((float)($r['match_distance'] ?? -1) - (float)($n['dist'] ?? -1)) < 1e-4;
            if (!$same) {
                $upd->execute([$n['person'], $n['status'], $n['dist'], $id]);
            }
        }
        $this->syncMediaTags($mediaId);
    }

    /**
     * Чистое сопоставление (без БД, покрыто тестом): каждому лицу — ближайший эталон,
     * один человек — не больше одного лица на фото (жадно по расстоянию).
     * @param array<int, array{d:list<float>, rej:?int}> $faces
     * @param array<int, list<float>> $refs
     * @param list<int> $taken люди, уже подтверждённые на этом фото
     * @return array<int, array{person:?int, dist:?float, status:string}>
     */
    public static function assign(array $faces, array $refs, array $taken = []): array
    {
        $pairs = [];
        foreach ($faces as $fid => $f) {
            foreach ($refs as $pid => $ref) {
                if ($f['rej'] !== null && $f['rej'] === (int)$pid) {
                    continue;
                }
                $dist = self::distance($f['d'], $ref);
                if ($dist < self::ASK_DISTANCE) {
                    $pairs[] = [$dist, (int)$fid, (int)$pid];
                }
            }
        }
        usort($pairs, fn($a, $b) => $a[0] <=> $b[0]);
        $out = [];
        foreach (array_keys($faces) as $fid) {
            $out[(int)$fid] = ['person' => null, 'dist' => null, 'status' => 'unlabeled'];
        }
        $usedPerson = array_fill_keys(array_map('intval', $taken), true);
        $usedFace = [];
        foreach ($pairs as [$dist, $fid, $pid]) {
            if (isset($usedFace[$fid]) || isset($usedPerson[$pid])) {
                continue;
            }
            $usedFace[$fid] = true;
            $usedPerson[$pid] = true;
            $out[$fid] = [
                'person' => $pid,
                'dist'   => round($dist, 4),
                'status' => $dist < self::AUTO_DISTANCE ? 'labeled' : 'suggested',
            ];
        }
        return $out;
    }

    /** Отметки фото от распознавания = люди с уверенными/подтверждёнными лицами; ручные отметки не трогаются */
    private function syncMediaTags(int $mediaId): void
    {
        $st = $this->db->prepare(
            'SELECT DISTINCT fe.person_id FROM face_embeddings fe
             JOIN persons p ON p.id = fe.person_id AND p.is_deleted = 0 AND p.face_opt_out = 0
             WHERE fe.media_id = ? AND fe.status IN ("labeled", "confirmed")'
        );
        $st->execute([$mediaId]);
        $want = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

        $cur = $this->db->prepare('SELECT person_id FROM album_media_people WHERE media_id = ? AND source = "face"');
        $cur->execute([$mediaId]);
        $have = array_map('intval', $cur->fetchAll(PDO::FETCH_COLUMN));

        $del = $this->db->prepare('DELETE FROM album_media_people WHERE media_id = ? AND person_id = ? AND source = "face"');
        foreach (array_diff($have, $want) as $pid) {
            $del->execute([$mediaId, $pid]);
        }
        $ins = $this->db->prepare('INSERT IGNORE INTO album_media_people (media_id, person_id, source) VALUES (?, ?, "face")');
        foreach (array_diff($want, $have) as $pid) {
            $ins->execute([$mediaId, $pid]); // уже есть ручная отметка — остаётся ручной
        }
    }

    // ======================================================= вспомогательные

    private function sanitizeFaces(array $faces): array
    {
        $out = [];
        foreach (array_slice(array_values($faces), 0, self::MAX_FACES_PER_MEDIA) as $f) {
            if (!is_array($f)) {
                continue;
            }
            $d = self::cleanDescriptor($f['descriptor'] ?? null);
            if ($d === null) {
                continue;
            }
            $vals = [];
            foreach (['x', 'y', 'w', 'h'] as $k) {
                $v = $f[$k] ?? null;
                $vals[$k] = is_int($v) || is_float($v) || (is_string($v) && is_numeric($v)) ? (float)$v : -1.0;
            }
            if ($vals['w'] <= 0 || $vals['h'] <= 0 || $vals['x'] < 0 || $vals['y'] < 0) {
                continue;
            }
            $x = min(1.0, $vals['x']);
            $y = min(1.0, $vals['y']);
            $w = min($vals['w'], 1.0 - $x);
            $h = min($vals['h'], 1.0 - $y);
            if ($w <= 0.001 || $h <= 0.001) {
                continue;
            }
            $out[] = ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'descriptor' => $d];
        }
        return $out;
    }

    /** 128 конечных чисел или null */
    public static function cleanDescriptor(mixed $d): ?array
    {
        if (!is_array($d) || count($d) !== self::DESCRIPTOR_LENGTH) {
            return null;
        }
        $out = [];
        foreach (array_values($d) as $v) {
            if (!is_int($v) && !is_float($v)) {
                return null;
            }
            $v = (float)$v;
            if (!is_finite($v) || abs($v) > 10) {
                return null;
            }
            $out[] = round($v, 6);
        }
        return $out;
    }

    public static function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }
        return $dot / (sqrt($na) * sqrt($nb));
    }

    public static function distance(array $a, array $b): float
    {
        $s = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $t = $a[$i] - $b[$i];
            $s += $t * $t;
        }
        return sqrt($s);
    }

    /** Пересечение рамок (доли 0..1) / объединение */
    public static function iou(array $a, array $b): float
    {
        $x1 = max($a[0], $b[0]);
        $y1 = max($a[1], $b[1]);
        $x2 = min($a[0] + $a[2], $b[0] + $b[2]);
        $y2 = min($a[1] + $a[3], $b[1] + $b[3]);
        $inter = max(0.0, $x2 - $x1) * max(0.0, $y2 - $y1);
        $union = $a[2] * $a[3] + $b[2] * $b[3] - $inter;
        return $union > 0 ? $inter / $union : 0.0;
    }

    private function getMedia(int $id): ?array
    {
        $st = $this->db->prepare('SELECT id, album_id, media_type FROM album_media WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    private function getSetting(string $key, string $default): string
    {
        $st = $this->db->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ?');
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return $v === false ? $default : (string)$v;
    }

    private function setSetting(string $key, string $value): void
    {
        $this->db->prepare(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        )->execute([$key, $value]);
    }
}
