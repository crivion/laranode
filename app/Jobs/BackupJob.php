<?php

namespace App\Jobs;

use App\Actions\Backup\DumpDatabaseAction;
use App\Actions\Backup\TarFilesAction;
use App\Actions\Backup\UploadToStorageAction;
use App\Models\Backup;
use App\Models\Database as DatabaseModel;
use App\Models\Operation;
use App\Models\Website;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class BackupJob extends OperationJob
{
    public function __construct(Operation $operation, public Backup $backup)
    {
        parent::__construct($operation);
    }

    protected function run(callable $emit): int
    {
        $backup = $this->backup;
        $user = $backup->user;

        // Re-register the S3 disk from the encrypted credentials stored on the Backup
        // row. This is required because queue workers run in a separate process and the
        // Config::set() call made during the HTTP request is gone. Local backups skip
        // this (s3DiskConfig() returns null).
        $s3Config = $backup->s3DiskConfig();
        if ($s3Config !== null) {
            Config::set("filesystems.disks.{$backup->disk_name}", $s3Config);
        }

        // Storage path: "{userId}/{type}/{target}/{Y-m-d-His}.{ext}"
        $ext = $backup->type === 'db' ? 'sql.gz' : 'tar.gz';
        $remotePath = sprintf(
            '%d/%s/%s/%s.%s',
            $user->id,
            $backup->type,
            $backup->target,
            now()->format('Y-m-d-His'),
            $ext
        );

        // Pre-create a placeholder temp path. For db backups the engine driver may
        // return its own temp file (a different path). We track both so the original
        // placeholder is always cleaned up.
        $tempBase = tempnam(sys_get_temp_dir(), 'laranode-backup-');
        $tempPlaceholder = $tempBase.'.'.$ext;
        rename($tempBase, $tempPlaceholder);

        // $tempPath points to the actual file that holds the backup data. It starts
        // equal to $tempPlaceholder but may be reassigned when the dump driver creates
        // its own file (MysqlBackupDriver does this).
        $tempPath = $tempPlaceholder;

        try {
            if ($backup->type === 'db') {
                $database = DatabaseModel::where('name', $backup->target)
                    ->where('user_id', $user->id)
                    ->firstOrFail();

                // Driver may return a different temp path (e.g. the one it created).
                // Delete the placeholder before reassigning so it is not leaked.
                $driverPath = app(DumpDatabaseAction::class)
                    ->execute($database, $tempPlaceholder, $emit);

                if ($driverPath !== $tempPlaceholder && file_exists($tempPlaceholder)) {
                    unlink($tempPlaceholder);
                }

                $tempPath = $driverPath;
            } else {
                $website = Website::where('url', $backup->target)
                    ->where('user_id', $user->id)
                    ->firstOrFail();

                $tempPath = app(TarFilesAction::class)
                    ->execute($website, $tempPlaceholder, $emit);
            }

            $disk = Storage::disk($backup->disk_name);

            app(UploadToStorageAction::class)
                ->execute($tempPath, $remotePath, $disk, $emit);

            $sizeBytes = file_exists($tempPath) ? filesize($tempPath) : null;

            $backup->update([
                'status' => 'completed',
                'path' => $remotePath,
                'size_bytes' => $sizeBytes,
            ]);

            $emit('Backup completed successfully.');

            return 0;
        } finally {
            // Clean up the driver's temp file (or the placeholder if it was never
            // reassigned, e.g. TarFilesAction writes into the placeholder itself).
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            // Also clean up the placeholder in case the driver returned a different
            // path but the unlink above failed or was skipped (e.g. exception before
            // the placeholder unlink inside the try block).
            if ($tempPath !== $tempPlaceholder && file_exists($tempPlaceholder)) {
                unlink($tempPlaceholder);
            }
        }
    }
}
