#!/usr/bin/env sh
set -eu

DUMP_FILE=${1:-}
ENV_FILE=${APP_ENV_FILE:-.env.docker}

if [ -z "$DUMP_FILE" ] || [ ! -f "$DUMP_FILE" ]; then
    echo "Usage: APP_ENV_FILE=.env.docker ./scripts/restore-database.sh path/to/database.dump" >&2
    exit 2
fi

docker compose --env-file "$ENV_FILE" up -d --wait postgres

TABLE_COUNT=$(docker compose --env-file "$ENV_FILE" exec -T postgres sh -ceu \
    'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "select count(*) from pg_tables where schemaname = '\''public'\'' and tablename <> '\''spatial_ref_sys'\''"')

if [ "$TABLE_COUNT" != "0" ]; then
    echo "Restore refused: target database is not empty ($TABLE_COUNT public tables)." >&2
    echo "Use a fresh postgres_data volume or restore manually after taking a verified backup." >&2
    exit 3
fi

# The PostGIS image initializes the target database with the PostGIS extension.
# Recreate an otherwise-empty target from template0 so the dump can restore its
# own extension and all dependent spatial objects without duplicate conflicts.
docker compose --env-file "$ENV_FILE" exec -T postgres sh -ceu '
    psql -U "$POSTGRES_USER" -d postgres -v ON_ERROR_STOP=1 \
      -c "select pg_terminate_backend(pid) from pg_stat_activity where datname = '\''$POSTGRES_DB'\'' and pid <> pg_backend_pid()";
    dropdb -U "$POSTGRES_USER" --if-exists "$POSTGRES_DB";
    createdb -U "$POSTGRES_USER" --template=template0 "$POSTGRES_DB"
'

docker compose --env-file "$ENV_FILE" exec -T postgres sh -ceu \
    'pg_restore --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --no-owner --no-privileges --exit-on-error' \
    < "$DUMP_FILE"

echo "Database restore completed successfully."
