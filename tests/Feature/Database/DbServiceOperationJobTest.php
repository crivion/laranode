<?php

/**
 * Feature tests for DbServiceOperationJob.
 * Verifies: lifecycle (success/failure), Process args, output content,
 * and that the resolved service name never leaks into output.
 */

use App\Jobs\DbServiceException;
use App\Jobs\DbServiceOperationJob;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    // Tests create Operation rows
});

// ──────────────────────────────────────────────────────────────────
// Success path
// ──────────────────────────────────────────────────────────────────

test('success path marks operation succeeded and contains expected output', function () {
    Process::fake([
        '*' => Process::result(output: "Running...\nDone.", exitCode: 0),
    ]);

    $user = User::factory()->create(['role' => 'admin']);
    $op = Operation::create([
        'user_id' => $user->id,
        'type' => 'db.service.restart',
        'target' => 'mysql:mysql',
        'status' => 'queued',
    ]);

    DbServiceOperationJob::dispatchSync($op, 'mysql', 'restart');

    $fresh = $op->fresh();
    expect($fresh->status)->toBe('succeeded');
    expect($fresh->output)->toContain('Running:');
    expect($fresh->output)->toContain('completed.');
});

// ──────────────────────────────────────────────────────────────────
// Failure path
// ──────────────────────────────────────────────────────────────────

test('failure path marks operation failed and throws DbServiceException', function () {
    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'Unit mysql not found', exitCode: 1),
    ]);

    $user = User::factory()->create(['role' => 'admin']);
    $op = Operation::create([
        'user_id' => $user->id,
        'type' => 'db.service.restart',
        'target' => 'mysql:mysql',
        'status' => 'queued',
    ]);

    expect(fn () => DbServiceOperationJob::dispatchSync($op, 'mysql', 'restart'))
        ->toThrow(DbServiceException::class);

    $fresh = $op->fresh();
    expect($fresh->status)->toBe('failed');
    expect($fresh->output)->toContain('ERROR: Unit mysql not found');
});

// ──────────────────────────────────────────────────────────────────
// Output capture
// ──────────────────────────────────────────────────────────────────

test('output from Process::run is appended to operation output', function () {
    Process::fake([
        '*' => Process::result(output: 'faked-script-output', exitCode: 0),
    ]);

    $user = User::factory()->create(['role' => 'admin']);
    $op = Operation::create([
        'user_id' => $user->id,
        'type' => 'db.service.restart',
        'target' => 'mysql:mysql',
        'status' => 'queued',
    ]);

    DbServiceOperationJob::dispatchSync($op, 'mysql', 'restart');

    expect($op->fresh()->output)->toContain('faked-script-output');
});

// ──────────────────────────────────────────────────────────────────
// Correct args to Process::run (mysql) — engine key, not service name
// ──────────────────────────────────────────────────────────────────

test('Process::run receives laranode-db-service.sh with action and engine key for mysql', function () {
    Process::fake([
        '*' => Process::result(output: '', exitCode: 0),
    ]);

    $user = User::factory()->create(['role' => 'admin']);
    $op = Operation::create([
        'user_id' => $user->id,
        'type' => 'db.service.restart',
        'target' => 'mysql:mysql',
        'status' => 'queued',
    ]);

    DbServiceOperationJob::dispatchSync($op, 'mysql', 'restart');

    Process::assertRan(function ($process) {
        $cmd = $process->command;

        // Command array must contain the script, action, and engine key
        return is_array($cmd)
            && collect($cmd)->contains(fn ($v) => str_contains($v, 'laranode-db-service.sh'))
            && in_array('restart', $cmd)
            && in_array('mysql', $cmd);
    });
});

// ──────────────────────────────────────────────────────────────────
// Correct args to Process::run (postgres) — engine key 'postgres', NOT 'postgresql'
// ──────────────────────────────────────────────────────────────────

test('Process::run receives engine key postgres not service name postgresql', function () {
    Process::fake([
        '*' => Process::result(output: '', exitCode: 0),
    ]);

    $user = User::factory()->create(['role' => 'admin']);
    $op = Operation::create([
        'user_id' => $user->id,
        'type' => 'db.service.restart',
        'target' => 'postgres:postgresql',
        'status' => 'queued',
    ]);

    DbServiceOperationJob::dispatchSync($op, 'postgres', 'restart');

    Process::assertRan(function ($process) {
        $cmd = $process->command;
        if (! is_array($cmd)) {
            return false;
        }
        // Must contain engine key 'postgres'
        $hasEngineKey = in_array('postgres', $cmd);
        // Must NOT contain resolved service name 'postgresql'
        $hasServiceName = in_array('postgresql', $cmd);

        return $hasEngineKey && ! $hasServiceName;
    });
});

// ──────────────────────────────────────────────────────────────────
// Output does not leak service name (postgres)
// Job emits engine key; 'postgresql' must not appear in output
// ──────────────────────────────────────────────────────────────────

test('output for postgres engine does not contain resolved service name postgresql', function () {
    Process::fake([
        '*' => Process::result(output: 'script ran ok', exitCode: 0),
    ]);

    $user = User::factory()->create(['role' => 'admin']);
    $op = Operation::create([
        'user_id' => $user->id,
        'type' => 'db.service.restart',
        'target' => 'postgres:postgresql',
        'status' => 'queued',
    ]);

    DbServiceOperationJob::dispatchSync($op, 'postgres', 'restart');

    $output = $op->fresh()->output;
    expect($output)->not->toContain('postgresql')
        ->and($output)->toContain('postgres');
});
