<?php

// tests/Feature/Websites/SwitchRuntimeBackToFpmTest.php

use App\Jobs\SwitchRuntimeOperationJob;
use App\Models\Operation;
use App\Models\PhpVersion;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;

/**
 * Helper: create a FrankenPHP site already running on a port.
 */
function makeFrankenSite(User $owner): Website
{
    $php = PhpVersion::firstOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);

    return $owner->websites()->create([
        'url' => 'franken-back.test',
        'document_root' => '/public',
        'php_version_id' => $php->id,
        'runtime' => 'frankenphp',
        'runtime_port' => 9150,
    ]);
}

test('switching FrankenPHP back to FPM succeeds: runtime=php-fpm, runtime_port=null', function () {
    Event::fake();
    Process::fake(['*' => Process::result(output: '', exitCode: 0)]);

    $owner = User::factory()->create();
    $site = makeFrankenSite($owner);
    $site->load('user');

    $op = Operation::create([
        'user_id' => $owner->id,
        'type' => 'runtime.switch',
        'target' => $site->url,
        'status' => 'queued',
    ]);

    (new SwitchRuntimeOperationJob($op, $site, 'php-fpm'))->handle();

    expect($op->fresh()->status)->toBe('succeeded');
    expect($site->fresh()->runtime)->toBe('php-fpm');
    expect($site->fresh()->runtime_port)->toBeNull();
});

test('switching back to FPM calls laranode-runtime-manage.sh stop for old frankenphp unit', function () {
    Event::fake();
    Process::fake(['*' => Process::result(output: '', exitCode: 0)]);

    $owner = User::factory()->create();
    $site = makeFrankenSite($owner);
    $site->load('user');

    $op = Operation::create([
        'user_id' => $owner->id,
        'type' => 'runtime.switch',
        'target' => $site->url,
        'status' => 'queued',
    ]);

    (new SwitchRuntimeOperationJob($op, $site, 'php-fpm'))->handle();

    Process::assertRan(fn ($process) => str_contains($process->command[1] ?? '', 'laranode-runtime-manage.sh')
        && ($process->command[2] ?? null) === 'stop'
        && str_contains($process->command[3] ?? '', 'laranode-frankenphp-franken-back.test.service')
    );
});

test('switching back to FPM calls laranode-vhost-switch.sh with runtime=php-fpm and port=0', function () {
    Event::fake();
    Process::fake(['*' => Process::result(output: '', exitCode: 0)]);

    $owner = User::factory()->create();
    $site = makeFrankenSite($owner);
    $site->load('user');

    $op = Operation::create([
        'user_id' => $owner->id,
        'type' => 'runtime.switch',
        'target' => $site->url,
        'status' => 'queued',
    ]);

    (new SwitchRuntimeOperationJob($op, $site, 'php-fpm'))->handle();

    Process::assertRan(fn ($process) => str_contains($process->command[1] ?? '', 'laranode-vhost-switch.sh')
        && ($process->command[2] ?? null) === 'franken-back.test'
        && ($process->command[3] ?? null) === 'php-fpm'
        && ($process->command[4] ?? null) === '0'  // FIXED: port 0 for FPM revert
    );
});

test('port 0 passed for FPM revert does not cause failure (FIXED: review fix #15)', function () {
    Event::fake();
    Process::fake(['*' => Process::result(output: '', exitCode: 0)]);

    $owner = User::factory()->create();
    $site = makeFrankenSite($owner);
    $site->load('user');

    $op = Operation::create([
        'user_id' => $owner->id,
        'type' => 'runtime.switch',
        'target' => $site->url,
        'status' => 'queued',
    ]);

    // Should not throw — port 0 is valid for FPM revert path.
    (new SwitchRuntimeOperationJob($op, $site, 'php-fpm'))->handle();

    expect($op->fresh()->status)->toBe('succeeded');
});
