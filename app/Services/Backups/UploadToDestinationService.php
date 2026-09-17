<?php

namespace App\Services\Backups;

use App\Models\Backup;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class UploadToDestinationService
{
    public function handle(Backup $backup, string $localDirectory): void
    {
        if (! $backup->destination || $backup->destination->driver === 'local') {
            return;
        }

        $disk = $backup->destination->disk();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($localDirectory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $relative = $backup->path.'/'.substr($file->getPathname(), strlen($localDirectory) + 1);
            $stream = fopen($file->getPathname(), 'rb');
            try {
                if (! $disk->writeStream($relative, $stream)) {
                    throw new RuntimeException("Failed to upload {$relative}.");
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }
    }
}
