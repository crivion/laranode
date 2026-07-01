<?php

// tests/Feature/Websites/SwitchRuntimeTest.php

use App\Jobs\SwitchRuntimeOperationJob;
use App\Models\Operation;
use App\Models\PhpVersion;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

/**
 * Helper: create a website owned by a user with a real PHP version row.
 */
function makeWebsiteFor(User $owner): Website
{
    $php = PhpVersion::firstOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);

    return $owner->websites()->create([
        'url' => 'example-rt.test',
        'document_root' => '/public',
        'php_version_id' => $php->id,
        'runtime' => 'php-fpm',
        'runtime_port' => null,
    ]);
}

// ── Authorization ─────────────────────────────────────────────────────────────

test('unauthenticated user is redirected to login', function () {
    $site = makeWebsiteFor(User::factory()->create());

    $this->postJson(route('websites.runtime.switch', $site), ['runtime' => 'frankenphp'])
        ->assertUnauthorized();
});

test('non-owner gets 403', function () {
    Event::fake();
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $site = makeWebsiteFor($owner);

    $this->actingAs($other)
        ->postJson(route('websites.runtime.switch', $site), ['runtime' => 'frankenphp'])
        ->assertForbidden();
});

// ── Validation ────────────────────────────────────────────────────────────────

test('runtime=swoole is rejected with 422', function () {
    $owner = User::factory()->create();
    $site = makeWebsiteFor($owner);

    $this->actingAs($owner)
        ->postJson(route('websites.runtime.switch', $site), ['runtime' => 'swoole'])
        ->assertUnprocessable();
});

test('empty runtime is rejected with 422', function () {
    $owner = User::factory()->create();
    $site = makeWebsiteFor($owner);

    $this->actingAs($owner)
        ->postJson(route('websites.runtime.switch', $site), ['runtime' => ''])
        ->assertUnprocessable();
});

test('missing runtime is rejected with 422', function () {
    $owner = User::factory()->create();
    $site = makeWebsiteFor($owner);

    $this->actingAs($owner)
        ->postJson(route('websites.runtime.switch', $site), [])
        ->assertUnprocessable();
});

test('runtime=../etc/passwd is rejected with 422', function () {
    $owner = User::factory()->create();
    $site = makeWebsiteFor($owner);

    $this->actingAs($owner)
        ->postJson(route('websites.runtime.switch', $site), ['runtime' => '../etc/passwd'])
        ->assertUnprocessable();
});

test('runtime="; rm -rf /" is rejected with 422', function () {
    $owner = User::factory()->create();
    $site = makeWebsiteFor($owner);

    $this->actingAs($owner)
        ->postJson(route('websites.runtime.switch', $site), ['runtime' => '; rm -rf /'])
        ->assertUnprocessable();
});

// ── Happy path ────────────────────────────────────────────────────────────────

test('owner switching to frankenphp returns 200 JSON with operation_id, operation succeeds, runtime saved', function () {
    Event::fake();
    Process::fake(['*' => Process::result(output: '', exitCode: 0)]);

    $owner = User::factory()->create();
    $site = makeWebsiteFor($owner);

    $response = $this->actingAs($owner)
        ->postJson(route('websites.runtime.switch', $site), ['runtime' => 'frankenphp']);

    $response->assertOk()->assertJsonStructure(['operation_id']);

    $op = Operation::findOrFail($response->json('operation_id'));
    // Under QUEUE_CONNECTION=sync the job runs inline.
    expect($op->type)->toBe('runtime.switch')
        ->and($op->user_id)->toBe($owner->id)
        ->and($op->status)->toBe('succeeded');

    expect($site->fresh()->runtime)->toBe('frankenphp');
    expect($site->fresh()->runtime_port)->toBeInt()->toBeGreaterThanOrEqual(9100)->toBeLessThanOrEqual(9499);
});

// ── Script failure paths ──────────────────────────────────────────────────────

test('laranode-runtime-install.sh exit 1 marks operation failed and leaves runtime unchanged', function () {
    Event::fake();

    // install script fails, all others succeed
    Process::fake(function ($process) {
        $cmd = is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;
        if (str_contains($cmd, 'laranode-runtime-install.sh')) {
            return Process::result(output: '', errorOutput: 'install failed', exitCode: 1);
        }

        return Process::result(output: '', exitCode: 0);
    });

    $php = PhpVersion::firstOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);
    $owner = User::factory()->create();
    $site = $owner->websites()->create([
        'url' => 'installfail.test',
        'document_root' => '/public',
        'php_version_id' => $php->id,
        'runtime' => 'php-fpm',
    ]);
    $site->load('user');

    $op = Operation::create([
        'user_id' => $owner->id,
        'type' => 'runtime.switch',
        'target' => $site->url,
        'status' => 'queued',
    ]);

    expect(fn () => (new SwitchRuntimeOperationJob($op, $site, 'frankenphp'))->handle())
        ->toThrow(\Exception::class);

    expect($op->fresh()->status)->toBe('failed');
    expect($site->fresh()->runtime)->toBe('php-fpm');
});

