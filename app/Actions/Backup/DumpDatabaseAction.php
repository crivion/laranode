<?php

namespace App\Actions\Backup;

use App\Backup\BackupEngineManager;
use App\Models\Database;

class DumpDatabaseAction
{
    public function __construct(private BackupEngineManager $engineManager) {}

    /**
     * Write a temp .cnf (mode 0600), call the engine driver, return the dump path.
     * The .cnf is deleted in finally regardless of success/failure.
     */
    public function execute(Database $database, string $tempPath, callable $emit): string
    {
        $cnfPath = sys_get_temp_dir().'/laranode-db-'.uniqid().'.cnf';

        // Set umask so the file is created 0600 from the start, eliminating the
        // TOCTOU window between file_put_contents() and a subsequent chmod().
        $prevUmask = umask(0177);
        file_put_contents($cnfPath, "[client]\npassword={$database->db_password}\n");
        umask($prevUmask);

        try {
            $driver = $this->engineManager->for($database->engine ?? 'mysql');

            return $driver->dump($database->name, $database->db_user, $cnfPath, $emit);
        } finally {
            if (file_exists($cnfPath)) {
                unlink($cnfPath);
            }
        }
    }
}
