<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('databases table has engine column after migration', function () {
    expect(Schema::hasColumn('databases', 'engine'))->toBeTrue();
});

test('row created without charset or collation has null values and engine postgres', function () {
    $id = DB::table('databases')->insertGetId([
        'name' => 'test_db_null_charset',
        'db_user' => 'test_user',
        'db_password' => encrypt('secret'),
        'charset' => null,
        'collation' => null,
        'engine' => 'postgres',
        'user_id' => App\Models\User::factory()->create()->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = DB::table('databases')->find($id);

    expect($row->charset)->toBeNull()
        ->and($row->collation)->toBeNull()
        ->and($row->engine)->toBe('postgres');
});

test('backfill query sets engine mysql on rows with null engine', function () {
    $userId = App\Models\User::factory()->create()->id;

    // Insert row with NULL engine directly, bypassing Eloquent
    $id = DB::table('databases')->insertGetId([
        'name' => 'test_db_backfill',
        'db_user' => 'test_user_bf',
        'db_password' => encrypt('secret'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'engine' => null,
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Run the backfill query (handles both NULL and '')
    DB::table('databases')
        ->where(fn ($q) => $q->whereNull('engine')->orWhere('engine', ''))
        ->update(['engine' => 'mysql']);

    $row = DB::table('databases')->find($id);

    expect($row->engine)->toBe('mysql');
});
