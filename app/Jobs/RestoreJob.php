<?php

namespace App\Jobs;

use App\Databases\DatabaseSpec;
use App\Models\Backup;
use App\Models\Operation;
use App\Services\Database\CreateDatabaseService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RestoreJob extends OperationJob
{
    public function __construct(
        Operation $operation,
        public Backup $backup,
        public string $newTarget,
    ) {
        parent::__construct($operation);
    }

    protected function run(callable $emit): int
    {
        $backup = $this->backup;
        $newTarget = $this->newTarget;

        // Re-register the S3 disk from encrypted credentials on the Backup row,
        // same reason as BackupJob: queue worker has no knowledge of disks registered
        // during the HTTP request.
        $s3Config = $backup->s3DiskConfig();
        if ($s3Config !== null) {
            Config::set("filesystems.disks.{$backup->disk_name}", $s3Config);
        }

        // Re-validate the new_target identifier inside the job (defence in depth).
        if (! preg_match('/^[a-zA-Z0-9_]{1,64}$/', $newTarget)) {
            throw new \InvalidArgumentException(
                "Invalid restore target identifier: '{$newTarget}'. Must match /^[a-zA-Z0-9_]{1,64}$/."
            );
        }

        if ($newTarget === $backup->target) {
            throw new \InvalidArgumentException(
                "Restore target '{$newTarget}' must differ from the backup source '{$backup->target}'."
            );
        }

        // Download backup to a local temp file.
        $ext = $backup->type === 'db' ? 'sql.gz' : 'tar.gz';
        $tempFile = tempnam(sys_get_temp_dir(), 'laranode-restore-').'.'.$ext;

        try {
            $emit("Downloading backup '{$backup->path}' from disk '{$backup->disk_name}'...");
            $stream = Storage::disk($backup->disk_name)->readStream($backup->path);
            $dest = fopen($tempFile, 'wb');
            stream_copy_to_stream($stream, $dest);
            fclose($dest);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($backup->type === 'db') {
                $this->restoreDatabase($backup, $newTarget, $tempFile, $emit);
            } else {
                $this->restoreFiles($backup, $newTarget, $tempFile, $emit);
            }

            $emit('Restore completed successfully.');

            return 0;
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Restore a database backup into a new panel-managed database.
     */
    private function restoreDatabase(Backup $backup, string $newTarget, string $tempFile, callable $emit): void
    {
        $user = $backup->user;
        $newDbUser = $newTarget.'_u';
        $newPassword = Str::random(16);

        $emit("Creating panel-managed database '{$newTarget}' with user '{$newDbUser}'...");

        $spec = new DatabaseSpec(
            name: $newTarget,
            dbUser: $newDbUser,
            password: $newPassword,
            userId: $user->id,
            options: [
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],
        );

        // CreateDatabaseService creates the DB + per-site user via the engine driver,
        // then persists a Database panel row. Never a bare CREATE DATABASE.
        $newDb = app(CreateDatabaseService::class)->handle($spec, 'mysql');

        // Write a temp .cnf for the restore script so the password never touches argv.
        $cnfPath = sys_get_temp_dir().'/laranode-restore-'.uniqid().'.cnf';
        $prevUmask = umask(0177);
        file_put_contents($cnfPath, "[client]\npassword={$newDb->db_password}\n");
        umask($prevUmask);

        try {
            $emit("Restoring dump into '{$newTarget}'...");
            $binPath = rtrim(config('laranode.laranode_bin_path'), '/');
            $result = Process::run([
                'sudo',
                $binPath.'/laranode-restore-db.sh',
                $cnfPath,
                $tempFile,
                $newTarget,
            ]);

            if ($result->failed()) {
                throw new \RuntimeException('DB restore failed: '.$result->errorOutput());
            }
        } finally {
            if (file_exists($cnfPath)) {
                unlink($cnfPath);
            }
        }
    }

    /**
     * Restore a files backup into the destination directory.
     */
    private function restoreFiles(Backup $backup, string $newTarget, string $tempFile, callable $emit): void
    {
        $user = $backup->user;
        $sysUser = $user->systemUsername;
        $destDir = $user->homedir.'/domains/'.$newTarget;

        $emit("Restoring files into '{$destDir}'...");
        $binPath = rtrim(config('laranode.laranode_bin_path'), '/');
        $result = Process::run([
            'sudo',
            $binPath.'/laranode-restore-files.sh',
            $tempFile,
            $destDir,
            $sysUser,
        ]);

        if ($result->failed()) {
            throw new \RuntimeException('Files restore failed: '.$result->errorOutput());
        }
    }
}
