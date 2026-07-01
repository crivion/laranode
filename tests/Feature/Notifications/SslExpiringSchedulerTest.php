<?php

// tests/Feature/Notifications/SslExpiringSchedulerTest.php

use App\Actions\SSL\SendSslExpiryNotificationsAction;
use App\Models\PhpVersion;
use App\Models\User;
use App\Notifications\SslExpiringNotification;
use Illuminate\Support\Facades\Notification;

// ─── Test 1: Website expiring in 7 days → DB notification row created ─────────

test('website expiring in 7 days triggers SslExpiringNotification', function () {
    // Run without Notification::fake() so the real DB write happens.
    $user = User::factory()->create();
    $php = PhpVersion::firstOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);

    $user->websites()->create([
        'url' => 'ssl7.test',
        'document_root' => '/public_html',
        'php_version_id' => $php->id,
        'ssl_enabled' => true,
        'ssl_status' => 'active',
        'ssl_expires_at' => now()->addDays(7),
    ]);

    (new SendSslExpiryNotificationsAction)();

    $this->assertDatabaseHas('notifications', ['type' => SslExpiringNotification::class]);
});

// ─── Test 2: Website expiring in 14 days → notification dispatched ───────────

test('website expiring in 14 days triggers SslExpiringNotification', function () {
    Notification::fake();

    $user = User::factory()->create();
    $php = PhpVersion::firstOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);

    $user->websites()->create([
        'url' => 'ssl14.test',
        'document_root' => '/public_html',
        'php_version_id' => $php->id,
        'ssl_enabled' => true,
        'ssl_status' => 'active',
        'ssl_expires_at' => now()->addDays(14),
    ]);

    (new SendSslExpiryNotificationsAction)();

    Notification::assertSentTo($user, SslExpiringNotification::class);
});

// ─── Test 3: Website expiring in 30 days → no notification ───────────────────

test('website expiring in 30 days sends no notification', function () {
    Notification::fake();

    $user = User::factory()->create();
    $php = PhpVersion::firstOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);

    $user->websites()->create([
        'url' => 'ssl30.test',
        'document_root' => '/public_html',
        'php_version_id' => $php->id,
        'ssl_enabled' => true,
        'ssl_status' => 'active',
        'ssl_expires_at' => now()->addDays(30),
    ]);

    (new SendSslExpiryNotificationsAction)();

    Notification::assertNothingSent();
});

// ─── Test 4: schedule:list contains ssl.expiring notifications ────────────────

test('schedule list contains ssl.expiring notifications entry', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('ssl.expiring notifications')
        ->assertExitCode(0);
});
