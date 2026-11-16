-- ========================================================
-- TASK-030: ДАННЫЕ — сопоставление текстовых persons.clan_ru со справочником clans.
-- Требует 2026_11_17_clans_tamga_registry.sql. Перед запуском на боевой базе сделайте дамп:
--   mysqldump --single-transaction altybayev persons clans > dump_task030.sql
-- Для отката ниже создаётся копия (id, clan_id, clan_ru) в таблице persons_clan_backup_task030:
--   UPDATE persons p JOIN persons_clan_backup_task030 b ON b.id = p.id SET p.clan_id = b.clan_id;
-- Текст persons.clan_ru НЕ меняется. Значения «белгісіз»/пусто не трогаются.
-- ========================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `persons_clan_backup_task030` (
  `id` INT UNSIGNED NOT NULL PRIMARY KEY,
  `clan_id` INT UNSIGNED DEFAULT NULL,
  `clan_ru` VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `persons_clan_backup_task030` (`id`, `clan_id`, `clan_ru`)
SELECT `id`, `clan_id`, `clan_ru` FROM `persons`;

-- 1) Новые ру из свободного текста (которых ещё нет в справочнике)
INSERT INTO `clans` (`name`, `zhuz`)
SELECT DISTINCT TRIM(p.`clan_ru`), 'other'
FROM `persons` p
WHERE p.`is_deleted` = 0
  AND TRIM(COALESCE(p.`clan_ru`, '')) <> ''
  AND LOWER(TRIM(p.`clan_ru`)) NOT IN ('белгісіз', 'не указан', 'неизвестен', 'белгісіз ру')
  AND NOT EXISTS (
    SELECT 1 FROM `clans` c
    WHERE LOWER(c.`name`) = LOWER(TRIM(p.`clan_ru`)) OR LOWER(COALESCE(c.`name_ru`, '')) = LOWER(TRIM(p.`clan_ru`))
  );

-- 2) Привязка людей к справочнику по названию
UPDATE `persons` p
JOIN `clans` c ON LOWER(c.`name`) = LOWER(TRIM(p.`clan_ru`)) OR LOWER(COALESCE(c.`name_ru`, '')) = LOWER(TRIM(p.`clan_ru`))
SET p.`clan_id` = c.`id`
WHERE p.`is_deleted` = 0 AND TRIM(COALESCE(p.`clan_ru`, '')) <> '';
