<?php

namespace App\Actions\Backup;

use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;

class UploadToStorageAction
{
    /**
     * Stream a local temp file onto the given disk at $remotePath.
     * Returns $remotePath on success.
     *
     * @throws RuntimeException if the file cannot be read or the upload fails.
     */
    public function execute(string $localPath, string $remotePath, Filesystem $disk, callable $emit): string
    {
        $emit("Uploading backup to storage at '{$remotePath}'...");

        $stream = fopen($localPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Cannot open local file for upload: {$localPath}");
        }

        try {
            $disk->writeStream($remotePath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $emit('Upload completed.');

        return $remotePath;
    }
}
