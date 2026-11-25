<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AccessRequestService;
use App\Services\PersonContactService;
use PDO;

/**
 * ALT-104: страница админа «Запросы на доступ» и решения по ним (одобрить / отклонить / заблокировать / вернуть).
 * Одобрение создаёт приглашение (invitations) с ролью и карточкой — вход идёт обычным путём через Google.
 */
class AccessRequestController
{
    private const ROLES = ['admin', 'editor', 'viewer'];

    /** GET /admin/access-requests */
    public function index(Request $request): void
    {
        $db = Database::getConnection();
        $svc = new AccessRequestService($db);
        $data = $svc->listForAdmin();

        // карточки для привязки: имя + номер (для выбора в списке)
        $persons = [];
        try {
            $persons = $db->query(
                "SELECT id, TRIM(CONCAT(COALESCE(last_name,''), ' ', COALESCE(first_name,''))) AS n
                 FROM persons WHERE is_deleted = 0 ORDER BY last_name, first_name LIMIT 3000"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $persons = [];
        }

        Response::view('admin/access_requests', [
            'user'      => Auth::user(),
            'requests'  => $data['requests'],
            'attempts'  => $data['attempts'],
            'persons'   => $persons,
            'csrf'      => Session::csrfToken(),
            'isAdmin'   => true,
            'canEdit'   => true,
        ]);
    }

    private function guard(Request $request): void
    {
        if (!Auth::isAdmin()) {
            Response::json(['error' => 'Тек әкімшіге рұқсат / Доступно только администратору'], 403);
        }
        if (!Session::validateCsrf((string)$request->post('csrf_token', ''))) {
            Response::json(['error' => 'Сессия ескірді, бетті жаңартыңыз / Сессия устарела, обновите страницу'], 419);
        }
    }

    /** POST /api/admin/access-requests/decide — id, action = approve|reject|block|reset, role, person_id */
    public function decide(Request $request): void
    {
        $this->guard($request);
        $id = (int)$request->post('id', 0);
        $action = (string)$request->post('action', '');
        if ($id <= 0 || !in_array($action, ['approve', 'reject', 'block', 'reset'], true)) {
            Response::json(['error' => 'Деректер қате / Некорректные параметры'], 422);
        }

        $db = Database::getConnection();
        $svc = new AccessRequestService($db);
        $req = $svc->find($id);
        if (!$req) {
            Response::json(['error' => 'Сұраныс табылмады / Запрос не найден'], 404);
        }
        $email = strtolower((string)$req['email']);
        $admin = Auth::user();

        try {
            $db->beginTransaction();

            if ($action === 'approve') {
                $role = trim((string)$request->post('role', 'viewer'));
                if (!in_array($role, self::ROLES, true)) {
                    $db->rollBack();
                    Response::json(['error' => 'Рөл қате / Некорректная роль'], 422);
                }
                $personId = (int)$request->post('person_id', 0);
                $pc = new PersonContactService($db);
                if ($personId > 0) {
                    $chk = $db->prepare('SELECT id FROM persons WHERE id = ? AND is_deleted = 0 LIMIT 1');
                    $chk->execute([$personId]);
                    if (!$chk->fetchColumn()) {
                        $db->rollBack();
                        Response::json(['error' => 'Карточка табылмады / Карточка не найдена'], 422);
                    }
                    if ($pc->personHasOtherUser($personId, 0)) {
                        $db->rollBack();
                        Response::json(['error' => 'Бұл карточка басқа пайдаланушыға байланған / Эта карточка уже занята другим пользователем'], 409);
                    }
                }

                // приглашение: создаём или возобновляем (email UNIQUE)
                $ex = $db->prepare('SELECT id FROM invitations WHERE email = ? LIMIT 1 FOR UPDATE');
                $ex->execute([$email]);
                $invId = (int)$ex->fetchColumn();
                $note = mb_substr('Запрос на доступ #' . $id . ((string)$req['message'] !== '' ? ': ' . $req['message'] : ''), 0, 255);
                if ($invId > 0) {
                    $db->prepare("UPDATE invitations SET role = ?, notes = ?, status = 'pending', invited_by = ?, created_at = NOW(), accepted_at = NULL WHERE id = ?")
                       ->execute([$role, $note, $admin['id'] ?? null, $invId]);
                } else {
                    $db->prepare("INSERT INTO invitations (email, role, invited_by, notes, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())")
                       ->execute([$email, $role, $admin['id'] ?? null, $note]);
                    $invId = (int)$db->lastInsertId();
                }
                if ($personId > 0 && $pc->hasInvitationPersonColumn()) {
                    $db->prepare('UPDATE invitations SET person_id = ? WHERE id = ?')->execute([$personId, $invId]);
                }
                $newStatus = 'approved';
            } else {
                $newStatus = ['reject' => 'rejected', 'block' => 'blocked', 'reset' => 'pending'][$action];
            }

            $db->prepare('UPDATE access_requests SET status = ?, decided_by = ?, decided_at = NOW() WHERE id = ?')
               ->execute([$newStatus, $admin['id'] ?? null, $id]);

            $db->prepare(
                "INSERT INTO audit_logs (user_id, user_name, user_email, action, entity_table, entity_id, summary, old_values, new_values, created_at)
                 VALUES (?, ?, ?, 'UPDATE', 'access_requests', ?, ?, ?, ?, NOW())"
            )->execute([
                $admin['id'] ?? null, $admin['name'] ?? 'Әкімші', $admin['email'] ?? null, $id,
                mb_substr("Қолжетімділік сұранысы / Запрос доступа: {$email} → {$newStatus}", 0, 255),
                json_encode(['status' => $req['status']], JSON_UNESCAPED_UNICODE),
                json_encode(['status' => $newStatus], JSON_UNESCAPED_UNICODE),
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Logger::error('access request decide: ' . $e->getMessage());
            Response::json(['error' => 'Серверде қате / Ошибка сервера'], 500);
        }

        Response::json(['success' => true, 'status' => $newStatus]);
    }
}
