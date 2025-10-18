<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Process;

class MysqlController extends Controller
{

    public function index(Request $request): \Inertia\Response
    {
        $user = $request->user();
        $prefix = $user->username . '_';

        // collect databases for current user
        $databases = DB::select("SHOW DATABASES");
        $dbNames = collect($databases)
            ->map(fn($row) => (array) $row)
            ->map(fn($row) => reset($row))
            ->filter(fn($name) => str_starts_with($name, $prefix))
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

    public function getCharsetsAndCollations()
    {
        // Get available character sets
        $charsets = DB::select("SHOW CHARACTER SET");
        $charsetData = collect($charsets)->map(function($charset) {
            return [
                'name' => $charset->Charset,
                'description' => $charset->Description,
                'default_collation' => $charset->{'Default collation'},
                'maxlen' => $charset->Maxlen,
            ];
        });

        // Get available collations
        $collations = DB::select("SHOW COLLATION");
        $collationData = collect($collations)->map(function($collation) {
            return [
                'name' => $collation->Collation,
                'charset' => $collation->Charset,
                'id' => $collation->Id,
                'default' => $collation->Default,
                'compiled' => $collation->Compiled,
                'sortlen' => $collation->Sortlen,
            ];
        });

        return response()->json([
            'charsets' => $charsetData,
            'collations' => $collationData,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string'],
            'db_user' => ['required', 'string'],
            'db_pass' => ['required', 'string'],
            'charset' => ['required', 'string'],
            'collation' => ['required', 'string'],
        ]);

        $user = $request->user();
        $prefix = $user->username . '_';

        $name = $request->string('name');
        $dbUser = $request->string('db_user');
        $dbPass = $request->string('db_pass');
        $charset = $request->string('charset');
        $collation = $request->string('collation');

        if (!str_starts_with($name, $prefix)) {
            return back()->withErrors(['name' => 'Database name must start with ' . $prefix]);
        }

        if (!str_starts_with($dbUser, $prefix)) {
            return back()->withErrors(['db_user' => 'Database username must start with ' . $prefix]);
        }

        // Create database with charset and collation
        DB::statement("CREATE DATABASE `$name` CHARACTER SET $charset COLLATE $collation");

        // Create user (if not exists) and grant all privileges on the new DB
        DB::statement("CREATE USER IF NOT EXISTS `$dbUser`@'localhost' IDENTIFIED BY '$dbPass'");
        DB::statement("GRANT ALL PRIVILEGES ON `$name`.* TO `$dbUser`@'localhost'");
        DB::statement("FLUSH PRIVILEGES");

        return redirect()->route('mysql.index')->with('success', 'Database created successfully.');
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string'],
            'charset' => ['required', 'string'],
            'collation' => ['required', 'string'],
        ]);

        $user = $request->user();
        $prefix = $user->username . '_';

        $name = $request->string('name');
        $charset = $request->string('charset');
        $collation = $request->string('collation');

        if (!str_starts_with($name, $prefix)) {
            return back()->withErrors(['name' => 'Database name must start with ' . $prefix]);
        }

        // Check if database exists
        $databases = DB::select("SHOW DATABASES");
        $dbNames = collect($databases)
            ->map(fn($row) => (array) $row)
            ->map(fn($row) => reset($row))
            ->filter(fn($dbName) => str_starts_with($dbName, $prefix))
            ->values();

        if (!$dbNames->contains($name)) {
            return back()->withErrors(['name' => 'Database not found or access denied']);
        }

        // Update database charset and collation
        DB::statement("ALTER DATABASE `$name` CHARACTER SET $charset COLLATE $collation");

        return redirect()->route('mysql.index')->with('success', 'Database updated successfully.');
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
