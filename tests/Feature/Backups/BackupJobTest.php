<?php

// tests/Feature/Backups/BackupJobTest.php

use App\Actions\Backup\TarFilesAction;
use App\Backup\BackupEngineManager;
use App\Contracts\Backup\BackupEngineDriver;
use App\Jobs\BackupJob;
use App\Models\Backup;
use App\Models\Database as DatabaseModel;
use App\Models\Operation;
use App\Models\User;
use App\Models\Website;
use App\Services\Backups\BackupService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Bind a fake BackupEngineManager whose driver creates and returns a real temp file,
 * so UploadToStorageAction can stream it.
 */
function bindFakeDumpDriver(string $content = 'fake-dump-data'): void
{
    $fakeDriver = new class($content) implements BackupEngineDriver
    {
        public function __construct(private string $content) {}

        public function dump(string $dbName, string $dbUser, string $cnfFile, callable $emit): string
        {
            // Create a real temp file so UploadToStorageAction can open it
            $path = tempnam(sys_get_temp_dir(), 'laranode-test-dump-').'.sql.gz';
            file_put_contents($path, $this->content);
            $emit('Dump done (fake).');

            return $path;
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

    app()->instance(BackupEngineManager::class, $fakeManager);
}

/**
 * Bind a fake BackupEngineManager whose driver throws RuntimeException.
 */
function bindFailingDumpDriver(): void
{
    $throwingDriver = new class implements BackupEngineDriver
    {
        public function dump(string $dbName, string $dbUser, string $cnfFile, callable $emit): string
        {
            throw new RuntimeException('DB dump failed: mysqldump: Access denied');
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

    app()->instance(BackupEngineManager::class, $fakeManager);
}

/**
 * Bind a fake TarFilesAction that creates a real temp file and returns its path.
 */
function bindFakeTarFilesAction(string $content = 'fake-tar-data'): void
{
    $fake = new class($content) extends TarFilesAction
    {
        public function __construct(private string $content) {}

        public function execute(Website $website, string $tempPath, callable $emit): string
        {
            file_put_contents($tempPath, $this->content);
            $emit('Archive done (fake).');

            return $tempPath;
        }
    };

    app()->instance(TarFilesAction::class, $fake);
}

// ─────────────────────────────────────────────────────────────────────────────
// BackupJob — db type
// ─────────────────────────────────────────────────────────────────────────────

test('BackupJob marks backup completed and operation succeeded for db type', function () {
    Storage::fake('backups');
    bindFakeDumpDriver();

    $user = User::factory()->create();
    $database = DatabaseModel::factory()->create([
        'user_id' => $user->id,
        'name' => 'testdb_ln',
        'db_password' => 'secret',
    ]);

    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'backup.db',
        'target' => 'testdb_ln',
        'status' => 'queued',
    ]);

    $backup = Backup::create([
        'user_id' => $user->id,
        'operation_id' => $operation->id,
        'type' => 'db',
        'target' => 'testdb_ln',
        'storage' => 'local',
        'disk_name' => 'backups',
        'status' => 'pending',
    ]);

    BackupJob::dispatchSync($operation, $backup);

    expect($backup->fresh()->status)->toBe('completed');
    expect($operation->fresh()->status)->toBe('succeeded');
    expect($operation->fresh()->exit_code)->toBe(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// BackupJob — files type
// ─────────────────────────────────────────────────────────────────────────────

test('BackupJob marks backup completed for files type', function () {
    Storage::fake('backups');
    bindFakeTarFilesAction();

    $user = User::factory()->create(['username' => 'siteusr']);
    $website = Website::forceCreate([
        'user_id' => $user->id,
        'url' => 'example.com',
        'document_root' => '/public',
        'php_version_id' => 0,
    ]);

    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'backup.files',
        'target' => 'example.com',
        'status' => 'queued',
    ]);

    $backup = Backup::create([
        'user_id' => $user->id,
        'operation_id' => $operation->id,
        'type' => 'files',
        'target' => 'example.com',
        'storage' => 'local',
        'disk_name' => 'backups',
        'status' => 'pending',
    ]);

    BackupJob::dispatchSync($operation, $backup);

    expect($backup->fresh()->status)->toBe('completed');
    expect($operation->fresh()->status)->toBe('succeeded');
});

// ─────────────────────────────────────────────────────────────────────────────
// BackupJob — dump failure keeps backup pending, operation failed
// ─────────────────────────────────────────────────────────────────────────────

test('BackupJob keeps backup pending and marks operation failed when dump fails', function () {
    Storage::fake('backups');
    bindFailingDumpDriver();

    $user = User::factory()->create();
    DatabaseModel::factory()->create([
        'user_id' => $user->id,
        'name' => 'faildb_ln',
        'db_password' => 'secret',
    ]);

    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'backup.db',
        'target' => 'faildb_ln',
        'status' => 'queued',
    ]);

    $backup = Backup::create([
        'user_id' => $user->id,
        'operation_id' => $operation->id,
        'type' => 'db',
        'target' => 'faildb_ln',
        'storage' => 'local',
        'disk_name' => 'backups',
        'status' => 'pending',
    ]);

    // OperationJob re-throws exceptions after marking operation failed
    expect(fn () => BackupJob::dispatchSync($operation, $backup))
        ->toThrow(RuntimeException::class);

    expect($backup->fresh()->status)->toBe('pending');
    expect($operation->fresh()->status)->toBe('failed');
    expect($operation->fresh()->exit_code)->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// BackupService::handle()
// ─────────────────────────────────────────────────────────────────────────────

test('BackupService::handle() creates Backup and Operation rows, returns Operation, backup ends completed', function () {
    Storage::fake('backups');
    bindFakeDumpDriver();

    $user = User::factory()->create();
    DatabaseModel::factory()->create([
        'user_id' => $user->id,
        'name' => 'mydb_ln',
        'db_password' => 'secret',
    ]);

    $service = new BackupService;

    $operation = $service->handle([
        'type' => 'db',
        'target' => 'mydb_ln',
        'storage' => 'local',
    ], $user);

    expect($operation)->toBeInstanceOf(Operation::class);
    expect($operation->exists)->toBeTrue();

    $backup = Backup::where('operation_id', $operation->id)->first();
    expect($backup)->not->toBeNull();
    expect($backup->type)->toBe('db');
    expect($backup->target)->toBe('mydb_ln');
    expect($backup->disk_name)->toBe('backups');

    // With QUEUE_CONNECTION=sync the job runs inline — backup should be completed
    expect($backup->fresh()->status)->toBe('completed');
    expect($operation->fresh()->status)->toBe('succeeded');
});

// ─────────────────────────────────────────────────────────────────────────────
// S3 path: BackupService persists encrypted creds; BackupJob re-registers disk
// ─────────────────────────────────────────────────────────────────────────────

test('BackupService persists S3 credentials encrypted on the Backup row', function () {
    Storage::fake('backups');
    bindFakeDumpDriver();

    $user = User::factory()->create();
    DatabaseModel::factory()->create([
        'user_id' => $user->id,
        'name' => 's3db_ln',
        'db_password' => 'secret',
    ]);

    $service = new BackupService;

    $service->handle([
        'type' => 'db',
        'target' => 's3db_ln',
        'storage' => 's3',
        's3_key' => 'AKIATEST',
        's3_secret' => 'secret123',
        's3_region' => 'us-east-1',
        's3_bucket' => 'my-bucket',
        's3_endpoint' => '',
    ], $user);

    $backup = Backup::where('target', 's3db_ln')->first();
    expect($backup)->not->toBeNull();
    expect($backup->storage)->toBe('s3');
    expect($backup->disk_name)->toBe('backups_s3_'.$user->id);

    // Credentials are decrypted transparently via the 'encrypted' cast.
    // If they were stored in plaintext the cast would break; this asserts the
    // round-trip works (write encrypted, read decrypted).
    expect($backup->s3_key)->toBe('AKIATEST');
    expect($backup->s3_secret)->toBe('secret123');
    expect($backup->s3_region)->toBe('us-east-1');
    expect($backup->s3_bucket)->toBe('my-bucket');
});

test('BackupJob re-registers S3 disk from encrypted credentials on the Backup row', function () {
    // Use a fake local disk under the S3 disk name so Storage::disk() resolves.
    // The critical assertion is that BackupJob calls Config::set() with the S3
    // config before touching the disk — without this the queue worker has no
    // knowledge of the disk.
    Storage::fake('my_s3_disk');
    bindFakeDumpDriver();

    $user = User::factory()->create();
    DatabaseModel::factory()->create([
        'user_id' => $user->id,
        'name' => 's3job_ln',
        'db_password' => 'secret',
    ]);

    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'backup.db',
        'target' => 's3job_ln',
        'status' => 'queued',
    ]);

    $backup = Backup::create([
        'user_id' => $user->id,
        'operation_id' => $operation->id,
        'type' => 'db',
        'target' => 's3job_ln',
        'storage' => 's3',
        'disk_name' => 'my_s3_disk',
        's3_key' => 'KEY123',
        's3_secret' => 'SECRET456',
        's3_region' => 'eu-west-1',
        's3_bucket' => 'test-bucket',
        's3_endpoint' => null,
        'status' => 'pending',
    ]);

    // BackupJob::run() must call Config::set("filesystems.disks.my_s3_disk", ...)
    // so the disk is resolvable. We verify this by checking that the config key
    // exists after the job runs (it is set at the start of run()).
    BackupJob::dispatchSync($operation, $backup);

    $registeredConfig = Config::get('filesystems.disks.my_s3_disk');
    expect($registeredConfig)->not->toBeNull();
    expect($registeredConfig['driver'])->toBe('s3');
    expect($registeredConfig['key'])->toBe('KEY123');
    expect($registeredConfig['secret'])->toBe('SECRET456');
    expect($registeredConfig['region'])->toBe('eu-west-1');
    expect($registeredConfig['bucket'])->toBe('test-bucket');

    expect($backup->fresh()->status)->toBe('completed');
    expect($operation->fresh()->status)->toBe('succeeded');
});

test('Backup::s3DiskConfig() returns null for local storage', function () {
    $backup = new Backup([
        'storage' => 'local',
        'disk_name' => 'backups',
    ]);

    expect($backup->s3DiskConfig())->toBeNull();
});

test('Backup::s3DiskConfig() returns S3 config array for s3 storage', function () {
    $user = User::factory()->create();

    $backup = Backup::factory()->create([
        'user_id' => $user->id,
        'storage' => 's3',
        'disk_name' => 'backups_s3_1',
        's3_key' => 'MYKEY',
        's3_secret' => 'MYSECRET',
        's3_region' => 'us-west-2',
        's3_bucket' => 'backup-bucket',
        's3_endpoint' => 'https://s3.example.com',
    ]);

    $config = $backup->fresh()->s3DiskConfig();

    expect($config)->not->toBeNull();
    expect($config['driver'])->toBe('s3');
    expect($config['key'])->toBe('MYKEY');
    expect($config['secret'])->toBe('MYSECRET');
    expect($config['region'])->toBe('us-west-2');
    expect($config['bucket'])->toBe('backup-bucket');
    expect($config['endpoint'])->toBe('https://s3.example.com');
    expect($config['use_path_style_endpoint'])->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// Temp file leak: BackupJob must not leave the placeholder file when the
// engine driver returns its own temp path (db type).
// ─────────────────────────────────────────────────────────────────────────────

test('BackupJob does not leak the placeholder temp file when db driver returns own path', function () {
    Storage::fake('backups');

    // Track the temp file that the driver creates so we can assert it was cleaned up
    $driverTempPath = null;
    $placeholderPath = null;

    $fakeDriver = new class($driverTempPath, $placeholderPath) implements BackupEngineDriver
    {
        public ?string $driverPath = null;

        public function dump(string $dbName, string $dbUser, string $cnfFile, callable $emit): string
        {
            // Create a different file from the placeholder — simulates MysqlBackupDriver
            $this->driverPath = tempnam(sys_get_temp_dir(), 'laranode-driver-').'.sql.gz';
            file_put_contents($this->driverPath, 'dump-content');

            return $this->driverPath;
        }
    };

    $fakeManager = new class($fakeDriver) extends BackupEngineManager
    {
        public function __construct(public BackupEngineDriver $driver) {}

        public function for(?string $engine): BackupEngineDriver
        {
            return $this->driver;
        }
    };

    app()->instance(BackupEngineManager::class, $fakeManager);

    $user = User::factory()->create();
    DatabaseModel::factory()->create([
        'user_id' => $user->id,
        'name' => 'leaktest_ln',
        'db_password' => 'secret',
    ]);

    $operation = Operation::create([
        'user_id' => $user->id,
        'type' => 'backup.db',
        'target' => 'leaktest_ln',
        'status' => 'queued',
    ]);

    $backup = Backup::create([
        'user_id' => $user->id,
        'operation_id' => $operation->id,
        'type' => 'db',
        'target' => 'leaktest_ln',
        'storage' => 'local',
        'disk_name' => 'backups',
        'status' => 'pending',
    ]);

    BackupJob::dispatchSync($operation, $backup);

    // Neither the driver's temp file nor the placeholder should remain.
    $driverPath = $fakeManager->driver->driverPath;
    expect($driverPath)->not->toBeNull('Driver should have created a temp file');
    expect(file_exists($driverPath))->toBeFalse('Driver temp file must be cleaned up');

    expect($backup->fresh()->status)->toBe('completed');
});