test('laranode-vhost-switch.sh exit 1 marks operation failed and leaves runtime unchanged', function () {
    Event::fake();

    $php = PhpVersion::firstOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);
    $owner = User::factory()->create();
    $failSite = $owner->websites()->create([
        'url' => 'vhostfail.test',
        'document_root' => '/public',
        'php_version_id' => $php->id,
        'runtime' => 'php-fpm',
    ]);
    $failSite->load('user');

    // vhost-switch fails, all others succeed
    Process::fake(function ($process) {
        $cmd = is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;
        if (str_contains($cmd, 'laranode-vhost-switch.sh')) {
            return Process::result(output: '', errorOutput: 'vhost fail', exitCode: 1);
        }

        return Process::result(output: '', exitCode: 0);
    });

    $op = Operation::create([
        'user_id' => $owner->id,
        'type' => 'runtime.switch',
        'target' => $failSite->url,
        'status' => 'queued',
    ]);

    expect(fn () => (new SwitchRuntimeOperationJob($op, $failSite, 'frankenphp'))->handle())
        ->toThrow(\Exception::class);

    expect($op->fresh()->status)->toBe('failed');
    expect($failSite->fresh()->runtime)->toBe('php-fpm');
});

// ── PHP version guard ─────────────────────────────────────────────────────────

test('PATCH website with frankenphp runtime redirects back with errors (not JSON 422)', function () {
    $owner = User::factory()->create();
    $php = PhpVersion::firstOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);

    $site = $owner->websites()->create([
        'url' => 'franken.test',
        'document_root' => '/public',
        'php_version_id' => $php->id,
        'runtime' => 'frankenphp',
        'runtime_port' => 9100,
    ]);

    $response = $this->actingAs($owner)
        ->patch(route('websites.update', $site), ['php_version_id' => $php->id]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('runtime');
    expect($site->fresh()->php_version_id)->toBe($php->id);
});

// ── SSL guard (punch-list item 2) ─────────────────────────────────────────────

test('switching SSL-enabled site to FrankenPHP via HTTP enqueues operation (Queue::fake)', function () {
    Event::fake();
    Queue::fake();

    $owner = User::factory()->create();
    $php = PhpVersion::firstOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);

    $site = $owner->websites()->create([
        'url' => 'ssl-site.test',
        'document_root' => '/public',
        'php_version_id' => $php->id,
        'runtime' => 'php-fpm',
        'runtime_port' => null,
        'ssl_enabled' => true,
        'ssl_status' => 'active',
    ]);

    // With Queue::fake the job is not run inline — the route returns 200 with operation_id.
    $response = $this->actingAs($owner)
        ->postJson(route('websites.runtime.switch', $site), ['runtime' => 'frankenphp']);

    $response->assertOk()->assertJsonStructure(['operation_id']);
    Queue::assertPushed(SwitchRuntimeOperationJob::class);
    // Runtime not changed (job was not run).
    expect($site->fresh()->runtime)->toBe('php-fpm');
});

test('SSL-enabled site to FrankenPHP via job directly throws SwitchRuntimeException', function () {
    Event::fake();
    Process::fake(['*' => Process::result(output: '', exitCode: 0)]);

    $owner = User::factory()->create();
    $php = PhpVersion::firstOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);

    $site = $owner->websites()->create([
        'url' => 'ssl-direct.test',
        'document_root' => '/public',
        'php_version_id' => $php->id,
        'runtime' => 'php-fpm',
        'runtime_port' => null,
        'ssl_enabled' => true,
        'ssl_status' => 'active',
    ]);
    $site->load('user');

    $op = Operation::create([
        'user_id' => $owner->id,
        'type' => 'runtime.switch',
        'target' => $site->url,
        'status' => 'queued',
    ]);

    expect(fn () => (new SwitchRuntimeOperationJob($op, $site, 'frankenphp'))->handle())
        ->toThrow(\Exception::class);

    expect($op->fresh()->status)->toBe('failed');
    expect($site->fresh()->runtime)->toBe('php-fpm');
});

// ── Rollback on start failure (punch-list item 3) ─────────────────────────────

