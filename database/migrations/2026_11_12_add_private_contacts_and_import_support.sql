-- ========================================================
-- Миграция: Добавление закрытых контактов (private_contacts)
-- и идентификатора пакета импорта (import_batch_id)
-- Проект: altybayev.kz (TASK-005)
-- ========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. Добавление колонок в таблицу persons
-- private_contacts: закрытые телефон, email, адрес (видны только админу)
-- import_batch_id: идентификатор пакета импорта для точного отката
-- --------------------------------------------------------
ALTER TABLE `persons`
  ADD COLUMN IF NOT EXISTS `private_contacts` JSON DEFAULT NULL COMMENT 'Жабық байланыс деректері (телефон, email, мекенжай) тек әкімшіге' AFTER `out_married_clan_id`,
  ADD COLUMN IF NOT EXISTS `import_batch_id` VARCHAR(64) DEFAULT NULL COMMENT 'Идентификатор пакета импорта GEDCOM' AFTER `is_deleted`;

ALTER TABLE `persons`
  ADD INDEX IF NOT EXISTS `idx_persons_batch` (`import_batch_id`);

-- --------------------------------------------------------
-- 2. Добавление колонок в таблицу marriages
-- --------------------------------------------------------
ALTER TABLE `marriages`
  ADD COLUMN IF NOT EXISTS `import_batch_id` VARCHAR(64) DEFAULT NULL COMMENT 'Идентификатор пакета импорта GEDCOM' AFTER `notes`;

ALTER TABLE `marriages`
  ADD INDEX IF NOT EXISTS `idx_marriages_batch` (`import_batch_id`);

SET FOREIGN_KEY_CHECKS = 1;
