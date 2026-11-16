<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * TASK-019: распознавание лиц в фотоальбомах — серверная часть.
 *
 * Распознавание выполняется в браузере (face-api.js, модели локально). Сервер только:
 *  - хранит координаты рамки и вектор-отпечаток (не показывает и не экспортирует их);
 *  - группирует похожие отпечатки в кластеры (косинусное сходство);
 *  - по подписи кластера привязывает все фото группы к карточке человека;
 *  - соблюдает приватность: глобальный переключатель и запрет на конкретного человека.
 */
class FaceRecognitionService
{
    private PDO $db;

    private const DESCRIPTOR_LENGTH = 128;
    private const CLUSTER_THRESHOLD = 0.55; // косинусное сходство >= порога → тот же человек
    private const MAX_FACES_PER_MEDIA = 50;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /** Таблицы созданы миграцией 2026_11_16_add_face_recognition.sql? */
    public function isReady(): bool
    {
        try {
            $st = $this->db->query("SHOW TABLES LIKE 'face_embeddings'");
            return $st !== false && $st->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ======================================================= приватность

    public function enabled(): bool
    {
        return $this->getSetting('face_recognition_enabled', '1') === '1';
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

    /** Запрет/разрешение распознавания конкретного человека; при запрете отпечатки удаляются */
    public function setPersonOptOut(int $personId, bool $out): int
    {
        $this->db->prepare('UPDATE persons SET face_opt_out = ? WHERE id = ?')->execute([$out ? 1 : 0, $personId]);
        return $out ? $this->deletePersonEmbeddings($personId) : 0;
    }

    // ======================================================= сохранение и кластеризация

    /**
     * Сохранить найденные лица одного фото (перезаписывает предыдущий прогон).
     * @param array $faces список ['x','y','w','h','descriptor'=>list<float>] (рамка 0..1)
     * @return array{saved:int, faces:list<array>, suggestions:list<array>}
     */
    public function saveFaces(int $mediaId, array $faces, ?int $userId): array
    {
        $media = $this->getMedia($mediaId);
        if (!$media) {
            throw new RuntimeException('media_not_found');
        }
        if (!$this->enabled()) {
            throw new RuntimeException('face_recognition_disabled');
        }

        $faces = $this->sanitizeFaces($faces);
        $this->clearMediaFaces($mediaId);

        $result = ['saved' => 0, 'faces' => [], 'suggestions' => []];
        foreach ($faces as $f) {
            $clusterId = $this->assignToCluster($f['descriptor']);
            $personId = $this->clusterPersonId($clusterId);
            $person = $personId ? $this->personRow($personId) : null;
            if ($person && $this->personOptedOut($personId)) {
                // распознавание этого человека запрещено — сохраняем без привязки
                $person = null;
                $personId = 0;
            }
            if ($personId > 0 && $person) {
                $this->tagMedia($mediaId, $personId, 'face');
            }
            $ins = $this->db->prepare(
                'INSERT INTO face_embeddings (media_id, cluster_id, person_id, box_x, box_y, box_w, box_h, descriptor, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([
                $mediaId,
                $clusterId,
                $personId > 0 ? $personId : null,
                $f['x'], $f['y'], $f['w'], $f['h'],
                json_encode($f['descriptor'], JSON_UNESCAPED_UNICODE),
                $personId > 0 ? 'labeled' : 'unlabeled',
                $userId,
            ]);
            $faceId = (int)$this->db->lastInsertId();
            $result['saved']++;
            $row = [
                'id'         => $faceId,
                'cluster_id' => $clusterId,
                'person_id'  => $personId > 0 ? $personId : null,
                'name'       => $person ? AlbumService::personName($person) : null,
            ];
            $result['faces'][] = $row;
            if ($personId > 0 && $person) {
                $result['suggestions'][] = [
                    'face_id'   => $faceId,
                    'person_id' => $personId,
                    'name'      => AlbumService::personName($person),
                ];
            }
        }
        return $result;
    }

    /** Список кластеров для экрана «Лица»: неподписанные группы + подписанные люди */
    public function summary(): array
    {
        $st = $this->db->query(
            'SELECT fe.id, fe.media_id, fe.cluster_id, fe.person_id, fe.box_x, fe.box_y, fe.box_w, fe.box_h,
                    am.thumb_path, am.media_type, am.width, am.height,
                    p.first_name, p.last_name
             FROM face_embeddings fe
             JOIN album_media am ON am.id = fe.media_id
             LEFT JOIN persons p ON p.id = fe.person_id
             WHERE fe.status <> "rejected"
             ORDER BY fe.cluster_id, fe.id'
        );
        $unlabeled = [];
        $labeled = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $face = $this->faceCard($r);
            if ($r['person_id']) {
                $cid = (int)$r['cluster_id'];
                if (!isset($labeled[$cid])) {
                    $labeled[$cid] = [
                        'cluster_id' => $cid,
                        'person_id'  => (int)$r['person_id'],
                        'name'       => AlbumService::personName($r),
                        'count'      => 0,
                        'face'       => $face,
                    ];
                }
                $labeled[$cid]['count']++;
            } else {
                $cid = (int)$r['cluster_id'];
                if (!isset($unlabeled[$cid])) {
                    $unlabeled[$cid] = ['cluster_id' => $cid, 'count' => 0, 'face' => $face];
                }
                $unlabeled[$cid]['count']++;
            }
        }
        usort($unlabeled, fn($a, $b) => $b['count'] <=> $a['count']);
        usort($labeled, fn($a, $b) => strcmp($a['name'], $b['name']));
        return ['unlabeled' => array_values($unlabeled), 'labeled' => array_values($labeled)];
    }

    /** Рамки лиц на фото (для отображения при наведении/в просмотре) */
    public function facesForMedia(int $mediaId): array
    {
        $st = $this->db->prepare(
            'SELECT fe.id, fe.person_id, fe.box_x, fe.box_y, fe.box_w, fe.box_h,
                    p.first_name, p.last_name
             FROM face_embeddings fe
             LEFT JOIN persons p ON p.id = fe.person_id
             WHERE fe.media_id = ? AND fe.status <> "rejected"
             ORDER BY fe.id'
        );
        $st->execute([$mediaId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = [
                'id'        => (int)$r['id'],
                'person_id' => $r['person_id'] ? (int)$r['person_id'] : null,
                'name'      => $r['first_name'] ? AlbumService::personName($r) : null,
                'box'       => [(float)$r['box_x'], (float)$r['box_y'], (float)$r['box_w'], (float)$r['box_h']],
            ];
        }
        return $out;
    }

    /** Подписать кластер: привязать все фото группы к человеку */
    public function assignCluster(int $clusterId, int $personId, ?int $userId): array
    {
        $cluster = $this->getCluster($clusterId);
        if (!$cluster) {
            throw new RuntimeException('cluster_not_found');
        }
        $person = $this->personRow($personId);
        if (!$person) {
            throw new RuntimeException('person_not_found');
        }
        if ($this->personOptedOut($personId)) {
            throw new RuntimeException('person_opt_out');
        }
        $this->db->prepare('UPDATE face_clusters SET person_id = ? WHERE id = ?')->execute([$personId, $clusterId]);
        $this->db->prepare('UPDATE face_embeddings SET person_id = ?, status = "labeled" WHERE cluster_id = ?')->execute([$personId, $clusterId]);

        $st = $this->db->prepare('SELECT DISTINCT media_id FROM face_embeddings WHERE cluster_id = ?');
        $st->execute([$clusterId]);
        $mediaIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        foreach ($mediaIds as $m) {
            $this->tagMedia($m, $personId, 'face');
        }
        return [
            'cluster_id'   => $clusterId,
            'person_id'    => $personId,
            'name'         => AlbumService::personName($person),
            'media_tagged' => count($mediaIds),
        ];
    }

    /** Снять подпись с кластера (лица снова станут «неподписанной группой») */
    public function unassignCluster(int $clusterId, ?int $userId): array
    {
        $cluster = $this->getCluster($clusterId);
        if (!$cluster) {
            throw new RuntimeException('cluster_not_found');
        }
        $personId = $cluster['person_id'] ? (int)$cluster['person_id'] : 0;
        $st = $this->db->prepare('SELECT DISTINCT media_id FROM face_embeddings WHERE cluster_id = ?');
        $st->execute([$clusterId]);
        $mediaIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

        $this->db->prepare('UPDATE face_clusters SET person_id = NULL WHERE id = ?')->execute([$clusterId]);
        $this->db->prepare('UPDATE face_embeddings SET person_id = NULL, status = "unlabeled" WHERE cluster_id = ?')->execute([$clusterId]);

        if ($personId > 0) {
            $del = $this->db->prepare('DELETE FROM album_media_people WHERE media_id = ? AND person_id = ? AND source = "face"');
            foreach ($mediaIds as $m) {
                $del->execute([$m, $personId]);
            }
        }
        return ['cluster_id' => $clusterId, 'removed_tags' => count($mediaIds)];
    }

    /** Убрать ошибочное лицо из группы (помечается rejected, из кластера исключается) */
    public function removeFace(int $faceId, ?int $userId): array
    {
        $face = $this->getFace($faceId);
        if (!$face) {
            throw new RuntimeException('face_not_found');
        }
        $this->db->prepare('UPDATE face_embeddings SET status = "rejected" WHERE id = ?')->execute([$faceId]);
        $this->reconcileMediaTag((int)$face['media_id'], (int)($face['person_id'] ?? 0));
        return ['face_id' => $faceId];
    }

    /** Удалить все отпечатки человека (человек или админ) */
    public function deletePersonEmbeddings(int $personId): int
    {
        $this->db->prepare('DELETE FROM album_media_people WHERE person_id = ? AND source = "face"')->execute([$personId]);

        $st = $this->db->prepare('SELECT DISTINCT cluster_id FROM face_embeddings WHERE person_id = ?');
        $st->execute([$personId]);
        $clusterIds = array_filter(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)), fn($v) => $v > 0);

        $del = $this->db->prepare('DELETE FROM face_embeddings WHERE person_id = ?');
        $del->execute([$personId]);
        $count = $del->rowCount();

        foreach (array_unique($clusterIds) as $cid) {
            $this->db->prepare('UPDATE face_clusters SET person_id = NULL WHERE id = ?')->execute([$cid]);
            $this->pruneClusterIfEmpty($cid);
        }
        return $count;
    }

