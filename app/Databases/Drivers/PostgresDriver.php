<?php

namespace App\Databases\Drivers;

use App\Contracts\DatabaseEngineDriver;
use App\Databases\DatabaseSpec;
use App\Databases\DatabaseStats;
use App\Databases\EngineCapabilities;
use App\Models\Database;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

class CreateDatabaseException extends \Exception {}

class PostgresDriver implements DatabaseEngineDriver
{
    public function connectionName(): string
    {
        return 'pgsql_admin';
    }

    public function create(DatabaseSpec $spec): void
    {
        $name = $spec->name;
        $dbUser = $spec->dbUser;
        $password = $spec->password;
        $encoding = $spec->options['encoding'] ?? 'UTF8';
        $locale = $spec->options['locale'] ?? 'en_US.UTF-8';

        $this->assertSafeName($name);
        $this->assertSafeName($dbUser);
        $this->assertSafeName($encoding);
        $this->assertSafeLocale($locale);

        $script = $this->scriptPath();

        // Step 1: create the database
        $result = Process::run(['sudo', $script, 'create-db', $name, $encoding, $locale]);

        if ($result->exitCode() !== 0) {
            throw new CreateDatabaseException('Failed to create PostgreSQL database: '.$result->output().$result->errorOutput());
        }

        // Step 2: create the user — password via stdin, never argv
        $result = Process::input($password)->run(['sudo', $script, 'create-user', $dbUser]);

        if ($result->exitCode() !== 0) {
            // Rollback: drop the database we just created
            Process::run(['sudo', $script, 'drop-db', $name]);

            throw new CreateDatabaseException('Failed to create PostgreSQL user: '.$result->output().$result->errorOutput());
        }

        // Step 3: grant the user access to the database
        $result = Process::run(['sudo', $script, 'grant', $dbUser, $name]);

        if ($result->exitCode() !== 0) {
            throw new CreateDatabaseException('Failed to grant privileges: '.$result->output().$result->errorOutput());
        }
    }

    public function updatePassword(Database $database, string $newPassword): void
    {
        $dbUser = $database->db_user;
        $this->assertSafeName($dbUser);

        $script = $this->scriptPath();

        // Password via stdin, never in argv
        Process::input($newPassword)->run(['sudo', $script, 'update-user-password', $dbUser]);
    }

    public function updateOptions(Database $database, array $options): void
    {
        // PostgreSQL does not support altering charset/collation after creation.
        // This is intentionally a no-op.
    }

    public function delete(Database $database): void
    {
        $name = $database->name;
        $dbUser = $database->db_user;
        $this->assertSafeName($name);
        $this->assertSafeName($dbUser);

        $script = $this->scriptPath();

        Process::run(['sudo', $script, 'drop-db', $name]);
        Process::run(['sudo', $script, 'drop-user', $dbUser]);
    }

    public function stats(Database $database): DatabaseStats
    {
        $name = $database->name;

        // Scope pg_database_size to existing databases only — calling it on a
        // name that no longer exists raises SQLSTATE 3D000 and would crash the
        // whole listing. A missing db yields no row → size 0.
        $row = DB::connection('pgsql_admin')->selectOne(
            'SELECT pg_database_size(datname) AS size_bytes FROM pg_database WHERE datname = ?',
            [$name]
        );

        $sizeMb = $row && $row->size_bytes ? round($row->size_bytes / 1024 / 1024, 2) : 0.0;

        return new DatabaseStats(tableCount: 0, sizeMb: $sizeMb);
    }

    public function capabilities(): EngineCapabilities
    {
        return new EngineCapabilities(
            label: 'PostgreSQL',
            hasUsers: true,
            optionFields: ['encoding', 'locale'],
        );
    }

    /**
     * Defense-in-depth: assert name/user matches safe pattern before passing to sudo script.
     */
    private function assertSafeName(string $value): void
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
            throw new InvalidArgumentException("Unsafe identifier value: {$value}");
        }
    }

    /**
     * Defense-in-depth: assert a locale matches a safe pattern before passing to sudo script.
     */
    private function assertSafeLocale(string $value): void
    {
        if (! preg_match('/^[a-zA-Z0-9_.@-]+$/', $value)) {
            throw new InvalidArgumentException("Unsafe locale value: {$value}");
        }
    }

    private function scriptPath(): string
    {
        return config('laranode.laranode_bin_path').'/laranode-postgres.sh';
    }
}
