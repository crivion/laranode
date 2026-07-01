<?php

use App\Models\User;
use Illuminate\Support\Str;

test('GET /notifications returns 200 for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withoutVite()
        ->get('/notifications')
        ->assertStatus(200);
});

test('GET /notifications redirects guest to /login', function () {
    $this->get('/notifications')
        ->assertRedirect('/login');
});

test('PATCH /notifications/read-all is not consumed as {id} parameter', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/notifications/read-all')
        ->assertStatus(200);
});

test('PATCH /notifications/read-all marks only current user notifications as read', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // Insert 3 unread notifications for user A
    foreach (range(1, 3) as $i) {
        \DB::table('notifications')->insert([
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\OperationFinishedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $userA->id,
            'data' => json_encode(['event_type' => 'operation.finished']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Insert 1 unread notification for user B
    \DB::table('notifications')->insert([
        'id' => Str::uuid()->toString(),
        'type' => 'App\\Notifications\\OperationFinishedNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $userB->id,
        'data' => json_encode(['event_type' => 'operation.finished']),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($userA)
        ->patchJson('/notifications/read-all')
        ->assertStatus(200);

    expect($userA->unreadNotifications()->count())->toBe(0);
    expect($userB->unreadNotifications()->count())->toBe(1);
});

test('PATCH /notifications/{id}/read sets read_at on the notification', function () {
    $user = User::factory()->create();

    $notificationId = Str::uuid()->toString();

    \DB::table('notifications')->insert([
        'id' => $notificationId,
        'type' => 'App\\Notifications\\OperationFinishedNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['event_type' => 'operation.finished']),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->patchJson("/notifications/{$notificationId}/read")
        ->assertStatus(200);

    $this->assertDatabaseMissing('notifications', [
        'id' => $notificationId,
        'read_at' => null,
    ]);
});

test('PATCH /notifications/{id}/read returns 404 when notification belongs to another user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $notificationId = Str::uuid()->toString();

    \DB::table('notifications')->insert([
        'id' => $notificationId,
        'type' => 'App\\Notifications\\OperationFinishedNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $userA->id,
        'data' => json_encode(['event_type' => 'operation.finished']),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // userB cannot mark userA's notification as read
    $this->actingAs($userB)
        ->patchJson("/notifications/{$notificationId}/read")
        ->assertStatus(404);

    // Notification remains unread
    $this->assertDatabaseHas('notifications', [
        'id' => $notificationId,
        'read_at' => null,
    ]);
});
