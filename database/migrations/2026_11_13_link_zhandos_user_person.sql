-- --------------------------------------------------------
-- Миграция: Автоматическая привязка аккаунта Жандоса (zhandos.alt06@gmail.com)
-- к его персоне в древе шежіре (Алтыбаев Жандос, @I6@)
-- --------------------------------------------------------

UPDATE `users` u
JOIN `persons` p ON (p.first_name LIKE '%Жандос%' OR p.gedcom_id = '@I6@')
SET u.person_id = p.id
WHERE (u.email = 'zhandos.alt06@gmail.com' OR u.email LIKE '%zhandos%')
  AND (u.person_id IS NULL OR u.person_id = 0)
LIMIT 1;
