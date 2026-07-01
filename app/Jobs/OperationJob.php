<?php

namespace App\Jobs;

use App\Models\Operation;
use App\Models\User;
use App\Notifications\OperationFinishedNotification;
use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

abstract class OperationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?User $notifyUser = null;

    public function __construct(public Operation $operation) {}

    /** Do the work; call $emit($line) per output line; return the exit code. */
    abstract protected function run(callable $emit): int;

    public function handle(): void
    {
        $this->operation->markRunning();

        try {
            $exit = $this->run(fn (string $line) => $this->operation->appendOutput($line));
            $this->operation->markFinished($exit);
            $this->safeNotify();
        } catch (\Throwable $e) {
            $this->operation->appendOutput('ERROR: '.$e->getMessage());
            $this->operation->markFinished(1);
            $this->safeNotify();
            throw $e; // also record in failed_jobs
        }
    }

    /**
     * Fire the user notification without letting any delivery failure affect
     * the operation status or propagate an exception to the caller.
     */
    protected function safeNotify(): void
    {
        if ($this->notifyUser === null) {
            return;
        }

        try {
            NotificationService::dispatch($this->notifyUser, new OperationFinishedNotification($this->operation));
        } catch (\Throwable $e) {
            Log::warning('OperationJob: notification delivery failed', [
                'operation_id' => $this->operation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
