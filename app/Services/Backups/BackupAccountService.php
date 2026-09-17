<?php

namespace App\Services\Backups;

use App\Models\Backup;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class BackupAccountService
{
    public function __construct(
        private BackupWebsiteFilesService $files,
        private BackupDatabaseService $databases,
        private UploadToDestinationService $uploader,
    ) {}

    public function handle(Backup $backup): void
    {
        $backup->load(['user.websites.phpVersion', 'user.databases.website', 'website.phpVersion', 'database.website', 'destination']);
        $relative = $backup->user->systemUsername.'/'.$backup->id;
        $directory = rtrim(config('laranode.backup_path'), '/').'/'.$relative;
        $this->prepareDirectory($relative);
        @mkdir($directory.'/files', 0770, true);
        @mkdir($directory.'/databases', 0770, true);

        $websites = [];
        $databases = [];
        if ($backup->scope === 'account') {
            foreach ($backup->user->websites as $website) {
                $websites[] = $this->files->handle($website, $directory);
            }
            foreach ($backup->user->databases as $database) {
                $databases[] = $this->databases->handle($database, $directory);
            }
        } elseif ($backup->scope === 'website_files') {
            $websites[] = $this->files->handle($backup->website, $directory);
        } elseif ($backup->scope === 'website_database') {
            $databases[] = $this->databases->handle($backup->database, $directory);
        } else {
            throw new RuntimeException('Unknown backup scope.');
        }

        $manifest = [
            'account' => ['username' => $backup->user->username, 'system_username' => $backup->user->systemUsername],
            'websites' => $websites,
            'databases' => $databases,
            'created_with' => 'laranode '.(config('app.version') ?? 'dev'),
        ];
        file_put_contents($directory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($backup->scope === 'account') {
            $bundle = Process::timeout(3600)->path($directory)->run(['tar', '-czf', 'account.tar.gz', 'manifest.json', 'files', 'databases']);
            if ($bundle->failed()) {
                throw new RuntimeException('Unable to create the account backup bundle: '.$bundle->errorOutput());
            }
            $manifest['bundle'] = 'account.tar.gz';
            file_put_contents($directory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $size = collect($websites)->sum('bytes') + collect($databases)->sum('bytes') + filesize($directory.'/manifest.json');
        $backup->update(['path' => $relative, 'manifest' => $manifest, 'size_bytes' => $size]);
        $this->uploader->handle($backup, $directory);
    }

    private function prepareDirectory(string $relative): void
    {
        $result = Process::run([
            'sudo', config('laranode.laranode_bin_path').'/laranode-backup-storage.sh',
            config('laranode.backup_path'), 'prepare', $relative,
        ]);
        if ($result->failed()) {
            throw new RuntimeException('Unable to prepare backup storage: '.$result->errorOutput());
        }
    }
}
