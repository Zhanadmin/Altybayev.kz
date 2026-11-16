-- ========================================================
-- TASK-030: «Ру таңбалары» — справочник ру со знаками. ТОЛЬКО СТРУКТУРА (данные — в следующем файле).
-- Расширяем существующую таблицу clans: русское название, краткое описание, файл таңба.
-- Перед применением на боевой базе: mysqldump --single-transaction altybayev clans persons > dump_task030.sql
-- ========================================================
SET NAMES utf8mb4;

ALTER TABLE `clans`
  ADD COLUMN IF NOT EXISTS `name_ru` VARCHAR(100) DEFAULT NULL COMMENT 'Название ру по-русски' AFTER `name`,
  ADD COLUMN IF NOT EXISTS `summary` VARCHAR(255) DEFAULT NULL COMMENT 'Краткое описание' AFTER `description`,
  ADD COLUMN IF NOT EXISTS `tamga_file` VARCHAR(120) DEFAULT NULL COMMENT 'Файл таңба (SVG/PNG) в /uploads/tamga/' AFTER `tamga`;

-- Русские названия для уже заведённых ру
UPDATE `clans` SET `name_ru` = 'Кереи'     WHERE `name` = 'Керей'      AND `name_ru` IS NULL;
UPDATE `clans` SET `name_ru` = 'Жагалбайлы' WHERE `name` = 'Жағалбайлы' AND `name_ru` IS NULL;
UPDATE `clans` SET `name_ru` = 'Аргыны'    WHERE `name` = 'Арғын'      AND `name_ru` IS NULL;
UPDATE `clans` SET `name_ru` = 'Коныраты'  WHERE `name` = 'Қоңырат'    AND `name_ru` IS NULL;
UPDATE `clans` SET `name_ru` = 'Найманы'   WHERE `name` = 'Найман'     AND `name_ru` IS NULL;
