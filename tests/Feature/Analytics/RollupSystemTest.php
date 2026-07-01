<?php

// tests/Feature/Analytics/RollupSystemTest.php
//
// Real system integration tests gated behind LARANODE_SYSTEM_TESTS=1.
// Verifies that UserResourceSnapshotService and UserSiteStatsService work
// against real system commands (du, wc -l) in the local-dev container.
//
// Run inside the local-dev container:
//   LARANODE_SYSTEM_TESTS=1 php artisan test --filter=RollupSystemTest

use App\Models\User;
use App\Models\UserResourceSnapshot;
use App\Models\UserSiteStat;
use App\Models\Website;
use App\Services\Analytics\UserResourceSnapshotService;
use App\Services\Analytics\UserSiteStatsService;

// Gate: skip the entire file unless LARANODE_SYSTEM_TESTS is set.
beforeEach(function () {
    if (! env('LARANODE_SYSTEM_TESTS')) {
        $this->markTestSkipped('LARANODE_SYSTEM_TESTS not set — skipping system integration tests.');
    }
});

test('[system] real du on container user homedir produces a snapshot with disk_bytes > 0', function () {
    // The laranode panel user's homedir /home/laranode_ln is provisioned in the container.
    $user = User::factory()->create(['username' => 'laranode']);

    $service = new UserResourceSnapshotService;
    $service->collect($user, fn ($msg) => null);

    expect(UserResourceSnapshot::count())->toBe(1);
    expect(UserResourceSnapshot::first()->disk_bytes)->toBeGreaterThan(0);
});

test('[system] real du on real website directory produces UserSiteStat with disk_bytes > 0', function () {
    $user = User::factory()->create(['username' => 'laranode']);

    $homedir = $user->homedir;
    $siteUrl = 'system-test-site.local';
    $siteRoot = $homedir.'/domains/'.$siteUrl;

    // Create the directory and a small file for du to measure
    if (! is_dir($siteRoot)) {
        mkdir($siteRoot, 0755, true);
        file_put_contents($siteRoot.'/index.html', '<html>test</html>');
    }

    $site = Website::factory()->for($user)->create(['url' => $siteUrl]);

    $service = new UserSiteStatsService;
    $service->collect($user, fn ($msg) => null);

    expect(UserSiteStat::count())->toBe(1);
    expect(UserSiteStat::first()->disk_bytes)->toBeGreaterThan(0);

    // Cleanup
    @unlink($siteRoot.'/index.html');
    @rmdir($siteRoot);
});
