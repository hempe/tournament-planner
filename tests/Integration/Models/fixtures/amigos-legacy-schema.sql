CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    admin TINYINT(1) NOT NULL,
    phone VARCHAR(255) DEFAULT NULL,
    handicap DECIMAL(3,1) DEFAULT NULL,
    prio INT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    capacity INT NOT NULL,
    timestamp DATETIME NOT NULL,
    locked TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE event_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    userId INT NOT NULL,
    eventId INT NOT NULL,
    comment VARCHAR(2048) DEFAULT NULL,
    state INT NOT NULL,
    timestamp DATETIME DEFAULT NULL,
    KEY FK_EVENT_USERS (userId),
    KEY FK_USER_EVENTS (eventId),
    CONSTRAINT FK_EVENT_USERS FOREIGN KEY (userId) REFERENCES users (id) ON DELETE CASCADE ON UPDATE NO ACTION,
    CONSTRAINT FK_USER_EVENTS FOREIGN KEY (eventId) REFERENCES events (id) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    userId INT NOT NULL,
    state INT NOT NULL,
    name VARCHAR(255) NOT NULL UNIQUE,
    handicap DECIMAL(3,1) DEFAULT NULL,
    prio INT DEFAULT NULL,
    CONSTRAINT FK_USERS FOREIGN KEY (userId) REFERENCES users (id) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE event_registration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    playerId INT NOT NULL,
    eventId INT NOT NULL,
    comment VARCHAR(2048) DEFAULT NULL,
    state INT NOT NULL,
    TIMESTAMP DATETIME DEFAULT NULL,
    CONSTRAINT FK_EVENTS FOREIGN KEY (eventId) REFERENCES events (id) ON DELETE CASCADE ON UPDATE NO ACTION,
    CONSTRAINT FK_PLAYERS FOREIGN KEY (playerId) REFERENCES players (id) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- made-up data: two-word, one-word and three-word usernames, one user without priority
INSERT INTO users (id, username, password, admin, phone, handicap, prio) VALUES
 (1, 'Muster Max', '$2y$10$abcdefghijklmnopqrstuuJ8rS3vS7m3K8eR3mOq6QWm5Hk3xY2W', 0, NULL, 12.4, 1200),
 (2, 'Arno', '$2y$10$abcdefghijklmnopqrstuuJ8rS3vS7m3K8eR3mOq6QWm5Hk3xY2W', 1, '+34 600 000 000', NULL, 1000),
 (3, 'De la Fuente Juan', '$2y$10$abcdefghijklmnopqrstuuJ8rS3vS7m3K8eR3mOq6QWm5Hk3xY2W', 0, NULL, 30.0, 1500),
 (4, 'Beispiel Bea', '$2y$10$abcdefghijklmnopqrstuuJ8rS3vS7m3K8eR3mOq6QWm5Hk3xY2W', 0, NULL, NULL, NULL);
INSERT INTO events (id, name, date, capacity, timestamp, locked) VALUES
 (1, 'Turnier A', '2099-03-01', 2, '2099-01-01 10:00:00', 0),
 (2, 'Turnier B', '2099-03-08', 10, '2099-01-01 10:00:00', 1);
INSERT INTO event_users (userId, eventId, comment, state, timestamp) VALUES
 (1, 1, '', 1, '2099-01-02 10:00:00'), (3, 1, 'spät', 1, '2099-01-02 11:00:00'), (4, 2, NULL, 1, '2099-01-03 10:00:00');
INSERT INTO players (id, userId, state, name, handicap, prio) VALUES (1, 1, 1, 'Muster Max', 12.4, 1200);
INSERT INTO event_registration (playerId, eventId, comment, state, TIMESTAMP) VALUES (1, 1, '', 1, '2025-02-11 19:05:17');
