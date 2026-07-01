<?php

use App\Databases\DatabaseSpec;
use App\Databases\Drivers\MysqlDriver;
use App\Databases\EngineManager;
use App\Models\User;
use App\Services\Database\CreateDatabaseService;
use App\Services\Database\DeleteDatabaseService;
use App\Services\Database\GetDatabasesWithStatsService;

/**
 * Real MySQL integration tests.
 * Gate: LARANODE_SYSTEM_TESTS=1.
 *
 * Tests full create → index → delete lifecycle against the real MySQL instance
 * running in the local-dev container.
 */
beforeEach(function () {
    if (! env('LARANODE_SYSTEM_TESTS')) {
        $this->markTestSkipped('LARANODE_SYSTEM_TESTS not set — skipping system integration tests.');
    }
});

test('mysql full lifecycle: create index delete', function () {
    $user = User::factory()->create(['role' => 'user']);
    $admin = User::factory()->create(['role' => 'admin']);

    $uniqueSuffix = substr(md5(uniqid('', true)), 0, 8);
    $dbName = 'ln_test_'.$uniqueSuffix;
    $dbUser = 'ln_u_'.$uniqueSuffix;
    $password = 'TestPass_'.$uniqueSuffix.'!';

    $spec = new DatabaseSpec(
        name: $dbName,
        dbUser: $dbUser,
        password: $password,
        userId: $user->id,
        options: ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'],
    );

    $driver = new MysqlDriver;
    $createService = new CreateDatabaseService($driver);

    // ---- CREATE ----
    $record = $createService->handle($spec, 'mysql');

    expect($record->engine)->toBe('mysql');
    expect($record->charset)->toBe('utf8mb4');
    expect($record->collation)->toBe('utf8mb4_unicode_ci');

    $this->assertDatabaseHas('databases', ['name' => $dbName, 'engine' => 'mysql']);

    // ---- INDEX: non-admin sees own row, admin sees all ----
    $manager = app(EngineManager::class);

    $this->actingAs($user);
    GetDatabasesWithStatsService::clearCache();
    $statsService = new GetDatabasesWithStatsService($manager);
    $results = $statsService->handle();

    $own = collect($results)->firstWhere('name', $dbName);
    expect($own)->not->toBeNull();
    expect($own['engine'])->toBe('mysql');

    // Non-admin must NOT see admin's databases (created by a different user_id)
    $otherResults = collect($results)->where('user_id', $admin->id)->values();
    expect($otherResults->count())->toBe(0);

    // Admin sees all
    $this->actingAs($admin);
    GetDatabasesWithStatsService::clearCache();
    $adminService = new GetDatabasesWithStatsService($manager);
    $allResults = $adminService->handle();
    $found = collect($allResults)->firstWhere('name', $dbName);
    expect($found)->not->toBeNull();

    // ---- DELETE ----
    $deleteService = new DeleteDatabaseService($driver);
    $deleteService->handle($record);

    $this->assertDatabaseMissing('databases', ['name' => $dbName]);
})->group('system');
