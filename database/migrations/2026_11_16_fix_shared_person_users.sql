-- TASK-024: у двух пользователей одна и та же карточка (person_id).
-- ПЕРЕД ЗАПУСКОМ сделать дамп таблиц users и invitations (phpMyAdmin → Экспорт).
-- 1) Просмотр: кто делит карточку
SELECT u.id, u.email, u.role, u.person_id
FROM users u
JOIN (SELECT person_id FROM users WHERE person_id IS NOT NULL GROUP BY person_id HAVING COUNT(*) > 1) d
  ON d.person_id = u.person_id
ORDER BY u.person_id, u.id;

-- 2) Исправление: у всех, кроме главного администратора, снять общую карточку.
--    Роль (editor), is_active и правки в дереве не меняются.
UPDATE users u
JOIN (SELECT person_id FROM users WHERE person_id IS NOT NULL GROUP BY person_id HAVING COUNT(*) > 1) d
  ON d.person_id = u.person_id
SET u.person_id = NULL, u.google_id = NULL, u.updated_at = NOW()
WHERE LOWER(u.email) <> 'zhandos.alt06@gmail.com';

-- Приглашения тех же адресов больше не держат чужую карточку
UPDATE invitations i
JOIN users u ON LOWER(u.email) = LOWER(i.email)
SET i.person_id = NULL
WHERE u.person_id IS NULL AND i.person_id IS NOT NULL;
