-- ========================================================
-- TASK-018: фотоальбомы семей и мероприятий (фото и видео от родственников)
-- Только новые таблицы; существующие данные не меняются.
-- Файлы хранятся в storage/uploads/albums/ (вне public, выдаются через /album/media).
-- ========================================================

SET NAMES utf8mb4;

-- Мероприятия (Іс-шаралар): туған күн, той, еске алу и т.п.
CREATE TABLE IF NOT EXISTS `events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `event_date` DATE DEFAULT NULL,
  `place` VARCHAR(200) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `kind` VARCHAR(30) NOT NULL DEFAULT 'other' COMMENT 'birthday | memorial | wedding | other',
  `source_person_id` INT UNSIGNED DEFAULT NULL COMMENT 'Чей день (если создано из памятной даты)',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_events_date` (`event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Участники мероприятия: люди
CREATE TABLE IF NOT EXISTS `event_people` (
  `event_id` INT UNSIGNED NOT NULL,
  `person_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`event_id`, `person_id`),
  KEY `idx_event_people_person` (`person_id`),
  CONSTRAINT `fk_event_people_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_people_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Участники мероприятия: семьи (браки)
CREATE TABLE IF NOT EXISTS `event_families` (
  `event_id` INT UNSIGNED NOT NULL,
  `marriage_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`event_id`, `marriage_id`),
  KEY `idx_event_families_marriage` (`marriage_id`),
  CONSTRAINT `fk_event_families_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_families_marriage` FOREIGN KEY (`marriage_id`) REFERENCES `marriages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Альбомы: у семьи (marriage) — несколько, у мероприятия — один
CREATE TABLE IF NOT EXISTS `albums` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_type` ENUM('family', 'event') NOT NULL,
  `owner_id` INT UNSIGNED NOT NULL COMMENT 'marriages.id или events.id',
  `title` VARCHAR(200) NOT NULL,
  `album_date` DATE DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_albums_owner` (`owner_type`, `owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Фото и видео альбома
CREATE TABLE IF NOT EXISTS `album_media` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `album_id` INT UNSIGNED NOT NULL,
  `media_type` ENUM('photo', 'video') NOT NULL,
  `file_path` VARCHAR(255) NOT NULL COMMENT 'Относительно storage/uploads/albums/',
  `thumb_path` VARCHAR(255) DEFAULT NULL COMMENT 'Превью (для видео — кадр из браузера, пересохранён через GD)',
  `ext` VARCHAR(8) NOT NULL COMMENT 'Расширение задаёт сервер: jpg | mp4 | mov | webm',
  `original_name` VARCHAR(255) DEFAULT NULL COMMENT 'Только для показа; на диске имя случайное',
  `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `width` INT UNSIGNED DEFAULT NULL,
  `height` INT UNSIGNED DEFAULT NULL,
  `duration_seconds` INT UNSIGNED DEFAULT NULL,
  `caption` VARCHAR(500) DEFAULT NULL,
  `taken_date` DATE DEFAULT NULL,
  `uploaded_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_album_media_album` (`album_id`),
  KEY `idx_album_media_uploader` (`uploaded_by`, `created_at`),
  CONSTRAINT `fk_album_media_album` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Отметки людей на фото/видео
CREATE TABLE IF NOT EXISTS `album_media_people` (
  `media_id` INT UNSIGNED NOT NULL,
  `person_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`media_id`, `person_id`),
  KEY `idx_amp_person` (`person_id`),
  CONSTRAINT `fk_amp_media` FOREIGN KEY (`media_id`) REFERENCES `album_media` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_amp_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
