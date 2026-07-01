<?php

/**
 * System integration tests for laranode-db-service.sh and the DB service control feature.
 * Gate: LARANODE_SYSTEM_TESTS=1.
 *
 * Run inside the container:
 *   LARANODE_SYSTEM_TESTS=1 php artisan test --filter=DbServiceSystemTest
 */
beforeEach(function () {
    if (! env('LARANODE_SYSTEM_TESTS')) {
        $this->markTestSkipped('requires LARANODE_SYSTEM_TESTS=1');
    }
});

/**
 * Helper: run the script directly (without sudo for validation tests).
 * Returns ['exitCode' => int, 'output' => string, 'error' => string].
 */
function runDbServiceScript(string ...$args): array
{
    $scriptPath = base_path('laranode-scripts/bin/laranode-db-service.sh');
    $cmd = array_merge([$scriptPath], $args);
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($proc);

    return ['exitCode' => $exitCode, 'output' => $output, 'error' => $error];
}

/**
 * Helper: run with sudo (for real systemctl tests).
 */
function runDbServiceScriptSudo(string ...$args): array
{
    $scriptPath = base_path('laranode-scripts/bin/laranode-db-service.sh');
    $cmd = array_merge(['sudo', $scriptPath], $args);
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($proc);

    return ['exitCode' => $exitCode, 'output' => $output, 'error' => $error];
}

test('invalid engine exits non-zero', function () {
    $result = runDbServiceScript('start', 'invalid_engine');
    expect($result['exitCode'])->not->toBe(0);
});

test('leading-dash action exits non-zero', function () {
    $result = runDbServiceScript('-p', 'mysql');
    expect($result['exitCode'])->not->toBe(0);
});

test('leading-dash engine exits non-zero', function () {
    $result = runDbServiceScript('restart', '-mysql');
    expect($result['exitCode'])->not->toBe(0);
});

test('restart mysql exits 0 and service remains active', function () {
    $result = runDbServiceScriptSudo('restart', 'mysql');
    expect($result['exitCode'])->toBe(0, "Script output: {$result['output']}\nScript stderr: {$result['error']}");

    // Verify mysql is still active after restart
    exec('systemctl is-active mysql', $statusOut, $statusCode);
    expect(trim(implode('', $statusOut)))->toBe('active');
});

test('restart postgres exits 0 and covers postgresql@16-main retry branch', function () {
    // postgresql generic alias is inactive on Ubuntu; script must fall through to postgresql@16-main
    $result = runDbServiceScriptSudo('restart', 'postgres');
    expect($result['exitCode'])->toBe(0, "Script output: {$result['output']}\nScript stderr: {$result['error']}");

    // postgresql@16-main should be active after restart
    exec('systemctl is-active postgresql@16-main', $statusOut, $statusCode);
    expect(trim(implode('', $statusOut)))->toBe('active');
});

test('status endpoint returns mysql active', function () {
    /** @var \Tests\TestCase $this */
    $admin = \App\Models\User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    $response = $this->getJson(route('databases.service.status'));

    $response->assertStatus(200)
        ->assertJsonStructure(['statuses'])
        ->assertJsonPath('statuses.mysql.active', true);
})->group('system');
