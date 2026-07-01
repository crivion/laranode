<?php

use App\Models\Option;
use App\Models\User;
use App\Services\Dashboard\GpuStatsService;
use Illuminate\Support\Facades\Process;

function gpuCmd($process): string
{
    return is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;
}

// ---- detection -----------------------------------------------------------

test('detect finds an NVIDIA GPU and persists the profile', function () {
    Process::fake(function ($p) {
        $c = gpuCmd($p);
        if (str_contains($c, 'command -v nvidia-smi')) return Process::result(''); // exit 0
        if (str_contains($c, 'command -v rocm-smi')) return Process::result(exitCode: 1);
        if (str_contains($c, 'nvidia-smi --query-gpu=name')) return Process::result("NVIDIA GeForce RTX 3080\n");

        return Process::result('');
    });

    $profile = (new GpuStatsService)->detect();

    expect($profile['detected'])->toBeTrue();
    expect($profile['vendor'])->toBe('nvidia');
    expect($profile['name'])->toContain('3080');

    $stored = json_decode(Option::get_option(GpuStatsService::OPTION), true);
    expect($stored['vendor'])->toBe('nvidia');
});

test('detect falls back to AMD when only rocm-smi is present', function () {
    Process::fake(function ($p) {
        $c = gpuCmd($p);
        if (str_contains($c, 'command -v nvidia-smi')) return Process::result(exitCode: 1);
        if (str_contains($c, 'command -v rocm-smi')) return Process::result('');
        if (str_contains($c, 'rocm-smi --showproductname')) {
            return Process::result(json_encode(['card0' => ['Card series' => 'AMD Radeon RX 6800']]));
        }

        return Process::result('');
    });

    $profile = (new GpuStatsService)->detect();

    expect($profile['vendor'])->toBe('amd');
    expect($profile['name'])->toContain('6800');
});

test('detect stores not-detected when no GPU tool exists', function () {
    Process::fake(fn ($p) => Process::result(exitCode: 1)); // every command-v fails

    $profile = (new GpuStatsService)->detect();

    expect($profile['detected'])->toBeFalse();
    expect((bool) json_decode(Option::get_option(GpuStatsService::OPTION), true)['detected'])->toBeFalse();
});

// ---- per-poll stats ------------------------------------------------------

test('stats returns null and does NOT probe when no GPU was detected', function () {
    Option::update_option(GpuStatsService::OPTION, json_encode(['detected' => false, 'vendor' => null]));
    Process::fake();

    $stats = (new GpuStatsService)->stats();

    expect($stats)->toBeNull();
    Process::assertDidntRun(fn ($p) => str_contains(gpuCmd($p), 'nvidia-smi'));
    Process::assertDidntRun(fn ($p) => str_contains(gpuCmd($p), 'rocm-smi'));
});

test('stats queries nvidia-smi when an NVIDIA GPU was detected', function () {
    Option::update_option(GpuStatsService::OPTION, json_encode([
        'detected' => true, 'vendor' => 'nvidia', 'name' => 'RTX 3080', 'tool' => 'nvidia-smi',
    ]));
    Process::fake(function ($p) {
        if (str_contains(gpuCmd($p), 'utilization.gpu')) {
            return Process::result("42, 1536, 8192, 61, 120.50\n");
        }

        return Process::result('');
    });

    $stats = (new GpuStatsService)->stats();

    expect($stats['vendor'])->toBe('nvidia');
    expect($stats['util'])->toBe(42.0);
    expect($stats['vramUsed'])->toBe(1.5);
    expect($stats['vramTotal'])->toBe(8.0);
    expect($stats['temp'])->toBe(61);
    expect($stats['power'])->toBe(121);
});

// ---- pure parsers --------------------------------------------------------

test('parseNvidiaStats handles the csv,noheader,nounits line', function () {
    $stats = GpuStatsService::parseNvidiaStats("13, 2048, 16384, 55, 90.0\n", 'RTX 4090');
    expect($stats['util'])->toBe(13.0);
    expect($stats['vramTotal'])->toBe(16.0);
    expect($stats['name'])->toBe('RTX 4090');
});

// ---- rescan endpoint -----------------------------------------------------

test('an admin can trigger a GPU rescan which persists the result', function () {
    Process::fake(fn ($p) => Process::result(exitCode: 1)); // no GPU tools
    $admin = User::factory()->isAdmin()->create();

    $this->actingAs($admin)
        ->post(route('dashboard.admin.gpuRescan'))
        ->assertRedirect();

    expect(json_decode(Option::get_option(GpuStatsService::OPTION), true)['detected'])->toBeFalse();
});

test('a non-admin cannot trigger a GPU rescan', function () {
    Process::fake();
    $user = User::factory()->isNotAdmin()->create();

    $this->actingAs($user)
        ->post(route('dashboard.admin.gpuRescan'))
        ->assertForbidden();
});

test('parseAmdStats reads util, vram and temp from rocm-smi json', function () {
    $json = json_encode(['card0' => [
        'GPU use (%)' => '23',
        'VRAM Total Memory (B)' => (string) (8 * 1024 ** 3),
        'VRAM Total Used Memory (B)' => (string) (2 * 1024 ** 3),
        'Temperature (Sensor edge) (C)' => '49.0',
        'Average Graphics Package Power (W)' => '85.0',
    ]]);

    $stats = GpuStatsService::parseAmdStats($json, 'RX 6800');
    expect($stats['util'])->toBe(23.0);
    expect($stats['vramUsed'])->toBe(2.0);
    expect($stats['vramTotal'])->toBe(8.0);
    expect($stats['temp'])->toBe(49);
    expect($stats['power'])->toBe(85);
});
