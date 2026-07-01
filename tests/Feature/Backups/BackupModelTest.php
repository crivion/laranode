<?php

// tests/Feature/Backups/BackupModelTest.php

use App\Models\Backup;
use App\Models\ScheduledBackup;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

// ─────────────────────────────────────────────────────────────────────────────
// Backup model
// ─────────────────────────────────────────────────────────────────────────────

test('backup belongs to user and defaults to pending', function () {
    $user = User::factory()->create();
    $backup = Backup::create([
        'user_id' => $user->id,
        'type' => 'db',
        'target' => 'mydb_ln',
        'storage' => 'local',
    ]);

    expect($backup->status)->toBe('pending')
        ->and($backup->user->is($user))->toBeTrue();
});

test('Backup scopeMine restricts non-admins to own rows', function () {
    $admin = User::factory()->isAdmin()->create();
    $user = User::factory()->isNotAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();

    Backup::factory()->create(['user_id' => $user->id]);
    Backup::factory()->create(['user_id' => $user->id]);
    Backup::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user);
    expect(Backup::mine()->count())->toBe(2);

    $this->actingAs($admin);
    expect(Backup::mine()->count())->toBe(3);
});

test('Backup MassPrunable targets rows older than 90 days', function () {
    $user = User::factory()->create();

    $old = Backup::factory()->create(['user_id' => $user->id]);
    $old->forceFill(['created_at' => now()->subDays(91)])->save();

    Backup::factory()->create(['user_id' => $user->id]); // recent

    expect((new Backup)->prunable()->count())->toBe(1);
});

test('Backup pruning hook deletes file from disk before pruning row', function () {
    Storage::fake('backups');

    $user = User::factory()->create();

    Storage::disk('backups')->put('test/backup.sql.gz', 'data');

    $backup = Backup::factory()->create([
        'user_id' => $user->id,
        'disk_name' => 'backups',
        'path' => 'test/backup.sql.gz',
    ]);

    // Simulate pruning hook
    $backup->pruning();

    Storage::disk('backups')->assertMissing('test/backup.sql.gz');
});

// ─────────────────────────────────────────────────────────────────────────────
// ScheduledBackup model
// ─────────────────────────────────────────────────────────────────────────────

test('ScheduledBackup encrypts s3_key and s3_secret at rest', function () {
    $user = User::factory()->create();

    $schedule = ScheduledBackup::create([
        'user_id' => $user->id,
        'type' => 'db',
        'target' => 'mydb_ln',
        'storage' => 's3',
        'cron_expression' => '0 3 * * *',
        's3_key' => 'AKIAIOSFODNN7EXAMPLE',
        's3_secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
        's3_region' => 'us-east-1',
        's3_bucket' => 'my-backups',
    ]);

    // Value round-trips correctly through the encrypted cast
    expect($schedule->s3_key)->toBe('AKIAIOSFODNN7EXAMPLE')
        ->and($schedule->s3_secret)->toBe('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY');

    // Raw DB column must NOT be the plaintext value
    $raw = \Illuminate\Support\Facades\DB::table('scheduled_backups')
        ->where('id', $schedule->id)
        ->value('s3_key');

    expect($raw)->not->toBe('AKIAIOSFODNN7EXAMPLE');
});

test('ScheduledBackup scopeMine restricts non-admins', function () {
    $admin = User::factory()->isAdmin()->create();
    $user = User::factory()->isNotAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();

    ScheduledBackup::factory()->create(['user_id' => $user->id]);
    ScheduledBackup::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user);
    expect(ScheduledBackup::mine()->count())->toBe(1);

    $this->actingAs($admin);
    expect(ScheduledBackup::mine()->count())->toBe(2);
});
