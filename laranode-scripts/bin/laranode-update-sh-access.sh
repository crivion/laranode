#!/bin/bash

# Check if the correct number of arguments is provided
if [ $# -ne 2 ]; then
    echo "Usage: $0 {systemUser} {shell_access = yes|no}"
    exit 1
fi

# Assign arguments to variables
SYSTEM_USER="$1"
SHELL_ACCESS="$2"

# Automatically append _ln to $USERNAME if not already present
if echo "$SYSTEM_USER" | grep -qv '_ln$'; then
    SYSTEM_USER+="_ln"
fi

# Check if the user exists
if ! id "$SYSTEM_USER" &>/dev/null; then
    echo "Error: User '$SYSTEM_USER' does not exist."
    exit 2
fi

# Check if shell_access parameter is valid
if [ "$SHELL_ACCESS" != "yes" ] && [ "$SHELL_ACCESS" != "no" ]; then
    echo "Error: Invalid shell_access parameter. Must be 'yes' or 'no'."
    echo "Usage: $0 {systemUser} {shell_access = yes|no}"
    exit 3
fi

# Function to grant SSH access
grant_ssh_access() {
    local user="$1"
    # Check current shell to see if it's already not /bin/false or /usr/sbin/nologin
    current_shell=$(grep "^$user:" /etc/passwd | cut -d: -f7)

    if [ "$current_shell" == "/bin/false" ] || [ "$current_shell" == "/usr/sbin/nologin" ]; then
        # Change shell to /bin/bash (or another valid shell)
        sudo usermod -s /bin/bash "$user"
        echo "SSH access granted for user '$user'."
    else
        echo "User '$user' already has SSH access."
    fi
}

# Function to remove SSH access
remove_ssh_access() {
    local user="$1"
    # Set shell to /bin/false to disable SSH login
    sudo usermod -s /bin/false "$user"
    echo "SSH access removed for user '$user'."
}

# Process based on shell_access parameter
if [ "$SHELL_ACCESS" == "yes" ]; then
    grant_ssh_access "$SYSTEM_USER"
else
    remove_ssh_access "$SYSTEM_USER"
fi

exit 0
