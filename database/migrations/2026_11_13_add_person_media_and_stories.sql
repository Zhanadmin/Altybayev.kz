-- ========================================================
-- Миграция: Таблица историй, аудиозаписей и архивных фото персон (TASK-004)
-- ========================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `person_media` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `person_id` INT UNSIGNED NOT NULL COMMENT 'Персона, к которой привязана запись',
  `media_type` ENUM('audio', 'story', 'photo') NOT NULL DEFAULT 'story' COMMENT 'Тип: audio (аудиозапись), story (текстовое воспоминание), photo (архивное фото)',
  `title` VARCHAR(255) NOT NULL COMMENT 'Тақырыбы / Заголовок',
  `content` MEDIUMTEXT DEFAULT NULL COMMENT 'Текстік естелік / Текст истории или транскрипт',
  `file_path` VARCHAR(255) DEFAULT NULL COMMENT 'Относительный путь к файлу в защищенном хранилище storage/uploads/person_media/',
  `file_name` VARCHAR(255) DEFAULT NULL COMMENT 'Бастапқы файл атауы / Исходное имя файла',
  `file_size` INT UNSIGNED DEFAULT NULL COMMENT 'Размер файла в байтах',
  `mime_type` VARCHAR(100) DEFAULT NULL COMMENT 'MIME тип (audio/mpeg, audio/wav, image/jpeg, etc.)',
  `duration_seconds` INT UNSIGNED DEFAULT NULL COMMENT 'Длительность аудио в секундах',
  `recorded_by` VARCHAR(150) DEFAULT NULL COMMENT 'Кім айтып берді / Рассказчик или автор',
  `event_year` VARCHAR(50) DEFAULT NULL COMMENT 'Оқиға болған жыл / Год события или записи',
  `is_private` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 - родственникам, 1 - приватная',
  `uploaded_by` INT UNSIGNED DEFAULT NULL COMMENT 'ID пользователя, загрузившего медиа',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_person_media_person` (`person_id`),
  KEY `idx_person_media_type` (`media_type`),
  KEY `idx_person_media_uploader` (`uploaded_by`),
  CONSTRAINT `fk_person_media_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
