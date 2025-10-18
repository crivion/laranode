<?php

namespace App\Services\MySQL;

use App\Models\Database;
use Exception;
use Illuminate\Support\Facades\DB;

class DeleteDatabaseException extends Exception {}

class DeleteDatabaseService
{
    public function __construct(private Database $database) {}

    public function handle(): void
    {
        $this->dropMySQLDatabase();
        $this->dropMySQLUser();
        $this->deleteDatabaseRecord();
    }

    private function dropMySQLDatabase(): void
    {
        $name = $this->database->name;

        try {
            DB::statement("DROP DATABASE IF EXISTS `$name`");
        } catch (Exception $e) {
            throw new DeleteDatabaseException('Failed to drop MySQL database: ' . $e->getMessage());
        }
    }

    private function dropMySQLUser(): void
    {
        $dbUser = $this->database->db_user;

        try {
            DB::statement("DROP USER IF EXISTS `$dbUser`@'localhost'");
            DB::statement("FLUSH PRIVILEGES");
        } catch (Exception $e) {
            throw new DeleteDatabaseException('Failed to drop MySQL user: ' . $e->getMessage());
        }
    }

    private function deleteDatabaseRecord(): void
    {
        $this->database->delete();
    }
}
