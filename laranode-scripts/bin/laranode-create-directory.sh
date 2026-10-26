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

# Only fix up what this call creates: walking every site in the user's domains
# folder made website creation scale with the size of all the other sites and
# time out on busy accounts. mkdir -p may create several levels, so start from
# the topmost one that is missing; if it all exists already (a retry after an
# earlier attempt was cut short), fix up the requested directory itself.
TARGET="$RESOLVED_PATH"
while [ ! -e "$(dirname -- "$TARGET")" ]; do
  TARGET=$(dirname -- "$TARGET")
done

# Create directory and apply permissions
mkdir -p -- "$RESOLVED_PATH"
find "$TARGET" -type d -exec chmod 770 {} +
find "$TARGET" -type f -exec chmod 660 {} +
chown -R "$SYSTEM_USER:$SYSTEM_USER" -- "$TARGET"

echo "Directory created successfully."
