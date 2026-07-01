<?php

// tests/Feature/Notifications/UnreadCountSharedPropTest.php
//
// Verifies that HandleInertiaRequests::share() exposes notifications.unreadCount
// as a shared Inertia prop that reflects the authenticated user's unread count.

use App\Models\User;
use Illuminate\Support\Str;

// ─── Test 1: authenticated user with N unread notifications returns correct count ─

test('authenticated user with unread notifications returns correct unread count in shared props', function () {
    $user = User::factory()->create();

    // Insert 3 unread notification rows directly
    foreach (range(1, 3) as $i) {
        $user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\OperationFinishedNotification',
            'data' => ['event_type' => 'operation_finished'],
            'read_at' => null,
        ]);
    }

    // Insert 1 already-read notification (must NOT be counted)
    $user->notifications()->create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\\Notifications\\OperationFinishedNotification',
        'data' => ['event_type' => 'operation_finished'],
        'read_at' => now(),
    ]);

    $this->actingAs($user)
        ->withoutVite()
        ->get('/profile')
        ->assertInertia(fn ($page) => $page
            ->where('notifications.unreadCount', 3)
        );
});

// ─── Test 2: user with no unread notifications returns 0 ────────────────────────

test('authenticated user with no notifications returns 0 unread count in shared props', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withoutVite()
        ->get('/profile')
        ->assertInertia(fn ($page) => $page
            ->where('notifications.unreadCount', 0)
        );
});

// ─── Test 3: unauthenticated request returns 0 unread count ─────────────────────

test('unauthenticated request returns 0 unread count in shared props', function () {
    // Unauthenticated requests to Inertia pages redirect to /login.
    // The middleware still runs and must not throw — verify prop is 0
    // by hitting the login page itself (no auth required).
    $response = $this->withoutVite()->get('/login');

    // The login page is rendered via Inertia; shared props include notifications.
    $response->assertInertia(fn ($page) => $page
        ->where('notifications.unreadCount', 0)
    );
});
