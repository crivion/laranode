<?php

// tests/Feature/Backups/SchedulerBackupTest.php

use App\Jobs\BackupJob;
use App\Jobs\RetainBackupsJob;
use App\Jobs\RunScheduledBackupsJob;
use App\Models\Backup;
use App\Models\ScheduledBackup;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

// ─────────────────────────────────────────────────────────────────────────────
// Test 1: RunScheduledBackupsJob dispatches BackupJob for due enabled schedules
// ─────────────────────────────────────────────────────────────────────────────

test('RunScheduledBackupsJob dispatches BackupJob for due enabled schedules', function () {
    // Bus::fake() intercepts BackupJob::dispatch() inside BackupService::handle()
    // without preventing Backup::create() — so we can assert both the Backup row
    // and that BackupJob was actually dispatched.
    Bus::fake();

    $user = User::factory()->create();

    // cron: every minute — always due
    ScheduledBackup::factory()->create([
        'user_id' => $user->id,
        'type' => 'db',
        'target' => 'mydb_ln',
        'storage' => 'local',
        'disk_name' => 'backups',
        'cron_expression' => '* * * * *',
        'enabled' => true,
        'last_run_at' => null,
    ]);

    (new RunScheduledBackupsJob)->handle();

    // BackupService::handle() creates the Backup row before dispatching the job.
    $backup = Backup::where('target', 'mydb_ln')->where('user_id', $user->id)->first();
    expect($backup)->not->toBeNull('Backup row should be created by BackupService::handle()');

    // The backup job was dispatched.
    Bus::assertDispatched(BackupJob::class);

    // Retention job must also be dispatched per due entry.
    Bus::assertDispatched(RetainBackupsJob::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 2: RunScheduledBackupsJob skips disabled schedules
// ─────────────────────────────────────────────────────────────────────────────

test('RunScheduledBackupsJob skips disabled schedules', function () {
    $user = User::factory()->create();

    ScheduledBackup::factory()->disabled()->create([
        'user_id' => $user->id,
        'type' => 'db',
        'target' => 'disableddb_ln',
        'cron_expression' => '* * * * *',
    ]);

    (new RunScheduledBackupsJob)->handle();

    expect(Backup::where('target', 'disableddb_ln')->exists())->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 3: RunScheduledBackupsJob skips entries whose last_run_at is within 50s
// ─────────────────────────────────────────────────────────────────────────────

test('RunScheduledBackupsJob skips entries whose last_run_at is within 50 seconds', function () {
    $user = User::factory()->create();

    ScheduledBackup::factory()->create([
        'user_id' => $user->id,
        'type' => 'db',
        'target' => 'recentdb_ln',
        'cron_expression' => '* * * * *',
        'enabled' => true,
        'last_run_at' => now()->subSeconds(30), // 30s ago — within 50s window
    ]);

    (new RunScheduledBackupsJob)->handle();

    expect(Backup::where('target', 'recentdb_ln')->exists())->toBeFalse(
        'Schedule run within 50 seconds should be skipped'
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 4: RunScheduledBackupsJob updates last_run_at after dispatching
// ─────────────────────────────────────────────────────────────────────────────

test('RunScheduledBackupsJob updates last_run_at after dispatching', function () {
    // Fake the bus so BackupJob / RetainBackupsJob are not actually executed.
    Bus::fake();

    $user = User::factory()->create();

    $schedule = ScheduledBackup::factory()->create([
        'user_id' => $user->id,
        'type' => 'db',
        'target' => 'updatedb_ln',
        'cron_expression' => '* * * * *',
        'enabled' => true,
        'last_run_at' => null,
    ]);

    // Truncate to seconds because MySQL stores timestamps without sub-second precision.
    $before = now()->startOfSecond();

    (new RunScheduledBackupsJob)->handle();

    $schedule->refresh();
    expect($schedule->last_run_at)->not->toBeNull();
    expect($schedule->last_run_at->gte($before))->toBeTrue(
        'last_run_at should be updated to now() AFTER dispatching BackupJob + RetainBackupsJob'
    );

    // Verify both jobs were dispatched (ensures last_run_at is stamped after, not before).
    Bus::assertDispatched(BackupJob::class);
    Bus::assertDispatched(RetainBackupsJob::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 5: RetainBackupsJob prunes backups beyond retention_count
// ─────────────────────────────────────────────────────────────────────────────

test('RetainBackupsJob prunes backups beyond retention_count, deleting files and rows', function () {
    Storage::fake('backups');

    $user = User::factory()->create();

    $schedule = ScheduledBackup::factory()->create([
        'user_id' => $user->id,
        'type' => 'db',
        'target' => 'prunedb_ln',
        'storage' => 'local',
        'disk_name' => 'backups',
        'retention_count' => 3,
    ]);

    // Create 5 completed backups on the fake disk with actual files
    $backups = collect();

    for ($i = 1; $i <= 5; $i++) {
        $path = "backups/{$user->id}/db/prunedb_ln/2026-01-0{$i}.sql.gz";
        Storage::disk('backups')->put($path, "data-{$i}");

        $backup = Backup::factory()->completed()->create([
            'user_id' => $user->id,
            'type' => 'db',
            'target' => 'prunedb_ln',
            'disk_name' => 'backups',
            'path' => $path,
            'created_at' => now()->subDays(6 - $i), // oldest first
        ]);

        $backups->push($backup);
    }

    expect(Backup::where('target', 'prunedb_ln')->count())->toBe(5);

    RetainBackupsJob::dispatchSync($schedule->id);

    // Only 3 should remain (most recent)
    expect(Backup::where('target', 'prunedb_ln')->count())->toBe(3);

    // The 2 oldest files should be deleted from the disk
    expect(Storage::disk('backups')->exists($backups[0]->path))->toBeFalse();
    expect(Storage::disk('backups')->exists($backups[1]->path))->toBeFalse();

    // The 3 newest files should still exist
    expect(Storage::disk('backups')->exists($backups[2]->path))->toBeTrue();
    expect(Storage::disk('backups')->exists($backups[3]->path))->toBeTrue();
    expect(Storage::disk('backups')->exists($backups[4]->path))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 6: schedule:list contains RunScheduledBackupsJob and model:prune with Backup
// ─────────────────────────────────────────────────────────────────────────────

test('schedule:list output contains RunScheduledBackupsJob and model:prune with Backup', function () {
    $result = \Illuminate\Support\Facades\Artisan::call('schedule:list');
    $output = \Illuminate\Support\Facades\Artisan::output();

    expect($output)->toContain('RunScheduledBackupsJob');
    expect($output)->toContain('model:prune');
    expect($output)->toContain('Backup');
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 7: RetainBackupsJob resolves the disk (incl. S3 creds) from a Backup row,
// not from ScheduledBackup.disk_name, and registers the S3 disk in-process.
// ─────────────────────────────────────────────────────────────────────────────

test('RetainBackupsJob registers the S3 disk from Backup creds before retaining', function () {
    $user = User::factory()->create();

    // Schedule intentionally has NO disk_name — it must be resolved from a Backup row.
    $schedule = ScheduledBackup::factory()->create([
        'user_id' => $user->id,
        'type' => 'db',
        'target' => 's3reg_ln',
        'storage' => 's3',
        'disk_name' => null,
        'retention_count' => 10, // high → nothing is pruned, so no real S3 I/O happens
    ]);

    $diskName = "backups_s3_{$user->id}";

    Backup::factory()->completed()->create([
        'user_id' => $user->id,
        'type' => 'db',
        'target' => 's3reg_ln',
        'storage' => 's3',
        'disk_name' => $diskName,
        's3_key' => 'AKIAEXAMPLE',
        's3_secret' => 'secretexample',
        's3_bucket' => 'my-bucket',
        'path' => 'x/1.sql.gz',
        'created_at' => now(),
    ]);

    // Disk is not registered until the job runs.
    expect(config("filesystems.disks.{$diskName}"))->toBeNull();

    RetainBackupsJob::dispatchSync($schedule->id);

    // The job re-registered the S3 disk in this process from the Backup row's
    // encrypted credentials (the worker/scheduler never had this disk config).
    expect(config("filesystems.disks.{$diskName}.driver"))->toBe('s3');
    expect(config("filesystems.disks.{$diskName}.bucket"))->toBe('my-bucket');
});
