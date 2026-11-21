-- E2E seed data. Logins: admin / Admin123!, member / Member123!, other / Other123!
-- All events are in the year 2099 so they never become "past" or locked by date.
USE TPDb;

INSERT INTO users (id, username, password, admin, male, first_name, last_name) VALUES
 (1, 'admin',  '$argon2id$v=19$m=65536,t=4,p=3$akFUMXR6cWRrR0dDWW0uRA$JaaC1XQvXv41nG698bV0mnEKQC/uH4hGkzl3kD5jrDU', 1, 1, 'Ada', 'Admin'),
 (2, 'member', '$argon2id$v=19$m=65536,t=4,p=3$aldkellzNE5sbUdac0Jabg$GD20AsYQpyIJBzXfY6rkTn0U8REAuyti0OMigQb9niQ', 0, 1, 'Max', 'Muster'),
 (3, 'other',  '$argon2id$v=19$m=65536,t=4,p=3$c0dzcGs1VkN1N2VvbXllaw$tcqs++f6VZzz7rS1GfkPiIXAiCAn7MKqDG2vVcOxuH0', 0, 0, 'Olga', 'Other');

-- 1: free for registration tests, 2: capacity 1 and taken by "other" (waitlist),
-- 3: locked, 4: has a linked dinner, 5/6: reserved for the admin and update specs
INSERT INTO events (id, name, date, capacity, timestamp, locked, mixed) VALUES
 (1, 'E2E Turnier',        '2099-06-01', 10, NOW(), 0, 1),
 (2, 'E2E Voll',           '2099-06-08', 1,  NOW(), 0, 1),
 (3, 'E2E Gesperrt',       '2099-06-15', 10, NOW(), 1, 1),
 (4, 'E2E Turnier Dinner', '2099-06-22', 10, NOW(), 0, 1),
 (5, 'E2E Admin',          '2099-06-29', 10, NOW(), 0, 1),
 (6, 'E2E Dinner Abmelden','2099-07-06', 10, NOW(), 0, 1);

INSERT INTO event_users (userId, eventId, comment, state, timestamp) VALUES (3, 2, '', 1, NOW());

INSERT INTO social_events (id, tournamentId, name, date, description, locked, timestamp) VALUES
 (1, 4, 'E2E Abendessen',  '2099-06-22', 'Gemeinsames Essen', 0, NOW()),
 (2, 6, 'E2E Abendessen 2','2099-07-06', NULL, 0, NOW());
INSERT INTO social_menus (id, socialEventId, name) VALUES (1, 1, 'Fleisch'), (2, 1, 'Fisch'), (3, 2, 'Pasta');
INSERT INTO social_tables (id, socialEventId, number, capacity) VALUES (1, 1, 1, 4), (2, 1, 2, 4), (3, 2, 1, 4);

-- "member" is already registered for tournament 6 and its dinner (unregister-both flow)
INSERT INTO event_users (userId, eventId, comment, state, timestamp) VALUES (2, 6, '', 1, NOW());
INSERT INTO social_registrations (socialEventId, userId, tableId, menuId) VALUES (2, 2, 3, 3);
