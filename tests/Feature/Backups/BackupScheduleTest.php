<?php

use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Models\User;
use App\Services\Backups\PruneBackupsService;
use Carbon\Carbon;

test('schedule next run is computed for each frequency', function () {
    $from = Carbon::parse('2026-09-17 12:00:00');
    $daily = new BackupSchedule(['frequency' => 'daily', 'run_at' => '02:00']);
    $weekly = new BackupSchedule(['frequency' => 'weekly', 'run_at' => '13:00', 'day_of_week' => 5]);
    $monthly = new BackupSchedule(['frequency' => 'monthly', 'run_at' => '13:00', 'day_of_month' => 20]);
    expect($daily->calculateNextRun($from)->toDateTimeString())->toBe('2026-09-18 02:00:00')
        ->and($weekly->calculateNextRun($from)->toDateTimeString())->toBe('2026-09-18 13:00:00')
        ->and($monthly->calculateNextRun($from)->toDateTimeString())->toBe('2026-09-20 13:00:00');
});

test('retention keeps the newest scheduled backups', function () {
    $user = User::factory()->create();
    BackupSchedule::create([
        'user_id' => $user->id, 'scope' => 'account', 'frequency' => 'daily',
        'run_at' => '02:00', 'retention_count' => 2, 'enabled' => true,
    ]);
    foreach (range(1, 4) as $day) {
        $backup = Backup::create([
            'user_id' => $user->id, 'scope' => 'account', 'kind' => 'scheduled',
            'status' => 'completed',
        ]);
        $backup->timestamps = false;
        $backup->created_at = Carbon::parse("2026-09-0{$day}");
        $backup->save();
    }

    app(PruneBackupsService::class)->handle();

    expect(Backup::count())->toBe(2)
        ->and(Backup::oldest()->first()->created_at->toDateString())->toBe('2026-09-03');
});
