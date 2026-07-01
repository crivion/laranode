#!/usr/bin/env bash
# laranode-postgres.sh — Privileged PostgreSQL management helper.
# Called via sudo from the PHP process (www-data). Never store credentials in argv.
#
# Usage:
#   laranode-postgres.sh create-db <name> <encoding> <locale>
#   laranode-postgres.sh create-user <user>           (password via stdin)
#   laranode-postgres.sh grant <user> <db>
#   laranode-postgres.sh revoke <user> <db>
#   laranode-postgres.sh drop-db <name>
#   laranode-postgres.sh drop-user <user>
#   laranode-postgres.sh update-user-password <user>  (password via stdin)

set -euo pipefail

# ---- helpers ----------------------------------------------------------------

die() { echo "ERROR: $*" >&2; exit 1; }

# Validate identifier: only [a-zA-Z0-9_] allowed.
assert_safe() {
    local val="$1"
    if ! echo "$val" | grep -qE '^[a-zA-Z0-9_]+$'; then
        die "Unsafe identifier: '$val' — only [a-zA-Z0-9_] allowed."
    fi
}

# Validate an encoding name (e.g. UTF8, LATIN1, SQL_ASCII): only [a-zA-Z0-9_].
assert_encoding() {
    local val="$1"
    if ! echo "$val" | grep -qE '^[a-zA-Z0-9_]+$'; then
        die "Unsafe encoding: '$val' — only [a-zA-Z0-9_] allowed."
    fi
}

# Validate a locale (e.g. en_US.UTF-8, C, POSIX): only [a-zA-Z0-9_.@-].
assert_locale() {
    local val="$1"
    if ! echo "$val" | grep -qE '^[a-zA-Z0-9_.@-]+$'; then
        die "Unsafe locale: '$val' — only [a-zA-Z0-9_.@-] allowed."
    fi
}

# Generate a random dollar-quote tag (lowercase alpha, 8 chars) to avoid
# dollar-quoting breakout attacks.
random_tag() {
    head -c 16 /dev/urandom | base64 | tr -dc 'a-z' | head -c 8
}

# Run SQL as the postgres superuser via stdin (never as a command argument).
run_as_postgres() {
    sudo -u postgres psql -v ON_ERROR_STOP=1 "$@"
}

# Convert a libc-style locale (e.g. "en_US.UTF-8") to an ICU locale tag
# (e.g. "en-US") for PostgreSQL 16+ on Ubuntu 24.04 which uses ICU by default.
# Returns the ICU tag via stdout. Falls back to "und" (undetermined) if not parseable.
libc_to_icu_locale() {
    local libc_locale="$1"
    # Strip encoding suffix (e.g. .UTF-8, .utf8)
    local lang_part="${libc_locale%%.*}"
    # Convert underscore to hyphen (en_US -> en-US)
    echo "${lang_part//_/-}"
}

# ---- actions ----------------------------------------------------------------

cmd_create_db() {
    local name="$1" encoding="${2:-UTF8}" locale="${3:-en_US.UTF-8}"
    assert_safe "$name"
    assert_encoding "$encoding"
    assert_locale "$locale"

    # Detect whether we should use ICU (PostgreSQL 16+ on Ubuntu 24.04).
    # We try libc locale first; if PostgreSQL reports "invalid LC_COLLATE locale name"
    # we fall back to ICU locale provider.
    local icu_locale
    icu_locale=$(libc_to_icu_locale "$locale")

    # Try ICU locale provider first (works on PostgreSQL 16 + Ubuntu 24.04).
    # Fall back to libc locale if ICU fails.
    if run_as_postgres --dbname=postgres <<SQL 2>/dev/null; then
CREATE DATABASE "$name"
    ENCODING '$encoding'
    LOCALE_PROVIDER icu
    ICU_LOCALE '$icu_locale'
    TEMPLATE template0;
REVOKE CONNECT ON DATABASE "$name" FROM PUBLIC;
SQL
        return 0
    fi

    # Fallback: libc locale (older PostgreSQL or systems with full locale support)
    run_as_postgres --dbname=postgres <<SQL
CREATE DATABASE "$name"
    ENCODING '$encoding'
    LC_COLLATE '$locale'
    LC_CTYPE '$locale'
    TEMPLATE template0;
REVOKE CONNECT ON DATABASE "$name" FROM PUBLIC;
SQL
}

cmd_create_user() {
    local user="$1"
    assert_safe "$user"

    # Read password from stdin, never from argv.
    local password
    password=$(cat)

    [ -n "$password" ] || die "Password must not be empty."

    local tag
    tag=$(random_tag)

    # Pass SQL via stdin to psql — password embedded as dollar-quoted literal.
    run_as_postgres --dbname=postgres <<SQL
CREATE ROLE "$user" LOGIN;
ALTER ROLE "$user" PASSWORD \$${tag}\$${password}\$${tag}\$;
SQL
}

cmd_grant() {
    local user="$1" db="$2"
    assert_safe "$user"
    assert_safe "$db"

    run_as_postgres --dbname=postgres <<SQL
GRANT CONNECT ON DATABASE "$db" TO "$user";
GRANT ALL PRIVILEGES ON DATABASE "$db" TO "$user";
SQL
}

cmd_revoke() {
    local user="$1" db="$2"
    assert_safe "$user"
    assert_safe "$db"

    run_as_postgres --dbname=postgres <<SQL
REVOKE ALL PRIVILEGES ON DATABASE "$db" FROM "$user";
REVOKE CONNECT ON DATABASE "$db" FROM "$user";
SQL
}

cmd_drop_db() {
    local name="$1"
    assert_safe "$name"

    run_as_postgres --dbname=postgres <<SQL
DROP DATABASE IF EXISTS "$name";
SQL
}

cmd_drop_user() {
    local user="$1"
    assert_safe "$user"

    run_as_postgres --dbname=postgres <<SQL
DROP ROLE IF EXISTS "$user";
SQL
}

cmd_update_user_password() {
    local user="$1"
    assert_safe "$user"

    # Read password from stdin, never from argv.
    local password
    password=$(cat)

    [ -n "$password" ] || die "Password must not be empty."

    local tag
    tag=$(random_tag)

    run_as_postgres --dbname=postgres <<SQL
ALTER ROLE "$user" PASSWORD \$${tag}\$${password}\$${tag}\$;
SQL
}

# ---- dispatch ---------------------------------------------------------------

ACTION="${1:-}"
shift || true

case "$ACTION" in
    create-db)             cmd_create_db "$@" ;;
    create-user)           cmd_create_user "$@" ;;
    grant)                 cmd_grant "$@" ;;
    revoke)                cmd_revoke "$@" ;;
    drop-db)               cmd_drop_db "$@" ;;
    drop-user)             cmd_drop_user "$@" ;;
    update-user-password)  cmd_update_user_password "$@" ;;
    *)                     die "Unknown action: '$ACTION'. Valid: create-db create-user grant revoke drop-db drop-user update-user-password" ;;
esac
