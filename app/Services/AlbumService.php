<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * TASK-018: альбомы семей и мероприятий, права доступа, список медиа.
 *
 * Права:
 *  - смотреть — любой вошедший член семьи;
 *  - добавлять фото/видео и альбомы — admin/editor, а также участник семьи/мероприятия (его персона);
 *  - удалять фото/видео — автор загрузки и admin;
 *  - менять/удалять альбом и мероприятие — создатель и admin.
 */
class AlbumService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /** Таблицы созданы миграцией 2026_11_15_add_albums_and_events.sql? */
    public function isReady(): bool
    {
        try {
            $st = $this->db->query("SHOW TABLES LIKE 'album_media'");
            return $st !== false && $st->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ------------------------------------------------------------------ люди и семьи

    public function getPerson(int $id): ?array
    {
        $st = $this->db->prepare('SELECT id, first_name, last_name, middle_name, gender, avatar_path FROM persons WHERE id = ? AND is_deleted = 0');
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Все люди для выбора (id, имя) — по фамилии и имени */
    public function peopleList(): array
    {
        $st = $this->db->query('SELECT id, first_name, last_name, middle_name, birth_year FROM persons WHERE is_deleted = 0 ORDER BY last_name, first_name');
        $out = [];
        $seen = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $name = self::personName($p) . (!empty($p['birth_year']) ? ' (' . (int)$p['birth_year'] . ')' : '');
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                $name .= ' · №' . (int)$p['id']; // тёзки различаются, чтобы отметка не ушла другому
            }
            $seen[$key] = true;
            $out[] = ['id' => (int)$p['id'], 'name' => $name];
        }
        return $out;
    }

    public static function personName(?array $p): string
    {
        if (!$p) {
            return '';
        }
        return trim(preg_replace('/\s+/u', ' ', ($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')));
    }

    /** Семья (брак) с мужем и женой */
    public function getFamily(int $marriageId): ?array
    {
        $st = $this->db->prepare('SELECT * FROM marriages WHERE id = ?');
        $st->execute([$marriageId]);
        $m = $st->fetch(PDO::FETCH_ASSOC);
        if (!$m) {
            return null;
        }
        $m['husband'] = !empty($m['husband_id']) ? $this->getPerson((int)$m['husband_id']) : null;
        $m['wife'] = !empty($m['wife_id']) ? $this->getPerson((int)$m['wife_id']) : null;
        $m['title'] = $this->familyTitle($m);
        return $m;
    }

    public function familyTitle(array $m): string
    {
        $h = $m['husband'] ?? null;
        $w = $m['wife'] ?? null;
        $parts = array_filter([self::personName($h), self::personName($w)]);
        return implode(' — ', $parts) ?: ('#' . (int)$m['id']);
    }

    /** Все семьи для выбора участников мероприятия */
    public function familiesList(): array
    {
        $sql = "SELECT m.id, h.first_name hf, h.last_name hl, w.first_name wf, w.last_name wl
                FROM marriages m
                LEFT JOIN persons h ON h.id = m.husband_id
                LEFT JOIN persons w ON w.id = m.wife_id
                ORDER BY h.last_name, h.first_name";
        $out = [];
        foreach ($this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $name = implode(' — ', array_filter([
                trim(($r['hf'] ?? '') . ' ' . ($r['hl'] ?? '')),
                trim(($r['wf'] ?? '') . ' ' . ($r['wl'] ?? '')),
            ]));
            $out[] = ['id' => (int)$r['id'], 'name' => $name !== '' ? $name : ('#' . (int)$r['id'])];
        }
        return $out;
    }

    /** Персоны — члены семьи: муж, жена и их общие дети */
    public function familyMemberIds(int $marriageId): array
    {
        $st = $this->db->prepare('SELECT husband_id, wife_id FROM marriages WHERE id = ?');
        $st->execute([$marriageId]);
        $m = $st->fetch(PDO::FETCH_ASSOC);
        if (!$m) {
            return [];
        }
        $ids = array_filter([(int)$m['husband_id'], (int)$m['wife_id']]);
        if ($ids) {
            // дети пары, а также дети, у которых в базе указан только один из родителей этой пары
            $c = $this->db->prepare('SELECT id FROM persons WHERE is_deleted = 0 AND ((father_id = ? AND (mother_id = ? OR mother_id IS NULL)) OR (mother_id = ? AND father_id IS NULL))');
            $c->execute([(int)$m['husband_id'], (int)$m['wife_id'], (int)$m['wife_id']]);
            foreach ($c->fetchAll(PDO::FETCH_COLUMN) as $cid) {
                $ids[] = (int)$cid;
            }
        }
        return array_values(array_unique($ids));
    }

    // ------------------------------------------------------------------ права

    /**
     * @param array $user ['id','role','person_id']
     */
    public function canContributeToAlbum(array $album, ?array $user): bool
    {
        if (!$user) {
            return false;
        }
        $role = $user['role'] ?? 'viewer';
        if ($role === 'admin' || $role === 'editor') {
            return true;
        }
        $pid = (int)($user['person_id'] ?? 0);
        if ($album['owner_type'] === 'family') {
            return $pid > 0 && in_array($pid, $this->familyMemberIds((int)$album['owner_id']), true);
        }
        return $this->isEventParticipant((int)$album['owner_id'], $user);
    }

    public function canContributeToFamily(int $marriageId, ?array $user): bool
    {
        return $this->canContributeToAlbum(['owner_type' => 'family', 'owner_id' => $marriageId], $user);
    }

    public function isEventParticipant(int $eventId, ?array $user): bool
    {
        if (!$user) {
            return false;
        }
        $ev = $this->getEventRow($eventId);
        if (!$ev) {
            return false;
        }
        if (!empty($ev['created_by']) && (int)$ev['created_by'] === (int)($user['id'] ?? 0)) {
            return true;
        }
        $pid = (int)($user['person_id'] ?? 0);
        if ($pid <= 0) {
            return false;
        }
        $st = $this->db->prepare('SELECT 1 FROM event_people WHERE event_id = ? AND person_id = ?');
        $st->execute([$eventId, $pid]);
        if ($st->fetchColumn()) {
            return true;
        }
        $f = $this->db->prepare('SELECT marriage_id FROM event_families WHERE event_id = ?');
        $f->execute([$eventId]);
        foreach ($f->fetchAll(PDO::FETCH_COLUMN) as $mid) {
            if (in_array($pid, $this->familyMemberIds((int)$mid), true)) {
                return true;
            }
        }
        return false;
    }

    public static function canManage(?array $row, ?array $user): bool
    {
        if (!$row || !$user) {
            return false;
        }
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }
        return !empty($row['created_by']) && (int)$row['created_by'] === (int)($user['id'] ?? 0);
    }

    public static function canDeleteMedia(array $media, ?array $user): bool
    {
        if (!$user) {
            return false;
        }
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }
        return !empty($media['uploaded_by']) && (int)$media['uploaded_by'] === (int)($user['id'] ?? 0);
    }

    // ------------------------------------------------------------------ альбомы

    public function getAlbum(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM albums WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Альбомы семьи; если ни одного нет — создаётся основной «Отбасы альбомы» (у любой семьи есть альбом) */
    public function familyAlbums(int $marriageId, string $lang): array
    {
        $list = $this->albumsOf('family', $marriageId);
        if (empty($list)) {
            $this->ensureFirstAlbum('family', $marriageId, $lang === 'ru' ? 'Семейный альбом' : 'Отбасы альбомы', null);
            $list = $this->albumsOf('family', $marriageId);
        }
        return $list;
    }

    /** Создать первый альбом владельца, только если у него ещё нет ни одного (без дублей при одновременных запросах) */
    private function ensureFirstAlbum(string $ownerType, int $ownerId, string $title, ?string $date): void
    {
        $st = $this->db->prepare(
            'INSERT INTO albums (owner_type, owner_id, title, album_date)
             SELECT ?, ?, ?, ? FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM albums WHERE owner_type = ? AND owner_id = ?)'
        );
        $st->execute([$ownerType, $ownerId, $title, $date, $ownerType, $ownerId]);
    }

    public function albumsOf(string $ownerType, int $ownerId): array
    {
        $st = $this->db->prepare(
            "SELECT a.*,
                    (SELECT COUNT(*) FROM album_media am WHERE am.album_id = a.id) AS media_count,
                    (SELECT am2.id FROM album_media am2 WHERE am2.album_id = a.id AND am2.thumb_path IS NOT NULL ORDER BY am2.id DESC LIMIT 1) AS cover_id
             FROM albums a WHERE a.owner_type = ? AND a.owner_id = ?
             ORDER BY (a.album_date IS NULL), a.album_date DESC, a.id ASC"
        );
        $st->execute([$ownerType, $ownerId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createAlbum(string $ownerType, int $ownerId, string $title, ?string $date, ?string $description, ?int $userId): int
    {
        $st = $this->db->prepare('INSERT INTO albums (owner_type, owner_id, title, album_date, description, created_by) VALUES (?, ?, ?, ?, ?, ?)');
        $st->execute([$ownerType, $ownerId, $title, $date, $description, $userId]);
        $id = (int)$this->db->lastInsertId();
        $this->audit('CREATE', 'albums', $id, "Альбом: «{$title}»", null, ['owner_type' => $ownerType, 'owner_id' => $ownerId, 'title' => $title], $userId);
        return $id;
    }

    public function updateAlbum(int $id, string $title, ?string $date, ?string $description, ?int $userId): void
    {
        $old = $this->getAlbum($id);
        $st = $this->db->prepare('UPDATE albums SET title = ?, album_date = ?, description = ? WHERE id = ?');
        $st->execute([$title, $date, $description, $id]);
        $this->audit('UPDATE', 'albums', $id, "Альбом өзгертілді: «{$title}»", $old, ['title' => $title, 'album_date' => $date, 'description' => $description], $userId);
    }

    /** Удаляется только пустой альбом (фото удаляют по одному — автор или админ) */
    public function deleteAlbum(int $id, ?int $userId): void
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM album_media WHERE album_id = ?');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            throw new RuntimeException('not_empty');
        }
        $old = $this->getAlbum($id);
        $this->db->prepare('DELETE FROM albums WHERE id = ?')->execute([$id]);
        $this->audit('DELETE', 'albums', $id, 'Альбом жойылды: «' . ($old['title'] ?? '') . '»', $old, null, $userId);
    }

    /** Медиа альбома с отметками людей */
    public function albumMedia(int $albumId): array
    {
        $st = $this->db->prepare(
            'SELECT am.*, u.name AS uploader_name FROM album_media am LEFT JOIN users u ON u.id = am.uploaded_by
             WHERE am.album_id = ? ORDER BY (am.taken_date IS NULL), am.taken_date ASC, am.id ASC'
        );
        $st->execute([$albumId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return [];
        }
        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        $tags = $this->tagsFor($ids);
        foreach ($rows as &$r) {
            $r['people'] = $tags[(int)$r['id']] ?? [];
        }
        unset($r);
        return $rows;
    }

    public function getMedia(int $id): ?array
    {
        $st = $this->db->prepare('SELECT am.*, u.name AS uploader_name FROM album_media am LEFT JOIN users u ON u.id = am.uploaded_by WHERE am.id = ?');
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            return null;
        }
        $r['people'] = $this->tagsFor([(int)$r['id']])[(int)$r['id']] ?? [];
        return $r;
    }

    /** @return array<int, list<array{id:int,name:string}>> */
    private function tagsFor(array $mediaIds): array
    {
        if (!$mediaIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($mediaIds), '?'));
        $st = $this->db->prepare(
            "SELECT amp.media_id, p.id, p.first_name, p.last_name FROM album_media_people amp
             JOIN persons p ON p.id = amp.person_id WHERE amp.media_id IN ($in) ORDER BY p.first_name"
        );
        $st->execute(array_values($mediaIds));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $out[(int)$t['media_id']][] = ['id' => (int)$t['id'], 'name' => self::personName($t)];
        }
        return $out;
    }

    public function insertMedia(array $d, array $personIds, ?int $userId): int
    {
        $st = $this->db->prepare(
            'INSERT INTO album_media (album_id, media_type, file_path, thumb_path, ext, original_name, file_size, width, height, duration_seconds, caption, taken_date, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $d['album_id'], $d['media_type'], $d['file_path'], $d['thumb_path'] ?? null, $d['ext'],
            $d['original_name'] ?? null, $d['file_size'] ?? 0, $d['width'] ?? null, $d['height'] ?? null,
            $d['duration_seconds'] ?? null, $d['caption'] ?? null, $d['taken_date'] ?? null, $userId,
        ]);
        $id = (int)$this->db->lastInsertId();
        $this->setTags($id, $personIds);
        $this->audit('CREATE', 'album_media', $id,
            ($d['media_type'] === 'video' ? 'Бейне' : 'Фото') . ' альбомға қосылды (альбом ' . (int)$d['album_id'] . ')', null,
            ['album_id' => (int)$d['album_id'], 'media_type' => $d['media_type'], 'file_size' => (int)($d['file_size'] ?? 0), 'people' => $personIds], $userId);
        return $id;
    }

    public function updateMediaInfo(int $id, ?string $caption, ?string $date, array $personIds, ?int $userId): void
    {
        $old = $this->getMedia($id);
        $this->db->prepare('UPDATE album_media SET caption = ?, taken_date = ? WHERE id = ?')->execute([$caption, $date, $id]);
        $this->setTags($id, $personIds);
        $this->audit('UPDATE', 'album_media', $id, 'Фото сипаттамасы өзгертілді', $old ? ['caption' => $old['caption'], 'taken_date' => $old['taken_date']] : null,
            ['caption' => $caption, 'taken_date' => $date, 'people' => $personIds], $userId);
    }

    public function deleteMediaRow(array $media, ?int $userId): void
    {
        $this->db->prepare('DELETE FROM album_media WHERE id = ?')->execute([(int)$media['id']]);
        $this->audit('DELETE', 'album_media', (int)$media['id'], 'Альбомнан жойылды (альбом ' . (int)$media['album_id'] . ')',
            ['album_id' => (int)$media['album_id'], 'media_type' => $media['media_type'], 'file_path' => $media['file_path'], 'caption' => $media['caption']], null, $userId);
    }

    private function setTags(int $mediaId, array $personIds): void
    {
        $this->db->prepare('DELETE FROM album_media_people WHERE media_id = ?')->execute([$mediaId]);
        $ids = $this->existingPersonIds($personIds);
        if (!$ids) {
            return;
        }
        $ins = $this->db->prepare('INSERT IGNORE INTO album_media_people (media_id, person_id) VALUES (?, ?)');
        foreach ($ids as $pid) {
            $ins->execute([$mediaId, $pid]);
        }
    }

    /** Только существующие персоны, не больше 50 */
    public function existingPersonIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        $ids = array_slice($ids, 0, 50);
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("SELECT id FROM persons WHERE is_deleted = 0 AND id IN ($in)");
        $st->execute($ids);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public function existingMarriageIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        $ids = array_slice($ids, 0, 50);
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("SELECT id FROM marriages WHERE id IN ($in)");
        $st->execute($ids);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Сколько файлов пользователь загрузил за последний час (лимит частоты) */
    public function uploadsLastHour(int $userId): int
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM album_media WHERE uploaded_by = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
        $st->execute([$userId]);
        return (int)$st->fetchColumn();
    }

    /** Число фото/видео по семьям (для карточек «Отбасылар») */
    public function mediaCountByFamily(): array
    {
        $out = [];
        $sql = "SELECT a.owner_id, COUNT(am.id) c FROM albums a LEFT JOIN album_media am ON am.album_id = a.id
                WHERE a.owner_type = 'family' GROUP BY a.owner_id";
        foreach ($this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int)$r['owner_id']] = (int)$r['c'];
        }
        return $out;
    }

    // ------------------------------------------------------------------ мероприятия

    public function getEventRow(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM events WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function getEvent(int $id): ?array
    {
        $ev = $this->getEventRow($id);
        if (!$ev) {
            return null;
        }
        $p = $this->db->prepare('SELECT p.id, p.first_name, p.last_name FROM event_people ep JOIN persons p ON p.id = ep.person_id WHERE ep.event_id = ? ORDER BY p.first_name');
        $p->execute([$id]);
        $ev['people'] = array_map(fn($r) => ['id' => (int)$r['id'], 'name' => self::personName($r)], $p->fetchAll(PDO::FETCH_ASSOC));
        $f = $this->db->prepare('SELECT marriage_id FROM event_families WHERE event_id = ?');
        $f->execute([$id]);
        $ev['families'] = [];
        foreach ($f->fetchAll(PDO::FETCH_COLUMN) as $mid) {
            $fam = $this->getFamily((int)$mid);
            if ($fam) {
                $ev['families'][] = ['id' => (int)$mid, 'name' => $fam['title']];
            }
        }
        $ev['album'] = $this->eventAlbum($id, (string)$ev['title'], $ev['event_date'] ?? null);
        return $ev;
    }

    /** У мероприятия ровно один альбом — создаётся при первом обращении */
    public function eventAlbum(int $eventId, string $title, ?string $date): array
    {
        $list = $this->albumsOf('event', $eventId);
        if (!$list) {
            $this->ensureFirstAlbum('event', $eventId, $title, $date);
            $list = $this->albumsOf('event', $eventId);
        }
        return $list[0];
    }

    public function listEvents(): array
    {
        $sql = "SELECT e.*,
                  (SELECT COUNT(am.id) FROM albums a JOIN album_media am ON am.album_id = a.id WHERE a.owner_type = 'event' AND a.owner_id = e.id) AS media_count,
                  (SELECT am2.id FROM albums a2 JOIN album_media am2 ON am2.album_id = a2.id WHERE a2.owner_type = 'event' AND a2.owner_id = e.id AND am2.thumb_path IS NOT NULL ORDER BY am2.id DESC LIMIT 1) AS cover_id
                FROM events e ORDER BY (e.event_date IS NULL), e.event_date DESC, e.id DESC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveEvent(?int $id, array $d, array $personIds, array $marriageIds, ?int $userId): int
    {
        $personIds = $this->existingPersonIds($personIds);
        $marriageIds = $this->existingMarriageIds($marriageIds);
        $old = $id ? $this->getEventRow($id) : null;

        if ($id) {
            $st = $this->db->prepare('UPDATE events SET title = ?, event_date = ?, place = ?, description = ? WHERE id = ?');
            $st->execute([$d['title'], $d['event_date'], $d['place'], $d['description'], $id]);
            $this->db->prepare('UPDATE albums SET title = ?, album_date = ? WHERE owner_type = ? AND owner_id = ?')
                ->execute([$d['title'], $d['event_date'], 'event', $id]);
        } else {
            $st = $this->db->prepare('INSERT INTO events (title, event_date, place, description, kind, source_person_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $st->execute([$d['title'], $d['event_date'], $d['place'], $d['description'], $d['kind'] ?? 'other', $d['source_person_id'] ?? null, $userId]);
            $id = (int)$this->db->lastInsertId();
        }

        $this->db->prepare('DELETE FROM event_people WHERE event_id = ?')->execute([$id]);
        $ins = $this->db->prepare('INSERT IGNORE INTO event_people (event_id, person_id) VALUES (?, ?)');
        foreach ($personIds as $pid) {
            $ins->execute([$id, $pid]);
        }
        $this->db->prepare('DELETE FROM event_families WHERE event_id = ?')->execute([$id]);
        $insF = $this->db->prepare('INSERT IGNORE INTO event_families (event_id, marriage_id) VALUES (?, ?)');
        foreach ($marriageIds as $mid) {
            $insF->execute([$id, $mid]);
        }

        $this->audit($old ? 'UPDATE' : 'CREATE', 'events', $id, 'Іс-шара: «' . $d['title'] . '»', $old,
            $d + ['people' => $personIds, 'families' => $marriageIds], $userId);
        return $id;
    }

    /** Удаляется мероприятие только без фото/видео */
    public function deleteEvent(int $id, ?int $userId): void
    {
        $st = $this->db->prepare("SELECT COUNT(am.id) FROM albums a JOIN album_media am ON am.album_id = a.id WHERE a.owner_type = 'event' AND a.owner_id = ?");
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            throw new RuntimeException('not_empty');
        }
        $old = $this->getEventRow($id);
        $this->db->prepare("DELETE FROM albums WHERE owner_type = 'event' AND owner_id = ?")->execute([$id]);
        $this->db->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
        $this->audit('DELETE', 'events', $id, 'Іс-шара жойылды: «' . ($old['title'] ?? '') . '»', $old, null, $userId);
    }

    // ------------------------------------------------------------------ аудит

    private function audit(string $action, string $table, int $id, string $summary, ?array $old, ?array $new, ?int $userId): void
    {
        try {
            $st = $this->db->prepare('INSERT INTO audit_logs (user_id, action, entity_table, entity_id, summary, old_values, new_values, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
            $st->execute([
                $userId, $action, $table, $id, $summary,
                $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
                $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            // аудит не должен ломать основную операцию
        }
    }
}
