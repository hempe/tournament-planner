-- One-off migration of the old Amigos de Tauro database (run once, AFTER database/init.sql).
-- Back up the database first (phpMyAdmin: Export). Safe to run again.
-- 1. Names: Amigos logins are "Lastname Firstname" - first word = last name, rest = first name.
--    Only users without names are touched, so fixes made in the app are kept.
UPDATE users
SET last_name = SUBSTRING_INDEX(TRIM(username), ' ', 1),
    first_name = TRIM(SUBSTRING(TRIM(username), CHAR_LENGTH(SUBSTRING_INDEX(TRIM(username), ' ', 1)) + 1))
WHERE (first_name IS NULL OR first_name = '') AND (last_name IS NULL OR last_name = '');

-- 2. Tables of the abandoned "players" model (last written 2025-02-11)
DROP TABLE IF EXISTS event_registration;
DROP TABLE IF EXISTS players;

-- 3. Check list: one-word and multi-part names need a look (fix them under Benutzer in the app)
SELECT id, username, first_name, last_name
FROM users
WHERE TRIM(username) NOT LIKE '% %'
   OR CHAR_LENGTH(TRIM(username)) - CHAR_LENGTH(REPLACE(TRIM(username), ' ', '')) >= 2
ORDER BY username
