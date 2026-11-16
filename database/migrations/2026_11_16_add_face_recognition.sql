-- ========================================================
-- TASK-019: распознавание лиц в фотоальбомах (в браузере, без внешних API и облаков)
-- Модели — локальные файлы на сайте (public/assets/vendor/face-api).
-- Сервер хранит только координаты рамки и вектор (отпечаток); лица считаются в браузере.
-- ========================================================

SET NAMES utf8mb4;

-- Настройки сайта (key-value)
CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` VARCHAR(64) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Группы похожих лиц (кластеры). person_id = NULL → группа ещё не подписана.
CREATE TABLE IF NOT EXISTS `face_clusters` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `person_id` INT UNSIGNED DEFAULT NULL COMMENT 'Подписанная персона из шежіре',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_face_clusters_person` (`person_id`),
  CONSTRAINT `fk_face_clusters_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Отпечатки лиц: по одной строке на каждое найденное лицо на фото.
CREATE TABLE IF NOT EXISTS `face_embeddings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `media_id` INT UNSIGNED NOT NULL,
  `cluster_id` INT UNSIGNED DEFAULT NULL,
  `person_id` INT UNSIGNED DEFAULT NULL COMMENT 'Денормализовано из кластера для быстрых выборок',
  `box_x` FLOAT NOT NULL COMMENT 'Нормализованные координаты 0..1 относительно фото',
  `box_y` FLOAT NOT NULL,
  `box_w` FLOAT NOT NULL,
  `box_h` FLOAT NOT NULL,
  `descriptor` JSON NOT NULL COMMENT 'Вектор из 128 float32 (отпечаток)',
  `status` ENUM('unlabeled','labeled','rejected') NOT NULL DEFAULT 'unlabeled',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fe_media` (`media_id`),
  KEY `idx_fe_cluster` (`cluster_id`),
  KEY `idx_fe_person` (`person_id`),
  CONSTRAINT `fk_fe_media` FOREIGN KEY (`media_id`) REFERENCES `album_media` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fe_cluster` FOREIGN KEY (`cluster_id`) REFERENCES `face_clusters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fe_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Источник отметки в album_media_people: ручная ('manual') или распознавание ('face').
-- Нужно, чтобы снятие подписи с кластера не удаляло ручные отметки.
ALTER TABLE `album_media_people`
  ADD COLUMN `source` ENUM('manual','face') NOT NULL DEFAULT 'manual';

-- Человек (или админ) может запретить распознавание конкретного человека и удалить его отпечатки.
ALTER TABLE `persons`
  ADD COLUMN `face_opt_out` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = распознавание этого человека запрещено';

-- По умолчанию распознавание лиц включено для всего сайта.
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('face_recognition_enabled', '1');
