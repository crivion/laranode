<?php

use App\Contracts\DatabaseEngineDriver;
use App\Databases\DatabaseSpec;
use App\Databases\DatabaseStats;
use App\Databases\EngineCapabilities;
use App\Databases\EngineManager;
use App\Models\Database;
use App\Models\User;
use Illuminate\Routing\Router;

// ──────────────────────────────────────────────────────────────────
// Helper: mock EngineManager so mysql engine is "available"
// and driver->create() is a no-op
// ──────────────────────────────────────────────────────────────────

function fakeEngineManagerForRouteTest(): void
{
    $noopDriver = new class implements DatabaseEngineDriver
    {
        public function connectionName(): string
        {
            return 'mysql_admin';
        }

        public function create(DatabaseSpec $spec): void {}

        public function updatePassword(Database $database, string $newPassword): void {}

        public function updateOptions(Database $database, array $options): void {}

        public function delete(Database $database): void {}

        public function stats(Database $database): DatabaseStats
        {
            return new DatabaseStats(tableCount: 0, sizeMb: 0.0);
        }

        public function capabilities(): EngineCapabilities
        {
            return new EngineCapabilities(label: 'MySQL', hasUsers: true, optionFields: ['charset', 'collation']);
        }
    };

    $manager = Mockery::mock(EngineManager::class);
    $manager->shouldReceive('available')->andReturn(['mysql' => 'mysql']);
    $manager->shouldReceive('for')->with('mysql')->andReturn($noopDriver);

    app()->instance(EngineManager::class, $manager);
}

// ──────────────────────────────────────────────────────────────────
// Test 1: databases.* — 5 canonical routes registered
// ──────────────────────────────────────────────────────────────────

test('databases routes are registered with correct names and methods', function () {
    /** @var Router $router */
    $router = app(Router::class);
    $routes = $router->getRoutes();

    $expected = [
        'databases.index' => ['GET', '/databases'],
        'databases.engine-options' => ['GET', '/databases/engine-options'],
        'databases.store' => ['POST', '/databases'],
        'databases.update' => ['PATCH', '/databases'],
        'databases.destroy' => ['DELETE', '/databases'],
    ];

    foreach ($expected as $name => [$method, $uri]) {
        $route = $routes->getByName($name);
        expect($route)->not->toBeNull("Route [{$name}] should be registered");
        expect($route->uri())->toBe(ltrim($uri, '/'));
        expect($route->methods())->toContain($method);
        expect($route->getActionName())->toContain('DatabasesController');
    }
});

// ──────────────────────────────────────────────────────────────────
// Test 2: mysql.* — 5 back-compat alias routes registered,
//         same handlers as databases.* (no redirect action)
// ──────────────────────────────────────────────────────────────────

test('mysql back-compat alias routes are registered with same handlers as databases routes', function () {
    /** @var Router $router */
    $router = app(Router::class);
    $routes = $router->getRoutes();

    $expected = [
        'mysql.index' => ['GET',    '/mysql'],
        'mysql.charsets-collations' => ['GET',    '/mysql/charsets-collations'],
        'mysql.store' => ['POST',   '/mysql'],
        'mysql.update' => ['PATCH',  '/mysql'],
        'mysql.destroy' => ['DELETE', '/mysql'],
    ];

    $handlerMap = [
        'mysql.index' => 'databases.index',
        'mysql.charsets-collations' => 'databases.engine-options',
        'mysql.store' => 'databases.store',
        'mysql.update' => 'databases.update',
        'mysql.destroy' => 'databases.destroy',
    ];

    foreach ($expected as $name => [$method, $uri]) {
        $mysqlRoute = $routes->getByName($name);
        $dbRoute = $routes->getByName($handlerMap[$name]);

        expect($mysqlRoute)->not->toBeNull("Route [{$name}] should be registered");
        expect($mysqlRoute->uri())->toBe(ltrim($uri, '/'));
        expect($mysqlRoute->methods())->toContain($method);

        // Same controller action as the canonical route — not a redirect
        expect($mysqlRoute->getActionName())->toBe($dbRoute->getActionName());
    }
});

// ──────────────────────────────────────────────────────────────────
// Test 3: POST /mysql → 302 to databases.index (service redirect),
//         NOT a 301 permanent redirect to /databases
// ──────────────────────────────────────────────────────────────────

test('POST to /mysql redirects to databases.index with 302 not 301', function () {
    app()->forgetInstance(EngineManager::class);
    fakeEngineManagerForRouteTest();

    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user);

    $prefix = $user->username.'_';

    $response = $this->post('/mysql', [
        'engine' => 'mysql',
        'name_suffix' => 'testdb',
        'db_user_suffix' => 'testuser',
        'db_pass' => 'password123',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ]);

    // Must be 302 (application redirect) to databases.index, not 301 (HTTP redirect to /databases)
    $response->assertStatus(302);
    $response->assertRedirect(route('databases.index'));
});
