<?php

namespace App\Services\MySQL;

use App\Models\Database;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class CreateDatabaseException extends Exception {}

class CreateDatabaseService
{
    public function __construct(private array $validated, private User $user) {}

    public function handle(): Database
    {
        $this->createMySQLDatabase();
        $this->createMySQLUser();
        
        return $this->createDatabaseRecord();
    }

    private function createMySQLDatabase(): void
    {
        $name = $this->validated['name'];
        $charset = $this->validated['charset'];
        $collation = $this->validated['collation'];

        try {
            DB::statement("CREATE DATABASE `$name` CHARACTER SET $charset COLLATE $collation");
        } catch (Exception $e) {
            throw new CreateDatabaseException('Failed to create MySQL database: ' . $e->getMessage());
        }
    }

    private function createMySQLUser(): void
    {
        $dbUser = $this->validated['db_user'];
        $dbPass = $this->validated['db_pass'];
        $name = $this->validated['name'];

        try {
            DB::statement("CREATE USER IF NOT EXISTS `$dbUser`@'localhost' IDENTIFIED BY '$dbPass'");
            DB::statement("GRANT ALL PRIVILEGES ON `$name`.* TO `$dbUser`@'localhost'");
            DB::statement("FLUSH PRIVILEGES");
        } catch (Exception $e) {
            // Rollback database creation if user creation fails
            DB::statement("DROP DATABASE IF EXISTS `{$this->validated['name']}`");
            throw new CreateDatabaseException('Failed to create MySQL user: ' . $e->getMessage());
        }
    }

    private function createDatabaseRecord(): Database
    {
        return Database::create([
            'name' => $this->validated['name'],
            'db_user' => $this->validated['db_user'],
            'db_password' => $this->validated['db_pass'],
            'charset' => $this->validated['charset'],
            'collation' => $this->validated['collation'],
            'user_id' => $this->user->id,
        ]);
    }
}
