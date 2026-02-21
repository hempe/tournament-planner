# Database

Database setup and initialization scripts.

## Files

### init.sql

Database schema initialization script. Creates the `TPDb` database and all required tables with foreign key constraints. Also includes `ALTER TABLE` migrations for columns added after the initial schema.

**Usage:**
```bash
# Initialize database (safe to re-run — uses IF NOT EXISTS)
mysql -u root -p < database/init.sql
```

---

## Schema

### `users`

User accounts and authentication.

| Column          | Type           | Constraints              | Description                        |
|-----------------|----------------|--------------------------|------------------------------------|
| `id`            | INT            | PK, AUTO_INCREMENT       | Primary key                        |
| `username`      | VARCHAR(255)   | NOT NULL, UNIQUE         | Login name                         |
| `password`      | VARCHAR(255)   | NOT NULL                 | Bcrypt-hashed password             |
| `admin`         | BOOLEAN        | NOT NULL                 | Whether the user has admin rights  |
| `male`          | BOOLEAN        | NOT NULL, DEFAULT 1      | Gender (1 = male, 0 = female)      |
| `rfeg`          | VARCHAR(255)   | NULL                     | RFEG federation number             |
| `member_number` | VARCHAR(255)   | NULL                     | Club membership number             |

---

### `events`

Golf tournament events.

| Column      | Type         | Constraints         | Description                                   |
|-------------|--------------|---------------------|-----------------------------------------------|
| `id`        | INT          | PK, AUTO_INCREMENT  | Primary key                                   |
| `name`      | VARCHAR(255) | NOT NULL            | Event name                                    |
| `date`      | DATE         | NOT NULL            | Date of the event                             |
| `capacity`  | INT          | NOT NULL            | Maximum number of registered participants     |
| `timestamp` | DATETIME     | NOT NULL            | Creation timestamp                            |
| `locked`    | BOOLEAN      | NOT NULL, DEFAULT 0 | Whether registrations are locked              |
| `mixed`     | BOOLEAN      | NOT NULL, DEFAULT 1 | Whether the event is open to all genders      |

---

### `event_users`

Registrations and waitlist entries linking users to events.

| Column      | Type          | Constraints                        | Description                              |
|-------------|---------------|------------------------------------|------------------------------------------|
| `id`        | INT           | PK, AUTO_INCREMENT                 | Primary key                              |
| `userId`    | INT           | NOT NULL, FK → `users.id`          | Registered user                          |
| `eventId`   | INT           | NOT NULL, FK → `events.id`         | Target event                             |
| `comment`   | VARCHAR(2048) | NULL                               | Optional registration comment            |
| `state`     | INT           | NOT NULL                           | Registration state (0=unregistered, 1=registered, 2=waitlist) |
| `timestamp` | DATETIME      | NULL                               | Timestamp of the registration action     |

**Constraints:**
- `UNIQUE KEY unique_user_event (userId, eventId)` — one entry per user per event
- `FK_EVENT_USERS`: `userId` → `users(id)` ON DELETE CASCADE
- `FK_USER_EVENTS`: `eventId` → `events(id)` ON DELETE CASCADE

---

### `event_guests`

Guest registrations for events (non-member participants).

| Column       | Type           | Constraints                | Description                        |
|--------------|----------------|----------------------------|------------------------------------|
| `id`         | INT            | PK, AUTO_INCREMENT         | Primary key                        |
| `event_id`   | INT            | NOT NULL, FK → `events.id` | Target event                       |
| `male`       | BOOLEAN        | NOT NULL, DEFAULT 1        | Gender (1 = male, 0 = female)      |
| `first_name` | VARCHAR(255)   | NOT NULL                   | Guest first name                   |
| `last_name`  | VARCHAR(255)   | NOT NULL                   | Guest last name                    |
| `email`      | VARCHAR(255)   | NULL                       | Guest email address                |
| `handicap`   | DECIMAL(4,1)   | NULL                       | Golf handicap                      |
| `rfeg`       | VARCHAR(255)   | NULL                       | RFEG federation number             |
| `comment`    | VARCHAR(2048)  | NULL                       | Optional comment                   |
| `timestamp`  | DATETIME       | NOT NULL, DEFAULT NOW()    | Registration timestamp             |

**Constraints:**
- `FK_GUEST_EVENT`: `event_id` → `events(id)` ON DELETE CASCADE

---

## Entity Relationships

```
users ──────────────────< event_users >──────────────── events
                                                            │
                                                            └──< event_guests
```

- One **user** can register for many **events** (via `event_users`).
- One **event** can have many **guests** (via `event_guests`).
- Deleting a user cascades to their `event_users` rows.
- Deleting an event cascades to its `event_users` and `event_guests` rows.
