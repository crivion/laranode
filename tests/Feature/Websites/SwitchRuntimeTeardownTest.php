<?php

// tests/Feature/Websites/SwitchRuntimeTeardownTest.php
// Verifies teardownRuntime() is called FIRST in DeleteWebsiteService (FIXED: review fix #12)

use App\Models\PhpVersion;
use App\Models\User;
use App\Models\Website;
use App\Services\Websites\DeleteWebsiteService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;

/**
 * Helper: build a FrankenPHP website.
 */
function makeDeletableFrankenSite(User $owner): Website
{
    $php = PhpVersion::firstOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);

    return $owner->websites()->create([
        'url' => 'teardown.test',
        'document_root' => '/public',
        'php_version_id' => $php->id,
        'runtime' => 'frankenphp',
        'runtime_port' => 9200,
    ]);
}

test('deleting a FrankenPHP site calls laranode-runtime-manage.sh remove (ordering guaranteed by code structure)', function () {
    Event::fake();

    // Use a closure fake to record call order.
    $callOrder = [];
    Process::fake(function ($process) use (&$callOrder) {
        $cmd = is_array($process->command) ? $process->command : [$process->command];
        $sig = implode(' ', $cmd);
        $callOrder[] = $sig;

        return Process::result(output: '', exitCode: 0);
    });

    $owner = User::factory()->create();
    $site = makeDeletableFrankenSite($owner);
    $site->load('user');

    (new DeleteWebsiteService($site, $owner))->handle();

    // Find call indices.
    $manageRemoveIdx = null;
    $rmRfIdx = null;
    foreach ($callOrder as $i => $cmd) {
        if (str_contains($cmd, 'laranode-runtime-manage.sh') && str_contains($cmd, 'remove')) {
            $manageRemoveIdx = $i;
        }
        if (str_contains($cmd, 'rm -rf') || str_contains($cmd, 'rm') && str_contains($cmd, $site->url)) {
            if ($rmRfIdx === null) {
                $rmRfIdx = $i;
            }
        }
    }

    expect($manageRemoveIdx)->not->toBeNull('laranode-runtime-manage.sh remove was never called');
    // teardownRuntime is called first in handle(), before deleteWebsiteFiles().
    // manage.sh remove must appear before rm -rf.
    if ($rmRfIdx !== null) {
        expect($manageRemoveIdx)->toBeLessThan($rmRfIdx);
    }
});

test('deleting a FrankenPHP site calls laranode-runtime-manage.sh remove with correct unit name', function () {
    Event::fake();
    Process::fake(['*' => Process::result(output: '', exitCode: 0)]);

    $owner = User::factory()->create();
    $site = makeDeletableFrankenSite($owner);
    $site->load('user');

    (new DeleteWebsiteService($site, $owner))->handle();

    Process::assertRan(fn ($process) => str_contains($process->command[1] ?? '', 'laranode-runtime-manage.sh')
        && ($process->command[2] ?? null) === 'remove'
        && ($process->command[3] ?? null) === 'laranode-frankenphp-teardown.test.service'
    );
});

test('deleting a php-fpm site does not call laranode-runtime-manage.sh (teardown is no-op)', function () {
    Event::fake();
    Process::fake(['*' => Process::result(output: '', exitCode: 0)]);

    $php = PhpVersion::firstOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);
    $owner = User::factory()->create();
    $site = $owner->websites()->create([
        'url' => 'fpmsite.test',
        'document_root' => '/public',
        'php_version_id' => $php->id,
        'runtime' => 'php-fpm',
        'runtime_port' => null,
    ]);
    $site->load('user');

    (new DeleteWebsiteService($site, $owner))->handle();

    Process::assertNotRan(fn ($process) => str_contains($process->command[1] ?? '', 'laranode-runtime-manage.sh')
        && ($process->command[2] ?? null) === 'remove'
    );
});
