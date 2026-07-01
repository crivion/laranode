<?php

// tests/Feature/Backups/BackupActionsTest.php

use App\Actions\Backup\DumpDatabaseAction;
use App\Actions\Backup\RetainBackupsAction;
use App\Actions\Backup\TarFilesAction;
use App\Actions\Backup\UploadToStorageAction;
use App\Backup\BackupEngineManager;
use App\Contracts\Backup\BackupEngineDriver;
use App\Models\Backup;
use App\Models\Database as DatabaseModel;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

// ─────────────────────────────────────────────────────────────────────────────
// DumpDatabaseAction
// ─────────────────────────────────────────────────────────────────────────────

test('DumpDatabaseAction writes temp .cnf (mode 0600), calls engine driver, deletes .cnf in finally', function () {
    $writtenCnfPaths = [];
    $cnfWasDeleted = false;

    // Build a fake driver that captures the cnf path and checks its permissions
    $fakeDriver = new class($writtenCnfPaths, $cnfWasDeleted) implements BackupEngineDriver
    {
        public function __construct(
            private array &$writtenCnfPaths,
            private bool &$cnfWasDeleted,
        ) {}

        public function dump(string $dbName, string $dbUser, string $cnfFile, callable $emit): string
        {
            $this->writtenCnfPaths[] = $cnfFile;

            // File must exist and be mode 0600 at call time
            expect(file_exists($cnfFile))->toBeTrue();

            $perms = decoct(fileperms($cnfFile) & 0777);
            expect($perms)->toBe('600');

            $contents = file_get_contents($cnfFile);
            expect($contents)->toContain('[client]');
            expect($contents)->toContain('password=');

            $emit('dump done');

            return '/tmp/fake-dump.sql.gz';
        }
    };

    $fakeManager = new class($fakeDriver) extends BackupEngineManager
    {
        public function __construct(private BackupEngineDriver $driver) {}

        public function for(?string $engine): BackupEngineDriver
        {
            return $this->driver;
        }
    };

    $user = User::factory()->create();
    $database = DatabaseModel::factory()->create(['user_id' => $user->id, 'db_password' => 'secret123']);

    $action = new DumpDatabaseAction($fakeManager);
    $emitted = [];
    $result = $action->execute($database, '/tmp/ignored-path.sql.gz', function (string $line) use (&$emitted) {
        $emitted[] = $line;
    });

    expect($result)->toBe('/tmp/fake-dump.sql.gz');
    expect($emitted)->toContain('dump done');

    // .cnf must have been deleted after the call
    expect($writtenCnfPaths)->not->toBeEmpty();
    foreach ($writtenCnfPaths as $cnf) {
        expect(file_exists($cnf))->toBeFalse('cnf file should be deleted in finally');
    }
});

test('DumpDatabaseAction deletes .cnf even when driver throws', function () {
    $capturedCnf = null;

    $throwingDriver = new class($capturedCnf) implements BackupEngineDriver
    {
        public function __construct(private mixed &$capturedCnf) {}

        public function dump(string $dbName, string $dbUser, string $cnfFile, callable $emit): string
        {
            $this->capturedCnf = $cnfFile;
            throw new RuntimeException('dump failed');
        }
    };

    $fakeManager = new class($throwingDriver) extends BackupEngineManager
    {
        public function __construct(private BackupEngineDriver $driver) {}

        public function for(?string $engine): BackupEngineDriver
        {
            return $this->driver;
        }
    };

    $user = User::factory()->create();
    $database = DatabaseModel::factory()->create(['user_id' => $user->id]);

    $action = new DumpDatabaseAction($fakeManager);

    expect(fn () => $action->execute($database, '/tmp/out.sql.gz', fn () => null))
        ->toThrow(RuntimeException::class, 'dump failed');

    // .cnf was deleted despite the exception
    expect($capturedCnf)->not->toBeNull();
    expect(file_exists($capturedCnf))->toBeFalse('cnf must be deleted in finally even on failure');
});

// ─────────────────────────────────────────────────────────────────────────────
// TarFilesAction
// ─────────────────────────────────────────────────────────────────────────────

test('TarFilesAction calls laranode-backup-files.sh via Process and returns the temp path', function () {
    Process::fake([
        '*laranode-backup-files.sh*' => Process::result(output: '', exitCode: 0),
    ]);

    $user = User::factory()->create(['username' => 'testuser']);
    $website = new Website([
        'url' => 'example.com',
        'document_root' => '/public',
    ]);
    $website->user_id = $user->id;
    $website->setRelation('user', $user);

    $action = new TarFilesAction;
    $emitted = [];
    $tempPath = '/tmp/laranode-files-archive.tar.gz';

    $result = $action->execute($website, $tempPath, function (string $line) use (&$emitted) {
        $emitted[] = $line;
    });

    expect($result)->toBe($tempPath);
    expect($emitted)->not->toBeEmpty();

    Process::assertRan(function (\Illuminate\Process\PendingProcess $process) {
        $cmd = $process->command;

        return is_array($cmd)
            && ($cmd[0] ?? '') === 'sudo'
            && str_contains($cmd[1] ?? '', 'laranode-backup-files.sh');
    });
});

