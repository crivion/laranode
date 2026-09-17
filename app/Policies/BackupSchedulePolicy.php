<?php

namespace App\Policies;

use App\Models\BackupSchedule;
use App\Models\User;

class BackupSchedulePolicy
{
    private function owns(User $user, BackupSchedule $schedule): bool
    {
        return $user->isAdmin() || $user->id === $schedule->user_id;
    }

    public function view(User $user, BackupSchedule $schedule): bool
    {
        return $this->owns($user, $schedule);
    }

    public function update(User $user, BackupSchedule $schedule): bool
    {
        return $this->owns($user, $schedule);
    }

    public function delete(User $user, BackupSchedule $schedule): bool
    {
        return $this->owns($user, $schedule);
    }
}
