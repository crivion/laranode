<?php

namespace App\Services\Backups;

use App\Actions\Backups\BuildDefaultsFileAction;
use App\Models\Database;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class BackupDatabaseService
{
    public function __construct(private BuildDefaultsFileAction $defaultsFile) {}

    public function handle(Database $database, string $directory): array
    {
        $defaults = $this->defaultsFile->execute();
        try {
            $result = Process::timeout(3600)->run([
                'sudo', config('laranode.laranode_bin_path').'/laranode-backup-db.sh',
                $database->name, $directory.'/databases', $defaults,
            ]);
        } finally {
            @unlink($defaults);
        }

        if ($result->failed()) {
            throw new RuntimeException('Database backup failed: '.$result->errorOutput());
        }

        $dump = 'databases/'.$database->name.'.sql.gz';

        return [
            'name' => $database->name,
            'db_user' => $database->db_user,
            'website' => $database->website?->url,
            'charset' => $database->charset,
            'collation' => $database->collation,
            'dump' => $dump,
            'bytes' => filesize($directory.'/'.$dump) ?: 0,
        ];
    }
}
