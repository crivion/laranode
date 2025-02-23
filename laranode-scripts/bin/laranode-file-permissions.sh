#!/bin/bash
# Check if at least two arguments are provided (path and system user)
if [ $# -lt 2 ]; then
  echo "Usage: $0 {path} {system user}"
  exit 1
fi

SYSTEM_USER=$2
# Automatically append _ln to $USERNAME if not already present
if echo "$SYSTEM_USER" | grep -qv '_ln$'; then
    SYSTEM_USER+="_ln"
fi

TARGET_PATH="/home/$SYSTEM_USER/$1"
# remove duplicate slashes
TARGET_PATH=$(echo "$TARGET_PATH" | tr -s '/')

echo "Setting permissions for $TARGET_PATH"

# Set file permissions
if [ -f "$TARGET_PATH" ]; then
    echo "$1 is a file. Doing 660"
    chmod 660 "$TARGET_PATH"
fi

# Set directory permissions
if [ -d "$TARGET_PATH" ]; then
    echo "$1 is a directory. Doing 770"
    chmod 770 "$TARGET_PATH"
fi

# Change owner to system user
chown "$SYSTEM_USER:$SYSTEM_USER" "$TARGET_PATH"
