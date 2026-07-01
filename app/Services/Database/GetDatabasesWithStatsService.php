<?php

namespace App\Services\Database;

use App\Databases\DatabaseStats;
use App\Databases\EngineManager;
use App\Models\Database;
use Exception;

class GetDatabasesWithStatsException extends Exception {}

class GetDatabasesWithStatsService
{
    /**
     * Per-request static cache keyed by database ID to avoid duplicate stats() calls.
     *
     * @var array<int, DatabaseStats>
     */
    private static array $statsCache = [];

    public function __construct(private EngineManager $manager) {}

    /**
     * Return all databases visible to the authenticated user, each decorated with stats.
     *
     * Admin users see all rows; non-admin users see only their own rows (via scopeMine).
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(): array
    {
        $databases = Database::mine()->get();

        $items = [];

        foreach ($databases as $database) {
            $items[] = $this->buildItem($database);
        }

        return $items;
    }

    private function buildItem(Database $database): array
    {
        $stats = $this->getStats($database);

        return [
            'id' => $database->id,
            'name' => $database->name,
            'db_user' => $database->db_user,
            'engine' => $database->engine,
            'charset' => $database->charset,
            'collation' => $database->collation,
            'tables' => $stats->tableCount,
            'sizeMb' => $stats->sizeMb,
            'user_id' => $database->user_id,
        ];
    }

    private function getStats(Database $database): DatabaseStats
    {
        if (isset(self::$statsCache[$database->id])) {
            return self::$statsCache[$database->id];
        }

        $driver = $this->manager->for($database->engine);
        $stats = $driver->stats($database);

        self::$statsCache[$database->id] = $stats;

        return $stats;
    }

    /**
     * Clear the per-request static cache (used in tests to avoid state leakage).
     */
    public static function clearCache(): void
    {
        self::$statsCache = [];
    }
}
