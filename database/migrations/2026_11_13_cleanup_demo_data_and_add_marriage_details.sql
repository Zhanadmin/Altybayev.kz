-- ========================================================
-- Миграция: Полное удаление демо-данных, поддержка статусов и дат брака/развода,
-- привязка пользователя Жандоса и соблюдение казахского правила передачи рода по отцу
-- ========================================================

-- 1. Удаление любых вымышленных демо-записей (персоны без корректного gedcom_id не из импорта)
DELETE FROM `persons` 
WHERE `gedcom_id` IS NULL 
   OR `gedcom_id` NOT LIKE '@I%'
   OR (`first_name` IN ('Керей', 'Самай', 'Сейітқали', 'Бибінұр', 'Қасым', 'Арнұр', 'Медина', 'Дәулет') AND `import_batch_id` IS NULL);

-- 2. Расширение таблицы браков (marriages) для поддержки нескольких браков, разводов и дат
ALTER TABLE `marriages` 
  ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) NOT NULL DEFAULT 'married' COMMENT 'married, divorced, widowed' AFTER `is_current`,
  ADD COLUMN IF NOT EXISTS `start_date` VARCHAR(50) DEFAULT NULL AFTER `marriage_date`,
  ADD COLUMN IF NOT EXISTS `end_date` VARCHAR(50) DEFAULT NULL AFTER `divorce_date`;

-- Синхронизация статусов разводов из существующих данных
UPDATE `marriages`
SET `status` = 'divorced', `is_current` = 0
WHERE (`divorce_date` IS NOT NULL AND `divorce_date` != '')
   OR `is_current` = 0;

UPDATE `marriages`
SET `start_date` = `marriage_date`
WHERE `start_date` IS NULL AND `marriage_date` IS NOT NULL;

UPDATE `marriages`
SET `end_date` = `divorce_date`
WHERE `end_date` IS NULL AND `divorce_date` IS NOT NULL;

-- 3. Гарантированная привязка аккаунта Жандоса к персоне @I6@ (ID 6)
UPDATE `users` u
JOIN `persons` p ON (p.gedcom_id = '@I6@' OR (p.first_name = 'Жандос' AND p.last_name = 'Алтыбаев'))
SET u.person_id = p.id
WHERE u.email = 'zhandos.alt06@gmail.com' OR u.email LIKE '%zhandos%';

-- 4. Корректировка рода: ру передаётся ТОЛЬКО по отцу
-- Жёны, пришедшие в род, не должны иметь род Керей по умолчанию, если их отец не Керей
UPDATE `persons`
SET `clan_ru` = 'белгісіз', `clan_id` = NULL
WHERE `gender` = 'F' 
  AND `father_id` IS NULL 
  AND `last_name` NOT LIKE '%Алтыбаев%'
  AND `clan_ru` = 'Керей';
