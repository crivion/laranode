<?php

use App\Events\SystemStatsEvent;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

// Stub all Process calls issued by SystemStatsService::getAllStats()
// to prevent real system calls in CI and to satisfy Process::preventStrayProcesses().
function fakeAllSystemStatsProcesses(): void
{
    Process::fake([
        // getCpuUsage
        'top*' => Process::result(output: "5.0\n"),
        // getLoadTimes
        'uptime | awk*' => Process::result(output: " 0.10, 0.05, 0.01\n"),
        // getUptime
        'uptime -p' => Process::result(output: "up 1 hour\n"),
        // getProcessCount
        'ps aux | wc -l' => Process::result(output: "42\n"),
        // getDiskUsage
        'df -h*' => Process::result(output: "100G 20G 80G 20%\n"),
        // getMemoryUsage (pipe)
        'free -m' => Process::result(output: "1024 512 256 2048\n"),
        // getPhpFpmStatus: list unit files
        'systemctl list-unit-files*' => Process::result(output: ''),
        // getApacheStatus
        'systemctl status apache2*' => Process::result(output: "Active: active (running)\nMemory: 32.0M\n"),
        // getMysqlStatus (pipe)
        'systemctl status mysql*' => Process::result(output: "123 | 64M | 0h1m | 2 days\n"),
        // getNetworkStats (pipe / /proc/net/dev)
        'cat /proc/net/dev*' => Process::result(output: ''),
        // getUserCount
        'who | wc -l' => Process::result(output: "1\n"),
        // catch-all
        '*' => Process::result(output: ''),
    ]);
}

test('SystemStatsEvent writes last-known stats to cache', function () {
    fakeAllSystemStatsProcesses();

    Cache::forget('dashboard_stats_last_known');

    new SystemStatsEvent;

    expect(Cache::has('dashboard_stats_last_known'))->toBeTrue();
});

test('GET /dashboard/admin returns initialStats prop for admin', function () {
    fakeAllSystemStatsProcesses();

    $admin = User::factory()->isAdmin()->create();

    $response = $this
        ->actingAs($admin)
        ->get(route('dashboard.admin'));

    $response->assertInertia(
        fn ($page) => $page
            ->component('Dashboard/Admin/AdminDashboard')
            ->has('initialStats')
    );
});

test('GET /dashboard/admin returns empty initialStats when cache is cold', function () {
    $admin = User::factory()->isAdmin()->create();

    Cache::forget('dashboard_stats_last_known');

    $response = $this
        ->actingAs($admin)
        ->get(route('dashboard.admin'));

    $response->assertInertia(
        fn ($page) => $page
            ->component('Dashboard/Admin/AdminDashboard')
            ->where('initialStats', [])
    );
});
