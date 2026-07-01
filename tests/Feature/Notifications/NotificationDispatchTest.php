<?php

use App\Models\NotificationPreference;
use App\Models\Operation;
use App\Models\User;
use App\Notifications\Channels\WebhookChannel;
use App\Notifications\OperationFinishedNotification;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

// 93.184.216.34 is example.com — public IP that passes the SSRF filter.
// Using an IP host avoids gethostbyname DNS failures in CI/container environments.
const WEBHOOK_TEST_URL = 'https://93.184.216.34/hook';

test('all channels enabled: db row written, mail routed, webhook POSTed', function () {
    Http::fake();

    $user = User::factory()->create([
        'webhook_url' => WEBHOOK_TEST_URL,
    ]);

    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'test-op',
        'status' => 'succeeded',
        'exit_code' => 0,
    ]);

    // All channels enabled by default (no preference rows = opt-out model).
    NotificationService::dispatch($user, new OperationFinishedNotification($operation));

    // DB channel written (real dispatch — no Notification::fake() here).
    $this->assertDatabaseHas('notifications', [
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'type' => OperationFinishedNotification::class,
    ]);

    // Webhook channel fired.
    Http::assertSent(fn ($request) => str_contains($request->url(), '93.184.216.34'));

    // Mail channel behavioral assertion: use Notification::fake() to verify
    // via() includes 'mail' for a fresh user with no disabled preferences.
    Notification::fake();

    $user2 = User::factory()->create(['webhook_url' => WEBHOOK_TEST_URL]);
    $operation2 = Operation::create([
        'user_id' => $user2->id,
        'type' => 'test-op',
        'status' => 'succeeded',
        'exit_code' => 0,
    ]);

    Notification::sendNow($user2, new OperationFinishedNotification($operation2));

    Notification::assertSentTo(
        $user2,
        OperationFinishedNotification::class,
        fn ($notification, $channels) => in_array('mail', $channels)
    );
});

test('mail disabled: db row still written, mail channel excluded from via()', function () {
    Http::fake();

    $user = User::factory()->create([
        'webhook_url' => null,
    ]);

    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'test-op',
        'status' => 'succeeded',
        'exit_code' => 0,
    ]);

    NotificationPreference::create([
        'user_id' => $user->id,
        'event_type' => 'operation.finished',
        'channel' => 'mail',
        'enabled' => false,
    ]);

    // Real dispatch: verify DB row is written even when mail is disabled.
    NotificationService::dispatch($user, new OperationFinishedNotification($operation));

    $this->assertDatabaseHas('notifications', [
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
    ]);

    // Behavioral assertion: mail channel must NOT be in via() when disabled.
    Notification::fake();

    $user2 = User::factory()->create(['webhook_url' => null]);
    $operation2 = Operation::create([
        'user_id' => $user2->id,
        'type' => 'test-op',
        'status' => 'succeeded',
        'exit_code' => 0,
    ]);

    NotificationPreference::create([
        'user_id' => $user2->id,
        'event_type' => 'operation.finished',
        'channel' => 'mail',
        'enabled' => false,
    ]);

    Notification::sendNow($user2, new OperationFinishedNotification($operation2));

    Notification::assertSentTo(
        $user2,
        OperationFinishedNotification::class,
        fn ($notification, $channels) => ! in_array('mail', $channels)
    );

    // Mail channel must be excluded when preference is disabled.
    $channels = NotificationService::resolveChannels($user, 'operation.finished');
    expect($channels)->not->toContain('mail');
    expect($channels)->toContain('database');
});

test('resolveChannels with webhook disabled does not include WebhookChannel', function () {
    $user = User::factory()->create();

    NotificationPreference::create([
        'user_id' => $user->id,
        'event_type' => 'operation.finished',
        'channel' => 'webhook',
        'enabled' => false,
    ]);

    $channels = NotificationService::resolveChannels($user, 'operation.finished');

    expect($channels)->not->toContain(WebhookChannel::class);
    expect($channels)->toContain('database');
    expect($channels)->toContain('mail');
});
