<?php

namespace App\Services\MySQL;

use App\Models\Database;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateDatabaseException extends Exception {}

class UpdateDatabaseService
{
    public function __construct(private Database $database, private array $validated) {}

    public function handle(): void
    {
        $this->updateMySQLDatabase();
        $this->updateMySQLUserPassword();
        $this->updateDatabaseRecord();
    }

    private function updateMySQLDatabase(): void
    {
        $name = $this->database->name;
        $charset = $this->validated['charset'];
        $collation = $this->validated['collation'];

        try {
            DB::statement("ALTER DATABASE `$name` CHARACTER SET $charset COLLATE $collation");
        } catch (Exception $e) {
            throw new UpdateDatabaseException('Failed to update MySQL database charset/collation: ' . $e->getMessage());
        }
    }

    private function updateMySQLUserPassword(): void
    {
        if (!isset($this->validated['db_password']) || empty($this->validated['db_password'])) {
            return;
        }

        $dbUser = $this->database->db_user;
        $newPassword = $this->validated['db_password'];

        try {
            DB::statement("ALTER USER `$dbUser`@'localhost' IDENTIFIED BY '$newPassword'");
            DB::statement("FLUSH PRIVILEGES");
        } catch (Exception $e) {
            throw new UpdateDatabaseException('Failed to update MySQL user password: ' . $e->getMessage());
        }
    }

    private function updateDatabaseRecord(): void
    {
        $updateData = [
            'charset' => $this->validated['charset'],
            'collation' => $this->validated['collation'],
        ];

        if (isset($this->validated['db_password']) && !empty($this->validated['db_password'])) {
            $updateData['db_password'] = $this->validated['db_password'];
        }

        $this->database->update($updateData);
    }
}
