#!/usr/bin/env bash

set -euo pipefail

cmd=${1:-}
arg=${2:-}

run() {
  if command -v ufw >/dev/null 2>&1; then
    ufw "$@"
  else
    echo "ufw command not found" >&2
    exit 127
  fi
}

case "$cmd" in
  status)
    run status | head -n1
    ;;
  enable)
    yes | run enable >/dev/null
    echo "enabled"
    ;;
  disable)
    yes | run disable >/dev/null
    echo "disabled"
    ;;
  list)
    run status numbered
    ;;
  allow)
    if [ -z "${arg:-}" ]; then
      echo "rule spec required" >&2
      exit 2
    fi
    run allow $arg >/dev/null
    echo "allowed: $arg"
    ;;
  deny)
    if [ -z "${arg:-}" ]; then
      echo "rule spec required" >&2
      exit 2
    fi
    run deny $arg >/dev/null
    echo "denied: $arg"
    ;;
  delete)
    if [ -z "${arg:-}" ]; then
      echo "rule id/spec required" >&2
      exit 2
    fi
    if [[ "$arg" =~ ^[0-9]+$ ]]; then
      yes | run delete "$arg" >/dev/null
    else
      yes | run delete $arg >/dev/null
    fi
    echo "deleted: $arg"
    ;;
  *)
    echo "unknown command" >&2
    exit 2
    ;;
}
