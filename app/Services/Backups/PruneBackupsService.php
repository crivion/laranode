<?php

namespace App\Services\Backups;

use App\Models\Backup;
use App\Models\BackupSchedule;
use Illuminate\Support\Facades\Process;

class PruneBackupsService
{
    public function handle(): void
    {
        Backup::where('expires_at', '<=', now())->whereIn('status', ['completed', 'failed'])->each(fn (Backup $backup) => $this->delete($backup));

        BackupSchedule::where('retention_count', '>', 0)->each(function (BackupSchedule $schedule) {
            $query = Backup::where('user_id', $schedule->user_id)
                ->where('scope', $schedule->scope)->where('kind', 'scheduled')
                ->where('status', 'completed')->latest('created_at');
            if ($schedule->website_id) {
                $query->where('website_id', $schedule->website_id);
            }
            if ($schedule->database_id) {
                $query->where('database_id', $schedule->database_id);
            }
            $query->skip($schedule->retention_count)->take(PHP_INT_MAX)->get()->each(fn (Backup $backup) => $this->delete($backup));
        });
    }

    public function delete(Backup $backup): void
    {
        if ($backup->path && $backup->destination && $backup->destination->driver !== 'local') {
            $backup->destination->disk()->deleteDirectory($backup->path);
        }
        if ($backup->path) {
            $result = Process::run(['sudo', config('laranode.laranode_bin_path').'/laranode-backup-storage.sh', config('laranode.backup_path'), 'remove', $backup->path]);
            if ($result->failed()) {
                throw new \RuntimeException('Unable to remove local backup files: '.$result->errorOutput());
            }
        }
        $backup->delete();
    }
}
