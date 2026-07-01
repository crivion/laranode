<?php

namespace App\Services\Database;

use App\Contracts\DatabaseEngineDriver;
use App\Models\Database;
use Exception;

class UpdateDatabaseException extends Exception {}

class UpdateDatabaseService
{
    public function __construct(private DatabaseEngineDriver $driver) {}

    /**
     * Update password and/or engine-specific options, then persist to the model.
     *
     * @throws UpdateDatabaseException
     */
    public function handle(Database $database, array $validated): void
    {
        try {
            if (! empty($validated['db_password'])) {
                $this->driver->updatePassword($database, $validated['db_password']);
            }

            $options = array_filter([
                'charset' => $validated['charset'] ?? null,
                'collation' => $validated['collation'] ?? null,
                'encoding' => $validated['encoding'] ?? null,
                'locale' => $validated['locale'] ?? null,
            ], fn ($v) => $v !== null);

            if (! empty($options)) {
                $this->driver->updateOptions($database, $options);
            }
        } catch (Exception $e) {
            throw new UpdateDatabaseException('Failed to update database: '.$e->getMessage(), 0, $e);
        }

        $update = [];

        if (! empty($validated['db_password'])) {
            $update['db_password'] = $validated['db_password'];
        }

        if (isset($validated['charset'])) {
            $update['charset'] = $validated['charset'];
        }

        if (isset($validated['collation'])) {
            $update['collation'] = $validated['collation'];
        }

        if (! empty($update)) {
            $database->update($update);
        }
    }
}
