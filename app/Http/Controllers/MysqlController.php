<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Process;
use App\Models\Database;

class MysqlController extends Controller
{

    public function index(Request $request): \Inertia\Response
    {
        $user = $request->user();

        // Get databases from our model for the current user
        $databases = Database::where('user_id', $user->id)->get();

        $items = [];
        foreach ($databases as $database) {
            // Get additional info from MySQL
            $dbName = $database->name;
            
            // number of tables
            $tables = DB::select("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = ?", [$dbName]);
            $tableCount = (int) ($tables[0]->cnt ?? 0);

            // total size (data + index)
            $sizeRow = DB::selectOne("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = ?", [$dbName]);
            $sizeMb = (float) ($sizeRow->size_mb ?? 0);

            $items[] = [
                'id' => $database->id,
                'name' => $database->name,
                'user' => $user->username,
                'db_user' => $database->db_user,
                'tables' => $tableCount,
                'sizeMb' => $sizeMb,
                'charset' => $database->charset,
                'collation' => $database->collation,
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

        // Create database record in our model
        Database::create([
            'name' => $name,
            'db_user' => $dbUser,
            'db_password' => $dbPass,
            'charset' => $charset,
            'collation' => $collation,
            'user_id' => $user->id,
        ]);

        return redirect()->route('mysql.index')->with('success', 'Database created successfully.');
    }

    public function update(Request $request)
    {
        $request->validate([
            'id' => ['required', 'integer'],
            'charset' => ['required', 'string'],
            'collation' => ['required', 'string'],
            'db_password' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $databaseId = $request->integer('id');
        $charset = $request->string('charset');
        $collation = $request->string('collation');
        $newPassword = $request->string('db_password');

        // Find the database record
        $database = Database::where('id', $databaseId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $name = $database->name;

        // Update database charset and collation in MySQL
        DB::statement("ALTER DATABASE `$name` CHARACTER SET $charset COLLATE $collation");

        // Update the database record
        $updateData = [
            'charset' => $charset,
            'collation' => $collation,
        ];

        // Update password if provided
        if ($newPassword) {
            $updateData['db_password'] = $newPassword;
            
            // Update MySQL user password
            DB::statement("ALTER USER `{$database->db_user}`@'localhost' IDENTIFIED BY '$newPassword'");
            DB::statement("FLUSH PRIVILEGES");
        }

        $database->update($updateData);

        return redirect()->route('mysql.index')->with('success', 'Database charset and collation updated successfully.');
    }


    public function destroy(Request $request)
    {
        $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $databaseId = $request->integer('id');

        // Find the database record
        $database = Database::where('id', $databaseId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $name = $database->name;
        $dbUser = $database->db_user;

        // Drop the MySQL database
        DB::statement("DROP DATABASE IF EXISTS `$name`");

        // Drop the MySQL user
        DB::statement("DROP USER IF EXISTS `$dbUser`@'localhost'");
        DB::statement("FLUSH PRIVILEGES");

        // Delete the database record
        $database->delete();

        return redirect()->route('mysql.index')->with('success', 'Database deleted successfully.');
    }
}
