<?php

namespace App\Contracts;

use App\Databases\DatabaseSpec;
use App\Databases\DatabaseStats;
use App\Databases\EngineCapabilities;
use App\Models\Database;

interface DatabaseEngineDriver
{
    /**
     * The named DB connection this driver uses (e.g. 'mysql_admin', 'pgsql_admin').
     */
    public function connectionName(): string;

    /**
     * Create the database and its associated user, then grant privileges.
     */
    public function create(DatabaseSpec $spec): void;

    /**
     * Update the database user's password.
     */
    public function updatePassword(Database $database, string $newPassword): void;

    /**
     * Update engine-specific options (charset/collation, encoding/locale, etc.).
     */
    public function updateOptions(Database $database, array $options): void;

    /**
     * Drop the database and its associated user.
     */
    public function delete(Database $database): void;

    /**
     * Return runtime statistics (table count, size) for the given database.
     */
    public function stats(Database $database): DatabaseStats;

    /**
     * Return static metadata describing this engine's capabilities and supported option fields.
     */
    public function capabilities(): EngineCapabilities;
}
