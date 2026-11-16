-- ========================================================
-- Миграция: связь приглашения с карточкой человека (TASK-022)
-- invitations.person_id — карточка, на которую оформлено приглашение.
-- Контакты (телефон, email) хранятся в persons.private_contacts (JSON, TASK-005).
-- ========================================================

SET NAMES utf8mb4;

ALTER TABLE `invitations`
  ADD COLUMN IF NOT EXISTS `person_id` INT UNSIGNED DEFAULT NULL COMMENT 'Карточка человека, которой принадлежит приглашение' AFTER `notes`;

ALTER TABLE `invitations`
  ADD INDEX IF NOT EXISTS `idx_invitations_person` (`person_id`);
