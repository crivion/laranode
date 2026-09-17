<?php

namespace App\Console\Commands;

use App\Jobs\PruneBackupsJob;
use App\Models\BackupSchedule;
use App\Services\Backups\CreateBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunBackupSchedulesCommand extends Command
{
    protected $signature = 'laranode:run-backup-schedules';

    protected $description = 'Queue due backup schedules and prune expired backups';

    public function handle(CreateBackupService $creator): int
    {
        BackupSchedule::where('enabled', true)->where('next_run_at', '<=', now())->each(function (BackupSchedule $schedule) use ($creator) {
            DB::transaction(function () use ($schedule, $creator) {
                $locked = BackupSchedule::lockForUpdate()->find($schedule->id);
                if (! $locked || ! $locked->enabled || ! $locked->next_run_at?->lte(now())) {
                    return;
                }
                $creator->handle($locked->user, [
                    'scope' => $locked->scope,
                    'website_id' => $locked->website_id,
                    'database_id' => $locked->database_id,
                    'destination_id' => $locked->destination_id,
                    'kind' => 'scheduled',
                ]);
                $locked->last_run_at = now();
                $locked->next_run_at = $locked->calculateNextRun(now()->addSecond());
                $locked->save();
            });
        });

        PruneBackupsJob::dispatch();

        return self::SUCCESS;
    }
}
