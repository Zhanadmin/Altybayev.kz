<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use PDO;

class HistoryController
{
    /**
     * Раздел «Тарих / История правок» (audit_logs)
     */
    public function index(Request $request): void
    {
        $user = Auth::user();
        $userPersonId = !empty($user['person_id']) ? (int)$user['person_id'] : 6;
        $db = Database::getConnection();

        $actionFilter = trim((string)$request->get('action', ''));
        $tableFilter = trim((string)$request->get('table', ''));

        $logs = [];
        $stmtT = $db->query("SHOW TABLES LIKE 'audit_logs'");
        if ($stmtT->rowCount() > 0) {
            $sql = "SELECT * FROM audit_logs WHERE 1=1";
            $params = [];

            if ($actionFilter !== '') {
                $sql .= " AND action = ?";
                $params[] = $actionFilter;
            }

            if ($tableFilter !== '') {
                $sql .= " AND entity_table = ?";
                $params[] = $tableFilter;
            }

            $sql .= " ORDER BY id DESC LIMIT 150";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        Response::view('history/index', [
            'user'         => $user,
            'userPersonId' => $userPersonId,
            'logs'         => $logs,
            'totalCount'   => count($logs),
            'filters'      => [
                'action' => $actionFilter,
                'table'  => $tableFilter,
            ],
            'canEdit'      => Auth::canEdit(),
            'isAdmin'      => Auth::isAdmin(),
        ]);
    }

    /**
     * Откат изменения (только для роли admin)
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

        $table = $log['entity_table'];
        $entityId = (int)$log['entity_id'];
        $action = $log['action'];
        $oldVals = !empty($log['old_values']) ? json_decode($log['old_values'], true) : null;

        // Откатить можно UPDATE или DELETE, если есть old_values
        if (empty($oldVals) || !is_array($oldVals)) {
            Response::json(['error' => 'Бұл жазба бойынша бұрынғы мәндер жоқ / Откат невозможен: нет сохраненных исходных данных'], 400);
            return;
        }

        try {
            $allowedTables = ['persons', 'marriages', 'person_media', 'clans'];
            if (!in_array($table, $allowedTables, true)) {
                Response::json(['error' => 'Кесте үшін откатқа тыйым салынған / Откат для этой таблицы запрещен'], 403);
                return;
            }

            // Получаем поля для UPDATE
            $setParts = [];
            $setParams = [];
            foreach ($oldVals as $col => $val) {
                if ($col === 'id' || $col === 'created_at') continue;
                $setParts[] = "`{$col}` = ?";
                $setParams[] = $val;
            }

            if (!empty($setParts)) {
                $setParams[] = $entityId;
                $updateSql = "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE `id` = ?";
                $uStmt = $db->prepare($updateSql);
                $uStmt->execute($setParams);
            }

            // Запись в audit_logs о выполненном откате
            $user = Auth::user();
            $logStmt = $db->prepare("
                INSERT INTO audit_logs 
                (user_id, user_name, user_email, action, entity_table, entity_id, summary, old_values, new_values, created_at)
                VALUES (?, ?, ?, 'ROLLBACK', ?, ?, ?, ?, ?, NOW())
            ");
            $summary = "Откат изменения #{$logId} для {$table} ID {$entityId} администратором";
            $logStmt->execute([
                $user['id'] ?? null,
                $user['name'] ?? 'Әкімші',
                $user['email'] ?? null,
                $table,
                $entityId,
                $summary,
                $log['new_values'],
                $log['old_values'],
            ]);

            Response::json([
                'success' => true,
                'message' => 'Өзгерту сәтті кері қайтарылды / Изменение успешно откачено',
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }
}
