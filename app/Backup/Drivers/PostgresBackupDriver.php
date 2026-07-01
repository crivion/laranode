<?php

namespace App\Backup\Drivers;

use App\Contracts\Backup\BackupEngineDriver;
use LogicException;

class PostgresBackupDriver implements BackupEngineDriver
{
    public function dump(string $dbName, string $dbUser, string $cnfFile, callable $emit): string
    {
        throw new LogicException('PostgreSQL driver not yet implemented');
    }
}
