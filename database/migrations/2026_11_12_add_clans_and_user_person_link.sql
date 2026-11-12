-- ========================================================
-- Миграция: Добавление сущности Родов (clans), привязки пользователей (users.person_id)
-- и девичьей фамилии для женщин (persons.maiden_name)
-- Проект: altybayev.kz (TASK-003)
-- ========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. Таблица родов (Шежіре-Орман: әр ру — жеке ағаш)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `clans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL COMMENT 'Ру атауы (мысалы: Керей, Жағалбайлы, Арғын)',
  `zhuz` ENUM('senior', 'middle', 'junior', 'other') NOT NULL DEFAULT 'other' COMMENT 'Жүз (Ұлы, Орта, Кіші, Жүзден тыс)',
  `parent_id` INT UNSIGNED DEFAULT NULL COMMENT 'Бас ру / Аталық ру (иерархия)',
  `tamga` VARCHAR(100) DEFAULT NULL COMMENT 'Таңбасы (Ашамай, Қос әліп, Тарақ...)',
  `uran` VARCHAR(100) DEFAULT NULL COMMENT 'Ұраны (Ошыбай, Ақжол, Қаптағай...)',
  `tree_color` VARCHAR(30) DEFAULT '#2e7d32' COMMENT 'Ормандағы ағаштың түсі (HEX)',
  `root_ancestor_name` VARCHAR(150) DEFAULT NULL COMMENT 'Түп атасы / Ру басы (мысалы: Керей)',
  `description` TEXT DEFAULT NULL COMMENT 'Тарихы, шежірелік жазбалар',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_clans_parent` (`parent_id`),
  KEY `idx_clans_zhuz` (`zhuz`),
  CONSTRAINT `fk_clans_parent` FOREIGN KEY (`parent_id`) REFERENCES `clans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Начальные данные для основного рода и дружественных родов
INSERT INTO `clans` (`id`, `name`, `zhuz`, `tamga`, `uran`, `tree_color`, `root_ancestor_name`, `description`) VALUES
(1, 'Керей', 'middle', 'Ашамай (X)', 'Ошыбай!', '#2d6a4f', 'Керей', 'Орта жүздің белді руы. Негізгі шежіре діңгегі Керей бабадан Алтыбайға дейін созылады.'),
(2, 'Жағалбайлы', 'junior', 'Балға (T)', 'Малатау!', '#1b4332', 'Жағалбайлы', 'Кіші жүз Жетіру бірлестігіне кіретін атақты ру.'),
(3, 'Арғын', 'middle', 'Көз (OO)', 'Ақжол!', '#40916c', 'Қодан тайшы', 'Орта жүздің ірі руы.'),
(4, 'Қоңырат', 'middle', 'Босаға (П)', 'Алатау!', '#52b788', 'Қоңырат', 'Орта жүз руы, құда-жекжат байланыстары жиі кездеседі.'),
(5, 'Найман', 'middle', 'Бақан (Y)', 'Қаптағай!', '#74c69d', 'Найман', 'Орта жүздің қалың руы.')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- --------------------------------------------------------
-- 2. Добавление внешнего ключа clan_id и maiden_name в persons
-- --------------------------------------------------------
-- Проверяем наличие колонок перед добавлением (совместимость с повторным запуском)
ALTER TABLE `persons`
  ADD COLUMN IF NOT EXISTS `clan_id` INT UNSIGNED DEFAULT 1 COMMENT 'Связь с таблицей clans' AFTER `id`,
  ADD COLUMN IF NOT EXISTS `maiden_name` VARCHAR(100) DEFAULT NULL COMMENT 'Қыз кезіндегі тегі / Девичья фамилия' AFTER `last_name`,
  ADD COLUMN IF NOT EXISTS `out_married_clan_id` INT UNSIGNED DEFAULT NULL COMMENT 'Тұрмысқа шыққан руы (қай руға келін болды)' AFTER `clan_subru`;

-- Индексы для ускорения поиска по родам
ALTER TABLE `persons`
  ADD INDEX IF NOT EXISTS `idx_persons_clan_id` (`clan_id`),
  ADD INDEX IF NOT EXISTS `idx_persons_out_clan` (`out_married_clan_id`);

-- --------------------------------------------------------
-- 3. Связка пользователей с персонами (users.person_id)
-- --------------------------------------------------------
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `person_id` INT UNSIGNED DEFAULT NULL COMMENT 'Связанная персона в шежіре (Сіз осындасыз)' AFTER `avatar_url`,
  ADD COLUMN IF NOT EXISTS `person_linking_mode` ENUM('admin', 'self_selected') NOT NULL DEFAULT 'admin' AFTER `person_id`;

ALTER TABLE `users`
  ADD INDEX IF NOT EXISTS `idx_users_person_id` (`person_id`);

SET FOREIGN_KEY_CHECKS = 1;
