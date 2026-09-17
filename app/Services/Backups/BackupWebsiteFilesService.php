<?php

namespace App\Services\Backups;

use App\Models\Website;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class BackupWebsiteFilesService
{
    public function handle(Website $website, string $directory): array
    {
        $result = Process::timeout(3600)->run([
            'sudo', config('laranode.laranode_bin_path').'/laranode-backup-files.sh',
            $website->user->systemUsername, $website->url, $directory.'/files',
        ]);

        if ($result->failed()) {
            throw new RuntimeException('Website backup failed: '.$result->errorOutput());
        }

        $archive = 'files/'.$website->url.'.tar.gz';

        return [
            'url' => $website->url,
            'document_root' => $website->document_root,
            'php_version' => $website->phpVersion?->version,
            'archive' => $archive,
            'bytes' => filesize($directory.'/'.$archive) ?: 0,
        ];
    }
}
