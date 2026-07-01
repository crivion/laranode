<?php

// tests/Feature/Backups/RestoreJobTest.php

use App\Contracts\DatabaseEngineDriver;
use App\Databases\DatabaseSpec;
use App\Databases\DatabaseStats;
use App\Databases\EngineCapabilities;
use App\Jobs\RestoreJob;
use App\Models\Backup;
use App\Models\Database as DatabaseModel;
use App\Models\Operation;
use App\Models\User;
use App\Services\Backups\RestoreService;
use App\Services\Database\CreateDatabaseService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Bind a fake CreateDatabaseService that records calls and creates a Database row
 * without hitting the real MySQL driver.
 */
function bindFakeCreateDatabaseService(): void
{
    $fakeDriver = new class implements DatabaseEngineDriver
    {
        public function connectionName(): string
        {
            return 'mysql_admin';
        }

        public function create(DatabaseSpec $spec): void {}

        public function updatePassword(\App\Models\Database $database, string $newPassword): void {}

        public function updateOptions(\App\Models\Database $database, array $options): void {}

        public function delete(\App\Models\Database $database): void {}

        public function stats(\App\Models\Database $database): DatabaseStats
        {
            return new DatabaseStats(0, 0);
        }

        public function capabilities(): EngineCapabilities
        {
            return new EngineCapabilities('MySQL', false, []);
        }
    };

    app()->bind(CreateDatabaseService::class, fn () => new CreateDatabaseService($fakeDriver));
}

/**
 * Create a completed Backup row with a fake file on the fake disk.
 */
