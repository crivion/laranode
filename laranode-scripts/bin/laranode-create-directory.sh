#!/bin/bash

# Check if at least two arguments are provided (system user and path)
if [ $# -lt 2 ]; then
  echo "Usage: $0 {fullPathToCreate} {system_user}"
  exit 1
fi

DIR_PATH=$1
SYSTEM_USER=$2

# Automatically append _ln to $USERNAME if not already present
if echo "$SYSTEM_USER" | grep -qv '_ln$'; then
    SYSTEM_USER+="_ln"
fi

if [[ ! $SYSTEM_USER =~ ^[A-Za-z0-9_-]+$ ]]; then
  echo "Invalid system user" >&2
  exit 1
fi

# This runs as root, so only ever create directories inside the user's own
# domains folder. realpath -m resolves "..", and symlinks in the parts that
# already exist, before the containment check.
DOMAINS_DIR="/home/$SYSTEM_USER/domains"
RESOLVED_PATH=$(realpath -m -- "$DIR_PATH")

if [[ $RESOLVED_PATH != "$DOMAINS_DIR"/* ]]; then
  echo "Refusing to create a directory outside $DOMAINS_DIR" >&2
  exit 1
fi

# Create directory and apply permisisons
mkdir -p -- "$RESOLVED_PATH"
find /home/$SYSTEM_USER/domains -type d -exec chmod 770 {} \;
find /home/$SYSTEM_USER/domains -type f -exec chmod 660 {} \;
chown -R $SYSTEM_USER:$SYSTEM_USER /home/$SYSTEM_USER

echo "Directory created successfully."
