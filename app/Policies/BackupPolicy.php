<?php

namespace App\Policies;

use App\Models\Backup;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BackupPolicy
{
    public function view(User $user, Backup $backup): Response
    {
        return ($user->isAdmin() || $user->id === $backup->user_id)
            ? Response::allow()
            : Response::deny('You are not authorized to view this backup.');
    }

    public function delete(User $user, Backup $backup): Response
    {
        return ($user->isAdmin() || $user->id === $backup->user_id)
            ? Response::allow()
            : Response::deny('You are not authorized to delete this backup.');
    }

    public function restore(User $user, Backup $backup): Response
    {
        return ($user->isAdmin() || $user->id === $backup->user_id)
            ? Response::allow()
            : Response::deny('You are not authorized to restore this backup.');
    }

    public function download(User $user, Backup $backup): Response
    {
        return ($user->isAdmin() || $user->id === $backup->user_id)
            ? Response::allow()
            : Response::deny('You are not authorized to download this backup.');
    }
}
