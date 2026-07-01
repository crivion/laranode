<?php

use App\Models\Operation;
use App\Models\PhpVersion;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;

function makeSiteFor(User $user): Website {
    $php = PhpVersion::firstOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);
    return $user->websites()->create([
        'url' => 'demo.test', 'document_root' => '/public_html', 'php_version_id' => $php->id,
    ]);
}

test('enabling SSL creates an operation and returns its id (queue sync runs it)', function () {
    Event::fake();
    Process::fake(['*' => Process::result(output: "active\n", exitCode: 0)]);
    $user = User::factory()->create();
    $site = makeSiteFor($user);

    $response = $this->actingAs($user)
        ->postJson(route('websites.ssl.toggle', $site), ['enabled' => true]);

    $response->assertOk()->assertJsonStructure(['operation_id']);

    $op = Operation::findOrFail($response->json('operation_id'));
    expect($op->type)->toBe('ssl.generate')
        ->and($op->user_id)->toBe($user->id)
        ->and($op->status)->toBe('succeeded'); // ran inline under QUEUE_CONNECTION=sync
    expect($site->fresh()->ssl_status)->toBe('active');
});

test('a failing certbot run marks the operation failed and reverts ssl flags', function () {
    Event::fake();
    Process::fake(['*' => Process::result(output: '', errorOutput: 'certbot boom', exitCode: 1)]);
    $user = User::factory()->create();
    $site = makeSiteFor($user);
    $op = \App\Models\Operation::create([
        'user_id' => $user->id, 'type' => 'ssl.generate', 'target' => $site->url, 'status' => 'queued',
    ]);

    expect(fn () => (new \App\Jobs\GenerateSslOperationJob($op, $site, $user->email))->handle())
        ->toThrow(\Exception::class);

    expect($op->fresh()->status)->toBe('failed')
        ->and($site->fresh()->ssl_enabled)->toBeFalse();
});
