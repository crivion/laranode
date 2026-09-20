#!/bin/bash

set -euo pipefail

if [ "$#" -lt 4 ]; then
  echo "Usage: $0 {operation} {system user} {staged input or -} {path} [additional path]" >&2
  exit 1
fi

OPERATION=$1
SYSTEM_USER=$2
STAGED_NAME=$3
shift 3

case "$OPERATION" in
  replace|append|copy|permissions|rename|remove|directory) ;;
  *) echo "Invalid secure filesystem operation" >&2; exit 1 ;;
esac

case "$SYSTEM_USER" in
  *[!a-zA-Z0-9_-]*|'') echo "Invalid system user" >&2; exit 1 ;;
esac

case "$SYSTEM_USER" in
  *_ln) ;;
  *) SYSTEM_USER="${SYSTEM_USER}_ln" ;;
esac

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
HELPER="$SCRIPT_DIR/../../app/Filesystem/safe_file.py"
PANEL_ROOT=$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)

if [ "$STAGED_NAME" = "-" ]; then
  INPUT_ROOT="-"
elif [[ "$STAGED_NAME" =~ ^chunk-[a-zA-Z0-9]+$ ]]; then
  INPUT_ROOT="$PANEL_ROOT/storage/app/secure-file-staging"
else
  echo "Invalid staged input" >&2
  exit 1
fi

exec /usr/bin/python3 "$HELPER" "$OPERATION" "/home/$SYSTEM_USER" "$SYSTEM_USER" "$INPUT_ROOT" "$STAGED_NAME" "$@"
