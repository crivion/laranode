<?php

namespace App\Actions\MySQL;

use App\Models\Database;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GetDatabasesWithStatsAction
{
    public function __construct(private User $user) {}

    public function execute(): array
    {
        $databases = Database::mine()->get();
        $items = [];

        foreach ($databases as $database) {
            $items[] = $this->buildDatabaseItem($database);
        }

        return $items;
    }

    private function buildDatabaseItem(Database $database): array
    {
        $dbName = $database->name;

        // Get table count
        $tableCount = $this->getTableCount($dbName);

        // Get database size
        $sizeMb = $this->getDatabaseSize($dbName);

        return [
            'id' => $database->id,
            'name' => $database->name,
            'user' => $this->user->username,
            'db_user' => $database->db_user,
            'tables' => $tableCount,
            'sizeMb' => $sizeMb,
            'charset' => $database->charset,
            'collation' => $database->collation,
        ];
    }

    private function getTableCount(string $dbName): int
    {
        $tables = DB::select(
            "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = ?",
            [$dbName]
        );

        return (int) ($tables[0]->cnt ?? 0);
    }

    private function getDatabaseSize(string $dbName): float
    {
        $sizeRow = DB::selectOne(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
             FROM information_schema.tables
             WHERE table_schema = ?",
            [$dbName]
        );

        return (float) ($sizeRow->size_mb ?? 0);
    }
}
