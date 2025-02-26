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


# Create directory and apply permisisons
mkdir -p "$DIR_PATH"
find /home/$SYSTEM_USER -type d -exec chmod 770 {} \;
find /home/$SYSTEM_USER -type f -exec chmod 660 {} \;
chown -R $SYSTEM_USER:$SYSTEM_USER /home/$SYSTEM_USER

echo "Directory created successfully."
