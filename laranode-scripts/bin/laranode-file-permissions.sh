#!/bin/bash
# Check if at least two arguments are provided (path and system user)
if [ $# -lt 2 ]; then
  echo "Usage: $0 {path} {system user}"
  exit 1
fi

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)

# The helper walks from an open home-directory descriptor with O_NOFOLLOW and
# mutates the opened file descriptor. No validation/use race remains.
exec "$SCRIPT_DIR/laranode-safe-file.sh" permissions "$2" - "$1"
