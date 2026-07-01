<?php

use Illuminate\Support\Facades\DB;

test('backfill query sets engine mysql on rows with null engine', function () {
    $userId = App\Models\User::factory()->create()->id;

    // Insert a row with NULL engine directly, bypassing Eloquent
    $id = DB::table('databases')->insertGetId([
        'name' => 'bf_test_db',
        'db_user' => 'bf_test_user',
        'db_password' => encrypt('secret'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'engine' => null,
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Run the canonical backfill query (handles both NULL and empty string)
    DB::table('databases')
        ->where(fn ($q) => $q->whereNull('engine')->orWhere('engine', ''))
        ->update(['engine' => 'mysql']);

    $row = DB::table('databases')->find($id);

    expect($row->engine)->toBe('mysql');
});
