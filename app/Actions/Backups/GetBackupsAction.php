<?php

namespace App\Actions\Backups;

use App\Models\Backup;

class GetBackupsAction
{
    public function execute(bool $includeSnapshots = false)
    {
        return Backup::query()->mine()
            ->with(['user:id,username', 'website:id,url', 'database:id,name', 'destination:id,name,driver'])
            ->when(! $includeSnapshots, fn ($query) => $query->where('kind', '!=', 'pre_restore'))
            ->latest()->get()->map(function (Backup $backup) {
                $backup->setAttribute('human_size', $this->humanSize($backup->size_bytes));
                $backup->setAttribute('confirmation_name', $backup->confirmationName());
                $backup->setAttribute('restore_attempts_remaining', $backup->restoreAttemptsRemaining());
                $backup->setAttribute('can_attempt_restore', $backup->canAttemptRestore());

                return $backup;
            });
    }

    private function humanSize(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, $index ? 1 : 0).' '.$units[$index];
    }
}
