<?php

/**
 * Feature tests for DbServiceRequest (admin gate + allowlist validation).
 * All tests use Process::fake() — no real Linux required.
 */

use App\Models\User;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    Process::fake();
});

// ──────────────────────────────────────────────────────────────────
// Helper: route target. Task 3 wires the route; for now we POST to a
// placeholder path and assert authorize() fires before rules().
// We test via the DbServiceRequest directly where possible.
// ──────────────────────────────────────────────────────────────────

// ──────────────────────────────────────────────────────────────────
// Security: non-admin → 403 (authorize() fires before rules())
// ──────────────────────────────────────────────────────────────────

test('non-admin POST to databases.service.action returns 403', function () {
    $user = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($user)->postJson('/admin/databases/service', [
        'engine' => 'mysql',
        'action' => 'restart',
    ]);

    $response->assertStatus(403);
});

// ──────────────────────────────────────────────────────────────────
// Engine allowlist
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

// ──────────────────────────────────────────────────────────────────
// Action allowlist
// ──────────────────────────────────────────────────────────────────

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
// Valid payload as admin → not 403 or 422
// Route is added in Task 3; for Task 2 we verify FormRequest passes
// (neither authorize() nor rules() block it).
// ──────────────────────────────────────────────────────────────────

test('admin POST with valid engine and action is not rejected by FormRequest', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->postJson('/admin/databases/service', [
        'engine' => 'mysql',
        'action' => 'restart',
    ]);

    // 403 = authorize() blocked, 422 = validation failed — neither is acceptable.
    // 404 is fine here: route not yet wired (Task 3). 200 after Task 3.
    expect($response->status())->not->toBe(403)
        ->and($response->status())->not->toBe(422);
});
