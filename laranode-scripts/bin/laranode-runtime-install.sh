#!/bin/bash
set -euo pipefail

# laranode-runtime-install.sh <runtime>
# Installs the given alternative PHP runtime binary.
# Called via sudo by InstallRuntimeService.

# ---- arg validation ----
if [ $# -ne 1 ]; then
    echo "Usage: $0 <runtime>" >&2
    exit 1
fi

RUNTIME="$1"

# Reject leading-dash args
case "$RUNTIME" in
    -*) echo "Invalid runtime: $RUNTIME" >&2; exit 1 ;;
esac

if ! echo "$RUNTIME" | grep -qE '^(frankenphp|swoole)$'; then
    echo "Invalid runtime '$RUNTIME'. Must be: frankenphp|swoole" >&2
    exit 1
fi

# ---- FrankenPHP ----
if [ "$RUNTIME" = "frankenphp" ]; then
    FRANKENPHP_VERSION="v1.12.4"
    FRANKENPHP_SHA256="becd9efc79783a4946fb4802433dc00be32de7e025b60fcab53db4d283a136e9"
    FRANKENPHP_URL="https://github.com/dunglas/frankenphp/releases/download/${FRANKENPHP_VERSION}/frankenphp-linux-x86_64"
    FRANKENPHP_BIN="/usr/local/bin/frankenphp"
    FRANKENPHP_TMP="/tmp/frankenphp"

    # Idempotent: if binary exists and --version works, skip re-download
    if [ -f "$FRANKENPHP_BIN" ]; then
        if "$FRANKENPHP_BIN" --version >/dev/null 2>&1; then
            echo "FrankenPHP ${FRANKENPHP_VERSION} already installed and functional. Skipping."
            exit 0
        else
            # Binary exists but is corrupt — re-download
            echo "FrankenPHP binary exists but '--version' failed (corrupt?). Re-downloading..."
        fi
    fi

    echo "Downloading FrankenPHP ${FRANKENPHP_VERSION}..."
    curl -sfL -o "$FRANKENPHP_TMP" "$FRANKENPHP_URL"

    echo "Verifying SHA-256 checksum..."
    if ! echo "${FRANKENPHP_SHA256}  ${FRANKENPHP_TMP}" | sha256sum -c -; then
        echo "SHA-256 mismatch — aborting install." >&2
        rm -f "$FRANKENPHP_TMP"
        exit 1
    fi

    mv "$FRANKENPHP_TMP" "$FRANKENPHP_BIN"
    chmod 0755 "$FRANKENPHP_BIN"

    echo "Verifying FrankenPHP binary..."
    "$FRANKENPHP_BIN" --version
    echo "FrankenPHP installed successfully."
    exit 0
fi

# ---- Swoole (v2 placeholder) ----
if [ "$RUNTIME" = "swoole" ]; then
    echo "Swoole runtime install not implemented in v1." >&2
    exit 1
fi
