<?php

namespace App\Backup;

use App\Backup\Drivers\MysqlBackupDriver;
use App\Backup\Drivers\PostgresBackupDriver;
use App\Contracts\Backup\BackupEngineDriver;

class BackupEngineManager
{
    /**
     * Resolve the backup driver for the given engine key.
     *
     * Unknown engine keys fall back to MysqlBackupDriver (default/legacy).
     */
    public function for(?string $engine): BackupEngineDriver
    {
        return match ($engine) {
            'postgres' => new PostgresBackupDriver,
            default => new MysqlBackupDriver,
        };
    }
}