test('TarFilesAction throws RuntimeException on nonzero exit code', function () {
    Process::fake([
        '*laranode-backup-files.sh*' => Process::result(
            output: '',
            exitCode: 1,
            errorOutput: 'tar: permission denied'
        ),
    ]);

    $user = User::factory()->create(['username' => 'testuser']);
    $website = new Website([
        'url' => 'example.com',
        'document_root' => '/public',
    ]);
    $website->user_id = $user->id;
    $website->setRelation('user', $user);

    $action = new TarFilesAction;

    expect(fn () => $action->execute($website, '/tmp/out.tar.gz', fn () => null))
        ->toThrow(RuntimeException::class, 'File archive failed:');
});

// ─────────────────────────────────────────────────────────────────────────────
// UploadToStorageAction
// ─────────────────────────────────────────────────────────────────────────────

test('UploadToStorageAction streams local file to Storage::fake disk and path exists', function () {
    Storage::fake('backups');

    // Create a real temp file to stream
    $localPath = tempnam(sys_get_temp_dir(), 'laranode-upload-test-');
    file_put_contents($localPath, 'backup-content-12345');

    $remotePath = '1/db/mydb/2026-06-26-020000.sql.gz';

    $action = new UploadToStorageAction;
    $emitted = [];

    $result = $action->execute(
        $localPath,
        $remotePath,
        Storage::disk('backups'),
        function (string $line) use (&$emitted) {
            $emitted[] = $line;
        }
    );

    expect($result)->toBe($remotePath);
    expect($emitted)->not->toBeEmpty();

    Storage::disk('backups')->assertExists($remotePath);

    unlink($localPath);
});

// ─────────────────────────────────────────────────────────────────────────────
// RetainBackupsAction
// ─────────────────────────────────────────────────────────────────────────────

test('RetainBackupsAction deletes the 2 oldest of 5 backups when retention_count=3', function () {
    Storage::fake('backups');

    $user = User::factory()->create();

    // Create 5 completed backups with staggered created_at timestamps
    $backups = collect();
    for ($i = 1; $i <= 5; $i++) {
        $path = "1/db/mydb/2026-06-{$i}-backup.sql.gz";
        Storage::disk('backups')->put($path, "backup-{$i}");

        $backup = Backup::factory()->create([
            'user_id' => $user->id,
            'type' => 'db',
            'target' => 'mydb_ln',
            'status' => 'completed',
            'disk_name' => 'backups',
            'path' => $path,
        ]);
        $backup->forceFill(['created_at' => now()->subDays(6 - $i)])->save();

        $backups->push($backup);
    }

    $action = new RetainBackupsAction;
    $action->execute($user->id, 'db', 'mydb_ln', 3, Storage::disk('backups'));

    // Only 3 backups remain in DB
    expect(Backup::where('user_id', $user->id)->where('type', 'db')->where('target', 'mydb_ln')->count())->toBe(3);

    // The 2 oldest files (days 5, 4 ago) are deleted; the 3 newest remain
    Storage::disk('backups')->assertMissing('1/db/mydb/2026-06-1-backup.sql.gz');
    Storage::disk('backups')->assertMissing('1/db/mydb/2026-06-2-backup.sql.gz');
    Storage::disk('backups')->assertExists('1/db/mydb/2026-06-3-backup.sql.gz');
    Storage::disk('backups')->assertExists('1/db/mydb/2026-06-4-backup.sql.gz');
    Storage::disk('backups')->assertExists('1/db/mydb/2026-06-5-backup.sql.gz');
});

test('RetainBackupsAction does nothing when backup count is within retention limit', function () {
    Storage::fake('backups');

    $user = User::factory()->create();

    for ($i = 1; $i <= 3; $i++) {
        $path = "1/db/mydb2/2026-06-{$i}-backup.sql.gz";
        Storage::disk('backups')->put($path, "data-{$i}");

        Backup::factory()->create([
            'user_id' => $user->id,
            'type' => 'db',
            'target' => 'mydb2_ln',
            'status' => 'completed',
            'disk_name' => 'backups',
            'path' => $path,
        ]);
    }

    $action = new RetainBackupsAction;
    $action->execute($user->id, 'db', 'mydb2_ln', 5, Storage::disk('backups'));

    expect(Backup::where('user_id', $user->id)->where('target', 'mydb2_ln')->count())->toBe(3);
});
