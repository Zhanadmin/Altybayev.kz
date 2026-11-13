-- Миграция: Исправление маппинга девичьей фамилии и текущей фамилии (по мужу)
-- В GEDCOM MyHeritage: SURN - фамилия при рождении (девичья), _MARNM - фамилия по мужу.

-- 1. Для женщин, у которых указаны обе фамилии (где девичья и по мужу были перепутаны местами)
UPDATE `persons`
JOIN (
    SELECT `id`, `last_name` AS `old_last`, `maiden_name` AS `old_maiden`
    FROM `persons`
    WHERE `gender` = 'F'
      AND `maiden_name` IS NOT NULL AND `maiden_name` != ''
      AND `last_name` IS NOT NULL AND `last_name` != ''
) AS `t` ON `persons`.`id` = `t`.`id`
SET `persons`.`last_name` = `t`.`old_maiden`,
    `persons`.`maiden_name` = `t`.`old_last`;

-- 2. Для женщин, у которых last_name был пуст, а фамилия записана в maiden_name: переносим в last_name
UPDATE `persons`
SET `last_name` = `maiden_name`,
    `maiden_name` = NULL
WHERE `gender` = 'F'
  AND (`last_name` IS NULL OR `last_name` = '')
  AND `maiden_name` IS NOT NULL
  AND `maiden_name` != '';
