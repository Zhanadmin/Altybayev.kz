-- ========================================================
-- Миграция: обложка карточки человека (TASK-022, note 16.11 15:45 и замечание 19:26)
-- cover_path  — URL обложки (загруженное фото или готовый фон /assets/img/covers/*.svg); NULL = стандартный фон
-- cover_pos_y — вертикальное положение кадра, % (0 — верх, 100 — низ)
-- cover_dim   — затемнение, % (0..70)
-- ========================================================

SET NAMES utf8mb4;

ALTER TABLE `persons`
  ADD COLUMN IF NOT EXISTS `cover_path` VARCHAR(255) DEFAULT NULL COMMENT 'Обложка карточки (URL)',
  ADD COLUMN IF NOT EXISTS `cover_pos_y` TINYINT UNSIGNED NOT NULL DEFAULT 50 COMMENT 'Положение обложки по вертикали, %',
  ADD COLUMN IF NOT EXISTS `cover_dim` TINYINT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'Затемнение обложки, %';
