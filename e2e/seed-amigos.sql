-- E2E seed for the Amigos flavour. Logins: admin / Admin123!, member / Member123! (= "Muster Max"), other / Other123!
-- Priorities: other 1000 (first off the waitlist), member 2000, filler 3000. Events are in 2099.
USE TPDb;

INSERT INTO users (id, username, password, admin, male, first_name, last_name) VALUES
 (1, 'admin',  '$argon2id$v=19$m=65536,t=4,p=3$akFUMXR6cWRrR0dDWW0uRA$JaaC1XQvXv41nG698bV0mnEKQC/uH4hGkzl3kD5jrDU', 1, 1, 'Ada', 'Admin'),
 (2, 'member', '$argon2id$v=19$m=65536,t=4,p=3$aldkellzNE5sbUdac0Jabg$GD20AsYQpyIJBzXfY6rkTn0U8REAuyti0OMigQb9niQ', 0, 1, 'Max', 'Muster'),
 (3, 'other',  '$argon2id$v=19$m=65536,t=4,p=3$c0dzcGs1VkN1N2VvbXllaw$tcqs++f6VZzz7rS1GfkPiIXAiCAn7MKqDG2vVcOxuH0', 0, 0, 'Olga', 'Other');
INSERT INTO users (id, username, password, admin, male, first_name, last_name) VALUES
 (4, 'filler', '$argon2id$v=19$m=65536,t=4,p=3$c0dzcGs1VkN1N2VvbXllaw$tcqs++f6VZzz7rS1GfkPiIXAiCAn7MKqDG2vVcOxuH0', 0, 1, 'Fritz', 'Filler');
UPDATE users SET prio = 2000, handicap = 12.4 WHERE id = 2;
UPDATE users SET prio = 1000 WHERE id = 3;
UPDATE users SET prio = 3000 WHERE id = 4;

-- 1: full (capacity 1, taken by filler) - waitlist by priority, 2: locked
INSERT INTO events (id, name, date, capacity, timestamp, locked, mixed) VALUES
 (1, 'E2E Amigos Voll',      '2099-06-08', 1,  NOW(), 0, 1),
 (2, 'E2E Amigos Gesperrt',  '2099-06-15', 10, NOW(), 1, 1);
INSERT INTO event_users (userId, eventId, comment, state, timestamp) VALUES (4, 1, '', 1, NOW());
