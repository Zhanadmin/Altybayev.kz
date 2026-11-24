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
 * ALT-060: добавить отца / мать из карточки человека, пока родитель не указан.
 * Существующий человек из списка или новый. Если второй родитель уже есть и они ещё не пара —
 * создаётся брак между ними. Поколение считается автоматически по father_id/mother_id (ALT-032).
 */
class ParentController
{
    private function loadPerson(PDO $db, int $id): ?array
    {
        $st = $db->prepare('SELECT * FROM persons WHERE id = ? AND is_deleted = 0');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Потомки человека (чтобы не создать цикл: родителем нельзя сделать своего потомка). */
    private function descendantIds(PDO $db, int $id): array
    {
        $seen = [$id => true];
        $queue = [$id];
        $st = $db->prepare('SELECT id FROM persons WHERE father_id = ? OR mother_id = ?');
        while ($queue) {
            $cur = array_shift($queue);
            $st->execute([$cur, $cur]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $cid) {
                $cid = (int)$cid;
                if (!isset($seen[$cid])) {
                    $seen[$cid] = true;
                    $queue[] = $cid;
                }
            }
        }
        return array_keys($seen);
    }

    private static function column(string $kind): ?string
    {
        return $kind === 'father' ? 'father_id' : ($kind === 'mother' ? 'mother_id' : null);
    }

    /** GET /api/person/parent-search?target_id=&kind=father|mother&q= */
    public function search(Request $request): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::json(['error' => 'Нужен вход'], 401);
            return;
        }
        $kind = (string)$request->get('kind', '');
        if (self::column($kind) === null) {
            Response::json(['error' => 'kind: father|mother'], 400);
            return;
        }
        $db = Database::getConnection();
        $target = $this->loadPerson($db, (int)$request->get('target_id', 0));
        if (!$target) {
            Response::json(['error' => 'Человек не найден'], 404);
            return;
        }
        if (!PersonAccessService::make()->canEditPerson((int)$target['id'], $user)) {
            Response::json(['error' => 'Нет прав'], 403);
            return;
        }
        $exclude = $this->descendantIds($db, (int)$target['id']);
        $in = implode(',', array_fill(0, count($exclude), '?'));
        $sql = "SELECT id, first_name, last_name, maiden_name, birth_year, death_year, is_deceased FROM persons
                WHERE is_deleted = 0 AND gender = ? AND id NOT IN ($in)";
        $params = array_merge([$kind === 'father' ? 'M' : 'F'], $exclude);
        $q = trim((string)$request->get('q', ''));
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

    /** POST /api/person/add-parent — kind=father|mother, mode=existing (parent_id) | new (first_name, ...). */
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
        $kind = (string)$request->post('kind', '');
        $mode = (string)$request->post('mode', '');
        $col = self::column($kind);
        if ($col === null) {
            Response::json(['error' => 'kind: father|mother'], 400);
            return;
        }
        try {
            $db = Database::getConnection();
            $target = $this->loadPerson($db, $targetId);
            if (!$target) {
                Response::json(['error' => 'Человек не найден'], 404);
                return;
            }
            if (!PersonAccessService::make()->canEditPerson($targetId, $user)) {
                Response::json(['error' => 'Нет прав менять семью этой карточки'], 403);
                return;
            }
            if (!empty($target[$col])) {
                Response::json(['error' => $kind === 'father' ? 'Отец уже указан' : 'Мать уже указана'], 409);
                return;
            }
            $gender = $kind === 'father' ? 'M' : 'F';
            $otherCol = $kind === 'father' ? 'mother_id' : 'father_id';
            $otherId = !empty($target[$otherCol]) ? (int)$target[$otherCol] : 0;

            if ($mode === 'existing') {
                $parentId = (int)$request->post('parent_id', 0);
                $parent = $parentId > 0 ? $this->loadPerson($db, $parentId) : null;
                if (!$parent || ($parent['gender'] ?? '') !== $gender) {
                    Response::json(['error' => 'Человек не найден или пол не подходит'], 422);
                    return;
                }
                if (in_array($parentId, $this->descendantIds($db, $targetId), true)) {
                    Response::json(['error' => 'Нельзя сделать родителем потомка этого человека'], 422);
                    return;
                }
                $newId = $parentId;
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
                $last = trim((string)$request->post('last_name', ''));
                if ($last === '' && $gender === 'M') {
                    $last = (string)($target['last_name'] ?? '');
                }
                $by = trim((string)$request->post('birth_year', ''));
                $by = ctype_digit($by) && strlen($by) === 4 ? (int)$by : null;
                $ins = $db->prepare(
                    "INSERT INTO persons (first_name, last_name, maiden_name, gender, birth_year, clan_id, clan_ru, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, NULL, 'белгісіз', ?, NOW())"
                );
                $ins->execute([
                    $first,
                    $last,
                    trim((string)$request->post('maiden_name', '')) ?: null,
                    $gender,
                    $by,
                    $user['id'] ?? null,
                ]);
                $newId = (int)$db->lastInsertId();
            } else {
                Response::json(['error' => 'mode: existing|new'], 400);
                return;
            }

            $db->prepare("UPDATE persons SET {$col} = ? WHERE id = ?")->execute([$newId, $targetId]);

            // Второй родитель уже есть и они ещё не пара — соединяем в брак
            $marriageId = null;
            if ($otherId > 0 && $otherId !== $newId) {
                $husbandId = $kind === 'father' ? $newId : $otherId;
                $wifeId = $kind === 'father' ? $otherId : $newId;
                $dup = $db->prepare('SELECT id FROM marriages WHERE (husband_id = ? AND wife_id = ?) OR (husband_id = ? AND wife_id = ?) LIMIT 1');
                $dup->execute([$husbandId, $wifeId, $wifeId, $husbandId]);
                if (!$dup->fetchColumn()) {
                    $db->prepare("INSERT INTO marriages (husband_id, wife_id, is_current, status, created_at) VALUES (?, ?, 1, 'married', NOW())")
                        ->execute([$husbandId, $wifeId]);
                    $marriageId = (int)$db->lastInsertId();
                }
            }

            try {
                $db->prepare(
                    "INSERT INTO audit_logs (user_id, user_name, user_email, action, entity_table, entity_id, summary, old_values, new_values, created_at)
                     VALUES (?, ?, ?, 'UPDATE', 'persons', ?, ?, NULL, ?, NOW())"
                )->execute([
                    $user['id'] ?? null,
                    $user['name'] ?? 'Система',
                    $user['email'] ?? 'system@altybayev.kz',
                    $targetId,
                    ($kind === 'father' ? 'Әке қосылды / Добавлен отец' : 'Ана қосылды / Добавлена мать') . ": #{$newId} → #{$targetId} ({$mode})",
                    json_encode([$col => $newId, 'marriage_id' => $marriageId], JSON_UNESCAPED_UNICODE),
                ]);
            } catch (\Throwable $e) {
                // журнал не должен ломать добавление
            }

            Response::json(['status' => 'success', 'parent_id' => $newId, 'marriage_id' => $marriageId]);
        } catch (\Throwable $e) {
            Response::json(['error' => 'Не удалось сохранить'], 500);
        }
    }
}
