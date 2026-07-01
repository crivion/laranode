<?php

use App\Models\User;

test('own notifications channel auth returns truthy', function () {
    $user = User::factory()->create();

    $broadcaster = app(\Illuminate\Broadcasting\BroadcastManager::class)->connection();
    $result = invokeBroadcastChannel($broadcaster, 'notifications.{userId}', $user, (string) $user->id);

    expect($result)->toBeTruthy();
});

test('another users notifications channel auth returns falsy', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $broadcaster = app(\Illuminate\Broadcasting\BroadcastManager::class)->connection();
    $callback = invokeBroadcastChannel($broadcaster, 'notifications.{userId}', $user, (string) $other->id);

    expect($callback)->toBeFalsy();
});

test('admin on another users notifications channel auth returns falsy', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $other = User::factory()->create();

    $broadcaster = app(\Illuminate\Broadcasting\BroadcastManager::class)->connection();
    $callback = invokeBroadcastChannel($broadcaster, 'notifications.{userId}', $admin, (string) $other->id);

    expect($callback)->toBeFalsy();
});

/**
 * Invoke the registered channel callback for a given channel pattern, user, and userId parameter.
 */
function invokeBroadcastChannel($broadcaster, string $pattern, $user, string $userId): mixed
{
    // Access the channels array via reflection
    $reflection = new \ReflectionObject($broadcaster);
    $prop = $reflection->getProperty('channels');
    $prop->setAccessible(true);
    $channels = $prop->getValue($broadcaster);

    if (! isset($channels[$pattern])) {
        throw new \RuntimeException("Channel '{$pattern}' not registered");
    }

    $callback = $channels[$pattern];

    return $callback($user, $userId);
}
