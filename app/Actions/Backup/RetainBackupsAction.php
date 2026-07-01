<?php

namespace App\Actions\Backup;

use App\Models\Backup;
use Illuminate\Contracts\Filesystem\Filesystem;

class RetainBackupsAction
{
    /**
     * Prune backups beyond $retentionCount for the given user/type/target.
     * Deletes the physical file from $disk then destroys the model row.
     */
    public function execute(int $userId, string $type, string $target, int $retentionCount, Filesystem $disk): void
    {
        $backups = Backup::where('user_id', $userId)
            ->where('type', $type)
            ->where('target', $target)
            ->where('status', 'completed')
            ->orderBy('created_at', 'asc')
            ->get();

        $excess = $backups->slice(0, max(0, $backups->count() - $retentionCount));

        foreach ($excess as $backup) {
            if ($backup->path) {
                $disk->delete($backup->path);
            }

            $backup->delete();
        }
    }
}
