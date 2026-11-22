#!/bin/bash
# Dry run of the Amigos migration against a phpMyAdmin export, in a throwaway MariaDB container.
#   bin/amigos-dry-run.sh /path/to/amigoDb-export.sql
# Prints only counts and the name check list - never other rows. The export is not copied anywhere.
set -euo pipefail
EXPORT="${1:?usage: bin/amigos-dry-run.sh <export.sql>}"
[ -f "$EXPORT" ] || { echo "Not a file: $EXPORT" >&2; exit 2; }
cd "$(dirname "$0")/.."
NAME="tp-amigos-dry-run-$$"
SQL=(docker exec -i "$NAME" mariadb -uroot -pdryrun amigoDb)

cleanup() { docker rm -f "$NAME" >/dev/null 2>&1 || true; }
trap cleanup EXIT

docker run -d --rm --name "$NAME" -e MARIADB_ROOT_PASSWORD=dryrun -e MARIADB_DATABASE=amigoDb mariadb:11 >/dev/null
for _ in $(seq 60); do
    docker exec "$NAME" mariadb -uroot -pdryrun -e 'SELECT 1' >/dev/null 2>&1 && break
    sleep 1
done

counts() {
    "${SQL[@]}" -t -e "SELECT (SELECT COUNT(*) FROM users) users, (SELECT COUNT(*) FROM events) events,
        (SELECT COUNT(*) FROM event_users) registrations, (SELECT COUNT(*) FROM event_users WHERE state = 2) waitlist"
}

"${SQL[@]}" < "$EXPORT"
echo "Before:"; counts
BEFORE="$(counts)"

# init.sql without CREATE DATABASE / USE (we stay in amigoDb)
sed -E '/^\s*(CREATE DATABASE|USE)\b/d' database/init.sql | "${SQL[@]}"
echo "Name check list (fix these in the app after the cut-over):"
"${SQL[@]}" -t < database/migrations/amigos-legacy.sql

echo "After:"; counts
AFTER="$(counts)"
[ "$BEFORE" = "$AFTER" ] || { echo "COUNTS CHANGED - do not migrate" >&2; exit 1; }
"${SQL[@]}" -t -e "SELECT COUNT(*) users_without_last_name FROM users WHERE last_name IS NULL OR last_name = ''"
echo "Dry run OK."
