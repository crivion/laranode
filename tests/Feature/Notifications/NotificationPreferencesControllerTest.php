<?php

use App\Models\User;

test('GET /profile/notifications returns Inertia page with required props', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withoutVite()
        ->get('/profile/notifications');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->has('eventTypes')
            ->has('channels')
            ->has('preferences')
            ->has('webhookUrl')
        );
});

test('PATCH /profile/notifications upserts notification preference row', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/profile/notifications', [
            'event_type' => 'operation.finished',
            'channel' => 'mail',
            'enabled' => false,
        ])
        ->assertStatus(200);

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $user->id,
        'event_type' => 'operation.finished',
        'channel' => 'mail',
        'enabled' => false,
    ]);
});

test('PATCH /profile/notifications with invalid event_type returns 422', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/profile/notifications', [
            'event_type' => 'nonexistent.event',
            'channel' => 'mail',
            'enabled' => true,
        ])
        ->assertStatus(422);
});

test('PATCH /profile/notifications with invalid channel returns 422', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/profile/notifications', [
            'event_type' => 'operation.finished',
            'channel' => 'telegram',
            'enabled' => true,
        ])
        ->assertStatus(422);
});

test('PATCH /profile/notifications/webhook with valid https URL saves encrypted', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/profile/notifications/webhook', [
            'webhook_url' => 'https://hooks.slack.com/x',
        ])
        ->assertStatus(200);

    // Reload user from DB — encrypted cast must round-trip
    $fresh = $user->fresh();
    expect($fresh->webhook_url)->toBe('https://hooks.slack.com/x');
});

test('PATCH /profile/notifications/webhook with ftp scheme returns 422', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/profile/notifications/webhook', [
            'webhook_url' => 'ftp://bad.example.com/hook',
        ])
        ->assertStatus(422);
});

test('PATCH /profile/notifications/webhook with RFC-1918 URL returns 422', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/profile/notifications/webhook', [
            'webhook_url' => 'http://192.168.1.1/hook',
        ])
        ->assertStatus(422);
});

test('PATCH /profile/notifications/webhook with null clears webhook_url', function () {
    $user = User::factory()->create(['webhook_url' => 'https://old.example.com/hook']);

    $this->actingAs($user)
        ->patchJson('/profile/notifications/webhook', [
            'webhook_url' => null,
        ])
        ->assertStatus(200);

    expect($user->fresh()->webhook_url)->toBeNull();
});

test('PATCH /profile/notifications/webhook with IPv6 loopback returns 422', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/profile/notifications/webhook', [
            'webhook_url' => 'http://[::1]/hook',
        ])
        ->assertStatus(422);
});
