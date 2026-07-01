<?php

namespace App\Actions\Backup;

use App\Models\Website;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class TarFilesAction
{
    /**
     * Archive the website root via laranode-backup-files.sh, return the archive path.
     */
    public function execute(Website $website, string $tempPath, callable $emit): string
    {
        $binPath = config('laranode.laranode_bin_path');
        $sysUser = $website->user->systemUsername ?? ($website->user->username.'_ln');

        $emit("Archiving files for '{$website->url}'...");

        $result = Process::run([
            'sudo',
            $binPath.'/laranode-backup-files.sh',
            $website->websiteRoot,
            $tempPath,
            $sysUser,
        ]);

        if ($result->exitCode() !== 0) {
            throw new RuntimeException('File archive failed: '.$result->errorOutput());
        }

        $emit('File archive completed.');

        return $tempPath;
    }
}