    // ======================================================= внутренние

    private function sanitizeFaces(array $faces): array
    {
        $out = [];
        foreach (array_slice(array_values($faces), 0, self::MAX_FACES_PER_MEDIA) as $f) {
            if (!is_array($f)) {
                continue;
            }
            $d = $f['descriptor'] ?? null;
            if (!is_array($d) || count($d) !== self::DESCRIPTOR_LENGTH) {
                continue;
            }
            $d = array_map('floatval', $d);
            $x = (float)($f['x'] ?? $f['box_x'] ?? 0);
            $y = (float)($f['y'] ?? $f['box_y'] ?? 0);
            $w = (float)($f['w'] ?? $f['box_w'] ?? 0);
            $h = (float)($f['h'] ?? $f['box_h'] ?? 0);
            if ($w <= 0 || $h <= 0) {
                continue;
            }
            $x = max(0.0, min(1.0, $x));
            $y = max(0.0, min(1.0, $y));
            $w = min($w, 1.0 - $x);
            $h = min($h, 1.0 - $y);
            $out[] = ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'descriptor' => $d];
        }
        return $out;
    }

    /** Найти ближайший кластер по косинусному сходству или создать новый */
    private function assignToCluster(array $descriptor): int
    {
        $bestId = 0;
        $bestSim = -1.0;
        foreach ($this->clusterCentroids() as $c) {
            $sim = self::cosine($descriptor, $c['centroid']);
            if ($sim > $bestSim) {
                $bestSim = $sim;
                $bestId = (int)$c['id'];
            }
        }
        if ($bestId > 0 && $bestSim >= self::CLUSTER_THRESHOLD) {
            return $bestId;
        }
        $this->db->prepare('INSERT INTO face_clusters (person_id) VALUES (NULL)')->execute();
        return (int)$this->db->lastInsertId();
    }

    /** @return list<array{id:int, centroid:list<float>}> */
    private function clusterCentroids(): array
    {
        $st = $this->db->query(
            'SELECT fc.id, fe.descriptor FROM face_clusters fc
             JOIN face_embeddings fe ON fe.cluster_id = fc.id
             WHERE fe.status <> "rejected"'
        );
        $acc = [];
        $cnt = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $d = json_decode((string)$r['descriptor'], true);
            if (!is_array($d) || count($d) !== self::DESCRIPTOR_LENGTH) {
                continue;
            }
            $id = (int)$r['id'];
            if (!isset($acc[$id])) {
                $acc[$id] = array_fill(0, self::DESCRIPTOR_LENGTH, 0.0);
                $cnt[$id] = 0;
            }
            foreach ($d as $i => $v) {
                $acc[$id][$i] += (float)$v;
            }
            $cnt[$id]++;
        }
        $out = [];
        foreach ($acc as $id => $sum) {
            $n = $cnt[$id];
            foreach ($sum as $i => $v) {
                $sum[$i] = $v / $n;
            }
            $out[] = ['id' => $id, 'centroid' => $sum];
        }
        return $out;
    }

    private function clearMediaFaces(int $mediaId): void
    {
        $this->db->prepare('DELETE FROM album_media_people WHERE media_id = ? AND source = "face"')->execute([$mediaId]);
        $st = $this->db->prepare('SELECT DISTINCT cluster_id FROM face_embeddings WHERE media_id = ?');
        $st->execute([$mediaId]);
        $clusterIds = array_filter(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)), fn($v) => $v > 0);
        $this->db->prepare('DELETE FROM face_embeddings WHERE media_id = ?')->execute([$mediaId]);
        foreach (array_unique($clusterIds) as $cid) {
            $this->pruneClusterIfEmpty($cid);
        }
    }

    private function pruneClusterIfEmpty(int $clusterId): void
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM face_embeddings WHERE cluster_id = ?');
        $st->execute([$clusterId]);
        if ((int)$st->fetchColumn() === 0) {
            $this->db->prepare('DELETE FROM face_clusters WHERE id = ?')->execute([$clusterId]);
        }
    }

    private function tagMedia(int $mediaId, int $personId, string $source): void
    {
        $this->db->prepare(
            'INSERT INTO album_media_people (media_id, person_id, source) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE source = IF(source = "manual", "manual", source)'
        )->execute([$mediaId, $personId, $source]);
    }

    private function reconcileMediaTag(int $mediaId, int $personId): void
    {
        if ($personId <= 0) {
            return;
        }
        $st = $this->db->prepare('SELECT COUNT(*) FROM face_embeddings WHERE media_id = ? AND person_id = ? AND status = "labeled"');
        $st->execute([$mediaId, $personId]);
        if ((int)$st->fetchColumn() === 0) {
            $this->db->prepare('DELETE FROM album_media_people WHERE media_id = ? AND person_id = ? AND source = "face"')->execute([$mediaId, $personId]);
        }
    }

    private function faceCard(array $r): array
    {
        return [
            'id'       => (int)$r['id'],
            'media_id' => (int)$r['media_id'],
            'type'     => (string)$r['media_type'],
            'thumb'    => $r['thumb_path'] ? (string)$r['thumb_path'] : null,
            'box'      => [(float)$r['box_x'], (float)$r['box_y'], (float)$r['box_w'], (float)$r['box_h']],
        ];
    }

    private function clusterPersonId(int $clusterId): int
    {
        $st = $this->db->prepare('SELECT person_id FROM face_clusters WHERE id = ?');
        $st->execute([$clusterId]);
        return (int)$st->fetchColumn();
    }

    private function getCluster(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM face_clusters WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    private function getFace(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM face_embeddings WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    private function getMedia(int $id): ?array
    {
        $st = $this->db->prepare('SELECT id, album_id, media_type FROM album_media WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    private function personRow(int $id): ?array
    {
        $st = $this->db->prepare('SELECT id, first_name, last_name FROM persons WHERE id = ? AND is_deleted = 0');
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

    private static function cosine(array $a, array $b): float
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
}
