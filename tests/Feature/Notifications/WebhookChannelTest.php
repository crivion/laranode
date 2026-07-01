<?php

use App\Models\Operation;
use App\Models\User;
use App\Notifications\Channels\WebhookChannel;
use App\Notifications\OperationFinishedNotification;
use Illuminate\Support\Facades\Http;

test('POST to valid URL sends JSON with event_type', function () {
    // Use an IP-based URL to avoid gethostbyname DNS resolution in container.
    // 93.184.216.34 is example.com's public IP — passes the SSRF filter.
    Http::fake(['https://93.184.216.34/*' => Http::response('ok', 200)]);

    $user = User::factory()->create(['webhook_url' => 'https://93.184.216.34/test']);
    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'ssl',
        'status' => 'succeeded',
        'exit_code' => 0,
    ]);

    $notification = new OperationFinishedNotification($operation);
    (new WebhookChannel)->send($user, $notification);

    Http::assertSent(fn ($request) => $request->isJson() && isset($request->data()['event_type']));
});

test('null webhook_url results in no HTTP call', function () {
    Http::fake();

    $user = User::factory()->create(['webhook_url' => null]);
    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'ssl',
        'status' => 'succeeded',
        'exit_code' => 0,
    ]);

    (new WebhookChannel)->send($user, new OperationFinishedNotification($operation));

    Http::assertNothingSent();
});

test('HTTP 500 response from webhook does not throw exception', function () {
    // Use IP-based URL so SSRF check passes; fake the request to return 500.
    Http::fake(['https://93.184.216.34/*' => Http::response('server error', 500)]);

    $user = User::factory()->create(['webhook_url' => 'https://93.184.216.34/test']);
    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'ssl',
        'status' => 'succeeded',
        'exit_code' => 0,
    ]);

    // Should not throw
    expect(fn () => (new WebhookChannel)->send($user, new OperationFinishedNotification($operation)))
        ->not->toThrow(\Throwable::class);
});

test('private IP URL is blocked (SSRF prevention)', function () {
    Http::fake();

    $user = User::factory()->create(['webhook_url' => 'http://192.168.1.1/hook']);
    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'ssl',
        'status' => 'succeeded',
        'exit_code' => 0,
    ]);

    (new WebhookChannel)->send($user, new OperationFinishedNotification($operation));

    Http::assertNothingSent();
});

test('non-http scheme is blocked', function () {
    Http::fake();

    $user = User::factory()->create(['webhook_url' => 'ftp://example.com/hook']);
    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'ssl',
        'status' => 'succeeded',
        'exit_code' => 0,
    ]);

    (new WebhookChannel)->send($user, new OperationFinishedNotification($operation));

    Http::assertNothingSent();
});

test('IPv6 loopback URL is blocked (SSRF prevention)', function () {
    Http::fake();

    $user = User::factory()->create(['webhook_url' => 'http://[::1]/hook']);
    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'ssl',
        'status' => 'succeeded',
        'exit_code' => 0,
    ]);

    (new WebhookChannel)->send($user, new OperationFinishedNotification($operation));

    Http::assertNothingSent();
});

test('webhook channel blocks private-IP hostname (system)', function () {
    if (! env('LARANODE_SYSTEM_TESTS')) {
        $this->markTestSkipped('system tests only');
    }

    Http::fake(); // should never be called

    $user = User::factory()->create(['webhook_url' => 'http://localhost/hook']);
    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'ssl',
        'status' => 'succeeded',
        'exit_code' => 0,
    ]);

    (new WebhookChannel)->send($user, new OperationFinishedNotification($operation));

    Http::assertNothingSent();
})->group('system');
