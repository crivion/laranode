<?php

use App\Contracts\DatabaseEngineDriver;
use App\Databases\DatabaseSpec;
use App\Databases\DatabaseStats;
use App\Databases\EngineCapabilities;
use App\Databases\EngineManager;
use App\Models\Database;
use App\Models\User;
use App\Services\Database\CreateDatabaseService;
use App\Services\Database\DeleteDatabaseService;
use App\Services\Database\GetDatabasesWithStatsService;
use App\Services\Database\UpdateDatabaseService;

beforeEach(function () {
    // Reset the per-request static stats cache so it never leaks across tests
    GetDatabasesWithStatsService::clearCache();
});

/**
 * Build a stub driver that records calls and returns predictable values.
 *
 * Pass in an array by reference; the driver will append to it on each call.
 */
function makeStubDriver(array &$calls): DatabaseEngineDriver
{
    return new class($calls) implements DatabaseEngineDriver
    {
        public function __construct(private array &$calls) {}

        public function connectionName(): string
        {
            return 'mysql_admin';
        }

        public function create(DatabaseSpec $spec): void
        {
            $this->calls[] = ['method' => 'create', 'spec' => $spec];
        }

        public function updatePassword(Database $database, string $newPassword): void
        {
            $this->calls[] = ['method' => 'updatePassword', 'database' => $database, 'password' => $newPassword];
        }

        public function updateOptions(Database $database, array $options): void
        {
            $this->calls[] = ['method' => 'updateOptions', 'database' => $database, 'options' => $options];
        }

        public function delete(Database $database): void
        {
            $this->calls[] = ['method' => 'delete', 'database' => $database];
        }

        public function stats(Database $database): DatabaseStats
        {
            $this->calls[] = ['method' => 'stats', 'database' => $database];

            return new DatabaseStats(tableCount: 2, sizeMb: 0.5);
        }

        public function capabilities(): EngineCapabilities
        {
            return new EngineCapabilities(label: 'MySQL', hasUsers: true, optionFields: ['charset', 'collation']);
        }
    };
}

// ──────────────────────────────────────────────────────────────────
// Test: CreateDatabaseService
// ──────────────────────────────────────────────────────────────────

test('CreateDatabaseService calls driver create then persists Database row with correct engine', function () {
    $calls = [];
    $driver = makeStubDriver($calls);

    $user = User::factory()->create();

    $spec = new DatabaseSpec(
        name: 'mydb_ln',
        dbUser: 'myuser_ln',
        password: 'secret123',
        userId: $user->id,
        options: ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'],
    );

    $service = new CreateDatabaseService($driver);
    $record = $service->handle($spec, 'mysql');

    // Driver was called with the spec
    $createCalls = array_filter($calls, fn ($c) => $c['method'] === 'create');
    expect($createCalls)->not->toBeEmpty();
    expect(array_values($createCalls)[0]['spec'])->toBe($spec);

    // Eloquent record was persisted
    expect($record)->toBeInstanceOf(Database::class);
    expect($record->exists)->toBeTrue();
    expect($record->engine)->toBe('mysql');
    expect($record->name)->toBe('mydb_ln');
    expect($record->db_user)->toBe('myuser_ln');
    expect($record->charset)->toBe('utf8mb4');
    expect($record->collation)->toBe('utf8mb4_unicode_ci');
    expect($record->user_id)->toBe($user->id);

    $this->assertDatabaseHas('databases', ['name' => 'mydb_ln', 'engine' => 'mysql']);

    // Password is stored encrypted but readable as plaintext via cast/accessor
    expect($record->decryptedPassword)->toBe('secret123');
});

// ──────────────────────────────────────────────────────────────────
// Test: UpdateDatabaseService
// ──────────────────────────────────────────────────────────────────

test('UpdateDatabaseService calls driver updatePassword and updateOptions then updates DB row', function () {
    $calls = [];
    $driver = makeStubDriver($calls);

    $user = User::factory()->create();
    $database = Database::factory()->create([
        'engine' => 'mysql',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'db_password' => 'old_pass',
        'user_id' => $user->id,
    ]);

    $service = new UpdateDatabaseService($driver);
    $service->handle($database, [
        'db_password' => 'new_pass_789',
        'charset' => 'utf8',
        'collation' => 'utf8_general_ci',
    ]);

    // driver->updatePassword called
    $pwCalls = array_filter($calls, fn ($c) => $c['method'] === 'updatePassword');
    expect($pwCalls)->not->toBeEmpty();
    expect(array_values($pwCalls)[0]['password'])->toBe('new_pass_789');

    // driver->updateOptions called
    $optCalls = array_filter($calls, fn ($c) => $c['method'] === 'updateOptions');
    expect($optCalls)->not->toBeEmpty();

    // Eloquent row updated
    $database->refresh();
    expect($database->charset)->toBe('utf8');
    expect($database->collation)->toBe('utf8_general_ci');
});

// ──────────────────────────────────────────────────────────────────
// Test: DeleteDatabaseService
// ──────────────────────────────────────────────────────────────────

test('DeleteDatabaseService calls driver delete then removes Database row from DB', function () {
    $calls = [];
    $driver = makeStubDriver($calls);

    $user = User::factory()->create();
    $database = Database::factory()->create([
        'engine' => 'mysql',
        'user_id' => $user->id,
    ]);
    $dbId = $database->id;
    $dbName = $database->name;

    $service = new DeleteDatabaseService($driver);
    $service->handle($database);

    // driver->delete called
    $deleteCalls = array_filter($calls, fn ($c) => $c['method'] === 'delete');
    expect($deleteCalls)->not->toBeEmpty();

    // Eloquent row is gone
    $this->assertDatabaseMissing('databases', ['id' => $dbId, 'name' => $dbName]);
});

// ──────────────────────────────────────────────────────────────────
// Test: GetDatabasesWithStatsService — scopeMine admin + non-admin
// ──────────────────────────────────────────────────────────────────

test('GetDatabasesWithStatsService non-admin sees only own rows and admin sees all rows', function () {
    $calls = [];
    $driver = makeStubDriver($calls);

    // Bind manager so both admin and user calls use our stub driver
    $manager = Mockery::mock(EngineManager::class);
    $manager->shouldReceive('for')->andReturn($driver);
    app()->instance(EngineManager::class, $manager);

    $admin = User::factory()->create(['role' => 'admin']);
    $user1 = User::factory()->create(['role' => 'user']);
    $user2 = User::factory()->create(['role' => 'user']);

    Database::factory()->create(['user_id' => $user1->id, 'engine' => 'mysql']);
    Database::factory()->create(['user_id' => $user1->id, 'engine' => 'mysql']);
    Database::factory()->create(['user_id' => $user2->id, 'engine' => 'mysql']);

    // Non-admin (user1) only sees their 2 rows
    $this->actingAs($user1);
    $service = new GetDatabasesWithStatsService($manager);
    $user1Results = $service->handle();
    expect($user1Results)->toHaveCount(2);

    // Admin sees all 3 rows
    $this->actingAs($admin);
    $adminService = new GetDatabasesWithStatsService($manager);
    $adminResults = $adminService->handle();
    expect($adminResults)->toHaveCount(3);
});
