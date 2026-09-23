<?php

namespace App\Services\Backups;

use App\Actions\Backups\BuildDefaultsFileAction;
use App\Models\Backup;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class RestoreBackupService
{
    public function __construct(
        private CreateBackupService $creator,
        private BackupAccountService $backupService,
        private BuildDefaultsFileAction $defaultsFile,
    ) {}

    public function handle(Backup $source, User $actor): void
    {
        $this->createSafetySnapshot($source);
        $this->materialize($source);

        $manifest = $source->manifest;
        $phpVersions = [];
        foreach ($manifest['websites'] ?? [] as $website) {
            $this->restoreWebsite($source, $website);
            $phpVersions[] = $this->servingPhpVersion($source, $website);
        }
        foreach ($manifest['databases'] ?? [] as $database) {
            $this->restoreDatabase($source, $database);
        }

        $source->update(['restored_at' => now(), 'restored_by' => $actor->id]);

        $this->reloadPhpFpm($phpVersions);
    }

    /**
     * The PHP version that serves the site now. The manifest records the
     * version at backup time, which is stale if the site has been switched
     * since - reloading that FPM would leave the real one serving cached code.
     */
    private function servingPhpVersion(Backup $backup, array $website): ?string
    {
        $live = Website::where('user_id', $backup->user_id)
            ->where('url', $website['url'])
            ->with('phpVersion')
            ->first()
            ?->phpVersion?->version;

        return $live ?? $website['php_version'] ?? null;
    }

    /**
     * Restored files keep their paths, so with opcache.validate_timestamps=0
     * FPM would keep serving the previously compiled code until reloaded.
     */
    private function reloadPhpFpm(array $versions): void
    {
        $failed = [];
        foreach (array_unique(array_filter($versions)) as $version) {
            if (! preg_match('/^\d+\.\d+$/', $version)) {
                $failed[] = $version;

                continue;
            }
            $result = Process::run([
                'sudo', config('laranode.laranode_bin_path').'/laranode-php-service.sh', 'reload', $version,
            ]);
            if ($result->failed()) {
                $failed[] = $version;
            }
        }

        if ($failed) {
            throw new RuntimeException('Files were restored, but reloading PHP-FPM failed for PHP '.implode(', ', $failed).'; the site may serve cached code until it is reloaded.');
        }
    }

    private function createSafetySnapshot(Backup $source): void
    {
        $snapshot = $this->creator->handle($source->user, [
            'scope' => $source->scope,
            'website_id' => $source->website_id,
            'database_id' => $source->database_id,
            'destination_id' => $source->destination_id,
            'kind' => 'pre_restore',
            'expires_at' => now()->addDays(7),
        ], false);

        try {
            $snapshot->update(['status' => 'running', 'started_at' => now()]);
            $this->backupService->handle($snapshot);
            $snapshot->update(['status' => 'completed', 'finished_at' => now()]);
        } catch (Throwable $exception) {
            $snapshot->update(['status' => 'failed', 'error' => $exception->getMessage(), 'finished_at' => now()]);
            throw new RuntimeException('The pre-restore safety snapshot failed; restore was aborted.', 0, $exception);
        }
    }

    private function restoreWebsite(Backup $backup, array $website): void
    {
        $domain = $website['url'];
        $archive = $this->localDirectory($backup).'/'.$website['archive'];
        $result = Process::timeout(3600)->run([
            'sudo', config('laranode.laranode_bin_path').'/laranode-restore-files.sh',
            $backup->manifest['account']['system_username'], $domain, $archive,
        ]);
        if ($result->failed()) {
            throw new RuntimeException("Failed to restore {$domain}: ".$result->errorOutput());
        }
    }

    private function restoreDatabase(Backup $backup, array $database): void
    {
        foreach (['name', 'db_user', 'charset', 'collation'] as $key) {
            if (! preg_match('/^[a-zA-Z0-9_]+$/', $database[$key] ?? '')) {
                throw new RuntimeException("Invalid {$key} in backup manifest.");
            }
        }

        DB::statement("DROP DATABASE IF EXISTS `{$database['name']}`");
        DB::statement("CREATE DATABASE `{$database['name']}` CHARACTER SET {$database['charset']} COLLATE {$database['collation']}");
        DB::statement("GRANT ALL PRIVILEGES ON `{$database['name']}`.* TO `{$database['db_user']}`@'localhost'");
        DB::statement('FLUSH PRIVILEGES');

        $defaults = $this->defaultsFile->execute();
        try {
            $result = Process::timeout(3600)->run([
                'sudo', config('laranode.laranode_bin_path').'/laranode-restore-db.sh',
                $database['name'], $this->localDirectory($backup).'/'.$database['dump'], $defaults,
            ]);
        } finally {
            @unlink($defaults);
        }
        if ($result->failed()) {
            throw new RuntimeException("Failed to restore {$database['name']}: ".$result->errorOutput());
        }
    }

    private function materialize(Backup $backup): void
    {
        $directory = $this->localDirectory($backup);
        if (is_file($directory.'/manifest.json')) {
            return;
        }
        if (! $backup->destination || $backup->destination->driver === 'local') {
            throw new RuntimeException('The local backup files are missing.');
        }

        $prepared = Process::run(['sudo', config('laranode.laranode_bin_path').'/laranode-backup-storage.sh', config('laranode.backup_path'), 'prepare', $backup->path]);
        if ($prepared->failed()) {
            throw new RuntimeException('Unable to prepare local restore storage: '.$prepared->errorOutput());
        }
        $files = ['manifest.json'];
        foreach ($backup->manifest['websites'] ?? [] as $item) {
            $files[] = $item['archive'];
        }
        foreach ($backup->manifest['databases'] ?? [] as $item) {
            $files[] = $item['dump'];
        }
        foreach ($files as $file) {
            @mkdir(dirname($directory.'/'.$file), 0770, true);
            $read = $backup->destination->disk()->readStream($backup->path.'/'.$file);
            $write = fopen($directory.'/'.$file, 'wb');
            if (! $read || ! $write) {
                throw new RuntimeException("Unable to download {$file} from the backup destination.");
            }
            stream_copy_to_stream($read, $write);
            fclose($read);
            fclose($write);
        }
    }

    private function localDirectory(Backup $backup): string
    {
        return rtrim(config('laranode.backup_path'), '/').'/'.$backup->path;
    }
}
