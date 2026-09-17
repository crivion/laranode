<?php

namespace App\Services\Backups;

use App\Jobs\RunBackupJob;
use App\Models\Backup;
use App\Models\BackupDestination;
use App\Models\User;

class CreateBackupService
{
    public function handle(User $user, array $data, bool $dispatch = true): Backup
    {
        $destinationId = $data['destination_id'] ?? BackupDestination::where('is_default', true)
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->id))
            ->value('id');
        $scope = $data['scope'];
        $backup = Backup::create([
            'user_id' => $user->id,
            'website_id' => $scope === 'website_files' ? ($data['website_id'] ?? null) : null,
            'database_id' => $scope === 'website_database' ? ($data['database_id'] ?? null) : null,
            'destination_id' => $destinationId,
            'scope' => $scope,
            'kind' => $data['kind'] ?? 'manual',
            'status' => 'pending',
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        if ($dispatch) {
            RunBackupJob::dispatch($backup->id);
        }

        return $backup;
    }
}
