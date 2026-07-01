<?php

namespace App\Services\Database;

use App\Contracts\DatabaseEngineDriver;
use App\Models\Database;
use Exception;

class DeleteDatabaseException extends Exception {}

class DeleteDatabaseService
{
    public function __construct(private DatabaseEngineDriver $driver) {}

    /**
     * Drop the database via the driver, then delete the Eloquent record.
     *
     * @throws DeleteDatabaseException
     */
    public function handle(Database $database): void
    {
        try {
            $this->driver->delete($database);
        } catch (Exception $e) {
            throw new DeleteDatabaseException('Failed to delete database: '.$e->getMessage(), 0, $e);
        }

        $database->delete();
    }
}
