<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use PDO;

/**
 * TASK-032: права обычного родственника на редактирование древа.
 *
 * Правила:
 *  - editor / admin — всё;
 *  - остальные вошедшие правят только «свой круг»: свою карточку (users.person_id), супругов
 *    (по маркам marriages) и детей (father_id / mother_id = я), а также тех людей, которых
 *    добавили сами (persons.created_by = users.id);
 *  - чужих, уже существующих людей — только просмотр;
 *  - ДОБАВЛЯТЬ новых людей и связи может любой вошедший (см. canAddRelativeTo).
 *
 * Все проверки — на сервере; кнопки в интерфейсе лишь отражают эти же ответы.
 */
class PersonAccessService
{
    /** @var array<int, int[]> кэш круга по person_id (на запрос) */
    private static array $circleCache = [];

    public function __construct(private PDO $db)
    {
    }

    public static function make(): self
    {
        return new self(\App\Core\Database::getConnection());
    }

    /** Свой круг карточки: она сама, супруги (все браки), дети. */
    public function circleOf(int $personId): array
    {
        if ($personId <= 0) {
            return [];
        }
        if (isset(self::$circleCache[$personId])) {
            return self::$circleCache[$personId];
        }
        $ids = [$personId];

        $st = $this->db->prepare('SELECT husband_id, wife_id FROM marriages WHERE husband_id = ? OR wife_id = ?');
        $st->execute([$personId, $personId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $ids[] = (int)$m['husband_id'];
            $ids[] = (int)$m['wife_id'];
        }

        $st = $this->db->prepare('SELECT id FROM persons WHERE (father_id = ? OR mother_id = ?) AND is_deleted = 0');
        $st->execute([$personId, $personId]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $cid) {
            $ids[] = (int)$cid;
        }

        return self::$circleCache[$personId] = array_values(array_unique(array_filter($ids)));
    }

    /** Может ли пользователь править эту карточку (данные, фото, контакты, даты). */
    public function canEditPerson(int $personId, ?array $user = null): bool
    {
        $user = $user ?? Auth::user();
        if (!$user || $personId <= 0) {
            return false;
        }
        $role = $user['role'] ?? 'viewer';
        if ($role === 'admin' || $role === 'editor') {
            return true;
        }
        $myPid = (int)($user['person_id'] ?? 0);
        if ($myPid > 0 && in_array($personId, $this->circleOf($myPid), true)) {
            return true;
        }
        return $this->isCreatedBy($personId, (int)($user['id'] ?? 0));
    }

    /** Брак можно править, если пользователь — редактор/админ либо один из супругов (или брак его круга: ребёнок не считается). */
    public function canEditMarriage(int $marriageId, ?array $user = null): bool
    {
        $user = $user ?? Auth::user();
        if (!$user || $marriageId <= 0) {
            return false;
        }
        $role = $user['role'] ?? 'viewer';
        if ($role === 'admin' || $role === 'editor') {
            return true;
        }
        $myPid = (int)($user['person_id'] ?? 0);
        if ($myPid <= 0) {
            return false;
        }
        $st = $this->db->prepare('SELECT husband_id, wife_id FROM marriages WHERE id = ? LIMIT 1');
        $st->execute([$marriageId]);
        $m = $st->fetch(PDO::FETCH_ASSOC);
        if (!$m) {
            return false;
        }
        return (int)$m['husband_id'] === $myPid || (int)$m['wife_id'] === $myPid;
    }

    /**
     * Добавлять новых людей может любой вошедший. Но «отец»/«мать» дописывают связь в УЖЕ
     * существующую карточку: если место занято — только тот, кто вправе править эту карточку.
     */
    public function canAddRelativeTo(array $target, string $relationType, ?array $user = null): bool
    {
        $user = $user ?? Auth::user();
        if (!$user) {
            return false;
        }
        $role = $user['role'] ?? 'viewer';
        if ($role === 'admin' || $role === 'editor') {
            return true;
        }
        if ($relationType === 'father' || $relationType === 'mother') {
            $slot = $relationType === 'father' ? 'father_id' : 'mother_id';
            if (empty($target[$slot])) {
                return true; // пустое место — это именно добавление
            }
            return $this->canEditPerson((int)$target['id'], $user);
        }
        return in_array($relationType, ['son', 'daughter', 'spouse', 'sibling'], true);
    }

    /** ALT-018: удалять человека может админ и тот, кто его добавил (persons.created_by). Редактор — нет. */
    public function canDeletePerson(int $personId, ?array $user = null): bool
    {
        $user = $user ?? Auth::user();
        if (!$user || $personId <= 0) {
            return false;
        }
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }
        return $this->isCreatedBy($personId, (int)($user['id'] ?? 0));
    }

    /** ALT-018: живые дети человека (для предупреждения при удалении). */
    public function childrenOf(int $personId): array
    {
        $st = $this->db->prepare(
            'SELECT id, first_name, last_name, father_id, mother_id FROM persons
             WHERE (father_id = ? OR mother_id = ?) AND is_deleted = 0 ORDER BY id ASC'
        );
        $st->execute([$personId, $personId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isCreatedBy(int $personId, int $userId): bool
    {
        if ($personId <= 0 || $userId <= 0) {
            return false;
        }
        $st = $this->db->prepare('SELECT created_by FROM persons WHERE id = ? AND is_deleted = 0 LIMIT 1');
        $st->execute([$personId]);
        $c = $st->fetchColumn();
        return $c !== false && $c !== null && (int)$c === $userId;
    }
}
