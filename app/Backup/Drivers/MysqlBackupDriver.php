<?php

namespace App\Backup\Drivers;

use App\Contracts\Backup\BackupEngineDriver;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class MysqlBackupDriver implements BackupEngineDriver
{
    public function dump(string $dbName, string $dbUser, string $cnfFile, callable $emit): string
    {
        $binPath = config('laranode.laranode_bin_path');
        $tempFile = sys_get_temp_dir().'/laranode-db-backup-'.uniqid().'.sql.gz';

        $emit("Dumping MySQL database '{$dbName}'...");

        $result = Process::run([
            'sudo',
            $binPath.'/laranode-db-backup.sh',
            'mysql',
            $dbName,
            $dbUser,
            $cnfFile,
            $tempFile,
        ]);

        if ($result->exitCode() !== 0) {
            throw new RuntimeException('DB dump failed: '.$result->errorOutput());
        }

        $emit('Database dump completed.');

        return $tempFile;
    }
}
