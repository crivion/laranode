<?php

namespace App\Services\Backups;

use App\Jobs\RestoreJob;
use App\Models\Backup;
use App\Models\Operation;
use App\Models\User;
use Exception;

class RestoreException extends Exception {}

class RestoreService
{
    /**
     * Create an Operation row and dispatch RestoreJob for the given backup.
     */
    public function handle(Backup $backup, string $newTarget, User $user): Operation
    {
        $operation = Operation::create([
            'user_id' => $user->id,
            'type' => 'restore.'.$backup->type,
            'target' => $newTarget,
            'status' => 'queued',
        ]);

        RestoreJob::dispatch($operation, $backup, $newTarget);

        return $operation;
    }
}
