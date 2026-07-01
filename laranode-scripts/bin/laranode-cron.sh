#!/bin/bash
set -euo pipefail

# laranode-cron.sh — manage per-user crontab entries for Laranode panel.
#
# Usage:
#   laranode-cron.sh set    <system_user> <tmp_file>
#   laranode-cron.sh remove <system_user>
#   laranode-cron.sh list   <system_user>
#
# Security:
#   - $SYSTEM_USER must match ^[a-zA-Z0-9_]+$, end in _ln, exist, and not be root.
#   - Each line in <tmp_file> must be: 5 schedule fields <TAB> command (no embedded newlines).
#   - Command portion must start with 'php /home/<system_user>/' (allowlist: php only, v1).
#   - Flag-smuggling: any argument starting with '-' is rejected.
#   - sudoers grants only (www-data) not (ALL); sudo only elevates to www-data.

MARKER="# laranode-managed"

# ── Reject flag-smuggling ─────────────────────────────────────────────────────
for arg in "$@"; do
    case "$arg" in
        -*)
            echo "ERROR: argument may not start with '-': $arg" >&2
            exit 1
            ;;
    esac
done

SUBCMD="${1:-}"
SYSTEM_USER="${2:-}"

# ── Validate SYSTEM_USER ──────────────────────────────────────────────────────
if [ -z "$SYSTEM_USER" ]; then
    echo "ERROR: SYSTEM_USER is required" >&2
    exit 1
fi

# Must match safe identifier pattern (no flags, no special chars)
if ! echo "$SYSTEM_USER" | grep -qE '^[a-zA-Z0-9_]+$'; then
    echo "ERROR: invalid system user name: $SYSTEM_USER" >&2
    exit 1
fi

# Must end in _ln (panel-owned accounts only)
if ! echo "$SYSTEM_USER" | grep -qE '_ln$'; then
    echo "ERROR: system user must end in _ln: $SYSTEM_USER" >&2
    exit 1
fi

# Must not be root
if [ "$SYSTEM_USER" = "root" ]; then
    echo "ERROR: refusing to manage root crontab" >&2
    exit 1
fi

# Must exist on the system
if ! id "$SYSTEM_USER" >/dev/null 2>&1; then
    echo "ERROR: system user does not exist: $SYSTEM_USER" >&2
    exit 1
fi

# ── Sub-commands ──────────────────────────────────────────────────────────────

case "$SUBCMD" in

    set)
        TMP_FILE="${3:-}"
        if [ -z "$TMP_FILE" ]; then
            echo "ERROR: tmp_file is required for 'set'" >&2
            exit 1
        fi

        if [ ! -f "$TMP_FILE" ]; then
            echo "ERROR: tmp_file not found: $TMP_FILE" >&2
            exit 1
        fi

        # Trap: clean up our own reference to TMP_FILE on any error/exit.
        # (PHP service also deletes it in finally{}; this is defence-in-depth.)
        trap 'rm -f "$TMP_FILE"' EXIT INT TERM

        # Validate every non-empty line in the tmp file.
        # Each line must be: <f1> <f2> <f3> <f4> <f5> TAB <command>
        # Command allowlist (v1): must start with 'php /home/<SYSTEM_USER>/'
        ALLOWED_CMD_PREFIX="php /home/${SYSTEM_USER}/"
        while IFS= read -r line || [ -n "$line" ]; do
            # Skip empty lines
            [ -z "$line" ] && continue

            # Reject lines containing embedded carriage-return
            case "$line" in
                *$'\r'*)
                    echo "ERROR: embedded carriage-return in crontab line" >&2
                    exit 1
                    ;;
            esac

            # Must match: 5 whitespace-separated schedule fields followed by a TAB and a command
            if ! printf '%s\n' "$line" | grep -qP '^\S+\s+\S+\s+\S+\s+\S+\s+\S+\t\S'; then
                echo "ERROR: invalid crontab line format (need 5 schedule fields + TAB + command): $line" >&2
                exit 1
            fi

            # Extract the command portion: everything after the first TAB (field 2 onward).
            # cut uses TAB as default delimiter; field 1 = "f1 f2 f3 f4 f5", field 2 = command.
            CMD_PART=$(printf '%s\n' "$line" | cut -f2-)

            # Allowlist check: command must start with 'php /home/<SYSTEM_USER>/'
            case "$CMD_PART" in
                "$ALLOWED_CMD_PREFIX"*)
                    ;;  # allowed
                *)
                    echo "ERROR: command not allowed (must start with '${ALLOWED_CMD_PREFIX}'): $CMD_PART" >&2
                    exit 1
                    ;;
            esac
        done < "$TMP_FILE"

        # Get existing crontab minus managed lines.
        # 'crontab -l' exits non-zero when the crontab is empty on some systems;
        # the '|| true' prevents set -e from aborting on an empty crontab.
        EXISTING=$(crontab -l -u "$SYSTEM_USER" 2>/dev/null || true)

        # Strip old managed block.
        # 'grep -v' exits 1 when no lines remain; '|| true' handles that under set -e.
        STRIPPED=$(printf '%s\n' "$EXISTING" | grep -v "$MARKER" || true)

        # Build new managed block from non-empty lines in the tmp file
        MANAGED_LINES=""
        while IFS= read -r line || [ -n "$line" ]; do
            [ -z "$line" ] && continue
            MANAGED_LINES="${MANAGED_LINES}${line} ${MARKER}"$'\n'
        done < "$TMP_FILE"

        # Write crontab: managed block first, then remaining non-managed entries.
        # '--' separates options from positional args; '-' means read from stdin.
        if [ -z "$MANAGED_LINES" ] && [ -z "$STRIPPED" ]; then
            # Nothing left — install an empty crontab
            printf '' | crontab -u "$SYSTEM_USER" -- -
        elif [ -z "$MANAGED_LINES" ]; then
            # No managed lines, only preserve existing
            printf '%s\n' "$STRIPPED" | crontab -u "$SYSTEM_USER" -- -
        elif [ -z "$STRIPPED" ]; then
            # Only managed lines
            printf '%s\n' "$MANAGED_LINES" | crontab -u "$SYSTEM_USER" -- -
        else
            # Both managed and pre-existing non-managed lines
            printf '%s\n%s\n' "$MANAGED_LINES" "$STRIPPED" | crontab -u "$SYSTEM_USER" -- -
        fi

        # Disarm trap (PHP service owns the file; don't double-delete)
        trap - EXIT INT TERM

        echo "OK: crontab updated for $SYSTEM_USER"
        ;;

    remove)
        # Strip all managed lines from the crontab
        EXISTING=$(crontab -l -u "$SYSTEM_USER" 2>/dev/null || true)
        STRIPPED=$(printf '%s\n' "$EXISTING" | grep -v "$MARKER" || true)

        if [ -z "$STRIPPED" ]; then
            # Empty crontab
            printf '' | crontab -u "$SYSTEM_USER" -- -
        else
            printf '%s\n' "$STRIPPED" | crontab -u "$SYSTEM_USER" -- -
        fi

        echo "OK: managed crontab entries removed for $SYSTEM_USER"
        ;;

    list)
        crontab -l -u "$SYSTEM_USER" 2>/dev/null || true
        ;;

    *)
        echo "ERROR: unknown sub-command: $SUBCMD" >&2
        echo "Usage: laranode-cron.sh <set|remove|list> <system_user> [tmp_file]" >&2
        exit 1
        ;;
esac
