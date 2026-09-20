<?php

namespace App\Actions\Filemanager;

use Illuminate\Support\Facades\Process;
use League\Flysystem\FilesystemException;
use League\Flysystem\WhitespacePathNormalizer;
use RuntimeException;

class EnsurePermissionsAction
{
    public function execute(string $path, string $systemUser): void
    {
        try {
            $path = (new WhitespacePathNormalizer)->normalizePath($path);
        } catch (FilesystemException $exception) {
            throw new RuntimeException('Invalid permission path', previous: $exception);
        }

        $result = Process::run([
            'sudo',
            config('laranode.laranode_bin_path').'/laranode-file-permissions.sh',
            $path,
            $systemUser,
        ]);

        if ($result->failed()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'Unable to set file permissions');
        }
    }
}
