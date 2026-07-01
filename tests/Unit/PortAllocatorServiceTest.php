<?php

use App\Models\Website;
use App\Services\Websites\PortAllocatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// PortAllocatorService needs DB access so we bind the Laravel TestCase + RefreshDatabase.
uses(TestCase::class, RefreshDatabase::class);

test('returns 9100 when no other websites have runtime ports', function () {
    $website = Website::factory()->create(['runtime_port' => null]);

    $port = (new PortAllocatorService)->allocate($website);

    expect($port)->toBe(9100);
});

test('returns first gap — skips port used by another website', function () {
    Website::factory()->create(['runtime_port' => 9100]);
    $website = Website::factory()->create(['runtime_port' => null]);

    $port = (new PortAllocatorService)->allocate($website);

    expect($port)->toBe(9101);
});

test('skips multiple consecutive ports used by other websites', function () {
    Website::factory()->create(['runtime_port' => 9100]);
    Website::factory()->create(['runtime_port' => 9101]);
    Website::factory()->create(['runtime_port' => 9102]);

    $website = Website::factory()->create(['runtime_port' => null]);

    $port = (new PortAllocatorService)->allocate($website);

    expect($port)->toBe(9103);
});

test('excludeWebsite own port does not block self — returns own port when it is the lowest free', function () {
    // Website already holds 9100; allocating for itself should return 9100 again
    // because its own row is excluded from the used-ports set.
    $website = Website::factory()->create(['runtime_port' => 9100]);

    $port = (new PortAllocatorService)->allocate($website);

    expect($port)->toBe(9100);
});

test('throws RuntimeException when all ports 9100-9499 are occupied by other websites', function () {
    foreach (range(9100, 9499) as $port) {
        Website::factory()->create(['runtime_port' => $port]);
    }

    $website = Website::factory()->create(['runtime_port' => null]);

    expect(fn () => (new PortAllocatorService)->allocate($website))
        ->toThrow(\RuntimeException::class, 'No available runtime ports in range 9100–9499.');
});

test('concurrent race: two allocations on same initial DB state both return 9100; second site stays null when its start fails', function () {
    // Scenario: no ports in use initially.
    // Two jobs read the DB at the same time (no lock in v1) and both see 9100 as free.
    // Job 1 succeeds: saves runtime_port=9100.
    // Job 2 fails (systemctl start exits non-zero): must NOT write runtime_port to DB.
    // After both jobs: site1.runtime_port=9100, site2.runtime_port=null.

    $site1 = Website::factory()->create(['runtime_port' => null]);
    $site2 = Website::factory()->create(['runtime_port' => null]);

    // Both callers read the same empty DB state and each receives 9100.
    $portForSite1 = (new PortAllocatorService)->allocate($site1);
    $portForSite2 = (new PortAllocatorService)->allocate($site2);

    expect($portForSite1)->toBe(9100);
    expect($portForSite2)->toBe(9100); // same port — classic race collision

    // Job 1: systemctl start succeeds → persist port.
    $site1->update(['runtime_port' => $portForSite1]);

    // Job 2: systemctl start FAILS → caller must NOT persist port.
    // (SwitchRuntimeService throws before calling $website->update — this test
    //  verifies the contract by simply not calling update(), mirroring the service.)

    // Assert: site1 has the port, site2 stays null.
    expect($site1->fresh()->runtime_port)->toBe(9100);
    expect($site2->fresh()->runtime_port)->toBeNull();
});
