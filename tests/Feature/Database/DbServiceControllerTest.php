<?php

/**
 * Feature tests for DbServiceController (admin gate + dispatch + status).
 * All tests use Process::fake() and mock EngineManager — no real Linux required.
 */

use App\Databases\EngineManager;
use App\Jobs\DbServiceOperationJob;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    Process::fake();
    Bus::fake();
    app()->forgetInstance(EngineManager::class);
});

// ──────────────────────────────────────────────────────────────────
// Helper: mock EngineManager::available() with mysql active
// ──────────────────────────────────────────────────────────────────

function fakeEngineManagerAvailableMysql(): void
{
    $manager = Mockery::mock(EngineManager::class);
    $manager->shouldReceive('available')->andReturn(['mysql' => 'mysql']);
    app()->instance(EngineManager::class, $manager);
}

// ──────────────────────────────────────────────────────────────────
// Admin gate — action endpoint
// ──────────────────────────────────────────────────────────────────

test('non-admin POST to databases.service.action returns 403', function () {
    $user = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($user)->postJson('/admin/databases/service', [
        'engine' => 'mysql',
        'action' => 'restart',
    ]);

    $response->assertStatus(403);
});

test('unauthenticated POST to databases.service.action returns 302', function () {
    $response = $this->postJson('/admin/databases/service', [
        'engine' => 'mysql',
        'action' => 'restart',
    ]);

    // postJson sends Accept: application/json → Laravel returns 401 for unauthenticated
    $response->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────────────
// Admin gate — status endpoint
// ──────────────────────────────────────────────────────────────────

test('non-admin GET to databases.service.status returns 403', function () {
    $user = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($user)->getJson('/admin/databases/service/status');

    $response->assertStatus(403);
});

test('unauthenticated GET to databases.service.status returns 302 or 401', function () {
    $response = $this->getJson('/admin/databases/service/status');

    $response->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────────────
// Validation — action endpoint
// ──────────────────────────────────────────────────────────────────

test('admin POST with invalid engine returns 422', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->postJson('/admin/databases/service', [
        'engine' => 'invalid_engine',
        'action' => 'restart',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['engine']);
});

test('admin POST with leading-dash engine returns 422', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->postJson('/admin/databases/service', [
        'engine' => '-mysql',
        'action' => 'restart',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['engine']);
});

test('admin POST with invalid action returns 422', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->postJson('/admin/databases/service', [
        'engine' => 'mysql',
        'action' => 'nuke',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['action']);
});

test('admin POST with leading-dash action returns 422', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->postJson('/admin/databases/service', [
        'engine' => 'mysql',
        'action' => '--help',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['action']);
});

// ──────────────────────────────────────────────────────────────────
// Successful dispatch
// ──────────────────────────────────────────────────────────────────

test('admin POST with valid payload dispatches DbServiceOperationJob and returns operation_id', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->postJson('/admin/databases/service', [
        'engine' => 'mysql',
        'action' => 'restart',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['operation_id']);

    $operationId = $response->json('operation_id');
    expect($operationId)->toBeInt();

    // Operation row exists with correct type, target, status
    $operation = Operation::find($operationId);
    expect($operation)->not->toBeNull()
        ->and($operation->type)->toBe('db.service.restart')
        ->and($operation->target)->toBe('mysql:mysql')
        ->and($operation->status)->toBe('queued');

    // DbServiceOperationJob was dispatched
    Bus::assertDispatched(DbServiceOperationJob::class, function ($job) use ($operationId) {
        return $job->operation->id === $operationId
            && $job->engine === 'mysql'
            && $job->action === 'restart';
    });
});

test('dispatched job has no service property (constructor only takes engine and action)', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->postJson('/admin/databases/service', [
        'engine' => 'mysql',
        'action' => 'restart',
    ]);

    Bus::assertDispatched(DbServiceOperationJob::class, function ($job) {
        // The job must NOT have a $service property — engine + action only
        return ! property_exists($job, 'service');
    });
});

// ──────────────────────────────────────────────────────────────────
// Status endpoint
// ──────────────────────────────────────────────────────────────────

test('GET databases.service.status as admin returns statuses with active flag', function () {
    fakeEngineManagerAvailableMysql();

    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->getJson('/admin/databases/service/status');

    $response->assertStatus(200);
    $response->assertJsonStructure(['statuses']);

    $statuses = $response->json('statuses');
    expect($statuses)->toHaveKey('mysql')
        ->and($statuses['mysql']['service'])->toBe('mysql')
        ->and($statuses['mysql']['active'])->toBeTrue();
});

test('status endpoint returns inactive for engines not in available()', function () {
    // Mock available() returning only postgres — mysql is inactive
    $manager = Mockery::mock(EngineManager::class);
    $manager->shouldReceive('available')->andReturn(['postgres' => 'postgresql']);
    app()->instance(EngineManager::class, $manager);

    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->getJson('/admin/databases/service/status');

    $response->assertStatus(200);

    $statuses = $response->json('statuses');

    // mysql and mariadb should be inactive (not in available())
    expect($statuses['mysql']['active'])->toBeFalse()
        ->and($statuses['postgres']['active'])->toBeTrue();
});
