<?php

namespace App\Jobs;

use App\Actions\Backup\RetainBackupsAction;
use App\Models\Backup;
use App\Models\ScheduledBackup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class RetainBackupsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $scheduledBackupId) {}

    public function handle(RetainBackupsAction $action): void
    {
        $schedule = ScheduledBackup::findOrFail($this->scheduledBackupId);

        // Resolve the disk from an actual completed Backup row for this schedule.
        // BackupService records disk_name (and the encrypted S3 creds) on every
        // Backup row; ScheduledBackup.disk_name is not reliably set, and the S3
        // disk config does not exist in the queue-worker process.
        $sample = Backup::where('user_id', $schedule->user_id)
            ->where('type', $schedule->type)
            ->where('target', $schedule->target)
            ->where('status', 'completed')
            ->latest()
            ->first();

        if (! $sample || ! $sample->disk_name) {
            return; // Nothing to retain yet.
        }

        // Re-register the S3 disk in this worker process if needed
        // (same pattern as BackupJob / RestoreJob).
        $s3Config = $sample->s3DiskConfig();
        if ($s3Config !== null) {
            Config::set("filesystems.disks.{$sample->disk_name}", $s3Config);
        }

        $action->execute(
            $schedule->user_id,
            $schedule->type,
            $schedule->target,
            $schedule->retention_count,
            Storage::disk($sample->disk_name),
        );
    }
}
