<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Models\User;
use App\Services\Backups\RestoreBackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunRestoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $backupId, public int $actorId) {}

    public function handle(RestoreBackupService $service): void
    {
        $backup = Backup::findOrFail($this->backupId);
        $backup->update(['status' => 'running', 'started_at' => now(), 'error' => null]);
        $service->handle($backup, User::findOrFail($this->actorId));
        $backup->update(['status' => 'completed', 'finished_at' => now(), 'restore_attempts' => 0]);
    }

    public function failed(Throwable $exception): void
    {
        Backup::whereKey($this->backupId)->update(['status' => 'failed', 'error' => $exception->getMessage(), 'finished_at' => now()]);
    }
}
