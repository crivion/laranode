<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Services\Backups\BackupAccountService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $backupId) {}

    public function handle(BackupAccountService $service): void
    {
        $backup = Backup::findOrFail($this->backupId);
        $backup->update(['status' => 'running', 'started_at' => now(), 'error' => null]);
        $service->handle($backup);
        $backup->update(['status' => 'completed', 'finished_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        Backup::whereKey($this->backupId)->update([
            'status' => 'failed', 'error' => $exception->getMessage(), 'finished_at' => now(),
        ]);
    }
}
