<?php

use App\Events\NotificationCreated;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

test('inserting a DatabaseNotification dispatches NotificationCreated event', function () {
    Event::fake([NotificationCreated::class]);

    $user = User::factory()->create();

    // Insert a notification row via the notifiable (triggers observer)
    $user->notifications()->create([
        'id' => \Illuminate\Support\Str::uuid(),
        'type' => 'App\\Notifications\\OperationFinishedNotification',
        'data' => ['event_type' => 'operation_finished'],
        'read_at' => null,
    ]);

    Event::assertDispatched(NotificationCreated::class, function ($event) use ($user) {
        return $event->userId === $user->id;
    });
});

test('observer does not throw when dispatch throws and DB notification row still exists', function () {
    Event::fake([NotificationCreated::class]);

    // Make dispatch throw via a partial mock
    Event::shouldReceive('dispatch')
        ->with(\Mockery::type(NotificationCreated::class))
        ->andThrow(new \RuntimeException('Reverb down'));

    Log::shouldReceive('warning')->once();

    $user = User::factory()->create();

    $notificationId = \Illuminate\Support\Str::uuid()->toString();

    // Should not throw
    expect(function () use ($user, $notificationId) {
        $user->notifications()->create([
            'id' => $notificationId,
            'type' => 'App\\Notifications\\OperationFinishedNotification',
            'data' => ['event_type' => 'operation_finished'],
            'read_at' => null,
        ]);
    })->not->toThrow(\Throwable::class);

    // DB row still exists
    $this->assertDatabaseHas('notifications', ['id' => $notificationId]);
});
