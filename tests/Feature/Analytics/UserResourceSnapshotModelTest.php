<?php

use App\Models\Database;
use App\Models\User;
use App\Models\UserResourceSnapshot;
use App\Models\UserSiteStat;
use App\Models\Website;

test('UserResourceSnapshot belongs to user', function () {
    $user = User::factory()->create();
    $snapshot = UserResourceSnapshot::create([
        'user_id' => $user->id,
        'snapshotted_at' => now(),
        'disk_bytes' => 12345,
    ]);

    expect($snapshot->user->is($user))->toBeTrue();
});

test('UserResourceSnapshot prunable returns old rows only', function () {
    $user = User::factory()->create();

    $old = UserResourceSnapshot::create([
        'user_id' => $user->id,
        'snapshotted_at' => now()->subDays(91),
        'disk_bytes' => 100,
    ]);
    $old->forceFill(['snapshotted_at' => now()->subDays(91)])->save();

    UserResourceSnapshot::create([
        'user_id' => $user->id,
        'snapshotted_at' => now(),
        'disk_bytes' => 200,
    ]);

    expect((new UserResourceSnapshot)->prunable()->count())->toBe(1);
});

test('admin user row not bleed to other user via explicit where', function () {
    $admin = User::factory()->isAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();

    UserResourceSnapshot::create([
        'user_id' => $admin->id,
        'snapshotted_at' => now(),
        'disk_bytes' => 100,
    ]);
    UserResourceSnapshot::create([
        'user_id' => $other->id,
        'snapshotted_at' => now(),
        'disk_bytes' => 200,
    ]);

    // explicit where — no scopeMine, no admin passthrough
    $count = UserResourceSnapshot::where('user_id', $admin->id)->count();
    expect($count)->toBe(1);
});

test('UserResourceSnapshot stores nullable apache_request_count', function () {
    $user = User::factory()->create();
    $snapshot = UserResourceSnapshot::create([
        'user_id' => $user->id,
        'snapshotted_at' => now(),
        'disk_bytes' => 500,
        'apache_request_count' => null,
    ]);

    expect($snapshot->apache_request_count)->toBeNull();
});

test('UserSiteStat belongs to user and website', function () {
    $user = User::factory()->create();
    $website = Website::factory()->for($user)->create();

    $stat = UserSiteStat::create([
        'website_id' => $website->id,
        'user_id' => $user->id,
        'snapshotted_at' => now(),
        'disk_bytes' => 9999,
    ]);

    expect($stat->user->is($user))->toBeTrue()
        ->and($stat->website->is($website))->toBeTrue();
});

test('UserSiteStat prunable returns old rows only', function () {
    $user = User::factory()->create();
    $website = Website::factory()->for($user)->create();

    $old = UserSiteStat::create([
        'website_id' => $website->id,
        'user_id' => $user->id,
        'snapshotted_at' => now()->subDays(91),
        'disk_bytes' => 100,
    ]);
    $old->forceFill(['snapshotted_at' => now()->subDays(91)])->save();

    UserSiteStat::create([
        'website_id' => $website->id,
        'user_id' => $user->id,
        'snapshotted_at' => now(),
        'disk_bytes' => 200,
    ]);

    expect((new UserSiteStat)->prunable()->count())->toBe(1);
});

test('User databases() relation returns HasMany and does not throw', function () {
    $user = User::factory()->create();

    Database::factory()->create(['user_id' => $user->id]);
    Database::factory()->create(['user_id' => $user->id]);

    expect($user->databases()->count())->toBe(2);
});

test('User databases() relation is HasMany type', function () {
    $user = User::factory()->create();
    expect($user->databases())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('admin UserSiteStat row does not bleed to other user via explicit where', function () {
    $admin = User::factory()->isAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();
    $adminSite = Website::factory()->for($admin)->create();
    $otherSite = Website::factory()->for($other)->create();

    UserSiteStat::create([
        'website_id' => $adminSite->id,
        'user_id' => $admin->id,
        'snapshotted_at' => now(),
        'disk_bytes' => 111,
    ]);
    UserSiteStat::create([
        'website_id' => $otherSite->id,
        'user_id' => $other->id,
        'snapshotted_at' => now(),
        'disk_bytes' => 222,
    ]);

    // explicit where — no scopeMine, no admin passthrough
    $count = UserSiteStat::where('user_id', $admin->id)->count();
    expect($count)->toBe(1);
});
