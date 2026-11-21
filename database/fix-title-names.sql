-- One-time repair for names split by the old /admin/migrate-names (2026):
-- titles like ", Dr." left a stray dot ("Karl-Uwe." / "Heinisch.") and "Mag." was
-- not recognised ("Christine, Mag."). Only rows still in exactly that state are changed,
-- names edited by hand afterwards are left alone. Safe to run more than once.
--
-- 1. Preview (shows what will change):
SELECT id, username, first_name, last_name FROM users
WHERE (first_name LIKE '%.' AND username REGEXP ',[[:space:]]*(Dr|Prof)\\.')
   OR (last_name LIKE '%.' AND username REGEXP ',[[:space:]]*(Dr|Prof)\\.[[:space:]]*$')
   OR first_name REGEXP ',[[:space:]]*(Dr|Prof|Mag)\\.?$'
   OR last_name REGEXP '^(Dr|Prof|Mag)\\.?$';

-- 2. Repair:
-- "Karl-Uwe, Dr. Bäcker" → first name "Karl-Uwe." → "Karl-Uwe"
UPDATE users SET first_name = TRIM(TRAILING '.' FROM first_name)
WHERE first_name LIKE '%.' AND username REGEXP ',[[:space:]]*(Dr|Prof)\\.';

-- "Martin Heinisch, Dr." → last name "Heinisch." → "Heinisch"
UPDATE users SET last_name = TRIM(TRAILING '.' FROM last_name)
WHERE last_name LIKE '%.' AND username REGEXP ',[[:space:]]*(Dr|Prof)\\.[[:space:]]*$';

-- "Christine, Mag. Strohmüller" → first name "Christine, Mag." → "Christine"
UPDATE users SET first_name = TRIM(REGEXP_REPLACE(first_name, ',[[:space:]]*(Dr|Prof|Mag)\\.?$', ''))
WHERE first_name REGEXP ',[[:space:]]*(Dr|Prof|Mag)\\.?$';

-- "Ulrike Pletscher, Mag." → first "Ulrike Pletscher," / last "Mag." → "Ulrike" / "Pletscher"
-- (last_name is assigned first and still sees the old first_name)
UPDATE users
SET last_name = TRIM(TRAILING ',' FROM SUBSTRING_INDEX(TRIM(first_name), ' ', -1)),
    first_name = TRIM(SUBSTRING(TRIM(first_name), 1, CHAR_LENGTH(TRIM(first_name)) - CHAR_LENGTH(SUBSTRING_INDEX(TRIM(first_name), ' ', -1))))
WHERE last_name REGEXP '^(Dr|Prof|Mag)\\.?$';
