<?php

namespace App\Services\Database;

use App\Contracts\DatabaseEngineDriver;
use App\Databases\DatabaseSpec;
use App\Models\Database;
use Exception;

class CreateDatabaseException extends Exception {}

class CreateDatabaseService
{
    public function __construct(private DatabaseEngineDriver $driver) {}

    /**
     * Create the database via the driver, then persist the Eloquent record.
     *
     * @throws CreateDatabaseException
     */
    public function handle(DatabaseSpec $spec, string $engine): Database
    {
        try {
            $this->driver->create($spec);
        } catch (Exception $e) {
            throw new CreateDatabaseException('Failed to create database: '.$e->getMessage(), 0, $e);
        }

        return Database::create([
            'name' => $spec->name,
            'db_user' => $spec->dbUser,
            'db_password' => $spec->password,
            'charset' => $spec->options['charset'] ?? null,
            'collation' => $spec->options['collation'] ?? null,
            'engine' => $engine,
            'user_id' => $spec->userId,
        ]);
    }
}
