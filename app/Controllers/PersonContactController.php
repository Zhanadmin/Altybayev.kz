<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\PersonContactService;
use PDO;

/**
 * TASK-022: контакты в карточке человека и приглашение из карточки.
 *  - POST /api/person/contacts/save — телефон(ы) и email: сам человек (своя карточка), editor, admin
 *  - POST /api/person/invite        — приглашение на email карточки: editor, admin
 */
class PersonContactController
{
    /** Может ли текущий пользователь менять контакты этой карточки */
    public static function canEditContacts(int $personId): bool
    {
        if (Auth::canEdit()) {
            return true;
        }
        $user = Auth::user();
        return $user !== null && !empty($user['person_id']) && (int)$user['person_id'] === $personId;
    }

    private function guardPost(Request $request): void
    {
        if (!Auth::check()) {
            Response::json(['error' => 'Кіру қажет / Нужен вход'], 401);
        }
        if (!Session::validateCsrf((string)$request->post('csrf_token', ''))) {
            Response::json(['error' => 'Сессия ескірді, бетті жаңартыңыз / Сессия устарела, обновите страницу'], 419);
        }
    }

    private function loadPerson(PDO $db, int $personId): array
    {
        $st = $db->prepare('SELECT id, first_name, last_name FROM persons WHERE id = ? AND is_deleted = 0 LIMIT 1');
        $st->execute([$personId]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            Response::json(['error' => 'Тұлға табылмады / Персона не найдена'], 404);
        }
        return $p;
    }

