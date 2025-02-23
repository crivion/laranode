#!/bin/bash

# Check if at least two arguments are provided (action and username)
if [ $# -lt 2 ]; then
  echo "Usage: $0 {create|delete} username {allow shell/ssh login (optional): yes|no} {password (required if allow shell/ssh login is yes)}"
  exit 1
fi

ACTION=$1
USERNAME=$2
ALLOW_LOGIN=${3:-no}  # Default to "no" if not specified
PASSWORD=$4

# Ensure password is provided if allow shell/ssh login is "yes"
if [ "$ALLOW_LOGIN" = "yes" ] && [ -z "$PASSWORD" ]; then
  echo "Error: Password is required when allow shell/ssh login is 'yes'."
  exit 1
fi

# Automatically append _ln to $USERNAME if not already present
if echo "$USERNAME" | grep -qv '_ln$'; then
  USERNAME+="_ln"
fi

# Function to create a user
create_user() {
  # Check if the user already exists
  if id "$USERNAME" &>/dev/null; then
    echo "User $USERNAME already exists."
    exit 1
  fi

  # Create the user
  if [ "$ALLOW_LOGIN" = "yes" ]; then
    # Create user with login shell
    useradd -m "$USERNAME"
    if [ $? -ne 0 ]; then
      echo "Error: Failed to create user $USERNAME."
      exit 1
    fi

    # add password to user
    echo "$USERNAME:$PASSWORD" | chpasswd
    echo "User $USERNAME created successfully with SSH login allowed."
  else
    # Create user with no login shell
    useradd -m -s /usr/sbin/nologin "$USERNAME"
    if [ $? -ne 0 ]; then
      echo "Error: Failed to create user $USERNAME."
      exit 1
    fi
    echo "User $USERNAME created successfully with no SSH login."
  fi

  # add this user group to www-data group too
  usermod -aG "$USERNAME" www-data

  # create /home/{user}/logs directory
  mkdir -p "/home/$USERNAME/logs"
  chown "$USERNAME:$USERNAME" "/home/$USERNAME/logs"
  chmod 770 "/home/$USERNAME/logs"

  # create /home/{user}/domains directory
  mkdir -p "/home/$USERNAME/domains"
  chown "$USERNAME:$USERNAME" "/home/$USERNAME/domains"
  chmod 770 "/home/$USERNAME/domains"
}

# Function to delete a user
delete_user() {
  # Check if the user exists
  if ! id "$USERNAME" &>/dev/null; then
    echo "User $USERNAME does not exist."
    exit 1
  fi

  # remove this user from www-data's groups
  deluser www-data "$USERNAME"

  # Delete the user and their home directory
  userdel -r "$USERNAME"
  if [ $? -ne 0 ]; then
    echo "Error: Failed to delete user $USERNAME."
    exit 1
  fi


  echo "User $USERNAME deleted successfully."
}

# Perform the action based on the first argument (create or delete)
case $ACTION in
  create)
    create_user
    ;;
  delete)
    delete_user
    ;;
  *)
    echo "Error: Invalid action. Use 'create' or 'delete'."
    exit 1
    ;;
esac

