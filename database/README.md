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

---

### `social_events`

Social/evening events that can be linked to a tournament.

| Column              | Type         | Constraints                          | Description                             |
|---------------------|--------------|--------------------------------------|-----------------------------------------|
| `id`                | INT          | PK, AUTO_INCREMENT                   | Primary key                             |
| `tournamentId`      | INT          | NULL, FK → `events.id` SET NULL      | Linked tournament (optional)            |
| `name`              | VARCHAR(255) | NOT NULL                             | Event name                              |
| `date`              | DATE         | NOT NULL                             | Date of the event                       |
| `description`       | TEXT         | NULL                                 | Optional description                    |
| `registration_close`| DATETIME     | NULL                                 | Optional registration deadline          |
| `locked`            | BOOLEAN      | NOT NULL, DEFAULT 0                  | Whether registrations are locked        |
| `timestamp`         | DATETIME     | NOT NULL                             | Creation timestamp                      |

---

### `social_menus`

Menu options for a social event (comma-separated list parsed on create/update).

| Column        | Type         | Constraints                              | Description              |
|---------------|--------------|------------------------------------------|--------------------------|
| `id`          | INT          | PK, AUTO_INCREMENT                       | Primary key              |
| `socialEventId` | INT        | NOT NULL, FK → `social_events.id` CASCADE | Parent social event      |
| `name`        | VARCHAR(255) | NOT NULL                                 | Menu option name         |

---

### `social_tables`

Seating tables for a social event. Each row is one physical table with a capacity.

| Column        | Type | Constraints                              | Description                        |
|---------------|------|------------------------------------------|------------------------------------|
| `id`          | INT  | PK, AUTO_INCREMENT                       | Primary key                        |
| `socialEventId` | INT | NOT NULL, FK → `social_events.id` CASCADE | Parent social event               |
| `number`      | INT  | NOT NULL                                 | Table number (1-based, sequential) |
| `capacity`    | INT  | NOT NULL                                 | Max seats at this table            |

---

### `social_registrations`

Registrations for a social event (members or guests).

| Column        | Type         | Constraints                                  | Description                              |
|---------------|--------------|----------------------------------------------|------------------------------------------|
| `id`          | INT          | PK, AUTO_INCREMENT                           | Primary key                              |
| `socialEventId` | INT        | NOT NULL, FK → `social_events.id` CASCADE    | Parent social event                      |
| `userId`      | INT          | NULL, FK → `users.id` CASCADE                | Registered member (NULL for guests)      |
| `firstName`   | VARCHAR(255) | NULL                                         | Guest first name                         |
| `lastName`    | VARCHAR(255) | NULL                                         | Guest last name                          |
| `email`       | VARCHAR(255) | NULL                                         | Guest email                              |
| `tableId`     | INT          | NULL, FK → `social_tables.id` SET NULL       | Selected table (NULL = libero/no preference) |
| `menuId`      | INT          | NOT NULL, FK → `social_menus.id`             | Selected menu option (required)          |
| `timestamp`   | DATETIME     | NOT NULL, DEFAULT CURRENT_TIMESTAMP          | Registration timestamp                   |

---

## Entity Relationships

```
users ──────────────────< event_users >──────────────── events ──< event_guests
                                                            │
                                                            └──── social_events ──< social_menus
                                                                        │
                                                                        ├──────< social_tables
                                                                        │
                                                                        └──────< social_registrations
                                                                                    ├── userId → users
                                                                                    ├── tableId → social_tables
                                                                                    └── menuId → social_menus
```

- One **user** can register for many **events** (via `event_users`).
- One **event** can have many **guests** (via `event_guests`).
- One **event** can have at most one linked **social_event**.
- One **social_event** has many **menus**, many **tables**, and many **registrations**.
- Social registrations can be members (`userId` set) or guests (`firstName`/`lastName` set).
- Deleting a user cascades to their `event_users` and `social_registrations` rows.
- Deleting an event cascades to its `event_users`, `event_guests` rows; `social_events.tournamentId` is set to NULL.
- Deleting a social event cascades to its menus, tables, and registrations.
