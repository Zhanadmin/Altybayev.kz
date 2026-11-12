-- ========================================================
-- База данных Шежіре (altybayev.kz)
-- Кодировка utf8mb4 с полной поддержкой казахских букв (Ә, І, Ң, Ғ, Ү, Ұ, Қ, Ө, Һ)
-- ========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. Таблица пользователей (только через Google OAuth)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `google_id` VARCHAR(128) DEFAULT NULL,
  `email` VARCHAR(191) NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `avatar_url` VARCHAR(500) DEFAULT NULL,
  `role` ENUM('admin', 'editor', 'viewer') NOT NULL DEFAULT 'viewer',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_users_email` (`email`),
  UNIQUE KEY `idx_users_google_id` (`google_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Таблица приглашений (белый список доступа по email)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invitations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(191) NOT NULL,
  `role` ENUM('admin', 'editor', 'viewer') NOT NULL DEFAULT 'viewer',
  `invited_by` INT UNSIGNED DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL COMMENT 'Заметка кого пригласили (например: внук Серика)',
  `status` ENUM('pending', 'accepted', 'revoked') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `accepted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_invitations_email` (`email`),
  KEY `idx_invitations_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Таблица персон (Шежіре)
-- Номер поколения вычисляется динамически от выбранного человека
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `persons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gedcom_id` VARCHAR(64) DEFAULT NULL COMMENT 'Идентификатор из MyHeritage (например @I1@)',
  `first_name` VARCHAR(100) NOT NULL COMMENT 'Аты / Имя',
  `last_name` VARCHAR(100) DEFAULT NULL COMMENT 'Тегі / Фамилия',
  `middle_name` VARCHAR(100) DEFAULT NULL COMMENT 'Әкесінің аты / Отчество',
  `gender` ENUM('M', 'F', 'unknown') NOT NULL DEFAULT 'unknown',
  
  -- Родительские связи (для Жеті ата и родословной)
  `father_id` INT UNSIGNED DEFAULT NULL COMMENT 'Отец',
  `mother_id` INT UNSIGNED DEFAULT NULL COMMENT 'Мать',

  -- Даты и статус
  `birth_date` DATE DEFAULT NULL,
  `birth_year` VARCHAR(30) DEFAULT NULL COMMENT 'Год рождения или примерный период (например: ок. 1925)',
  `birth_place` VARCHAR(255) DEFAULT NULL COMMENT 'Туған жері',
  `is_deceased` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 - жив, 1 - ушел из жизни',
  `death_date` DATE DEFAULT NULL,
  `death_year` VARCHAR(30) DEFAULT NULL,
  `death_place` VARCHAR(255) DEFAULT NULL,

  -- Родословная принадлежность
  `clan_ru` VARCHAR(100) DEFAULT NULL COMMENT 'Руы (Младший/Средний/Старший жуз, род)',
  `clan_subru` VARCHAR(150) DEFAULT NULL COMMENT 'Бөлімі / Тармағы',

  -- Биография, заметки и главное фото
  `biography` MEDIUMTEXT DEFAULT NULL COMMENT 'Өмірбаяны / Заметки / Истории',
  `avatar_path` VARCHAR(255) DEFAULT NULL COMMENT 'Путь к главному фото',
  `private_contacts` JSON DEFAULT NULL COMMENT 'Жабық байланыс деректері (телефон, email, мекенжай) тек әкімшіге',

  -- Служебные поля
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Мягкое удаление (soft delete)',
  `import_batch_id` VARCHAR(64) DEFAULT NULL COMMENT 'Идентификатор пакета импорта GEDCOM',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_persons_father` (`father_id`),
  KEY `idx_persons_mother` (`mother_id`),
  KEY `idx_persons_ru` (`clan_ru`),
  KEY `idx_persons_names` (`last_name`, `first_name`),
  KEY `idx_persons_gedcom` (`gedcom_id`),
  KEY `idx_persons_deleted` (`is_deleted`),
  CONSTRAINT `fk_persons_father` FOREIGN KEY (`father_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_persons_mother` FOREIGN KEY (`mother_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Таблица браков / супругов
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `marriages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gedcom_id` VARCHAR(64) DEFAULT NULL,
  `husband_id` INT UNSIGNED NOT NULL,
  `wife_id` INT UNSIGNED NOT NULL,
  `marriage_date` VARCHAR(50) DEFAULT NULL,
  `marriage_place` VARCHAR(255) DEFAULT NULL,
  `is_current` TINYINT(1) NOT NULL DEFAULT 1,
  `divorce_date` VARCHAR(50) DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `import_batch_id` VARCHAR(64) DEFAULT NULL COMMENT 'Идентификатор пакета импорта GEDCOM',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_marriages_husband` (`husband_id`),
  KEY `idx_marriages_wife` (`wife_id`),
  CONSTRAINT `fk_marriages_husband` FOREIGN KEY (`husband_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_marriages_wife` FOREIGN KEY (`wife_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Таблица дополнительных фотографий и документов персоны
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `person_photos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `person_id` INT UNSIGNED NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `thumbnail_path` VARCHAR(255) DEFAULT NULL,
  `caption` VARCHAR(255) DEFAULT NULL COMMENT 'Подпись к фотографии',
  `is_avatar` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `uploaded_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_photos_person` (`person_id`),
  CONSTRAINT `fk_photos_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Журнал аудита правок и отката (Audit Log / History)
-- Хранит полные снимки состояний для мгновенного отката любой правки
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `user_name` VARCHAR(191) DEFAULT NULL,
  `user_email` VARCHAR(191) DEFAULT NULL,
  `action` ENUM('CREATE', 'UPDATE', 'DELETE', 'RESTORE', 'ROLLBACK', 'IMPORT_GEDCOM') NOT NULL,
  `entity_table` VARCHAR(50) NOT NULL COMMENT 'persons, marriages, person_photos',
  `entity_id` INT UNSIGNED NOT NULL,
  `summary` VARCHAR(255) DEFAULT NULL COMMENT 'Краткое описание на KZ/RU',
  `old_values` JSON DEFAULT NULL COMMENT 'Состояние ДО правки',
  `new_values` JSON DEFAULT NULL COMMENT 'Состояние ПОСЛЕ правки',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_entity` (`entity_table`, `entity_id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
