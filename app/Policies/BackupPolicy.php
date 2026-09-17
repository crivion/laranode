<?php

namespace App\Policies;

use App\Models\Backup;
use App\Models\User;

class BackupPolicy
{
    private function owns(User $user, Backup $backup): bool
    {
        return $user->isAdmin() || $user->id === $backup->user_id;
    }

    public function view(User $user, Backup $backup): bool
    {
        return $this->owns($user, $backup);
    }

    public function download(User $user, Backup $backup): bool
    {
        return $this->owns($user, $backup);
    }

    public function restore(User $user, Backup $backup): bool
    {
        return $this->owns($user, $backup);
    }

    public function delete(User $user, Backup $backup): bool
    {
        return $this->owns($user, $backup);
    }
}