    private function audit(PDO $db, string $action, string $table, int $entityId, string $summary, ?array $old, ?array $new): void
    {
        $u = Auth::user();
        try {
            $db->prepare("
                INSERT INTO audit_logs
                (user_id, user_name, user_email, action, entity_table, entity_id, summary, old_values, new_values, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $u['id'] ?? null,
                $u['name'] ?? '',
                $u['email'] ?? null,
                $action,
                $table,
                $entityId,
                mb_substr($summary, 0, 255),
                $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
                $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            Logger::error('PersonContact audit: ' . $e->getMessage(), $e);
        }
    }

    /**
     * POST /api/person/contacts/save: person_id, phones[] , email, csrf_token
     */
    public function save(Request $request): void
    {
        $this->guardPost($request);

        $personId = (int)$request->post('person_id', 0);
        if ($personId <= 0 || !self::canEditContacts($personId)) {
            Response::json(['error' => 'Рұқсат жоқ / Нет прав на изменение контактов'], 403);
        }

        $phones = $request->post('phones', []);
        $phones = is_array($phones) ? $phones : [(string)$phones];
        $email = (string)$request->post('email', '');

        $db = Database::getConnection();
        $person = $this->loadPerson($db, $personId);
        $svc = new PersonContactService($db);

        try {
            $res = $svc->saveContacts($personId, array_map('strval', array_slice($phones, 0, 20)), $email);
        } catch (\Throwable $e) {
            Logger::error('contacts save: ' . $e->getMessage(), $e);
            Response::json(['error' => 'Серверде қате / Ошибка сервера'], 500);
        }
        if (empty($res['ok'])) {
            Response::json(['error' => $res['error'] ?? 'Қате / Ошибка'], 422);
        }

        $name = trim(($person['last_name'] ?? '') . ' ' . ($person['first_name'] ?? ''));
        $this->audit(
            $db,
            'UPDATE',
            'persons',
            $personId,
            "Байланыс / Контакты: {$name}",
            ['phones' => $res['old']['phones'], 'emails' => $res['old']['emails']],
            ['phones' => $res['contacts']['phones'], 'emails' => $res['contacts']['emails']]
        );

        $status = $svc->inviteStatus($personId, $res['contacts']['emails']);
        Response::json([
            'success'      => true,
            'message'      => 'Сақталды / Сохранено',
            'phones'       => array_map(static fn($p) => ['raw' => $p, 'pretty' => PersonContactService::formatPhone($p)], $res['contacts']['phones']),
            'email'        => $res['contacts']['emails'][0] ?? '',
            'invite_state' => $status['state'],
        ]);
    }

    /**
     * POST /api/person/invite: person_id, role, csrf_token — приглашение на email из карточки
     */
    public function invite(Request $request): void
    {
        $this->guardPost($request);

        if (!Auth::canEdit()) {
            Response::json(['error' => 'Тек редактор / әкімші шақыра алады / Приглашать могут только editor и admin'], 403);
        }

        $personId = (int)$request->post('person_id', 0);
        $role = trim((string)$request->post('role', 'viewer'));
        $allowedRoles = Auth::isAdmin() ? ['viewer', 'editor', 'admin'] : ['viewer', 'editor'];
        if (!in_array($role, $allowedRoles, true)) {
            Response::json(['error' => 'Рөл қате / Некорректная роль'], 422);
        }

        $db = Database::getConnection();
        $person = $this->loadPerson($db, $personId);
        $svc = new PersonContactService($db);

        if (!$svc->hasInvitationPersonColumn()) {
            Response::json(['error' => 'Миграция қолданылмаған (2026_11_16_add_invitation_person_link) / Не применена миграция БД'], 503);
        }

        $contacts = $svc->getContacts($personId);
        $email = $contacts['emails'][0] ?? '';
        if ($email === '' || PersonContactService::normalizeEmail($email) === null) {
            Response::json(['error' => 'Алдымен email көрсетіңіз / Сначала укажите email в карточке'], 422);
        }
        $superAdmin = strtolower(trim((string)Config::get('SUPER_ADMIN_EMAIL', 'zhandos.alt06@gmail.com')));
        if ($email === $superAdmin) {
            Response::json(['error' => 'Бұл бас әкімші / Это главный администратор — приглашение не нужно'], 422);
        }

        $currUser = Auth::user();
        $name = trim(($person['last_name'] ?? '') . ' ' . ($person['first_name'] ?? ''));
        $notes = mb_substr('Карточка: ' . $name . ' (#' . $personId . ')', 0, 255);

        try {
            $db->beginTransaction();

            $st = $db->prepare('SELECT * FROM invitations WHERE email = ? LIMIT 1 FOR UPDATE');
            $st->execute([$email]);
            $existing = $st->fetch(PDO::FETCH_ASSOC) ?: null;

            $us = $db->prepare('SELECT id, is_active, person_id FROM users WHERE email = ? LIMIT 1');
            $us->execute([$email]);
            $existingUser = $us->fetch(PDO::FETCH_ASSOC) ?: null;

            // Чужая карточка уже владеет этим приглашением — не перехватываем
            if ($existing && $existing['status'] !== 'revoked'
                && !empty($existing['person_id']) && (int)$existing['person_id'] !== $personId) {
                $db->rollBack();
                Response::json(['error' => 'Бұл email басқа карточкаға шақырылған / Этот e-mail уже приглашён по другой карточке'], 409);
            }
            if ($existing && $existing['status'] !== 'revoked') {
                // Приглашение из админки без привязки — просто привязываем к карточке, роль не меняем
                $db->prepare('UPDATE invitations SET person_id = ? WHERE id = ?')->execute([$personId, (int)$existing['id']]);
                if ($existingUser && empty($existingUser['person_id'])) {
                    $db->prepare('UPDATE users SET person_id = ?, updated_at = NOW() WHERE id = ?')->execute([$personId, (int)$existingUser['id']]);
                }
                $this->audit($db, 'UPDATE', 'invitations', (int)$existing['id'],
                    "Шақыру карточкаға байланды / Приглашение привязано к карточке: {$email} → #{$personId}",
                    ['person_id' => $existing['person_id'] ?? null], ['person_id' => $personId]);
                $db->commit();
                Response::json([
                    'success' => true,
                    'message' => 'Шақыру бұрын жасалған, карточкаға байланды / Приглашение уже было — привязано к карточке',
                    'invite_state' => $existing['status'] === 'accepted' ? 'accepted' : 'pending',
                ]);
            }
            if (!$existing && $existingUser && (int)$existingUser['is_active'] === 1) {
                if (empty($existingUser['person_id'])) {
                    $db->prepare('UPDATE users SET person_id = ?, updated_at = NOW() WHERE id = ?')->execute([$personId, (int)$existingUser['id']]);
                }
                $db->commit();
                Response::json([
                    'success' => true,
                    'message' => 'Бұл адам сайтқа кірген / Этот человек уже вошёл на сайт',
                    'invite_state' => 'accepted',
                ]);
            }

            $newStatus = $existingUser ? 'accepted' : 'pending';
            $acceptedSql = $newStatus === 'accepted' ? 'NOW()' : 'NULL';
            if ($existing) { // отозванное — восстанавливаем (email UNIQUE)
                $db->prepare("UPDATE invitations SET role = ?, notes = ?, status = ?, invited_by = ?, person_id = ?, created_at = NOW(), accepted_at = {$acceptedSql} WHERE id = ?")
                    ->execute([$role, $notes, $newStatus, $currUser['id'] ?? null, $personId, (int)$existing['id']]);
                $invId = (int)$existing['id'];
            } else {
                $db->prepare("INSERT INTO invitations (email, role, invited_by, notes, person_id, status, created_at, accepted_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), {$acceptedSql})")
                    ->execute([$email, $role, $currUser['id'] ?? null, $notes, $personId, $newStatus]);
                $invId = (int)$db->lastInsertId();
            }
            if ($existingUser) {
                $db->prepare('UPDATE users SET role = ?, is_active = 1, person_id = COALESCE(person_id, ?), updated_at = NOW() WHERE id = ?')
                    ->execute([$role, $personId, (int)$existingUser['id']]);
            }

            $this->audit($db, $existing ? 'UPDATE' : 'CREATE', 'invitations', $invId,
                "Шақыру / Приглашение из карточки: {$email} ({$role}) → #{$personId}",
                null, ['email' => $email, 'role' => $role, 'status' => $newStatus, 'person_id' => $personId]);

            $db->commit();
        } catch (\PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e->getCode() === '23000') {
                Response::json(['error' => 'Бұл email-ге шақыру бар / Этот e-mail уже приглашён'], 409);
            }
            Logger::error('person invite: ' . $e->getMessage(), $e);
            Response::json(['error' => 'Серверде қате / Ошибка сервера'], 500);
        }

        Response::json([
            'success' => true,
            'message' => 'Шақыру жасалды — адам Google арқылы кіреді / Приглашение создано — человек войдёт через Google',
            'invite_state' => $newStatus === 'accepted' ? 'accepted' : 'pending',
            'role' => $role,
        ]);
    }
}
