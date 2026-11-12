<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Сервис выполнения импорта GEDCOM в базу данных Шежіре и отката изменений
 * 
 * Соответствует требованиям:
 * - 5. Транзакционная запись в БД
 * - 6. Фиксация операции в audit_logs (IMPORT_GEDCOM) с поддержкой полного отката
 * - 7. Предотвращение дубликатов по gedcom_id
 * - 4. Изоляция контактов в private_contacts
 */
class GedcomImportService
{
    /**
     * Выполнение импорта в базу данных внутри единой транзакции
     *
     * @param array $parsedData Результат работы GedcomParserService
     * @param int $userId ID администратора
     * @param string $userName Имя администратора
     * @param string $userEmail Email администратора
     * @return array Результат импорта со статистикой и batch_id
     */
    public function executeImport(array $parsedData, int $userId, string $userName, string $userEmail): array
    {
        $batchId = 'imp_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));

        return Database::transaction(function (PDO $pdo) use ($parsedData, $batchId, $userId, $userName, $userEmail) {
            $createdPersonIds = [];
            $updatedPersonIds = [];
            $createdMarriageIds = [];
            $updatedMarriageIds = [];
            $personIdMap = []; // gedcom_id (@I1@) => database id

            // 1. Получение существующих персон для защиты от дублей (Требование 7)
            $stmt = $pdo->query("SELECT `id`, `gedcom_id` FROM `persons` WHERE `gedcom_id` IS NOT NULL AND `is_deleted` = 0");
            $existingPersons = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $existingPersons[$row['gedcom_id']] = (int)$row['id'];
            }

            // 2. Получение существующих браков для защиты от дублей (по gedcom_id и по паре супругов)
            $stmtM = $pdo->query("SELECT `id`, `gedcom_id`, `husband_id`, `wife_id` FROM `marriages`");
            $existingMarriages = [];
            $existingMarriagesByCouple = [];
            while ($rowM = $stmtM->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($rowM['gedcom_id'])) {
                    $existingMarriages[$rowM['gedcom_id']] = (int)$rowM['id'];
                }
                $coupleKey = $rowM['husband_id'] . '_' . $rowM['wife_id'];
                $existingMarriagesByCouple[$coupleKey] = (int)$rowM['id'];
            }

            // 3. Подготовка запросов для персон
            $insertPersonStmt = $pdo->prepare("
                INSERT INTO `persons` (
                    `gedcom_id`, `first_name`, `last_name`, `maiden_name`, `middle_name`,
                    `gender`, `birth_date`, `birth_year`, `birth_place`,
                    `is_deceased`, `death_date`, `death_year`, `death_place`,
                    `clan_id`, `clan_ru`, `clan_subru`, `biography`,
                    `private_contacts`, `import_batch_id`, `created_by`, `created_at`
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, NOW()
                )
            ");

            $updatePersonStmt = $pdo->prepare("
                UPDATE `persons` SET
                    `first_name` = ?,
                    `last_name` = ?,
                    `maiden_name` = ?,
                    `middle_name` = ?,
                    `gender` = ?,
                    `birth_date` = ?,
                    `birth_year` = ?,
                    `birth_place` = ?,
                    `is_deceased` = ?,
                    `death_date` = ?,
                    `death_year` = ?,
                    `death_place` = ?,
                    `clan_id` = ?,
                    `clan_ru` = ?,
                    `clan_subru` = ?,
                    `biography` = ?,
                    `private_contacts` = ?,
                    `import_batch_id` = ?,
                    `updated_at` = NOW()
                WHERE `id` = ?
            ");

            // Обработка персон
            foreach ($parsedData['persons'] as $gedcomId => $p) {
                $contactsJson = (!empty($p['private_contacts']['phones']) ||
                                 !empty($p['private_contacts']['emails']) ||
                                 !empty($p['private_contacts']['addresses']))
                    ? json_encode($p['private_contacts'], JSON_UNESCAPED_UNICODE)
                    : null;

                if (isset($existingPersons[$gedcomId])) {
                    // Обновляем существующую запись (без дублирования)
                    $dbId = $existingPersons[$gedcomId];
                    $updatePersonStmt->execute([
                        $p['first_name'] ?: 'Белгісіз',
                        $p['last_name'] ?: null,
                        $p['maiden_name'] ?: null,
                        $p['middle_name'] ?: null,
                        $p['gender'] ?: 'unknown',
                        $p['birth_date'] ?: null,
                        $p['birth_year'] ?: null,
                        $p['birth_place'] ?: null,
                        $p['is_deceased'] ? 1 : 0,
                        $p['death_date'] ?: null,
                        $p['death_year'] ?: null,
                        $p['death_place'] ?: null,
                        $p['clan_id'] ?? 1,
                        $p['clan_ru'] ?? 'Керей',
                        $p['clan_subru'] ?? null,
                        $p['biography'] ?: null,
                        $contactsJson,
                        $batchId,
                        $dbId
                    ]);
                    $personIdMap[$gedcomId] = $dbId;
                    $updatedPersonIds[] = $dbId;
                } else {
                    // Вставляем новую запись
                    $insertPersonStmt->execute([
                        $gedcomId,
                        $p['first_name'] ?: 'Белгісіз',
                        $p['last_name'] ?: null,
                        $p['maiden_name'] ?: null,
                        $p['middle_name'] ?: null,
                        $p['gender'] ?: 'unknown',
                        $p['birth_date'] ?: null,
                        $p['birth_year'] ?: null,
                        $p['birth_place'] ?: null,
                        $p['is_deceased'] ? 1 : 0,
                        $p['death_date'] ?: null,
                        $p['death_year'] ?: null,
                        $p['death_place'] ?: null,
                        $p['clan_id'] ?? 1,
                        $p['clan_ru'] ?? 'Керей',
                        $p['clan_subru'] ?? null,
                        $p['biography'] ?: null,
                        $contactsJson,
                        $batchId,
                        $userId
                    ]);
                    $newId = (int)$pdo->lastInsertId();
                    $personIdMap[$gedcomId] = $newId;
                    $createdPersonIds[] = $newId;
                }
            }

            // 4. Обновление родительских связей (father_id, mother_id)
            $updateBothParentsStmt = $pdo->prepare("UPDATE `persons` SET `father_id` = ?, `mother_id` = ? WHERE `id` = ?");
            $updateFatherOnlyStmt = $pdo->prepare("UPDATE `persons` SET `father_id` = ? WHERE `id` = ?");
            $updateMotherOnlyStmt = $pdo->prepare("UPDATE `persons` SET `mother_id` = ? WHERE `id` = ?");

            foreach ($parsedData['persons'] as $gedcomId => $p) {
                if (!isset($personIdMap[$gedcomId])) {
                    continue;
                }
                $selfDbId = $personIdMap[$gedcomId];
                $fatherDbId = !empty($p['father_gedcom_id']) && isset($personIdMap[$p['father_gedcom_id']])
                    ? $personIdMap[$p['father_gedcom_id']]
                    : null;
                $motherDbId = !empty($p['mother_gedcom_id']) && isset($personIdMap[$p['mother_gedcom_id']])
                    ? $personIdMap[$p['mother_gedcom_id']]
                    : null;

                if ($fatherDbId !== null && $motherDbId !== null) {
                    $updateBothParentsStmt->execute([$fatherDbId, $motherDbId, $selfDbId]);
                } elseif ($fatherDbId !== null) {
                    $updateFatherOnlyStmt->execute([$fatherDbId, $selfDbId]);
                } elseif ($motherDbId !== null) {
                    $updateMotherOnlyStmt->execute([$motherDbId, $selfDbId]);
                }
            }

            // 5. Обработка семей и браков (marriages)
            $insertMarriageStmt = $pdo->prepare("
                INSERT INTO `marriages` (
                    `gedcom_id`, `husband_id`, `wife_id`, `marriage_date`, `marriage_place`,
                    `is_current`, `divorce_date`, `notes`, `import_batch_id`, `created_at`
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, NOW()
                )
            ");

            $updateMarriageStmt = $pdo->prepare("
                UPDATE `marriages` SET
                    `husband_id` = ?,
                    `wife_id` = ?,
                    `marriage_date` = ?,
                    `marriage_place` = ?,
                    `divorce_date` = ?,
                    `import_batch_id` = ?
                WHERE `id` = ?
            ");

            foreach ($parsedData['families'] as $famGedcomId => $fam) {
                $husbDbId = !empty($fam['husband_gedcom_id']) && isset($personIdMap[$fam['husband_gedcom_id']])
                    ? $personIdMap[$fam['husband_gedcom_id']]
                    : null;
                $wifeDbId = !empty($fam['wife_gedcom_id']) && isset($personIdMap[$fam['wife_gedcom_id']])
                    ? $personIdMap[$fam['wife_gedcom_id']]
                    : null;

                if (!$husbDbId || !$wifeDbId) {
                    continue;
                }

                $existingId = $existingMarriages[$famGedcomId] 
                    ?? $existingMarriagesByCouple[$husbDbId . '_' . $wifeDbId] 
                    ?? $existingMarriagesByCouple[$wifeDbId . '_' . $husbDbId] 
                    ?? null;

                if ($existingId !== null) {
                    $updateMarriageStmt->execute([
                        $husbDbId,
                        $wifeDbId,
                        $fam['marriage_date'] ?: null,
                        $fam['marriage_place'] ?: null,
                        $fam['divorce_date'] ?: null,
                        $batchId,
                        $existingId
                    ]);
                    $updatedMarriageIds[] = $existingId;
                } else {
                    $insertMarriageStmt->execute([
                        $famGedcomId,
                        $husbDbId,
                        $wifeDbId,
                        $fam['marriage_date'] ?: null,
                        $fam['marriage_place'] ?: null,
                        empty($fam['divorce_date']) ? 1 : 0,
                        $fam['divorce_date'] ?: null,
                        $fam['notes'] ?: null,
                        $batchId
                    ]);
                    $newMId = (int)$pdo->lastInsertId();
                    $createdMarriageIds[] = $newMId;
                    $existingMarriages[$famGedcomId] = $newMId;
                    $existingMarriagesByCouple[$husbDbId . '_' . $wifeDbId] = $newMId;
                }
            }

            // 6. Запись в журнал аудита (Требование 6)
            $createdCount = count($createdPersonIds);
            $updatedCount = count($updatedPersonIds);
            $marriagesCount = count($createdMarriageIds) + count($updatedMarriageIds);
            $summaryKk = "GEDCOM импорты ({$batchId}): {$createdCount} жаңа адам қосылды, {$updatedCount} жаңартылды, {$marriagesCount} неке тіркелді.";
            $summaryRu = "Импорт GEDCOM ({$batchId}): добавлено {$createdCount} новых персон, обновлено {$updatedCount}, зарегистрировано {$marriagesCount} браков.";

            $auditPayload = [
                'batch_id' => $batchId,
                'source_file' => $parsedData['file_name'] ?? 'import.ged',
                'created_person_ids' => $createdPersonIds,
                'updated_person_ids' => $updatedPersonIds,
                'created_marriage_ids' => $createdMarriageIds,
                'updated_marriage_ids' => $updatedMarriageIds,
                'stats' => $parsedData['stats'] ?? [],
                'imported_at' => date('Y-m-d H:i:s'),
            ];

            $insertAuditStmt = $pdo->prepare("
                INSERT INTO `audit_logs` (
                    `user_id`, `user_name`, `user_email`, `action`,
                    `entity_table`, `entity_id`, `summary`, `old_values`, `new_values`, `created_at`
                ) VALUES (
                    ?, ?, ?, 'IMPORT_GEDCOM',
                    'persons', 0, ?, NULL, ?, NOW()
                )
            ");

            $insertAuditStmt->execute([
                $userId,
                $userName,
                $userEmail,
                "{$summaryKk} / {$summaryRu}",
                json_encode($auditPayload, JSON_UNESCAPED_UNICODE)
            ]);

            return [
                'success' => true,
                'batch_id' => $batchId,
                'created_persons' => $createdCount,
                'updated_persons' => $updatedCount,
                'created_marriages' => count($createdMarriageIds),
                'updated_marriages' => count($updatedMarriageIds),
                'total_persons_in_file' => count($parsedData['persons']),
                'total_families_in_file' => count($parsedData['families']),
                'photos_skipped' => $parsedData['stats']['photos_skipped'] ?? 0,
                'xss_threats_neutralized' => $parsedData['stats']['xss_threats_neutralized'] ?? 0,
                'contacts_secured' => $parsedData['stats']['contacts_secured'] ?? 0,
                'message' => "Импорт сәтті аяқталды! Барлығы {$createdCount} адам қосылды. / Импорт успешно выполнен! Добавлено {$createdCount} персон."
            ];
        });
    }

    /**
     * Откат пакета импорта
     *
     * @param string $batchId Идентификатор пакета импорта
     * @param int $userId ID администратора
     * @param string $userName Имя администратора
     * @param string $userEmail Email администратора
     * @return array Результат отката
     */
    public function rollback(string $batchId, int $userId, string $userName, string $userEmail): array
    {
        $batchId = trim($batchId);
        if ($batchId === '') {
            throw new \InvalidArgumentException("Пакет идентификаторы бос / Идентификатор пакета не указан");
        }

        return Database::transaction(function (PDO $pdo) use ($batchId, $userId, $userName, $userEmail) {
            // 1. Поиск записи импорта в audit_logs
            $stmt = $pdo->prepare("
                SELECT `id`, `new_values` FROM `audit_logs`
                WHERE `action` = 'IMPORT_GEDCOM'
                  AND `summary` LIKE ?
                ORDER BY `id` DESC LIMIT 1
            ");
            $stmt->execute(["%{$batchId}%"]);
            $log = $stmt->fetch(PDO::FETCH_ASSOC);

            $createdPersonIds = [];
            $createdMarriageIds = [];

            if ($log && !empty($log['new_values'])) {
                $payload = json_decode($log['new_values'], true);
                if (is_array($payload)) {
                    $createdPersonIds = $payload['created_person_ids'] ?? [];
                    $createdMarriageIds = $payload['created_marriage_ids'] ?? [];
                }
            }

            // 2. Удаление добавленных браков
            $deletedMarriages = 0;
            if (!empty($createdMarriageIds)) {
                $inM = implode(',', array_map('intval', $createdMarriageIds));
                $deletedMarriages = $pdo->exec("DELETE FROM `marriages` WHERE `id` IN ({$inM})");
            } else {
                $stmtDelM = $pdo->prepare("DELETE FROM `marriages` WHERE `import_batch_id` = ?");
                $stmtDelM->execute([$batchId]);
                $deletedMarriages = $stmtDelM->rowCount();
            }

            // 3. Обнуление ссылок father_id / mother_id у удаляемых персон для снятия внешних ключей
            if (!empty($createdPersonIds)) {
                $inP = implode(',', array_map('intval', $createdPersonIds));
                $pdo->exec("UPDATE `persons` SET `father_id` = NULL, `mother_id` = NULL WHERE `id` IN ({$inP})");
                $pdo->exec("UPDATE `persons` SET `father_id` = NULL WHERE `father_id` IN ({$inP})");
                $pdo->exec("UPDATE `persons` SET `mother_id` = NULL WHERE `mother_id` IN ({$inP})");
                $deletedPersons = $pdo->exec("DELETE FROM `persons` WHERE `id` IN ({$inP})");
            } else {
                $pdo->exec("UPDATE `persons` SET `father_id` = NULL, `mother_id` = NULL WHERE `import_batch_id` = " . $pdo->quote($batchId));
                $stmtDelP = $pdo->prepare("DELETE FROM `persons` WHERE `import_batch_id` = ?");
                $stmtDelP->execute([$batchId]);
                $deletedPersons = $stmtDelP->rowCount();
            }

            // 4. Запись отката в audit_logs
            $summary = "Откат импорта GEDCOM ({$batchId}): удалено {$deletedPersons} персон и {$deletedMarriages} браков.";
            $stmtAudit = $pdo->prepare("
                INSERT INTO `audit_logs` (
                    `user_id`, `user_name`, `user_email`, `action`,
                    `entity_table`, `entity_id`, `summary`, `old_values`, `new_values`, `created_at`
                ) VALUES (
                    ?, ?, ?, 'ROLLBACK',
                    'persons', 0, ?, NULL, ?, NOW()
                )
            ");
            $stmtAudit->execute([
                $userId,
                $userName,
                $userEmail,
                $summary,
                json_encode([
                    'rolled_back_batch' => $batchId,
                    'deleted_persons' => $deletedPersons,
                    'deleted_marriages' => $deletedMarriages,
                    'rolled_back_at' => date('Y-m-d H:i:s'),
                ], JSON_UNESCAPED_UNICODE)
            ]);

            return [
                'success' => true,
                'batch_id' => $batchId,
                'deleted_persons' => $deletedPersons,
                'deleted_marriages' => $deletedMarriages,
                'message' => "{$batchId} импорты сәтті қайтарылды! / Пакет {$batchId} успешно откачен."
            ];
        });
    }

    /**
     * Получить историю импортов и откатов из audit_logs
     */
    public function getImportHistory(int $limit = 20): array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT `id`, `user_id`, `user_name`, `user_email`, `action`, `summary`, `new_values`, `created_at`
                FROM `audit_logs`
                WHERE `action` IN ('IMPORT_GEDCOM', 'ROLLBACK')
                ORDER BY `id` DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();

            $history = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $payload = !empty($row['new_values']) ? json_decode($row['new_values'], true) : [];
                $row['payload'] = is_array($payload) ? $payload : [];
                $history[] = $row;
            }

            return $history;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
