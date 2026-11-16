<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Config;
use App\Core\Response;
use App\Core\Session;
use PDO;

class AdminController
{
    /**
     * Главная страница раздела «Әкімші / Админка»
     */
    public function index(Request $request): void
    {
        if (!Auth::isAdmin()) {
            Response::redirect('/?error=admin_only');
        }

        $user = Auth::user();
        $userPersonId = !empty($user['person_id']) ? (int)$user['person_id'] : 6;
        $db = Database::getConnection();

        // 1. Список пользователей
        $users = [];
        $stmtU = $db->query("SHOW TABLES LIKE 'users'");
        if ($stmtU->rowCount() > 0) {
            // TASK-014 (note 16.11): имя связанной персоны и признак «карточка у нескольких пользователей»
            $uStmt = $db->query(
                "SELECT u.id, u.name, u.email, u.role, u.person_id, u.is_active, u.last_login_at, u.created_at,
                        TRIM(CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))) AS person_name,
                        (SELECT COUNT(*) FROM users u2 WHERE u2.person_id = u.person_id) AS person_shared
                 FROM users u
                 LEFT JOIN persons p ON p.id = u.person_id
                 ORDER BY u.id ASC"
            );
            $users = $uStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 2. Список приглашений
        $invitations = [];
        $stmtI = $db->query("SHOW TABLES LIKE 'invitations'");
        if ($stmtI->rowCount() > 0) {
            $iStmt = $db->query("SELECT * FROM invitations ORDER BY id DESC LIMIT 50");
            $invitations = $iStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 3. Статистика
        $personsCount = (int)$db->query("SELECT COUNT(*) FROM persons WHERE is_deleted = 0")->fetchColumn();
        $marriagesCount = (int)$db->query("SELECT COUNT(*) FROM marriages")->fetchColumn();
        $auditCount = (int)$db->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
        $mediaCount = (int)$db->query("SELECT COUNT(*) FROM person_media")->fetchColumn();

        $faceSvc = new \App\Services\FaceRecognitionService($db);
        $faceReady = $faceSvc->isReady();
        $faceEnabled = $faceReady && $faceSvc->enabled();

        Response::view('admin/index', [
            'user'                   => $user,
            'userPersonId'           => $userPersonId,
            'users'                  => $users,
            'invitations'            => $invitations,
            'personsCount'           => $personsCount,
            'marriagesCount'         => $marriagesCount,
            'auditCount'             => $auditCount,
            'mediaCount'             => $mediaCount,
            'canEdit'                => true,
            'isAdmin'                => true,
            'faceRecognitionReady'   => $faceReady,
            'faceRecognitionEnabled' => $faceEnabled,
        ]);
    }

    /**
     * API обновления роли пользователя
     */
    public function updateUserRole(Request $request): void
    {
        if (!Auth::isAdmin()) {
            Response::json(['error' => 'Тек әкімшіге рұқсат / Доступно только администратору'], 403);
            return;
        }

        if (!Session::validateCsrf((string)$request->post('csrf_token', ''))) {
            Response::json(['error' => 'Сессия ескірді, бетті жаңартыңыз / Сессия устарела, обновите страницу'], 419);
            return;
        }

        $userId = (int)$request->post('user_id', 0);
        $role = trim((string)$request->post('role', ''));

        if ($userId <= 0 || !in_array($role, ['admin', 'editor', 'viewer'], true)) {
            Response::json(['error' => 'Деректер қате / Некорректные параметры'], 400);
            return;
        }

        try {
            $db = Database::getConnection();

            // TASK-014: нельзя менять роль главному администратору и самому себе
            $tStmt = $db->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            $tStmt->execute([$userId]);
            $targetEmail = strtolower((string)$tStmt->fetchColumn());
            if ($targetEmail === '') {
                Response::json(['error' => 'Пайдаланушы табылмады / Пользователь не найден'], 404);
            }
            if ($targetEmail === $this->superAdminEmail() || $userId === (int)Auth::id()) {
                Response::json(['error' => 'Өз рөліңізді және бас әкімшінің рөлін өзгертуге болмайды / Нельзя менять свою роль и роль главного администратора'], 422);
            }

            $stmt = $db->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$role, $userId]);
            // держим роль в приглашении в согласии с ролью пользователя
            $db->prepare("UPDATE invitations SET role = ? WHERE email = ?")->execute([$role, $targetEmail]);

