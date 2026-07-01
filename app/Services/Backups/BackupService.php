<?php

namespace App\Services\Backups;

use App\Jobs\BackupJob;
use App\Models\Backup;
use App\Models\Operation;
use App\Models\User;
use Exception;

class BackupException extends Exception {}

class BackupService
{
    /**
     * Create the Backup + Operation rows, persist any S3 credentials (encrypted),
     * register a runtime disk when needed, and dispatch BackupJob.
     *
     * @param  array{type: string, target: string, storage: string, s3_key?: string, s3_secret?: string, s3_region?: string, s3_bucket?: string, s3_endpoint?: string}  $data
     */
    public function handle(array $data, User $user): Operation
    {
        $storage = $data['storage'] ?? 'local';

        if ($storage === 's3') {
            // Each user gets a deterministic disk name so tests can assert on it.
            $diskName = 'backups_s3_'.$user->id;
        } else {
            $diskName = 'backups';
        }

        $operation = Operation::create([
            'user_id' => $user->id,
            'type' => 'backup.'.($data['type'] ?? 'db'),
            'target' => $data['target'] ?? '',
            'status' => 'queued',
        ]);

        // S3 credentials are stored encrypted on the Backup row so BackupJob can
        // re-register the disk inside the queue worker process (where the Config
        // set during the request is no longer present).
        $backup = Backup::create([
            'user_id' => $user->id,
            'operation_id' => $operation->id,
            'type' => $data['type'] ?? 'db',
            'target' => $data['target'] ?? '',
            'storage' => $storage,
            'disk_name' => $diskName,
            's3_key' => $data['s3_key'] ?? null,
            's3_secret' => $data['s3_secret'] ?? null,
            's3_region' => $data['s3_region'] ?? null,
            's3_bucket' => $data['s3_bucket'] ?? null,
            's3_endpoint' => $data['s3_endpoint'] ?? null,
            'status' => 'pending',
        ]);

        BackupJob::dispatch($operation, $backup);

        return $operation;
    }
}
