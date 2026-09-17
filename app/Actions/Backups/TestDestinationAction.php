<?php

namespace App\Actions\Backups;

use App\Models\BackupDestination;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class TestDestinationAction
{
    public function execute(BackupDestination $destination): void
    {
        if ($destination->driver === 'local') {
            $this->executeLocal($destination);

            return;
        }

        $path = '.laranode-probe-'.bin2hex(random_bytes(8));
        $disk = $destination->disk();
        $failure = null;

        try {
            $disk->put($path, 'laranode');
            if ($disk->get($path) !== 'laranode') {
                throw new \RuntimeException('The destination returned unexpected probe data.');
            }
        } catch (Throwable $exception) {
            $failure = $exception;

            throw $exception;
        } finally {
            try {
                $disk->delete($path);
            } catch (Throwable $cleanupException) {
                if ($failure === null) {
                    throw $cleanupException;
                }
            }
        }
    }

    private function executeLocal(BackupDestination $destination): void
    {
        $relative = 'probes/'.bin2hex(random_bytes(8));
        $script = config('laranode.laranode_bin_path').'/laranode-backup-storage.sh';
        $prepare = Process::run(['sudo', $script, config('laranode.backup_path'), 'prepare', $relative]);
        if ($prepare->failed()) {
            throw new RuntimeException('Unable to prepare local backup storage: '.$prepare->errorOutput());
        }

        $path = $relative.'/.laranode-probe';
        $disk = $destination->disk();
        try {
            $disk->put($path, 'laranode');
            if ($disk->get($path) !== 'laranode') {
                throw new RuntimeException('The local destination returned unexpected probe data.');
            }
        } finally {
            Process::run(['sudo', $script, config('laranode.backup_path'), 'remove', $relative]);
        }
    }
}
