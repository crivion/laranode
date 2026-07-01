<?php

use App\Databases\EngineManager;
use App\Models\Database;
use App\Models\User;
use App\Services\Database\GetDatabasesWithStatsService;

beforeEach(function () {
    // Reset EngineManager singleton so each test gets a fresh instance
    app()->forgetInstance(EngineManager::class);
    // Reset the per-request static stats cache so it never leaks across tests
    GetDatabasesWithStatsService::clearCache();
});

// ──────────────────────────────────────────────────────────────────
// Helper: fake EngineManager::available() to return a known engine list
// ──────────────────────────────────────────────────────────────────

function fakeEngineManagerWithMysql(): void
{
    $manager = Mockery::mock(EngineManager::class);
    $manager->shouldReceive('available')->andReturn(['mysql' => 'mysql']);
    $manager->shouldReceive('for')->with('mysql')->andReturn(
        new class implements \App\Contracts\DatabaseEngineDriver
        {
            public function connectionName(): string
            {
                return 'mysql_admin';
            }

            public function create(\App\Databases\DatabaseSpec $spec): void {}

            public function updatePassword(\App\Models\Database $database, string $newPassword): void {}

            public function updateOptions(\App\Models\Database $database, array $options): void {}

            public function delete(\App\Models\Database $database): void {}

            public function stats(\App\Models\Database $database): \App\Databases\DatabaseStats
            {
                return new \App\Databases\DatabaseStats(tableCount: 0, sizeMb: 0.0);
            }

            public function capabilities(): \App\Databases\EngineCapabilities
            {
                return new \App\Databases\EngineCapabilities(label: 'MySQL', hasUsers: true, optionFields: ['charset', 'collation']);
            }
        }
    );
    app()->instance(EngineManager::class, $manager);
}

// ──────────────────────────────────────────────────────────────────
// Test 1: GET /databases — unauthenticated → 302 to login
// ──────────────────────────────────────────────────────────────────

test('GET /databases unauthenticated redirects to login', function () {
    $response = $this->get('/databases');

    $response->assertStatus(302);
    $response->assertRedirect('/login');
});

// ──────────────────────────────────────────────────────────────────
// Test 2: GET /databases — non-admin sees only own rows
// ──────────────────────────────────────────────────────────────────

test('GET /databases non-admin sees only own databases', function () {
    fakeEngineManagerWithMysql();

    $user = User::factory()->create(['role' => 'user']);
    $other = User::factory()->create(['role' => 'user']);

    Database::factory()->create(['user_id' => $user->id, 'engine' => 'mysql']);
    Database::factory()->create(['user_id' => $user->id, 'engine' => 'mysql']);
    Database::factory()->create(['user_id' => $other->id, 'engine' => 'mysql']);

    $this->actingAs($user);

    $response = $this->get('/databases');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Databases/Index')
        ->has('databases', 2)
    );
});

// ──────────────────────────────────────────────────────────────────
// Test 3: GET /databases — admin sees all rows
// ──────────────────────────────────────────────────────────────────

test('GET /databases admin sees all databases', function () {
    fakeEngineManagerWithMysql();

    $admin = User::factory()->create(['role' => 'admin']);
    $user1 = User::factory()->create(['role' => 'user']);
    $user2 = User::factory()->create(['role' => 'user']);

    Database::factory()->create(['user_id' => $user1->id, 'engine' => 'mysql']);
    Database::factory()->create(['user_id' => $user2->id, 'engine' => 'mysql']);
    Database::factory()->create(['user_id' => $user2->id, 'engine' => 'mysql']);

    $this->actingAs($admin);

    $response = $this->get('/databases');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Databases/Index')
        ->has('databases', 3)
    );
});

// ──────────────────────────────────────────────────────────────────
// Test 4: POST /databases with engine not in available() → 422
// ──────────────────────────────────────────────────────────────────

test('POST /databases with unknown engine returns 422', function () {
    fakeEngineManagerWithMysql();

    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user);

    // Send as JSON so validation returns 422 instead of redirect-back
    $response = $this->postJson('/databases', [
        'engine' => 'oracle',
        'name_suffix' => 'mydb',
        'db_user_suffix' => 'myuser',
        'db_pass' => 'password123',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ]);

    $response->assertStatus(422);
});

// ──────────────────────────────────────────────────────────────────
// Test 5: POST /databases with SQL-injection charset → 422
// ──────────────────────────────────────────────────────────────────

test('POST /databases with SQL injection in charset returns 422', function () {
    fakeEngineManagerWithMysql();

    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user);

    // Send as JSON so validation returns 422 instead of redirect-back
    $response = $this->postJson('/databases', [
        'engine' => 'mysql',
        'name_suffix' => 'mydb',
        'db_user_suffix' => 'myuser',
        'db_pass' => 'password123',
        'charset' => 'utf8; DROP DATABASE foo',
        'collation' => 'utf8mb4_unicode_ci',
    ]);

    $response->assertStatus(422);
});

// ──────────────────────────────────────────────────────────────────
// Test 6: mysql.index route resolves to DatabasesController@index (no redirect)
// ──────────────────────────────────────────────────────────────────

test('mysql.index route resolves to same handler as databases.index without redirect', function () {
    fakeEngineManagerWithMysql();

    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user);

    $response = $this->get(route('mysql.index'));

    // Must be 200, not 301/302 (which would silently become GET on POST/PATCH/DELETE)
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Databases/Index'));
});