function makeCompletedBackup(User $user, string $type = 'db', string $target = 'sourcedb'): Backup
{
    Storage::disk('backups')->put("1/{$type}/{$target}/backup.sql.gz", 'fake-dump-data');

    return Backup::create([
        'user_id' => $user->id,
        'type' => $type,
        'target' => $target,
        'storage' => 'local',
        'disk_name' => 'backups',
        'path' => "1/{$type}/{$target}/backup.sql.gz",
        'status' => 'completed',
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// Test 1: RestoreJob (db type) calls CreateDatabaseService and marks operation succeeded
// ─────────────────────────────────────────────────────────────────────────────

test('RestoreJob (db type) calls CreateDatabaseService and marks operation succeeded', function () {
    Storage::fake('backups');

    Process::fake([
        '*laranode-restore-db.sh*' => Process::result(output: '', exitCode: 0),
    ]);

    bindFakeCreateDatabaseService();

    $user = User::factory()->create(['username' => 'testowner']);
    $backup = makeCompletedBackup($user, 'db', 'sourcedb');

    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'restore.db',
        'target' => 'newdb',
        'status' => 'queued',
    ]);

    RestoreJob::dispatchSync($operation, $backup, 'newdb');

    expect($operation->fresh()->status)->toBe('succeeded');
    expect($operation->fresh()->exit_code)->toBe(0);

    // A Database panel row was created for the new target.
    expect(DatabaseModel::where('name', 'newdb')->exists())->toBeTrue();

    // restore-db.sh was invoked via Process.
    Process::assertRan(function (\Illuminate\Process\PendingProcess $process) {
        $cmd = $process->command;

        return is_array($cmd)
            && ($cmd[0] ?? '') === 'sudo'
            && str_contains($cmd[1] ?? '', 'laranode-restore-db.sh');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 2: RestoreJob throws InvalidArgumentException when new_target === source
// ─────────────────────────────────────────────────────────────────────────────

test('RestoreJob throws InvalidArgumentException when new_target equals backup source, marks operation failed', function () {
    Storage::fake('backups');
    bindFakeCreateDatabaseService();

    $user = User::factory()->create(['username' => 'sameuser']);
    $backup = makeCompletedBackup($user, 'db', 'sourcedb');

    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'restore.db',
        'target' => 'sourcedb',
        'status' => 'queued',
    ]);

    expect(fn () => RestoreJob::dispatchSync($operation, $backup, 'sourcedb'))
        ->toThrow(\InvalidArgumentException::class);

    expect($operation->fresh()->status)->toBe('failed');
    expect($operation->fresh()->exit_code)->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 3: RestoreJob throws InvalidArgumentException when new_target fails regex
// ─────────────────────────────────────────────────────────────────────────────

test('RestoreJob throws InvalidArgumentException when new_target fails identifier regex, marks operation failed', function (string $badTarget) {
    Storage::fake('backups');
    bindFakeCreateDatabaseService();

    $user = User::factory()->create(['username' => 'regexuser']);
    $backup = makeCompletedBackup($user, 'db', 'sourcedb');

    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'restore.db',
        'target' => $badTarget,
        'status' => 'queued',
    ]);

    expect(fn () => RestoreJob::dispatchSync($operation, $backup, $badTarget))
        ->toThrow(\InvalidArgumentException::class);

    expect($operation->fresh()->status)->toBe('failed');
})->with([
    'contains space' => ['bad name'],
    'contains backtick' => ['bad`name'],
    'empty string' => [''],
    'too long (65)' => [str_repeat('a', 65)],
    'contains hyphen' => ['bad-name'],
    'contains dot' => ['bad.name'],
]);

// ─────────────────────────────────────────────────────────────────────────────
// Test 4: RestoreService::handle() creates Operation row and returns it
// ─────────────────────────────────────────────────────────────────────────────

test('RestoreService::handle() creates Operation row and returns it', function () {
    Storage::fake('backups');

    Process::fake([
        '*laranode-restore-db.sh*' => Process::result(output: '', exitCode: 0),
    ]);

    bindFakeCreateDatabaseService();

    $user = User::factory()->create(['username' => 'svcuser']);
    $backup = makeCompletedBackup($user, 'db', 'sourcedb2');

    $service = new RestoreService;
    $operation = $service->handle($backup, 'newdb2', $user);

    expect($operation)->toBeInstanceOf(Operation::class);
    expect($operation->exists)->toBeTrue();
    expect($operation->type)->toBe('restore.db');
    expect($operation->target)->toBe('newdb2');

    // With QUEUE_CONNECTION=sync the job runs inline — operation completes.
    expect($operation->fresh()->status)->toBe('succeeded');
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 5: RestoreJob (files type) calls restore-files.sh and marks operation succeeded
// ─────────────────────────────────────────────────────────────────────────────

test('RestoreJob (files type) calls restore-files.sh and marks operation succeeded', function () {
    Storage::fake('backups');

    Process::fake([
        '*laranode-restore-files.sh*' => Process::result(output: '', exitCode: 0),
    ]);

    $user = User::factory()->create(['username' => 'filesuser']);

    Storage::disk('backups')->put('1/files/example.com/backup.tar.gz', 'fake-tar-data');

    $backup = Backup::create([
        'user_id' => $user->id,
        'type' => 'files',
        'target' => 'example.com',
        'storage' => 'local',
        'disk_name' => 'backups',
        'path' => '1/files/example.com/backup.tar.gz',
        'status' => 'completed',
    ]);

    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'restore.files',
        'target' => 'newsite.com',
        'status' => 'queued',
    ]);

    RestoreJob::dispatchSync($operation, $backup, 'newsite_com');

    expect($operation->fresh()->status)->toBe('succeeded');

    Process::assertRan(function (\Illuminate\Process\PendingProcess $process) {
        $cmd = $process->command;

        return is_array($cmd)
            && ($cmd[0] ?? '') === 'sudo'
            && str_contains($cmd[1] ?? '', 'laranode-restore-files.sh');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 6: RestoreJob removes temp file in finally even on failure
// ─────────────────────────────────────────────────────────────────────────────

test('RestoreJob removes temp file in finally even when restore script fails', function () {
    Storage::fake('backups');

    Process::fake([
        '*laranode-restore-db.sh*' => Process::result(
            output: '',
            exitCode: 1,
            errorOutput: 'ERROR 1045: Access denied'
        ),
    ]);

    bindFakeCreateDatabaseService();

    $user = User::factory()->create(['username' => 'cleanupuser']);
    $backup = makeCompletedBackup($user, 'db', 'cleanupdb');

    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'restore.db',
        'target' => 'cleanupdest',
        'status' => 'queued',
    ]);

    expect(fn () => RestoreJob::dispatchSync($operation, $backup, 'cleanupdest'))
        ->toThrow(\RuntimeException::class, 'DB restore failed:');

    expect($operation->fresh()->status)->toBe('failed');

    // No stray temp files in system temp dir from this job
    $stragglers = glob(sys_get_temp_dir().'/laranode-restore-*.sql.gz');
    expect($stragglers)->toBeEmpty('Temp files must be cleaned up in finally');
});

// ─────────────────────────────────────────────────────────────────────────────
// Security: new_target identifier must be validated in the job (defence in depth)
// ─────────────────────────────────────────────────────────────────────────────

test('RestoreJob rejects new_target with SQL-injection characters regardless of FormRequest validation', function () {
    Storage::fake('backups');
    bindFakeCreateDatabaseService();

    $user = User::factory()->create(['username' => 'sqliuser']);
    $backup = makeCompletedBackup($user, 'db', 'safe_source');

    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'restore.db',
        'target' => 'safe_source; DROP TABLE users--',
        'status' => 'queued',
    ]);

    expect(fn () => RestoreJob::dispatchSync($operation, $backup, 'safe_source; DROP TABLE users--'))
        ->toThrow(\InvalidArgumentException::class);

    expect($operation->fresh()->status)->toBe('failed');
});

// ─────────────────────────────────────────────────────────────────────────────
// S3 path: RestoreJob re-registers the S3 disk from encrypted credentials at
// the top of run() — the queue worker has no knowledge of disks that were
// registered during the original HTTP request.
// ─────────────────────────────────────────────────────────────────────────────

test('RestoreJob re-registers S3 disk from encrypted credentials on the Backup row', function () {
    // Use a fake local disk under the S3 disk name so Storage::disk() resolves
    // after Config::set() runs inside RestoreJob::run().
    // The critical assertion is that Config::set() is called with the expected
    // S3 config before the disk is accessed.
    Storage::fake('restore_s3_disk');

    Process::fake([
        '*laranode-restore-db.sh*' => Process::result(output: '', exitCode: 0),
    ]);

    bindFakeCreateDatabaseService();

    $user = User::factory()->create(['username' => 's3restoreuser']);

    // Put a fake backup file on the fake disk so readStream() succeeds.
    Storage::disk('restore_s3_disk')->put('1/db/s3sourcedb/backup.sql.gz', 'fake-s3-dump-data');

    // Create a Backup with S3 storage and encrypted credentials.
    // The 'encrypted' cast on s3_key/s3_secret means the values are stored
    // encrypted and decrypted transparently on read — this exercises the
    // round-trip and the s3DiskConfig() method.
    $backup = Backup::create([
        'user_id' => $user->id,
        'type' => 'db',
        'target' => 's3sourcedb',
        'storage' => 's3',
        'disk_name' => 'restore_s3_disk',
        's3_key' => 'RESTOREKEY123',
        's3_secret' => 'RESTORESECRET456',
        's3_region' => 'eu-central-1',
        's3_bucket' => 'restore-bucket',
        's3_endpoint' => null,
        'path' => '1/db/s3sourcedb/backup.sql.gz',
        'status' => 'completed',
    ]);

    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'restore.db',
        'target' => 's3newdb',
        'status' => 'queued',
    ]);

    // Dispatch the job. RestoreJob::run() must call Config::set() with the S3
    // config derived from the encrypted credentials before touching the disk.
    RestoreJob::dispatchSync($operation, $backup, 's3newdb');

    // Assert Config::set() fired with the correct S3 disk config.
    $registeredConfig = Config::get('filesystems.disks.restore_s3_disk');
    expect($registeredConfig)->not->toBeNull('S3 disk config must be registered by RestoreJob::run()');
    expect($registeredConfig['driver'])->toBe('s3');
    expect($registeredConfig['key'])->toBe('RESTOREKEY123');
    expect($registeredConfig['secret'])->toBe('RESTORESECRET456');
    expect($registeredConfig['region'])->toBe('eu-central-1');
    expect($registeredConfig['bucket'])->toBe('restore-bucket');

    // The operation must succeed end-to-end.
    expect($operation->fresh()->status)->toBe('succeeded');
    expect($operation->fresh()->exit_code)->toBe(0);

    // A panel-managed Database row was created for the restore target.
    expect(DatabaseModel::where('name', 's3newdb')->exists())->toBeTrue();
});
