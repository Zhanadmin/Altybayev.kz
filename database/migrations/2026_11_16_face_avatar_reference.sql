-- ========================================================
-- TASK-019 (замечание владельца 16.11 20:49): эталон лица — аватарка человека.
-- Предыдущая миграция 2026_11_16_add_face_recognition.sql уже применена (batch 6) — здесь только новое.
--  * face_references — отпечаток лица с аватарки (считается в браузере; avatar_path — с какой аватарки,
--    при смене аватара отпечаток считается устаревшим и пересчитывается);
--  * face_embeddings: статусы suggested (вопрос «Это X?») и confirmed (подтвердил человек),
--    расстояние до эталона и «это не он» (rejected_person_id);
--  * album_media.faces_scanned_at — фото уже проверено на лица.
-- Таблица face_clusters больше не используется (группы не показываются), данные не удаляются.
-- ========================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `face_references` (
  `person_id` INT UNSIGNED NOT NULL,
  `avatar_path` VARCHAR(255) NOT NULL COMMENT 'Аватар, из которого посчитан отпечаток',
  `descriptor` JSON DEFAULT NULL COMMENT '128 float; NULL — на аватаре лицо не найдено',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`person_id`),
  CONSTRAINT `fk_face_ref_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `face_embeddings`
  MODIFY COLUMN `status` ENUM('unlabeled','labeled','rejected','suggested','confirmed') NOT NULL DEFAULT 'unlabeled',
  ADD COLUMN `match_distance` FLOAT DEFAULT NULL COMMENT 'Расстояние до эталона (аватара) найденного человека',
  ADD COLUMN `rejected_person_id` INT UNSIGNED DEFAULT NULL COMMENT 'Человек ответил «это не он»';

ALTER TABLE `album_media`
  ADD COLUMN `faces_scanned_at` DATETIME DEFAULT NULL COMMENT 'Когда фото проверено на лица (в браузере)';
