<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Process;

class MysqlController extends Controller
{

    public function index(): \Inertia\Response
    {
        $user = auth()->user();
        $prefix = $user->username . '_';

        // collect databases for current user
        $databases = DB::select("SHOW DATABASES");
        $dbNames = collect($databases)
            ->map(fn ($row) => (array) $row)
            ->map(fn ($row) => reset($row))
            ->filter(fn ($name) => str_starts_with($name, $prefix))
            ->values();

        $items = [];
        foreach ($dbNames as $dbName) {
            // number of tables
            $tables = DB::select("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = ?", [$dbName]);
            $tableCount = (int) ($tables[0]->cnt ?? 0);

            // total size (data + index)
            $sizeRow = DB::selectOne("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = ?", [$dbName]);
            $sizeMb = (float) ($sizeRow->size_mb ?? 0);

            // default engine and charset from schema
            $schema = DB::selectOne("SELECT DEFAULT_CHARACTER_SET_NAME as charset, DEFAULT_COLLATION_NAME as collation FROM information_schema.schemata WHERE schema_name = ?", [$dbName]);

            $items[] = [
                'name' => $dbName,
                'user' => $user->username,
                'tables' => $tableCount,
                'sizeMb' => $sizeMb,
                'charset' => $schema->charset ?? null,
                'collation' => $schema->collation ?? null,
            ];
        }

        return Inertia::render('Mysql/Index', [
            'databases' => $items,
        ]);
    }

    public function rename(Request $request)
    {
        $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string'],
        ]);

        $user = $request->user();
        $prefix = $user->username . '_';

        $from = $request->string('from');
        $to = $request->string('to');

        if (!str_starts_with($from, $prefix) || !str_starts_with($to, $prefix)) {
            return back()->withErrors(['to' => 'Database names must start with ' . $prefix]);
        }

        // MySQL has no native RENAME DATABASE; approach: create new DB, move tables, drop old
        DB::statement("CREATE DATABASE IF NOT EXISTS `$to`");
        $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = ?", [$from]);
        foreach ($tables as $t) {
            $table = $t->table_name ?? reset((array)$t);
            DB::statement("RENAME TABLE `$from`.`$table` TO `$to`.`$table`");
        }
        DB::statement("DROP DATABASE `$from`");

        return back()->with('success', 'Database renamed successfully.');
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string'],
        ]);

        $user = $request->user();
        $prefix = $user->username . '_';
        $name = $request->string('name');

        if (!str_starts_with($name, $prefix)) {
            return back()->withErrors(['name' => 'Database name must start with ' . $prefix]);
        }

        DB::statement("DROP DATABASE IF EXISTS `$name`");

        return back()->with('success', 'Database deleted successfully.');
    }
}
