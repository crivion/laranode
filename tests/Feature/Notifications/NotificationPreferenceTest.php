<?php

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\QueryException;

test('isEnabled returns true when no row exists', function () {
    $user = User::factory()->create();

    expect(NotificationPreference::isEnabled($user->id, 'operation.finished', 'mail'))->toBeTrue();
});

test('isEnabled returns false when disabled row exists', function () {
    $user = User::factory()->create();

    NotificationPreference::create([
        'user_id' => $user->id,
        'event_type' => 'operation.finished',
        'channel' => 'mail',
        'enabled' => false,
    ]);

    expect(NotificationPreference::isEnabled($user->id, 'operation.finished', 'mail'))->toBeFalse();
});

test('isEnabled returns true when enabled row exists', function () {
    $user = User::factory()->create();

    NotificationPreference::create([
        'user_id' => $user->id,
        'event_type' => 'operation.finished',
        'channel' => 'database',
        'enabled' => true,
    ]);

    expect(NotificationPreference::isEnabled($user->id, 'operation.finished', 'database'))->toBeTrue();
});

test('duplicate insert throws QueryException due to unique constraint', function () {
    $user = User::factory()->create();

    NotificationPreference::create([
        'user_id' => $user->id,
        'event_type' => 'ssl.expiring',
        'channel' => 'webhook',
        'enabled' => true,
    ]);

    expect(fn () => NotificationPreference::create([
        'user_id' => $user->id,
        'event_type' => 'ssl.expiring',
        'channel' => 'webhook',
        'enabled' => false,
    ]))->toThrow(QueryException::class);
});
