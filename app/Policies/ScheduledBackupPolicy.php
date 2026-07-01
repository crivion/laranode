<?php

namespace App\Policies;

use App\Models\ScheduledBackup;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ScheduledBackupPolicy
{
    public function delete(User $user, ScheduledBackup $scheduledBackup): Response
    {
        return ($user->isAdmin() || $user->id === $scheduledBackup->user_id)
            ? Response::allow()
            : Response::deny('You are not authorized to delete this scheduled backup.');
    }
}
