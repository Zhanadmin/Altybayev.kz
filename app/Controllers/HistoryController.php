<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Services\ChangeLogService;
use PDO;

class HistoryController
{
    /**
     * ALT-004: «Журнал изменений» — только для администратора (маршрут с middleware admin + проверка здесь).
     * Фильтры: пользователь, дата, человек, тип действия, объект; поиск по имени.
     */
    public function index(Request $request): void
    {
        if (!Auth::isAdmin()) {
            Response::json(['error' => 'Тек әкімшіге рұқсат / Доступно только администратору'], 403);
            return;
        }

        $user = Auth::user();
        $userPersonId = !empty($user['person_id']) ? (int)$user['person_id'] : 6;
        $db = Database::getConnection();
        $svc = new ChangeLogService($db, I18n::lang());

        $filters = [
            'user'      => (int)$request->get('user', 0),
            'date_from' => trim((string)$request->get('date_from', '')),
            'date_to'   => trim((string)$request->get('date_to', '')),
            'person'    => (int)$request->get('person', 0),
            'action'    => trim((string)$request->get('action', '')),
            'table'     => trim((string)$request->get('table', '')),
            'q'         => trim((string)$request->get('q', '')),
            'kind'      => trim((string)$request->get('kind', '')),
        ];
        if (!in_array($filters['kind'], ChangeLogService::KINDS, true)) {
            $filters['kind'] = '';
        }
        $page = max(1, (int)$request->get('page', 1));

        $logs = [];
        $total = 0;
        $users = [];
        $personName = '';
        $kindCounts = [];
        if ($svc->isReady()) {
            $res = $svc->search($filters, $page);
            $logs = $res['rows'];
            $total = $res['total'];
            $users = $svc->users();
            $kindCounts = $svc->kindCounts($filters);
            if ($filters['person'] > 0) {
                $st = $db->prepare("SELECT TRIM(CONCAT_WS(' ', last_name, first_name, middle_name)) FROM persons WHERE id = ?");
                $st->execute([$filters['person']]);
                $personName = (string)$st->fetchColumn();
            }
        }

        Response::view('history/index', [
            'user'         => $user,
            'userPersonId' => $userPersonId,
            'logs'         => $logs,
            'totalCount'   => $total,
            'page'         => $page,
            'perPage'      => ChangeLogService::PER_PAGE,
            'users'        => $users,
            'kindCounts'   => $kindCounts,
            'personName'   => $personName,
            'filters'      => $filters,
            'tableLabels'  => ChangeLogService::tableLabels(I18n::lang()),
            'canEdit'      => Auth::canEdit(),
            'isAdmin'      => true,
        ]);
    }

    /**
     * Откат изменения (только для роли admin).
     * UPDATE/DELETE — возвращаются только поля, изменённые этой записью; CREATE человека — мягкое удаление.
     * Сама запись журнала не меняется: откат добавляет новую строку ROLLBACK.
     */
    public function rollback(Request $request): void
    {
        if (!Auth::isAdmin()) {
            Response::json(['error' => 'Тек әкімшіге рұқсат / Доступно только администратору'], 403);
            return;
        }

        $logId = (int)$request->post('log_id', 0);
        if ($logId <= 0) {
            Response::json(['error' => 'log_id is required'], 400);
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM audit_logs WHERE id = ?");
        $stmt->execute([$logId]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$log) {
            Response::json(['error' => 'Жазба табылмады / Запись аудита не найдена'], 404);
            return;
        }

        $table = (string)$log['entity_table'];
        $entityId = (int)$log['entity_id'];
        $action = (string)$log['action'];

        // повторный откат той же записи не допускаем
        $dup = $db->prepare("SELECT 1 FROM audit_logs WHERE action = 'ROLLBACK' AND entity_table = ? AND entity_id = ?
                              AND (summary LIKE ? OR summary LIKE ?) LIMIT 1");
        $dup->execute([$table, $entityId, "%#{$logId} для%", "%#{$logId}:%"]);
        if ($dup->fetchColumn()) {
            Response::json(['error' => 'Бұл өзгеріс бұрын қайтарылған / Это изменение уже откачено'], 409);
            return;
        }

        $u = Auth::user();
        $logRollback = function (string $summary, ?string $old, ?string $new) use ($db, $u, $table, $entityId): void {
            $db->prepare("INSERT INTO audit_logs (user_id, user_name, user_email, action, entity_table, entity_id, summary, old_values, new_values, created_at)
                          VALUES (?, ?, ?, 'ROLLBACK', ?, ?, ?, ?, ?, NOW())")->execute([
                $u['id'] ?? null,
                $u['name'] ?? 'Әкімші',
                $u['email'] ?? null,
                $table,
                $entityId,
                $summary,
                $old,
                $new,
            ]);
        };

        // TASK-032: откат добавления человека — мягкое удаление карточки (данные остаются, можно вернуть)
        if ($action === 'CREATE' && $table === 'persons') {
            try {
                $db->prepare('UPDATE `persons` SET `is_deleted` = 1, `updated_at` = NOW() WHERE `id` = ?')->execute([$entityId]);
                $logRollback("Откат добавления #{$logId}: карточка скрыта (soft-delete)", $log['new_values'], json_encode(['is_deleted' => 1]));
                Response::json(['success' => true, 'message' => 'Қосылған тұлға жасырылды / Добавленная карточка скрыта']);
            } catch (\Throwable $e) {
                Response::json(['error' => $e->getMessage()], 500);
            }
            return;
        }

        $plan = (new ChangeLogService($db, I18n::lang()))->rollbackPlan($log);
        if ($plan === null) {
            Response::json(['error' => 'Бұл жазбаны қайтару мүмкін емес / Откат невозможен: нет сохранённых исходных данных или таблица не поддерживается'], 400);
            return;
        }

        try {
            $setParts = [];
            $params = [];
            foreach ($plan['values'] as $col => $val) {
                $setParts[] = "`{$col}` = ?";
                $params[] = is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : $val;
            }
            $params[] = $plan['id'];
            $db->prepare("UPDATE `{$plan['table']}` SET " . implode(', ', $setParts) . " WHERE `id` = ?")->execute($params);

            $logRollback("Откат изменения #{$logId} для {$table} ID {$entityId} администратором", $log['new_values'], $log['old_values']);

            Response::json(['success' => true, 'message' => 'Өзгерту сәтті кері қайтарылды / Изменение успешно откачено']);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }
}
