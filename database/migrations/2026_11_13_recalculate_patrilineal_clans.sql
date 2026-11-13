-- ========================================================
-- Миграция: Перерасчет принадлежности к роду (Ру) по мужской линии
-- Проект: altybayev.kz (TASK-007, замечания от 13.11 12:16, 12:17)
-- 
-- Правила:
-- 1. Ру передается строго по отцу (по мужской линии).
-- 2. Жены сохраняют род своего отца; если он неизвестен — «белгісіз», но НЕ Керей.
-- 3. Керей остается только у прямых потомков Керея по мужской линии (родоначальник ID 249).
-- ========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Снимаем ошибочный «Керей» у всех персон
UPDATE `persons` 
SET `clan_id` = NULL, 
    `clan_ru` = 'белгісіз';

-- 2. Устанавливаем «Керей» только родоначальнику Керея и его потомкам по мужской линии
UPDATE `persons` p
JOIN (
    WITH RECURSIVE kerey_patrilineal AS (
        SELECT `id`, `gender` FROM `persons` WHERE `id` = 249
        UNION ALL
        SELECT `child`.`id`, `child`.`gender` 
        FROM `persons` `child`
        INNER JOIN `kerey_patrilineal` `kp` ON `child`.`father_id` = `kp`.`id`
        WHERE `kp`.`gender` = 'M'
    )
    SELECT `id` FROM `kerey_patrilineal`
) `k` ON `p`.`id` = `k`.`id`
SET `p`.`clan_id` = 1, 
    `p`.`clan_ru` = 'Керей';

-- 3. Фиксация в аудит-логе
INSERT INTO `audit_logs` (`user_id`, `user_name`, `user_email`, `action`, `entity_table`, `entity_id`, `summary`, `created_at`)
VALUES (1, 'Жандос Алтыбаев', 'system@altybayev.kz', 'UPDATE', 'persons', 0, 'Миграция 2026_11_13_recalculate_patrilineal_clans: снят ошибочный Керей у жен, ру пересчитан строго по мужской линии', NOW());

SET FOREIGN_KEY_CHECKS = 1;