            // Логируем в аудит
            $currUser = Auth::user();
            $logStmt = $db->prepare("
                INSERT INTO audit_logs 
                (user_id, user_name, user_email, action, entity_table, entity_id, summary, new_values, created_at)
                VALUES (?, ?, ?, 'UPDATE', 'users', ?, ?, ?, NOW())
            ");
            $summary = "Смена роли пользователя #{$userId} на {$role}";
            $logStmt->execute([
                $currUser['id'] ?? null,
                $currUser['name'] ?? 'Әкімші',
                $currUser['email'] ?? null,
                $userId,
                $summary,
                json_encode(['role' => $role]),
            ]);

            Response::json(['success' => true, 'message' => 'Рөл сәтті жаңартылды / Роль успешно обновлена']);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('updateUserRole: ' . $e->getMessage(), $e);
            Response::json(['error' => 'Серверде қате / Ошибка сервера'], 500);
        }
    }

    // ------------------------------------------------------------------
    // TASK-024: отвязка аккаунта и удаление пользователя (люди в дереве не затрагиваются)
    // ------------------------------------------------------------------

    /** Общая проверка: admin + CSRF + цель существует и это не я / не главный админ. Возвращает строку users. */
    private function guardUserTarget(Request $request, PDO $db): array
    {
        $this->guardInvitePost($request);
        $userId = (int)$request->post('user_id', 0);
        if ($userId <= 0) {
            Response::json(['error' => 'Деректер қате / Некорректные параметры'], 422);
        }
        $st = $db->prepare('SELECT id, email, role, person_id, google_id FROM users WHERE id = ? LIMIT 1');
        $st->execute([$userId]);
        $target = $st->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            Response::json(['error' => 'Пайдаланушы табылмады / Пользователь не найден'], 404);
        }
        if (strtolower((string)$target['email']) === $this->superAdminEmail() || (int)$target['id'] === (int)Auth::id()) {
            Response::json(['error' => 'Өзіңізді және бас әкімшіні өзгертуге болмайды / Нельзя трогать себя и главного администратора'], 422);
        }
        return $target;
    }

    private function auditUser(PDO $db, string $action, int $userId, string $summary, ?array $old, ?array $new): void
    {
        $c = Auth::user();
        $db->prepare("
            INSERT INTO audit_logs (user_id, user_name, user_email, action, entity_table, entity_id, summary, old_values, new_values, created_at)
            VALUES (?, ?, ?, ?, 'users', ?, ?, ?, ?, NOW())
        ")->execute([
            $c['id'] ?? null, $c['name'] ?? 'Әкімші', $c['email'] ?? null, $action, $userId,
            mb_substr($summary, 0, 255),
            $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    /**
     * POST /api/admin/user/unlink — отвязать аккаунт: сбрасываются привязка к карточке и Google-ID.
     * Роль, активность и e-mail остаются; следующий вход через Google привяжет заново.
     */
    public function unlinkUser(Request $request): void
    {
        $db = Database::getConnection();
        $t = $this->guardUserTarget($request, $db);
        try {
            $db->beginTransaction();
            $db->prepare('UPDATE users SET person_id = NULL, google_id = NULL, updated_at = NOW() WHERE id = ?')->execute([(int)$t['id']]);
            // приглашение этого e-mail больше не держит чужую карточку
            $svc = new \App\Services\PersonContactService($db);
            if ($svc->hasInvitationPersonColumn()) {
                $db->prepare('UPDATE invitations SET person_id = NULL WHERE email = ?')->execute([strtolower((string)$t['email'])]);
            }
            $this->auditUser($db, 'UPDATE', (int)$t['id'], "Аккаунт ажыратылды / Аккаунт отвязан: #{$t['id']} {$t['email']}",
                ['person_id' => $t['person_id'], 'google_id' => $t['google_id'] ? 'set' : null], ['person_id' => null, 'google_id' => null]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            \App\Core\Logger::error('unlinkUser: ' . $e->getMessage(), $e);
            Response::json(['error' => 'Серверде қате / Ошибка сервера'], 500);
        }
        Response::json(['success' => true, 'message' => 'Аккаунт ажыратылды / Аккаунт отвязан']);
    }

    /**
     * POST /api/admin/user/delete — удалить пользователя (запись users). Персоны и правки в дереве остаются.
     * Приглашение возвращается в «ожидает», чтобы человек мог войти заново и получить новый ID.
     */
    public function deleteUser(Request $request): void
    {
        $db = Database::getConnection();
        $t = $this->guardUserTarget($request, $db);
        try {
            $db->beginTransaction();
            $email = strtolower((string)$t['email']);
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([(int)$t['id']]);
            $db->prepare("UPDATE invitations SET status = 'pending', accepted_at = NULL WHERE email = ? AND status = 'accepted'")->execute([$email]);
            $this->auditUser($db, 'DELETE', (int)$t['id'], "Пайдаланушы өшірілді / Пользователь удалён: #{$t['id']} {$email}",
                ['email' => $email, 'role' => $t['role'], 'person_id' => $t['person_id']], null);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            \App\Core\Logger::error('deleteUser: ' . $e->getMessage(), $e);
            Response::json(['error' => 'Серверде қате / Ошибка сервера (возможна связь с другими данными)'], 500);
        }
        Response::json(['success' => true, 'message' => 'Пайдаланушы өшірілді / Пользователь удалён']);
    }

    // ------------------------------------------------------------------
    // TASK-014: Приглашения (Шақырулар)
    // ------------------------------------------------------------------

    private const INVITE_ROLES = ['admin', 'editor', 'viewer'];

    /**
     * Общая проверка для POST-запросов приглашений: только admin + CSRF.
     */
    private function guardInvitePost(Request $request): void
    {
        if (!Auth::isAdmin()) {
            Response::json(['error' => 'Тек әкімшіге рұқсат / Доступно только администратору'], 403);
        }
        if (!Session::validateCsrf((string)$request->post('csrf_token', ''))) {
            Response::json(['error' => 'Сессия ескірді, бетті жаңартыңыз / Сессия устарела, обновите страницу'], 419);
        }
    }

    private function superAdminEmail(): string
    {
        return strtolower(trim((string)Config::get('SUPER_ADMIN_EMAIL', 'zhandos.alt06@gmail.com')));
    }

    private function auditInvitation(PDO $db, string $action, int $invitationId, string $summary, ?array $old, ?array $new): void
    {
        $currUser = Auth::user();
        $stmt = $db->prepare("
            INSERT INTO audit_logs
            (user_id, user_name, user_email, action, entity_table, entity_id, summary, old_values, new_values, created_at)
            VALUES (?, ?, ?, ?, 'invitations', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $currUser['id'] ?? null,
            $currUser['name'] ?? 'Әкімші',
            $currUser['email'] ?? null,
            $action,
            $invitationId,
            mb_substr($summary, 0, 255),
            $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    private function findInvitation(PDO $db, int $id): ?array
    {
        $stmt = $db->prepare("SELECT * FROM invitations WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * POST /api/admin/invitations/create — пригласить по Gmail с ролью и заметкой.
     * Если приглашение было отозвано — восстанавливает его (email UNIQUE, дублей нет).
     */
    public function createInvitation(Request $request): void
    {
        $this->guardInvitePost($request);

        $email = strtolower(trim((string)$request->post('email', '')));
        $role = trim((string)$request->post('role', 'viewer'));
        $notes = trim((string)$request->post('notes', ''));
        $notes = $notes === '' ? null : mb_substr($notes, 0, 255);

        if ($email === '' || mb_strlen($email) > 191 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Response::json(['error' => 'Email қате / Некорректный e-mail'], 422);
        }
        if (!in_array($role, self::INVITE_ROLES, true)) {
            Response::json(['error' => 'Рөл қате / Некорректная роль'], 422);
        }
        if ($email === $this->superAdminEmail()) {
            Response::json(['error' => 'Бұл бас әкімші / Это главный администратор — приглашение не нужно'], 422);
        }

        $db = Database::getConnection();
        $currUser = Auth::user();

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT * FROM invitations WHERE email = ? LIMIT 1 FOR UPDATE");
            $stmt->execute([$email]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            $uStmt = $db->prepare("SELECT id, role, is_active FROM users WHERE email = ? LIMIT 1");
            $uStmt->execute([$email]);
            $existingUser = $uStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($existing && $existing['status'] !== 'revoked') {
                $db->rollBack();
                Response::json(['error' => 'Бұл email-ге шақыру бар / Этот e-mail уже приглашён'], 409);
            }
            if (!$existing && $existingUser && (int)$existingUser['is_active'] === 1) {
                $db->rollBack();
                Response::json(['error' => 'Бұл адам сайтқа кіре алады — рөлді «Пайдаланушылар» кестесінде өзгертіңіз / Этот человек уже пользователь — роль меняется в таблице «Пользователи»'], 409);
            }

            // Если пользователь уже есть (был заблокирован при отзыве) — сразу возвращаем доступ
            $newStatus = $existingUser ? 'accepted' : 'pending';

            if ($existing) {
                $upd = $db->prepare("
                    UPDATE invitations
                    SET role = ?, notes = ?, status = ?, invited_by = ?, created_at = NOW(),
                        accepted_at = " . ($newStatus === 'accepted' ? 'NOW()' : 'NULL') . "
                    WHERE id = ?
                ");
                $upd->execute([$role, $notes, $newStatus, $currUser['id'] ?? null, (int)$existing['id']]);
                $invitationId = (int)$existing['id'];
            } else {
                $ins = $db->prepare("
                    INSERT INTO invitations (email, role, invited_by, notes, status, created_at, accepted_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), " . ($newStatus === 'accepted' ? 'NOW()' : 'NULL') . ")
                ");
                $ins->execute([$email, $role, $currUser['id'] ?? null, $notes, $newStatus]);
                $invitationId = (int)$db->lastInsertId();
            }

            if ($existingUser) {
                $db->prepare("UPDATE users SET role = ?, is_active = 1, updated_at = NOW() WHERE id = ?")
                   ->execute([$role, (int)$existingUser['id']]);
            }

            // TASK-022: приглашение из админки — привязать к карточке, если email совпал с контактом человека
            try {
                (new \App\Services\PersonContactService($db))->linkByEmailMatch($email);
            } catch (\Throwable $e) {
                \App\Core\Logger::error('createInvitation link person: ' . $e->getMessage(), $e);
            }

            $this->auditInvitation(
                $db,
                $existing ? 'UPDATE' : 'CREATE',
                $invitationId,
                ($existing ? 'Шақыру қалпына келтірілді / Приглашение восстановлено: ' : 'Шақыру / Приглашение: ') . "{$email} ({$role})",
                $existing ? ['role' => $existing['role'], 'status' => $existing['status'], 'notes' => $existing['notes']] : null,
                ['email' => $email, 'role' => $role, 'status' => $newStatus, 'notes' => $notes]
            );

            $db->commit();
        } catch (\PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // 23000 — нарушение UNIQUE (параллельный запрос с тем же email)
            if ($e->getCode() === '23000') {
                Response::json(['error' => 'Бұл email-ге шақыру бар / Этот e-mail уже приглашён'], 409);
            }
            \App\Core\Logger::error('createInvitation: ' . $e->getMessage(), $e);
            Response::json(['error' => 'Серверде қате / Ошибка сервера'], 500);
        }

        Response::json([
            'success' => true,
            'message' => 'Шақыру сақталды / Приглашение сохранено',
            'invitation' => ['id' => $invitationId, 'email' => $email, 'role' => $role, 'status' => $newStatus],
        ]);
    }

    /**
     * POST /api/admin/invitations/role — сменить роль в приглашении
     * (если человек уже вошёл — меняется и его роль в users).
     */
    public function updateInvitationRole(Request $request): void
    {
        $this->guardInvitePost($request);

        $id = (int)$request->post('id', 0);
        $role = trim((string)$request->post('role', ''));
        if ($id <= 0 || !in_array($role, self::INVITE_ROLES, true)) {
            Response::json(['error' => 'Деректер қате / Некорректные параметры'], 422);
        }

        $db = Database::getConnection();
        $currEmail = strtolower((string)(Auth::user()['email'] ?? ''));

        $db->beginTransaction();
        try {
            $lock = $db->prepare("SELECT * FROM invitations WHERE id = ? LIMIT 1 FOR UPDATE");
            $lock->execute([$id]);
            $inv = $lock->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$inv) {
                $db->rollBack();
                Response::json(['error' => 'Шақыру табылмады / Приглашение не найдено'], 404);
            }
            if ($inv['status'] === 'revoked') {
                $db->rollBack();
                Response::json(['error' => 'Шақыру қайтарылған / Приглашение отозвано — пригласите заново'], 409);
            }
            $invEmail = strtolower((string)$inv['email']);
            if ($invEmail === $this->superAdminEmail() || $invEmail === $currEmail) {
                $db->rollBack();
                Response::json(['error' => 'Өз рөліңізді өзгертуге болмайды / Нельзя менять свою роль'], 422);
            }

            $db->prepare("UPDATE invitations SET role = ? WHERE id = ?")->execute([$role, $id]);
            // если человек уже входил — меняем и его роль (по email, независимо от статуса в памяти)
            $db->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE email = ?")->execute([$role, $invEmail]);
            $this->auditInvitation($db, 'UPDATE', $id, "Шақыру рөлі / Роль приглашения: {$inv['email']} → {$role}",
                ['role' => $inv['role']], ['role' => $role]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            \App\Core\Logger::error('updateInvitationRole: ' . $e->getMessage(), $e);
            Response::json(['error' => 'Серверде қате / Ошибка сервера'], 500);
        }

        Response::json(['success' => true, 'message' => 'Рөл өзгертілді / Роль изменена']);
    }

    /**
     * POST /api/admin/invitations/revoke — отозвать приглашение.
     * Если человек уже входил — его аккаунт блокируется (is_active = 0), данные не удаляются.
     */
    public function revokeInvitation(Request $request): void
    {
        $this->guardInvitePost($request);

        $id = (int)$request->post('id', 0);
        if ($id <= 0) {
            Response::json(['error' => 'Деректер қате / Некорректные параметры'], 422);
        }

        $db = Database::getConnection();
        $inv = $this->findInvitation($db, $id);
        if (!$inv) {
            Response::json(['error' => 'Шақыру табылмады / Приглашение не найдено'], 404);
        }
        if ($inv['status'] === 'revoked') {
            Response::json(['success' => true, 'message' => 'Шақыру бұрын қайтарылған / Уже отозвано']);
        }

        $email = strtolower((string)$inv['email']);
        $currUser = Auth::user();
        if ($email === $this->superAdminEmail() || $email === strtolower((string)($currUser['email'] ?? ''))) {
            Response::json(['error' => 'Өз аккаунтыңызды бұғаттауға болмайды / Нельзя отозвать доступ у себя'], 422);
        }

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE invitations SET status = 'revoked' WHERE id = ?")->execute([$id]);
            $db->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE email = ?")->execute([$email]);
            $this->auditInvitation($db, 'UPDATE', $id, "Шақыру қайтарылды / Приглашение отозвано: {$email}",
                ['status' => $inv['status']], ['status' => 'revoked']);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            \App\Core\Logger::error('revokeInvitation: ' . $e->getMessage(), $e);
            Response::json(['error' => 'Серверде қате / Ошибка сервера'], 500);
        }

        Response::json(['success' => true, 'message' => 'Шақыру қайтарылды / Приглашение отозвано']);
    }
}
