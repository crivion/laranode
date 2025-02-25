#!/bin/bash

# Check if the correct number of arguments is provided
if [ $# -ne 2 ]; then
    echo "Usage: $0 {systemUser} {password}"
    exit 1
fi

# Assign arguments to variables
SYSTEM_USER="$1"
NEW_PASSWORD="$2"

# Check if the user exists
if ! id "$SYSTEM_USER" &>/dev/null; then
    echo "Error: User '$SYSTEM_USER' does not exist."
    exit 2
fi

# Update the user's password
echo "$SYSTEM_USER:$NEW_PASSWORD" | sudo chpasswd

# Check if the password update was successful
if [ $? -eq 0 ]; then
    echo "Password for user '$SYSTEM_USER' has been updated successfully."
else
    echo "Failed to update password for user '$SYSTEM_USER'."
    exit 3
fi

exit 0
