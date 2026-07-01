<?php

namespace App\Contracts\Backup;

interface BackupEngineDriver
{
    /**
     * Dump the given database to $tempFile using $cnfFile for credentials.
     *
     * @param  string  $dbName  Database name to dump.
     * @param  string  $dbUser  Database user.
     * @param  string  $cnfFile  Path to a temp .cnf file (already written, mode 0600) containing the password.
     * @param  callable  $emit  Callable(string $line): void — progress output.
     * @return string Path to the written dump file.
     *
     * @throws \RuntimeException on nonzero exit from the dump command.
     */
    public function dump(string $dbName, string $dbUser, string $cnfFile, callable $emit): string;
}
