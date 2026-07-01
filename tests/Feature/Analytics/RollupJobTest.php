<?php

use App\Jobs\Analytics\RollupSiteStatsJob;
use App\Jobs\Analytics\RollupUserResourceSnapshotJob;
use App\Models\Operation;
use App\Models\User;
use App\Models\UserResourceSnapshot;
use App\Models\UserSiteStat;
use App\Models\Website;
use Illuminate\Support\Facades\Process;

// ─── RollupUserResourceSnapshotJob ───────────────────────────────────────────

test('RollupUserResourceSnapshotJob: du+wc success -> one snapshot row, operation succeeded', function () {
    $user = User::factory()->create(['username' => 'jobuser']);
    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'analytics.resource-rollup',
        'target' => $user->username,
        'status' => 'queued',
    ]);

    Process::fake([
        "'du'*" => Process::result("98765\t/home/jobuser_ln", exitCode: 0),
        "'wc'*" => Process::result('77 /home/jobuser_ln/logs/apache-access.log', exitCode: 0),
    ]);

    RollupUserResourceSnapshotJob::dispatchSync($operation, $user);

    expect(UserResourceSnapshot::count())->toBe(1);
    expect($operation->fresh()->status)->toBe('succeeded');
});

test('RollupUserResourceSnapshotJob: two users dispatched separately -> two rows, each scoped to correct user_id', function () {
    $userA = User::factory()->create(['username' => 'userA']);
    $userB = User::factory()->create(['username' => 'userB']);

    $opA = Operation::create([
        'user_id' => $userA->id,
        'type' => 'analytics.resource-rollup',
        'target' => $userA->username,
        'status' => 'queued',
    ]);
    $opB = Operation::create([
        'user_id' => $userB->id,
        'type' => 'analytics.resource-rollup',
        'target' => $userB->username,
        'status' => 'queued',
    ]);

    Process::fake([
        "'du'*" => Process::result("10000\t/home/someuser_ln", exitCode: 0),
        "'wc'*" => Process::result('5 /home/someuser_ln/logs/apache-access.log', exitCode: 0),
    ]);

    RollupUserResourceSnapshotJob::dispatchSync($opA, $userA);
    RollupUserResourceSnapshotJob::dispatchSync($opB, $userB);

    expect(UserResourceSnapshot::count())->toBe(2);
    expect(UserResourceSnapshot::where('user_id', $userA->id)->count())->toBe(1);
    expect(UserResourceSnapshot::where('user_id', $userB->id)->count())->toBe(1);
    expect($opA->fresh()->status)->toBe('succeeded');
    expect($opB->fresh()->status)->toBe('succeeded');
});

test('RollupUserResourceSnapshotJob: du exit code 1 -> operation failed, zero snapshot rows', function () {
    $user = User::factory()->create(['username' => 'dufail']);
    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'analytics.resource-rollup',
        'target' => $user->username,
        'status' => 'queued',
    ]);

    Process::fake([
        "'du'*" => Process::result('', exitCode: 1),
    ]);

    // OperationJob re-throws after marking failed
    expect(fn () => RollupUserResourceSnapshotJob::dispatchSync($operation, $user))
        ->toThrow(\RuntimeException::class);

    expect($operation->fresh()->status)->toBe('failed');
    expect(UserResourceSnapshot::count())->toBe(0);
});

test('RollupUserResourceSnapshotJob: wc exit code 1 -> operation succeeded, apache_request_count null', function () {
    $user = User::factory()->create(['username' => 'wcfail']);
    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'analytics.resource-rollup',
        'target' => $user->username,
        'status' => 'queued',
    ]);

    Process::fake([
        "'du'*" => Process::result("50000\t/home/wcfail_ln", exitCode: 0),
        "'wc'*" => Process::result('', exitCode: 1),
    ]);

    RollupUserResourceSnapshotJob::dispatchSync($operation, $user);

    expect($operation->fresh()->status)->toBe('succeeded');
    expect(UserResourceSnapshot::count())->toBe(1);
    expect(UserResourceSnapshot::first()->apache_request_count)->toBeNull();
});

// ─── RollupSiteStatsJob ───────────────────────────────────────────────────────

test('RollupSiteStatsJob: one site -> one UserSiteStat row, operation succeeded', function () {
    $user = User::factory()->create(['username' => 'siteowner']);
    Website::factory()->for($user)->create(['url' => 'mysite.com']);

    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'analytics.site-rollup',
        'target' => $user->username,
        'status' => 'queued',
    ]);

    Process::fake([
        "'du'*" => Process::result("30000\t/home/siteowner_ln/domains/mysite.com", exitCode: 0),
    ]);

    RollupSiteStatsJob::dispatchSync($operation, $user);

    expect(UserSiteStat::count())->toBe(1);
    expect($operation->fresh()->status)->toBe('succeeded');
});

test('RollupSiteStatsJob: du exit code 1 -> operation failed, zero UserSiteStat rows', function () {
    $user = User::factory()->create(['username' => 'sitefail']);
    Website::factory()->for($user)->create(['url' => 'broken.com']);

    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'analytics.site-rollup',
        'target' => $user->username,
        'status' => 'queued',
    ]);

    Process::fake([
        "'du'*" => Process::result('', exitCode: 1),
    ]);

    expect(fn () => RollupSiteStatsJob::dispatchSync($operation, $user))
        ->toThrow(\RuntimeException::class);

    expect($operation->fresh()->status)->toBe('failed');
    expect(UserSiteStat::count())->toBe(0);
});
