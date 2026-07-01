<?php

use App\Models\Database;
use App\Models\User;
use App\Models\UserResourceSnapshot;
use App\Models\UserSiteStat;
use App\Models\Website;

// ─── Unauthenticated access ────────────────────────────────────────────────────

test('unauthenticated request redirects to login', function () {
    $this->get(route('analytics.index'))
        ->assertRedirect(route('login'));
});

// ─── Authenticated non-admin ───────────────────────────────────────────────────

test('non-admin user gets 200 with Inertia Analytics/Index component', function () {
    $user = User::factory()->isNotAdmin()->create();

    $this->actingAs($user)
        ->withoutVite()
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Analytics/Index')
            ->has('resourceHistory')
            ->has('siteStats')
            ->has('quotaSummary')
            ->has('sslOverview')
        );
});

test('resourceHistory contains only the authenticated user rows', function () {
    $user = User::factory()->isNotAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();

    UserResourceSnapshot::create([
        'user_id' => $user->id,
        'snapshotted_at' => now(),
        'disk_bytes' => 1000,
    ]);
    UserResourceSnapshot::create([
        'user_id' => $other->id,
        'snapshotted_at' => now(),
        'disk_bytes' => 9999,
    ]);

    $this->actingAs($user)
        ->withoutVite()
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Analytics/Index')
            ->has('resourceHistory', 1)
        );
});

test('siteStats is empty when auth user has no site stats', function () {
    $user = User::factory()->isNotAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();

    // Create a site stat for the other user — should not appear for $user
    $otherSite = Website::factory()->for($other)->create();
    UserSiteStat::create([
        'website_id' => $otherSite->id,
        'user_id' => $other->id,
        'snapshotted_at' => now(),
        'disk_bytes' => 5000,
    ]);

    $this->actingAs($user)
        ->withoutVite()
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Analytics/Index')
            ->has('siteStats', 0)
        );
});

// ─── Admin sees only their own data ───────────────────────────────────────────

test('admin user sees exactly their own resourceHistory row and not other users rows', function () {
    $admin = User::factory()->isAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();

    UserResourceSnapshot::create([
        'user_id' => $admin->id,
        'snapshotted_at' => now(),
        'disk_bytes' => 111,
    ]);
    UserResourceSnapshot::create([
        'user_id' => $other->id,
        'snapshotted_at' => now(),
        'disk_bytes' => 222,
    ]);

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Analytics/Index')
            ->has('resourceHistory', 1)
        );
});

// ─── Empty state ───────────────────────────────────────────────────────────────

test('resourceHistory is empty collection when no snapshots exist (no 500)', function () {
    $user = User::factory()->isNotAdmin()->create();

    $this->actingAs($user)
        ->withoutVite()
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Analytics/Index')
            ->has('resourceHistory', 0)
        );
});

// ─── quotaSummary ──────────────────────────────────────────────────────────────

test('quotaSummary contains correct counts for the authenticated user', function () {
    $user = User::factory()->isNotAdmin()->create([
        'domain_limit' => 5,
        'database_limit' => 10,
    ]);

    Website::factory()->for($user)->create();
    Website::factory()->for($user)->create();
    Database::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->withoutVite()
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Analytics/Index')
            ->where('quotaSummary.websites_count', 2)
            ->where('quotaSummary.websites_limit', 5)
            ->where('quotaSummary.databases_count', 1)
            ->where('quotaSummary.databases_limit', 10)
        );
});

// ─── sslOverview ───────────────────────────────────────────────────────────────

test('sslOverview returns only the authenticated user sites', function () {
    $user = User::factory()->isNotAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();

    Website::factory()->for($user)->create(['ssl_enabled' => true, 'ssl_status' => 'active']);
    Website::factory()->for($user)->create(['ssl_enabled' => false, 'ssl_status' => 'inactive']);
    Website::factory()->for($other)->create(['ssl_enabled' => true, 'ssl_status' => 'active']);

    $this->actingAs($user)
        ->withoutVite()
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Analytics/Index')
            ->has('sslOverview', 2)
        );
});

test('sslOverview rows include ssl_expires_at field (nullable)', function () {
    $user = User::factory()->isNotAdmin()->create();
    Website::factory()->for($user)->create([
        'ssl_enabled' => false,
        'ssl_status' => 'inactive',
        'ssl_expires_at' => null,
    ]);

    $this->actingAs($user)
        ->withoutVite()
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Analytics/Index')
            ->has('sslOverview', 1)
            ->where('sslOverview.0.ssl_expires_at', null)
        );
});

// ─── Admin multi-tenant isolation (siteStats) ─────────────────────────────────

test('admin user sees only their own siteStats rows and not other tenants', function () {
    $admin = User::factory()->isAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();

    $adminSite = Website::factory()->for($admin)->create();
    $otherSite = Website::factory()->for($other)->create();

    UserSiteStat::create([
        'website_id' => $adminSite->id,
        'user_id' => $admin->id,
        'snapshotted_at' => now(),
        'disk_bytes' => 1111,
    ]);
    UserSiteStat::create([
        'website_id' => $otherSite->id,
        'user_id' => $other->id,
        'snapshotted_at' => now(),
        'disk_bytes' => 9999,
    ]);

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Analytics/Index')
            ->has('siteStats', 1)
        );
});

// ─── Admin multi-tenant isolation (sslOverview) ───────────────────────────────

test('admin user sees only their own sslOverview sites and not other tenants', function () {
    $admin = User::factory()->isAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();

    Website::factory()->for($admin)->create(['ssl_enabled' => true, 'ssl_status' => 'active']);
    Website::factory()->for($other)->create(['ssl_enabled' => true, 'ssl_status' => 'active']);
    Website::factory()->for($other)->create(['ssl_enabled' => true, 'ssl_status' => 'active']);

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Analytics/Index')
            ->has('sslOverview', 1)
        );
});

// ─── Admin multi-tenant isolation (quotaSummary) ──────────────────────────────

test('admin quotaSummary reflects only their own websites and databases not other tenants', function () {
    $admin = User::factory()->isAdmin()->create([
        'domain_limit' => 3,
        'database_limit' => 5,
    ]);
    $other = User::factory()->isNotAdmin()->create();

    // Admin owns 1 site and 1 database
    Website::factory()->for($admin)->create();
    Database::factory()->create(['user_id' => $admin->id]);

    // Other tenant owns 2 sites and 3 databases — must NOT bleed into admin's quotaSummary
    Website::factory()->for($other)->create();
    Website::factory()->for($other)->create();
    Database::factory()->create(['user_id' => $other->id]);
    Database::factory()->create(['user_id' => $other->id]);
    Database::factory()->create(['user_id' => $other->id]);

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Analytics/Index')
            ->where('quotaSummary.websites_count', 1)
            ->where('quotaSummary.websites_limit', 3)
            ->where('quotaSummary.databases_count', 1)
            ->where('quotaSummary.databases_limit', 5)
        );
});
