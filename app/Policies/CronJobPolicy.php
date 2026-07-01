<?php

namespace App\Policies;

use App\Models\CronJob;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CronJobPolicy
{
    public function update(User $user, CronJob $cronJob): Response
    {
        return ($user->isAdmin() || $user->id === $cronJob->user_id)
            ? Response::allow()
            : Response::deny('You are not authorized to update this cron job.');
    }

    public function delete(User $user, CronJob $cronJob): Response
    {
        return ($user->isAdmin() || $user->id === $cronJob->user_id)
            ? Response::allow()
            : Response::deny('You are not authorized to delete this cron job.');
    }
}
