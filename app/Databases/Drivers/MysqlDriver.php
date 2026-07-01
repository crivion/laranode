<?php

namespace App\Databases\Drivers;

use App\Contracts\DatabaseEngineDriver;
use App\Databases\DatabaseSpec;
use App\Databases\DatabaseStats;
use App\Databases\EngineCapabilities;
use App\Models\Database;
use Exception;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MysqlDriver implements DatabaseEngineDriver
{
    public function connectionName(): string
    {
        return 'mysql_admin';
    }

    public function create(DatabaseSpec $spec): void
    {
        $charset = $spec->options['charset'] ?? 'utf8mb4';
        $collation = $spec->options['collation'] ?? 'utf8mb4_unicode_ci';

        $this->assertSafeIdentifier($charset);
        $this->assertSafeIdentifier($collation);

        $name = $spec->name;
        $dbUser = $spec->dbUser;
        $password = $spec->password;

        $this->assertSafeIdentifier($name);
        $this->assertSafeIdentifier($dbUser);

        $conn = DB::connection($this->connectionName());

        try {
            $conn->statement("CREATE DATABASE `{$name}` CHARACTER SET {$charset} COLLATE {$collation}");
        } catch (Exception $e) {
            throw new \RuntimeException('Failed to create MySQL database: '.$e->getMessage(), 0, $e);
        }

        try {
            $conn->statement("CREATE USER IF NOT EXISTS `{$dbUser}`@'localhost' IDENTIFIED BY ?", [$password]);
            $conn->statement("GRANT ALL PRIVILEGES ON `{$name}`.* TO `{$dbUser}`@'localhost'");
            $conn->statement('FLUSH PRIVILEGES');
        } catch (Exception $e) {
            // Rollback database creation if user creation fails
            $conn->statement("DROP DATABASE IF EXISTS `{$name}`");
            throw new \RuntimeException('Failed to create MySQL user: '.$e->getMessage(), 0, $e);
        }
    }

    public function updatePassword(Database $database, string $newPassword): void
    {
        $dbUser = $database->db_user;
        $this->assertSafeIdentifier($dbUser);
        $conn = DB::connection($this->connectionName());

        $conn->statement("ALTER USER `{$dbUser}`@'localhost' IDENTIFIED BY ?", [$newPassword]);
        $conn->statement('FLUSH PRIVILEGES');
    }

    public function updateOptions(Database $database, array $options): void
    {
        $name = $database->name;
        $charset = $options['charset'] ?? $database->charset;
        $collation = $options['collation'] ?? $database->collation;

        $this->assertSafeIdentifier($name);
        $this->assertSafeIdentifier($charset);
        $this->assertSafeIdentifier($collation);

        $conn = DB::connection($this->connectionName());
        $conn->statement("ALTER DATABASE `{$name}` CHARACTER SET {$charset} COLLATE {$collation}");
    }

    public function delete(Database $database): void
    {
        $name = $database->name;
        $dbUser = $database->db_user;
        $this->assertSafeIdentifier($name);
        $this->assertSafeIdentifier($dbUser);
        $conn = DB::connection($this->connectionName());

        $conn->statement("DROP DATABASE IF EXISTS `{$name}`");
        $conn->statement("DROP USER IF EXISTS `{$dbUser}`@'localhost'");
        $conn->statement('FLUSH PRIVILEGES');
    }

    public function stats(Database $database): DatabaseStats
    {
        $name = $database->name;
        $conn = DB::connection($this->connectionName());

        $countRow = $conn->selectOne(
            'SELECT COUNT(*) AS table_count FROM information_schema.tables WHERE table_schema = ?',
            [$name]
        );

        $sizeRow = $conn->selectOne(
            'SELECT SUM(data_length + index_length) AS size_bytes FROM information_schema.tables WHERE table_schema = ?',
            [$name]
        );

        $tableCount = $countRow ? (int) $countRow->table_count : 0;
        $sizeMb = $sizeRow && $sizeRow->size_bytes ? round($sizeRow->size_bytes / 1024 / 1024, 2) : 0.0;

        return new DatabaseStats(tableCount: $tableCount, sizeMb: $sizeMb);
    }

    public function capabilities(): EngineCapabilities
    {
        return new EngineCapabilities(
            label: 'MySQL',
            hasUsers: true,
            optionFields: ['charset', 'collation'],
        );
    }

    /**
     * Defense-in-depth: assert identifier matches safe pattern before SQL interpolation.
     */
    protected function assertSafeIdentifier(string $value): void
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
            throw new InvalidArgumentException("Unsafe identifier value: {$value}");
        }
    }
}
