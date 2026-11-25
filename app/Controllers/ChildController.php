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
 * ALT-067: «Родственники» в окне «Өзгерту» — состояние кнопок и добавление ребёнка.
 * Ребёнок связывается через persons.father_id / mother_id (поколение считается автоматически, ALT-032).
 * Второй родитель берётся из выбранного действующего брака.
 */
class ChildController
{
    private function loadPerson(PDO $db, int $id): ?array
    {
        $st = $db->prepare('SELECT * FROM persons WHERE id = ? AND is_deleted = 0');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Идентификаторы родственников по линии (вверх или вниз) вместе с самим человеком. */
    private function lineIds(PDO $db, int $id, bool $up): array
    {
        $seen = [$id => true];
        $queue = [$id];
        $st = $up
            ? $db->prepare('SELECT father_id, mother_id FROM persons WHERE id = ?')
            : $db->prepare('SELECT id FROM persons WHERE father_id = ? OR mother_id = ?');
        while ($queue) {
            $cur = array_shift($queue);
            $up ? $st->execute([$cur]) : $st->execute([$cur, $cur]);
            $ids = [];
            if ($up) {
                $row = $st->fetch(PDO::FETCH_NUM);
                $ids = $row ? $row : [];
            } else {
                $ids = $st->fetchAll(PDO::FETCH_COLUMN);
            }
            foreach ($ids as $x) {
                $x = (int)$x;
                if ($x > 0 && !isset($seen[$x])) {
                    $seen[$x] = true;
                    $queue[] = $x;
                }
            }
        }
        return array_keys($seen);
    }

    private static function parentColumn(array $target): ?string
    {
        $g = $target['gender'] ?? '';
        return $g === 'M' ? 'father_id' : ($g === 'F' ? 'mother_id' : null);
    }

    /** Действующий брак по id среди браков человека → id второго супруга или null. */
    private function spouseOfMarriage(PDO $db, int $marriageId, int $personId): ?int
    {
        $st = $db->prepare('SELECT husband_id, wife_id FROM marriages WHERE id = ?');
        $st->execute([$marriageId]);
        $m = $st->fetch(PDO::FETCH_ASSOC);
        if (!$m) {
            return null;
        }
        $h = (int)$m['husband_id'];
        $w = (int)$m['wife_id'];
        if ($h === $personId) {
            return $w;
        }
        return $w === $personId ? $h : null;
    }

    /** GET /api/person/relatives-state?id= — что показывать в блоке «Родственники». */
    public function state(Request $request): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::json(['error' => 'Нужен вход'], 401);
            return;
        }
        $db = Database::getConnection();
        $p = $this->loadPerson($db, (int)$request->get('id', 0));
        if (!$p) {
            Response::json(['error' => 'Человек не найден'], 404);
            return;
        }
        $canEdit = PersonAccessService::make()->canEditPerson((int)$p['id'], $user);
        $hasGender = in_array($p['gender'] ?? '', ['M', 'F'], true);
        $marriages = ($canEdit && $hasGender) ? SpouseController::activeMarriages($db, (int)$p['id']) : [];
        Response::json([
            'can_edit'  => $canEdit,
            'gender'    => $p['gender'] ?? '',
            'has_father' => !empty($p['father_id']),
            'has_mother' => !empty($p['mother_id']),
            'marriages' => $marriages,
        ]);
    }

    /** GET /api/person/child-search?target_id=&q= — кандидаты в дети. */
    public function search(Request $request): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::json(['error' => 'Нужен вход'], 401);
            return;
        }
        $db = Database::getConnection();
        $target = $this->loadPerson($db, (int)$request->get('target_id', 0));
        $col = $target ? self::parentColumn($target) : null;
        if (!$target || $col === null) {
            Response::json(['error' => 'Человек не найден / не указан пол'], 404);
            return;
        }
        if (!PersonAccessService::make()->canEditPerson((int)$target['id'], $user)) {
            Response::json(['error' => 'Нет прав'], 403);
            return;
        }
        $tid = (int)$target['id'];
        $exclude = array_values(array_unique(array_merge($this->lineIds($db, $tid, true), $this->lineIds($db, $tid, false))));
        $in = implode(',', array_fill(0, count($exclude), '?'));
        $sql = "SELECT id, first_name, last_name, maiden_name, birth_year, death_year, is_deceased FROM persons
                WHERE is_deleted = 0 AND {$col} IS NULL AND id NOT IN ($in)";
        $params = $exclude;
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

    /** POST /api/person/add-child — mode=existing (child_id) | new (first_name, gender, ...); marriage_id — от какого брака. */
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
            $target = $this->loadPerson($db, $targetId);
            $col = $target ? self::parentColumn($target) : null;
            if (!$target || $col === null) {
                Response::json(['error' => 'Человек не найден / не указан пол'], 404);
                return;
            }
            if (!PersonAccessService::make()->canEditPerson($targetId, $user)) {
                Response::json(['error' => 'Нет прав менять семью этой карточки'], 403);
                return;
            }
            $otherCol = $col === 'father_id' ? 'mother_id' : 'father_id';

            // Второй родитель — из действующего брака
            $active = SpouseController::activeMarriages($db, $targetId);
            $marriageId = (int)$request->post('marriage_id', 0);
            $spouseId = 0;
            if ($active) {
                $ids = array_map(static fn($m) => (int)$m['id'], $active);
                if ($marriageId === 0 && count($ids) === 1) {
                    $marriageId = $ids[0];
                }
                if ($marriageId === 0 || !in_array($marriageId, $ids, true)) {
                    Response::json(['error' => 'Выберите брак / Қай некеден екенін таңдаңыз'], 422);
                    return;
                }
                $spouseId = (int)$this->spouseOfMarriage($db, $marriageId, $targetId);
            }

            if ($mode === 'existing') {
                $childId = (int)$request->post('child_id', 0);
                $child = $childId > 0 ? $this->loadPerson($db, $childId) : null;
                if (!$child) {
                    Response::json(['error' => 'Человек не найден'], 422);
                    return;
                }
                $bad = array_merge($this->lineIds($db, $targetId, true), $this->lineIds($db, $targetId, false));
                if (in_array($childId, $bad, true)) {
                    Response::json(['error' => 'Этот человек не может быть ребёнком (предок или потомок)'], 422);
                    return;
                }
                if (!empty($child[$col])) {
                    Response::json(['error' => 'У этого человека уже указан ' . ($col === 'father_id' ? 'отец' : 'мать')], 409);
                    return;
                }
                $newId = $childId;
                $set = "{$col} = ?";
                $args = [$targetId];
                if ($spouseId > 0 && empty($child[$otherCol])) {
                    $set .= ", {$otherCol} = ?";
                    $args[] = $spouseId;
                }
                $args[] = $newId;
                $db->prepare("UPDATE persons SET {$set} WHERE id = ?")->execute($args);
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
                $gender = (string)$request->post('gender', '');
                if (!in_array($gender, ['M', 'F'], true)) {
                    Response::json(['error' => 'Укажите пол ребёнка / Баланың жынысын көрсетіңіз'], 422);
                    return;
                }
                $last = trim((string)$request->post('last_name', ''));
                if ($last === '') {
                    if ($col === 'father_id') {
                        $last = (string)($target['last_name'] ?? '');
                    } elseif ($spouseId > 0) {
                        $sp = $this->loadPerson($db, $spouseId);
                        $last = $sp ? (string)($sp['last_name'] ?? '') : '';
                    }
                }
                $by = trim((string)$request->post('birth_year', ''));
                $by = ctype_digit($by) && strlen($by) === 4 ? (int)$by : null;
                $fatherId = $col === 'father_id' ? $targetId : ($spouseId > 0 ? $spouseId : null);
                $motherId = $col === 'mother_id' ? $targetId : ($spouseId > 0 ? $spouseId : null);
                $db->prepare(
                    "INSERT INTO persons (first_name, last_name, gender, birth_year, father_id, mother_id, clan_id, clan_ru, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NULL, 'белгісіз', ?, NOW())"
                )->execute([$first, $last, $gender, $by, $fatherId, $motherId, $user['id'] ?? null]);
                $newId = (int)$db->lastInsertId();
            } else {
                Response::json(['error' => 'mode: existing|new'], 400);
                return;
            }

            try {
                $db->prepare(
                    "INSERT INTO audit_logs (user_id, user_name, user_email, action, entity_table, entity_id, summary, old_values, new_values, created_at)
                     VALUES (?, ?, ?, 'UPDATE', 'persons', ?, ?, NULL, ?, NOW())"
                )->execute([
                    $user['id'] ?? null,
                    $user['name'] ?? 'Система',
                    $user['email'] ?? 'system@altybayev.kz',
                    $newId,
                    "Бала қосылды / Добавлен ребёнок: #{$newId} → #{$targetId} ({$mode})",
                    json_encode(['parent_id' => $targetId, 'other_parent_id' => $spouseId ?: null, 'marriage_id' => $marriageId ?: null], JSON_UNESCAPED_UNICODE),
                ]);
            } catch (\Throwable $e) {
                // журнал не должен ломать добавление
            }

            Response::json(['status' => 'success', 'child_id' => $newId]);
        } catch (\Throwable $e) {
            Response::json(['error' => 'Не удалось сохранить'], 500);
        }
    }
}
