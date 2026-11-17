-- TASK-036: религия, национальность, образование + история проживания (переезды).
-- Применяет владелец вручную. Существующие данные не трогаются: только новые столбцы (NULL) и новая таблица.
-- До применения код работает как раньше (новые поля просто не показываются и не сохраняются).

ALTER TABLE `persons`
  ADD COLUMN `religion`    VARCHAR(150) DEFAULT NULL COMMENT 'Діні / Религия' AFTER `birth_place`,
  ADD COLUMN `nationality` VARCHAR(150) DEFAULT NULL COMMENT 'Ұлты / Национальность' AFTER `religion`,
  ADD COLUMN `education`   VARCHAR(255) DEFAULT NULL COMMENT 'Білімі / Образование' AFTER `nationality`;

CREATE TABLE IF NOT EXISTS `person_residences` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `person_id` INT UNSIGNED NOT NULL,
  `place` VARCHAR(255) NOT NULL COMMENT 'Город / населённый пункт',
  `year_from` VARCHAR(10) DEFAULT NULL,
  `year_to` VARCHAR(10) DEFAULT NULL,
  `is_current` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 — қазір / живёт сейчас',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT 'Порядок переездов (от раннего к позднему)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_residences_person` (`person_id`, `sort_order`),
  CONSTRAINT `fk_residences_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
