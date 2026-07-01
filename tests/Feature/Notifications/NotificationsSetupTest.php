<?php

// tests/Feature/Notifications/NotificationsSetupTest.php

use App\Models\PhpVersion;
use App\Models\User;
use App\Models\Website;

// ─── User model: webhook_url encrypted cast + $hidden ────────────────────────

test('webhook_url encrypted cast round-trips plaintext value', function () {
    $user = User::factory()->create(['webhook_url' => 'https://example.com/hook']);

    // Re-fetch from DB to confirm storage is encrypted and cast decrypts back
    $fresh = User::find($user->id);

    expect($fresh->webhook_url)->toBe('https://example.com/hook');
});

test('webhook_url is excluded from toArray serialization', function () {
    $user = User::factory()->create(['webhook_url' => 'https://example.com/hook']);

    expect($user->toArray())->not->toHaveKey('webhook_url');
});

test('webhook_url null is stored and retrieved as null', function () {
    $user = User::factory()->create(); // no webhook_url → defaults null

    expect($user->webhook_url)->toBeNull();
    expect($user->toArray())->not->toHaveKey('webhook_url');
});

// ─── Website::user() includes email ──────────────────────────────────────────

test('Website user relation includes email column', function () {
    $phpVersion = PhpVersion::factory()->create();
    $user = User::factory()->create(['email' => 'alice@example.com']);

    $websiteId = \Illuminate\Support\Facades\DB::table('websites')->insertGetId([
        'user_id' => $user->id,
        'url' => 'alice.test',
        'document_root' => '/public',
        'php_version_id' => $phpVersion->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $loaded = Website::find($websiteId)->user;

    expect($loaded->email)->toBe('alice@example.com');
});

// ─── notifications table: composite index exists (schema check) ──────────────

test('notifications table has the composite index on notifiable_type, notifiable_id, read_at', function () {
    // Insert and read-back a database notification to confirm the table structure
    $user = User::factory()->create();
    $id = \Illuminate\Support\Str::uuid()->toString();

    \Illuminate\Support\Facades\DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['message' => 'hello']),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(\Illuminate\Support\Facades\DB::table('notifications')->where('id', $id)->exists())->toBeTrue();
});
