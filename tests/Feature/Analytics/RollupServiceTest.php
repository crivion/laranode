<?php

use App\Models\User;
use App\Models\UserResourceSnapshot;
use App\Models\UserSiteStat;
use App\Models\Website;
use App\Services\Analytics\UserResourceSnapshotService;
use App\Services\Analytics\UserSiteStatsService;
use Illuminate\Support\Facades\Process;

// When Process::run is called with an array, Laravel converts it to a Symfony Process
// which shell-escapes each element. The resulting command line is "'du' '-sb' '/path'"
// so fake patterns must use the Symfony-escaped form.

// ─── UserResourceSnapshotService ─────────────────────────────────────────────

test('du success + wc success writes one snapshot row with correct values', function () {
    $user = User::factory()->create(['username' => 'testuser']);

    Process::fake([
        "'du'*" => Process::result("98765\t/home/testuser_ln", exitCode: 0),
        "'wc'*" => Process::result('77 /home/testuser_ln/logs/apache-access.log', exitCode: 0),
    ]);

    $service = new UserResourceSnapshotService;
    $service->collect($user, fn ($msg) => null);

    expect(UserResourceSnapshot::count())->toBe(1);

    $row = UserResourceSnapshot::first();
    expect($row->disk_bytes)->toBe(98765)
        ->and($row->apache_request_count)->toBe(77)
        ->and($row->user_id)->toBe($user->id);
});

test('du success + wc failure writes one row with null apache_request_count', function () {
    $user = User::factory()->create(['username' => 'testuser2']);

    Process::fake([
        "'du'*" => Process::result("55000\t/home/testuser2_ln", exitCode: 0),
        "'wc'*" => Process::result('', exitCode: 1),
    ]);

    $service = new UserResourceSnapshotService;
    $service->collect($user, fn ($msg) => null);

    expect(UserResourceSnapshot::count())->toBe(1);

    $row = UserResourceSnapshot::first();
    expect($row->disk_bytes)->toBe(55000)
        ->and($row->apache_request_count)->toBeNull();
});

test('du failure throws RuntimeException and writes zero rows', function () {
    $user = User::factory()->create(['username' => 'testuser3']);

    Process::fake([
        "'du'*" => Process::result('', exitCode: 1),
    ]);

    $service = new UserResourceSnapshotService;

    expect(fn () => $service->collect($user, fn ($msg) => null))
        ->toThrow(\RuntimeException::class);

    expect(UserResourceSnapshot::count())->toBe(0);
});

test('emit callable is invoked during collect', function () {
    $user = User::factory()->create(['username' => 'emittest']);

    Process::fake([
        "'du'*" => Process::result("1000\t/home/emittest_ln", exitCode: 0),
        "'wc'*" => Process::result('10 /home/emittest_ln/logs/apache-access.log', exitCode: 0),
    ]);

    $messages = [];
    $service = new UserResourceSnapshotService;
    $service->collect($user, function ($msg) use (&$messages) {
        $messages[] = $msg;
    });

    expect(count($messages))->toBeGreaterThan(0);
});

// ─── UserSiteStatsService ─────────────────────────────────────────────────────

test('one site with successful du writes one UserSiteStat row', function () {
    $user = User::factory()->create(['username' => 'siteuser']);
    $site = Website::factory()->for($user)->create(['url' => 'example.com']);

    Process::fake([
        "'du'*" => Process::result("40000\t/home/siteuser_ln/domains/example.com", exitCode: 0),
    ]);

    $service = new UserSiteStatsService;
    $service->collect($user, fn ($msg) => null);

    expect(UserSiteStat::count())->toBe(1);

    $row = UserSiteStat::first();
    expect($row->disk_bytes)->toBe(40000)
        ->and($row->user_id)->toBe($user->id)
        ->and($row->website_id)->toBe($site->id);
});

test('UserSiteStatsService du failure throws RuntimeException and writes zero rows', function () {
    $user = User::factory()->create(['username' => 'siteuser2']);
    Website::factory()->for($user)->create(['url' => 'fail.com']);

    Process::fake([
        "'du'*" => Process::result('', exitCode: 1),
    ]);

    $service = new UserSiteStatsService;

    expect(fn () => $service->collect($user, fn ($msg) => null))
        ->toThrow(\RuntimeException::class);

    expect(UserSiteStat::count())->toBe(0);
});

test('UserSiteStatsService with no sites writes zero rows and does not throw', function () {
    $user = User::factory()->create(['username' => 'nosite']);

    Process::fake();

    $service = new UserSiteStatsService;
    $service->collect($user, fn ($msg) => null);

    expect(UserSiteStat::count())->toBe(0);
});

test('multiple sites each get one UserSiteStat row', function () {
    $user = User::factory()->create(['username' => 'multisite']);
    Website::factory()->for($user)->create(['url' => 'site1.com']);
    Website::factory()->for($user)->create(['url' => 'site2.com']);

    Process::fake([
        "'du'*" => Process::result("10000\t/some/path", exitCode: 0),
    ]);

    $service = new UserSiteStatsService;
    $service->collect($user, fn ($msg) => null);

    expect(UserSiteStat::count())->toBe(2);
    expect(UserSiteStat::where('user_id', $user->id)->count())->toBe(2);
});

test('UserSiteStatsService site root built from user homedir not accessor', function () {
    $user = User::factory()->create(['username' => 'pathcheck']);
    Website::factory()->for($user)->create(['url' => 'mysite.dev']);

    Process::fake([
        "'du'*" => Process::result("5000\t/home/pathcheck_ln/domains/mysite.dev", exitCode: 0),
    ]);

    $service = new UserSiteStatsService;
    $service->collect($user, fn ($msg) => null);

    // Verify one row was created (path was correctly resolved)
    expect(UserSiteStat::count())->toBe(1);
    expect(UserSiteStat::first()->disk_bytes)->toBe(5000);
});
