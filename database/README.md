# Database

Database setup and initialization scripts.

## Files

### init.sql

Database schema initialization script. Creates all required tables, indexes, and initial data.

**Tables created:**
- `users` - User accounts and authentication
- `events` - Golf tournament events
- `event_users` - Event registrations and waitlist

**Usage:**
```bash
# Initialize new database
mysql -u root -p < database/init.sql

# Or import into existing database
mysql -u root -p TPDb < database/init.sql
```

**Note:** This will drop existing tables if they exist. Use caution in production.

## Setup

See the main [Installation Guide](../docs/INSTALLATION.md) for complete database setup instructions.
