<?php

namespace App\Jobs;

use App\Models\ScheduledBackup;
use App\Services\Backups\BackupService;
use Cron\CronExpression;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunScheduledBackupsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * A fixed unique ID prevents multiple instances from queuing simultaneously
     * when the everyMinute() scheduler fires faster than jobs drain.
     */
    public function uniqueId(): string
    {
        return 'run-scheduled-backups';
    }

    public function handle(): void
    {
        $service = app(BackupService::class);

        ScheduledBackup::where('enabled', true)->each(function (ScheduledBackup $entry) use ($service) {
            // Guard: skip if last_run_at is within 50 seconds to avoid double-fire.
            if ($entry->last_run_at && $entry->last_run_at->diffInSeconds(now()) < 50) {
                return;
            }

            $cron = new CronExpression($entry->cron_expression);

            if (! $cron->isDue()) {
                return;
            }

            $data = [
                'type' => $entry->type,
                'target' => $entry->target,
                'storage' => $entry->storage,
                's3_key' => $entry->s3_key,
                's3_secret' => $entry->s3_secret,
                's3_region' => $entry->s3_region,
                's3_bucket' => $entry->s3_bucket,
                's3_endpoint' => $entry->s3_endpoint,
            ];

            $service->handle($data, $entry->user);

            RetainBackupsJob::dispatch($entry->id);

            // Stamp last_run_at AFTER dispatching so a service failure does not
            // silently suppress the schedule for the next 50+ seconds.
            $entry->update(['last_run_at' => now()]);
        });
    }
}