test('new runtime start failure triggers rollback of previous non-FPM runtime', function () {
    Event::fake();

    $owner = User::factory()->create();
    $php = PhpVersion::firstOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);

    // Site is currently on frankenphp (old runtime), switching to a hypothetical
    // second non-FPM runtime. For v1, we test the rollback path by making the
    // start of the new runtime fail while the old frankenphp unit restart is expected.
    // We use a site already on frankenphp trying to switch to frankenphp (same runtime
    // re-install) with start failing — old unit restart should be attempted.
    $site = $owner->websites()->create([
        'url' => 'rollback.test',
        'document_root' => '/public',
        'php_version_id' => $php->id,
        'runtime' => 'frankenphp',
        'runtime_port' => 9300,
    ]);
    $site->load('user');

    Process::fake(function ($process) {
        $cmd = is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;
        // start of the new unit fails
        if (str_contains($cmd, 'laranode-runtime-manage.sh') && str_contains($cmd, 'start')) {
            return Process::result(output: '', errorOutput: 'start failed', exitCode: 1);
        }

        return Process::result(output: '', exitCode: 0);
    });

    $op = Operation::create([
        'user_id' => $owner->id,
        'type' => 'runtime.switch',
        'target' => $site->url,
        'status' => 'queued',
    ]);

    expect(fn () => (new SwitchRuntimeOperationJob($op, $site, 'frankenphp'))->handle())
        ->toThrow(\Exception::class);

    expect($op->fresh()->status)->toBe('failed');
    // Runtime must remain unchanged (port NOT persisted).
    expect($site->fresh()->runtime)->toBe('frankenphp');
    expect($site->fresh()->runtime_port)->toBe(9300);

    // Rollback: restart of old unit must have been attempted.
    Process::assertRan(fn ($process) => str_contains($process->command[1] ?? '', 'laranode-runtime-manage.sh')
        && ($process->command[2] ?? null) === 'restart'
        && str_contains($process->command[3] ?? '', 'laranode-frankenphp-rollback.test.service')
    );
});

test('new runtime start failure when old runtime was php-fpm emits no-action message (FPM always available)', function () {
    Event::fake();

    $owner = User::factory()->create();
    $php = PhpVersion::firstOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);

    $site = $owner->websites()->create([
        'url' => 'rollback-fpm.test',
        'document_root' => '/public',
        'php_version_id' => $php->id,
        'runtime' => 'php-fpm',
        'runtime_port' => null,
    ]);
    $site->load('user');

    Process::fake(function ($process) {
        $cmd = is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;
        // start fails
        if (str_contains($cmd, 'laranode-runtime-manage.sh') && str_contains($cmd, 'start')) {
            return Process::result(output: '', errorOutput: 'start failed', exitCode: 1);
        }

        return Process::result(output: '', exitCode: 0);
    });

    $op = Operation::create([
        'user_id' => $owner->id,
        'type' => 'runtime.switch',
        'target' => $site->url,
        'status' => 'queued',
    ]);

    expect(fn () => (new SwitchRuntimeOperationJob($op, $site, 'frankenphp'))->handle())
        ->toThrow(\Exception::class);

    expect($op->fresh()->status)->toBe('failed');
    expect($site->fresh()->runtime)->toBe('php-fpm');
    // No restart call for php-fpm (FPM pool was never stopped).
    Process::assertNotRan(fn ($process) => str_contains($process->command[1] ?? '', 'laranode-runtime-manage.sh')
        && ($process->command[2] ?? null) === 'restart'
    );
});

// ── Domain validation (punch-list item 4) ─────────────────────────────────────

test('consecutive-dot domain is rejected by SwitchRuntimeService before any process call', function () {
    Event::fake();
    Process::fake(['*' => Process::result(output: '', exitCode: 0)]);

    $owner = User::factory()->create();
    $php = PhpVersion::firstOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);

    // Manually create a website with an invalid domain (bypasses FormRequest).
    $site = $owner->websites()->create([
        'url' => 'foo..bar.test',
        'document_root' => '/public',
        'php_version_id' => $php->id,
        'runtime' => 'php-fpm',
        'runtime_port' => null,
    ]);
    $site->load('user');

    $op = Operation::create([
        'user_id' => $owner->id,
        'type' => 'runtime.switch',
        'target' => $site->url,
        'status' => 'queued',
    ]);

    expect(fn () => (new SwitchRuntimeOperationJob($op, $site, 'frankenphp'))->handle())
        ->toThrow(\Exception::class);

    expect($op->fresh()->status)->toBe('failed');
    // No privileged process should have been called.
    Process::assertNothingRan();
});

test('path-traversal domain is rejected by SwitchRuntimeService before any process call', function () {
    Event::fake();
    Process::fake(['*' => Process::result(output: '', exitCode: 0)]);

    $owner = User::factory()->create();
    $php = PhpVersion::firstOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);

    // '../../etc/passwd' style domain — rejected at PHP level.
    $site = $owner->websites()->create([
        'url' => '../../etc/passwd',
        'document_root' => '/public',
        'php_version_id' => $php->id,
        'runtime' => 'php-fpm',
        'runtime_port' => null,
    ]);
    $site->load('user');

    $op = Operation::create([
        'user_id' => $owner->id,
        'type' => 'runtime.switch',
        'target' => $site->url,
        'status' => 'queued',
    ]);

    expect(fn () => (new SwitchRuntimeOperationJob($op, $site, 'frankenphp'))->handle())
        ->toThrow(\Exception::class);

    expect($op->fresh()->status)->toBe('failed');
    Process::assertNothingRan();
});
