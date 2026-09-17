<?php

namespace App\Actions\Backups;

use App\Models\Backup;
use RuntimeException;

class DownloadBackupAction
{
    public function execute(Backup $backup)
    {
        $file = match ($backup->scope) {
            'account' => $backup->manifest['bundle'] ?? 'account.tar.gz',
            'website_files' => $backup->manifest['websites'][0]['archive'] ?? null,
            'website_database' => $backup->manifest['databases'][0]['dump'] ?? null,
            default => null,
        };
        if (! $file) {
            throw new RuntimeException('This backup has no downloadable archive.');
        }

        $name = basename($file);
        $local = rtrim(config('laranode.backup_path'), '/').'/'.$backup->path.'/'.$file;
        if (! is_file($local) && $backup->destination?->driver === 's3') {
            return redirect()->away($backup->destination->disk()->temporaryUrl($backup->path.'/'.$file, now()->addMinutes(10)));
        }

        return response()->streamDownload(function () use ($backup, $file) {
            $local = rtrim(config('laranode.backup_path'), '/').'/'.$backup->path.'/'.$file;
            $stream = is_file($local)
                ? fopen($local, 'rb')
                : $backup->destination?->disk()->readStream($backup->path.'/'.$file);
            if (! $stream) {
                throw new RuntimeException('The backup archive could not be opened.');
            }
            fpassthru($stream);
            fclose($stream);
        }, $name, ['Content-Type' => 'application/gzip']);
    }
}
