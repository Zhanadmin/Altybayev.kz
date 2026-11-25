<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\PersonAccessService;
use PDO;

/**
 * ALT-048: добавить мужа/жену прямо из карточки человека.
 * Существующий человек из списка или новый; создаётся запись в marriages.
 */
class SpouseController
{
    /**
     * Причина, по которой нельзя добавить нового супруга, либо null, если можно.
     * Нельзя, пока есть действующий брак: статус не «развод», нет даты развода/окончания,
     * супруг(а) не удалён(а) и жив(а) (нет отметки умершего и даты смерти).
     */
    public static function blockReason(PDO $db, int $personId): ?string
    {
        return self::activeMarriages($db, $personId) ? 'Уже в браке. Укажите дату развода, чтобы добавить нового супруга' : null;
    }

    /** ALT-051: действующие браки человека: [['id' => marriage_id, 'name' => имя супруга(и)], ...] */
    public static function activeMarriages(PDO $db, int $personId): array
    {
        $out = [];
        $st = $db->prepare(
            'SELECT m.*, p.is_deceased AS sp_deceased, p.death_date AS sp_death_date,
                    p.first_name AS sp_first, p.last_name AS sp_last
             FROM marriages m
             JOIN persons p ON p.id = IF(m.husband_id = ?, m.wife_id, m.husband_id) AND p.is_deleted = 0
             WHERE m.husband_id = ? OR m.wife_id = ?'
        );
        $st->execute([$personId, $personId, $personId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $status = (string)($m['status'] ?? '');
            $ended = $status === 'divorced'
                || (isset($m['is_current']) && (int)$m['is_current'] === 0)
                || trim((string)($m['divorce_date'] ?? '')) !== ''
                || trim((string)($m['end_date'] ?? '')) !== '';
            if ($ended) {
                continue;
            }
            $dead = !empty($m['sp_deceased']) || trim((string)($m['sp_death_date'] ?? '')) !== '';
            if ($dead) {
                continue;
            }
            $out[] = [
                'id'   => (int)$m['id'],
                'name' => trim((string)($m['sp_first'] ?? '') . ' ' . (string)($m['sp_last'] ?? '')),
            ];
        }
        return $out;
    }

    private function loadTarget(PDO $db, int $id): ?array
    {
        $st = $db->prepare('SELECT * FROM persons WHERE id = ? AND is_deleted = 0');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** GET /api/person/spouse-search?target_id=&q= — кандидаты в супруги (противоположный пол). */
    public function search(Request $request): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::json(['error' => 'Нужен вход'], 401);
            return;
        }
        $db = Database::getConnection();
        $target = $this->loadTarget($db, (int)$request->get('target_id', 0));
        if (!$target || !in_array($target['gender'] ?? '', ['M', 'F'], true)) {
            Response::json(['error' => 'Человек не найден'], 404);
            return;
        }
        if (!PersonAccessService::make()->canEditPerson((int)$target['id'], $user)) {
            Response::json(['error' => 'Нет прав'], 403);
            return;
        }
        $q = trim((string)$request->get('q', ''));
        $want = $target['gender'] === 'M' ? 'F' : 'M';
        $tid = (int)$target['id'];
        $sql = 'SELECT id, first_name, last_name, maiden_name, birth_year, death_year, is_deceased FROM persons
                WHERE is_deleted = 0 AND gender = ? AND id <> ?
                AND id NOT IN (SELECT IF(husband_id = ?, wife_id, husband_id) FROM marriages WHERE husband_id = ? OR wife_id = ?)';
        $params = [$want, $tid, $tid, $tid, $tid];
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $sql .= ' AND (first_name LIKE ? OR last_name LIKE ? OR maiden_name LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $sql .= ' ORDER BY last_name, first_name LIMIT 20';
        $st = $db->prepare($sql);
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
            if (self::blockReason($db, (int)$p['id']) !== null) {
                continue; // уже в действующем браке
            }
            $out[] = [
                'id'          => (int)$p['id'],
                'name'        => trim((string)$p['last_name'] . ' ' . (string)$p['first_name']),
                'maiden_name' => $p['maiden_name'] ?: null,
                'years'       => !empty($p['is_deceased'])
                    ? (($p['birth_year'] ?: '—') . ' — ' . ($p['death_year'] ?: '—'))
                    : (string)($p['birth_year'] ?: '—'),
            ];
        }
        Response::json(['results' => $out]);
    }

    /** POST /api/person/add-spouse — mode=existing (spouse_id) | new (first_name, ...). */
    public function add(Request $request): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::json(['error' => 'Нужен вход'], 401);
            return;
        }
        if (!Session::validateCsrf((string)$request->post('csrf_token', ''))) {
            Response::json(['error' => 'Сессия ескірді, бетті жаңартыңыз / Сессия устарела, обновите страницу'], 403);
            return;
        }
        $targetId = (int)$request->post('target_id', 0);
        $mode = (string)$request->post('mode', '');
        try {
            $db = Database::getConnection();
            $target = $this->loadTarget($db, $targetId);
            if (!$target || !in_array($target['gender'] ?? '', ['M', 'F'], true)) {
                Response::json(['error' => 'Человек не найден / не указан пол'], 404);
                return;
            }
            if (!PersonAccessService::make()->canEditPerson($targetId, $user)) {
                Response::json(['error' => 'Нет прав менять семью этой карточки'], 403);
                return;
            }
            $reason = self::blockReason($db, $targetId);
            if ($reason !== null) {
                Response::json(['error' => $reason], 409);
                return;
            }
            $want = $target['gender'] === 'M' ? 'F' : 'M';

            if ($mode === 'existing') {
                $spouseId = (int)$request->post('spouse_id', 0);
                $spouse = $spouseId > 0 && $spouseId !== $targetId ? $this->loadTarget($db, $spouseId) : null;
                if (!$spouse || ($spouse['gender'] ?? '') !== $want) {
                    Response::json(['error' => 'Супруг не найден или пол не подходит'], 422);
                    return;
                }
                if (self::blockReason($db, $spouseId) !== null) {
                    Response::json(['error' => 'Этот человек уже в браке'], 409);
                    return;
                }
                $dup = $db->prepare('SELECT 1 FROM marriages WHERE (husband_id = ? AND wife_id = ?) OR (husband_id = ? AND wife_id = ?) LIMIT 1');
                $dup->execute([$targetId, $spouseId, $spouseId, $targetId]);
                if ($dup->fetchColumn()) {
                    Response::json(['error' => 'Этот человек уже добавлен как супруг(а)'], 409);
                    return;
                }
                $newId = $spouseId;
            } elseif ($mode === 'new') {
                $first = trim((string)$request->post('first_name', ''));
                if ($first === '') {
                    Response::json(['error' => 'Аты (имя) толтырылуы тиіс / Имя обязательно'], 422);
                    return;
                }
                if (mb_strlen($first) > 100) {
                    Response::json(['error' => 'Слишком длинное имя'], 422);
                    return;
                }
                $last = trim((string)$request->post('last_name', '')) ?: ($want === 'F' ? (string)$target['last_name'] : '');
                $by = trim((string)$request->post('birth_year', ''));
                $by = ctype_digit($by) && strlen($by) === 4 ? (int)$by : null;
                $ins = $db->prepare(
                    "INSERT INTO persons (first_name, last_name, maiden_name, gender, birth_year, clan_id, clan_ru, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, NULL, 'белгісіз', ?, NOW())"
                );
                $ins->execute([
                    $first,
                    $last,
                    $want === 'F' ? (trim((string)$request->post('maiden_name', '')) ?: null) : null,
                    $want,
                    $by,
                    $user['id'] ?? null,
                ]);
                $newId = (int)$db->lastInsertId();
            } else {
                Response::json(['error' => 'mode: existing|new'], 400);
                return;
            }

            $husbandId = $target['gender'] === 'M' ? $targetId : $newId;
            $wifeId = $target['gender'] === 'M' ? $newId : $targetId;
            $db->prepare("INSERT INTO marriages (husband_id, wife_id, is_current, status, created_at) VALUES (?, ?, 1, 'married', NOW())")
                ->execute([$husbandId, $wifeId]);
            $marriageId = (int)$db->lastInsertId();

            try {
                $db->prepare(
                    "INSERT INTO audit_logs (user_id, user_name, user_email, action, entity_table, entity_id, summary, old_values, new_values, created_at)
                     VALUES (?, ?, ?, 'CREATE', 'marriages', ?, ?, NULL, ?, NOW())"
                )->execute([
                    $user['id'] ?? null,
                    $user['name'] ?? 'Система',
                    $user['email'] ?? 'system@altybayev.kz',
                    $marriageId,
                    "Жұбай қосылды / Добавлен супруг(а): #{$targetId} + #{$newId} ({$mode})",
                    json_encode(['husband_id' => $husbandId, 'wife_id' => $wifeId], JSON_UNESCAPED_UNICODE),
                ]);
            } catch (\Throwable $e) {
                // журнал не должен ломать добавление
            }

            Response::json(['status' => 'success', 'marriage_id' => $marriageId, 'spouse_id' => $newId]);
        } catch (\Throwable $e) {
            Response::json(['error' => 'Не удалось сохранить'], 500);
        }
    }
}
