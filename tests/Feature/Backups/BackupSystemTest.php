<?php

// tests/Feature/Backups/BackupSystemTest.php
//
// Real system integration tests gated behind LARANODE_SYSTEM_TESTS=1.
// Exercises MysqlBackupDriver::dump(), laranode-backup-files.sh, and a full
// RestoreJob dispatch including CreateDatabaseService.
//
// Run inside the local-dev container:
//   LARANODE_SYSTEM_TESTS=1 php artisan test --filter=BackupSystemTest

use App\Backup\Drivers\MysqlBackupDriver;
use App\Contracts\DatabaseEngineDriver;
use App\Databases\Drivers\MysqlDriver;
use App\Jobs\RestoreJob;
use App\Models\Backup;
use App\Models\Database as DatabaseModel;
use App\Models\Operation;
use App\Models\User;
use App\Services\Database\CreateDatabaseService;
use App\Services\Database\DeleteDatabaseService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Gate: skip the entire file unless LARANODE_SYSTEM_TESTS is set.
beforeEach(function () {
    if (! env('LARANODE_SYSTEM_TESTS')) {
        $this->markTestSkipped('LARANODE_SYSTEM_TESTS not set — skipping system integration tests.');
    }
});

// ---------------------------------------------------------------------------
// Helper: run a raw MySQL statement via the mysql_admin connection.
// ---------------------------------------------------------------------------
function mysqlAdmin(string $sql): void
{
    DB::connection('mysql_admin')->statement($sql);
}

// ---------------------------------------------------------------------------
// Test 1: MysqlBackupDriver::dump() dumps a real MySQL database
//
// The plan requires exercising MysqlBackupDriver, not calling the bash script
// directly. We instantiate the driver, write a .cnf with admin credentials,
// call $driver->dump() and assert the returned path has gzip magic bytes.
// ---------------------------------------------------------------------------
test('MysqlBackupDriver dumps a real MySQL DB via --defaults-extra-file', function () {
    $suffix = substr(md5(uniqid('', true)), 0, 8);
    $dbName = 'ln_bkp_src_'.$suffix;

    $adminUser = env('MYSQL_ADMIN_USERNAME', 'laranode');
    $adminPass = env('MYSQL_ADMIN_PASSWORD', 'laranode_local_dev_pw');
    $adminHost = env('MYSQL_ADMIN_HOST', '127.0.0.1');

    // Create a real test database with one table and one row.
    mysqlAdmin("CREATE DATABASE IF NOT EXISTS `{$dbName}`");
    mysqlAdmin("CREATE TABLE `{$dbName}`.`test_tbl` (id INT PRIMARY KEY, val VARCHAR(32))");
    mysqlAdmin("INSERT INTO `{$dbName}`.`test_tbl` VALUES (1, 'hello-backup')");

    // Write a --defaults-extra-file .cnf (mode 0600) with host, user, and password.
    $cnfPath = sys_get_temp_dir().'/laranode-system-test-'.uniqid().'.cnf';
    file_put_contents($cnfPath, "[client]\nhost={$adminHost}\nuser={$adminUser}\npassword={$adminPass}\n");
    chmod($cnfPath, 0600);

    // Point the driver at the bind-mounted scripts so the test works regardless
    // of whether LARANODE_BIN_PATH has been overridden to /opt/laranode/bin.
    Config::set('laranode.laranode_bin_path', base_path('laranode-scripts/bin'));

    $returnedPath = null;

    try {
        // Instantiate MysqlBackupDriver and call dump() — this exercises the PHP
        // driver layer (Process::run, exit-code check, RuntimeException on failure).
        $driver = new MysqlBackupDriver;
        $emitted = [];
        $returnedPath = $driver->dump($dbName, $adminUser, $cnfPath, function (string $line) use (&$emitted) {
            $emitted[] = $line;
        });

        // The driver must return a non-empty path to the created temp file.
        expect($returnedPath)->not->toBeEmpty('Driver must return the temp file path');

        // Output file must exist and be non-empty.
        expect(file_exists($returnedPath))->toBeTrue('Dump file was not created');
        expect(filesize($returnedPath))->toBeGreaterThan(0, 'Dump file is empty');

        // Check gzip magic bytes 0x1f 0x8b.
        $fp = fopen($returnedPath, 'rb');
        $magic = fread($fp, 2);
        fclose($fp);
        expect(bin2hex($magic))->toBe('1f8b', 'Output file does not have gzip magic bytes');

        // Emit callbacks were fired.
        expect($emitted)->not->toBeEmpty('Driver must emit progress lines');

        // Verify the dump contains our table/row when decompressed.
        $decompressed = shell_exec('zcat '.escapeshellarg($returnedPath));
        expect($decompressed)->toContain('test_tbl');
        expect($decompressed)->toContain('hello-backup');

    } finally {
        mysqlAdmin("DROP DATABASE IF EXISTS `{$dbName}`");
        @unlink($cnfPath);
        if ($returnedPath && file_exists($returnedPath)) {
            @unlink($returnedPath);
        }
    }
})->group('system');

// ---------------------------------------------------------------------------
// Test 2: laranode-backup-files.sh tars a real directory
// ---------------------------------------------------------------------------
test('laranode-backup-files.sh archives a directory and produces a valid gzipped tar', function () {
    // Create a temp directory with known test files.
    $siteRoot = sys_get_temp_dir().'/laranode-bkp-site-'.uniqid();
    mkdir($siteRoot, 0755, true);
    file_put_contents($siteRoot.'/index.html', '<html>hello</html>');
    file_put_contents($siteRoot.'/README.txt', 'test content for backup');

    $outFile = sys_get_temp_dir().'/laranode-bkp-files-'.uniqid().'.tar.gz';
    $sysUser = 'root'; // container runs as root; chown is a no-op on an already-root-owned file

    try {
        $scriptPath = base_path('laranode-scripts/bin/laranode-backup-files.sh');
        $cmd = escapeshellarg($scriptPath)
            .' '.escapeshellarg($siteRoot)
            .' '.escapeshellarg($outFile)
            .' '.escapeshellarg($sysUser);

        $output = [];
        $exitCode = 0;
        exec($cmd.' 2>&1', $output, $exitCode);

        expect($exitCode)->toBe(0, 'Script exited non-zero: '.implode("\n", $output));

        // Archive must exist and be non-empty.
        expect(file_exists($outFile))->toBeTrue('Archive file was not created');
        expect(filesize($outFile))->toBeGreaterThan(0, 'Archive file is empty');

        // List archive with tar tzf — both files must appear.
        $listing = shell_exec('tar tzf '.escapeshellarg($outFile));
        expect($listing)->toContain('index.html');
        expect($listing)->toContain('README.txt');

    } finally {
        @unlink($siteRoot.'/index.html');
        @unlink($siteRoot.'/README.txt');
        @rmdir($siteRoot);
        @unlink($outFile);
    }
})->group('system');

// ---------------------------------------------------------------------------
// Test 3: RestoreJob restores a real dump into a new DB name via CreateDatabaseService
//
// The plan requires dispatching RestoreJob (QUEUE_CONNECTION=sync) after creating
// a real Backup row pointing to the dump file, and asserting the operation ends
// succeeded.
//
// Flow:
//   1. Create source DB with known data.
//   2. Dump it with MysqlBackupDriver::dump() — real dump file.
//   3. Write the dump into Storage::disk('backups') at a known relative path.
//   4. Create User, Backup row (pointing to that path), Operation row.
//   5. Dispatch RestoreJob::dispatchSync() — runs synchronously (QUEUE_CONNECTION=sync).
//   6. Assert operation is 'succeeded', panel Database row exists, restored data present.
// ---------------------------------------------------------------------------
test('RestoreJob restores a real dump into a new DB name via CreateDatabaseService', function () {
    $suffix = substr(md5(uniqid('', true)), 0, 8);
    $srcDbName = 'ln_restore_src_'.$suffix;
    $destDbName = 'ln_restore_dst_'.$suffix;

    $adminUser = env('MYSQL_ADMIN_USERNAME', 'laranode');
    $adminPass = env('MYSQL_ADMIN_PASSWORD', 'laranode_local_dev_pw');
    $adminHost = env('MYSQL_ADMIN_HOST', '127.0.0.1');

    // Point the driver at the bind-mounted scripts.
    Config::set('laranode.laranode_bin_path', base_path('laranode-scripts/bin'));

    // Write a .cnf for dumping the source DB.
    $srcCnf = sys_get_temp_dir().'/laranode-src-cnf-'.uniqid().'.cnf';
    file_put_contents($srcCnf, "[client]\nhost={$adminHost}\nuser={$adminUser}\npassword={$adminPass}\n");
    chmod($srcCnf, 0600);

    // User row for scoping — uses the SQLite test DB via RefreshDatabase.
    $user = User::factory()->create(['role' => 'user']);
    $destDbRecord = null;
    $driverTempPath = null;

    try {
        // 1. Create source DB with known data.
        mysqlAdmin("CREATE DATABASE IF NOT EXISTS `{$srcDbName}`");
        mysqlAdmin("CREATE TABLE `{$srcDbName}`.`items` (id INT PRIMARY KEY, name VARCHAR(64))");
        mysqlAdmin("INSERT INTO `{$srcDbName}`.`items` VALUES (42, 'restored-item')");

        // 2. Dump it via MysqlBackupDriver::dump().
        $driver = new MysqlBackupDriver;
        $driverTempPath = $driver->dump($srcDbName, $adminUser, $srcCnf, fn () => null);
        expect(file_exists($driverTempPath))->toBeTrue('Driver must produce a dump file');

        // 3. Write the dump into Storage::disk('backups') at a relative path so
        //    RestoreJob can call Storage::disk('backups')->readStream($path).
        //    The 'backups' disk root is /home (from config/filesystems.php).
        $relativePath = "{$user->id}/db/{$srcDbName}/dump-{$suffix}.sql.gz";
        Storage::disk('backups')->put($relativePath, file_get_contents($driverTempPath));
        expect(Storage::disk('backups')->exists($relativePath))->toBeTrue('Dump must exist on the backups disk');

        // 4. Create Backup row pointing to the dump on the 'backups' disk.
        $backup = Backup::create([
            'user_id' => $user->id,
            'type' => 'db',
            'target' => $srcDbName,
            'storage' => 'local',
            'disk_name' => 'backups',
            'path' => $relativePath,
            'size_bytes' => Storage::disk('backups')->size($relativePath),
            'status' => 'completed',
        ]);

        // Create an Operation row for the restore.
        $operation = Operation::create([
            'user_id' => $user->id,
            'type' => 'restore.db',
            'target' => $destDbName,
            'status' => 'queued',
        ]);

        // 5. Bind a real MysqlDriver so RestoreJob's app(CreateDatabaseService::class)
        //    resolves correctly. The interface is not auto-bound in the container.
        app()->bind(DatabaseEngineDriver::class, MysqlDriver::class);
        app()->bind(CreateDatabaseService::class, fn ($app) => new CreateDatabaseService(
            $app->make(MysqlDriver::class)
        ));

        // Dispatch RestoreJob synchronously (QUEUE_CONNECTION=sync in phpunit.xml).
        //    RestoreJob::run() will:
        //      a) Read the dump from Storage::disk('backups')->readStream()
        //      b) Call CreateDatabaseService to create the dest DB + panel row
        //      c) Write a temp .cnf from the new DB's password
        //      d) Call sudo laranode-restore-db.sh to restore the dump
        RestoreJob::dispatchSync($operation, $backup, $destDbName);

        // 6a. Operation must have succeeded.
        $operation->refresh();
        expect($operation->status)->toBe('succeeded', 'RestoreJob must mark operation succeeded. Output: '.($operation->output ?? ''));
        expect($operation->exit_code)->toBe(0);

        // 6b. Panel Database row must exist (created by CreateDatabaseService).
        expect(DatabaseModel::where('name', $destDbName)->exists())->toBeTrue(
            'Panel database row was not created by CreateDatabaseService'
        );

        // 6c. The restored data must exist in the destination DB.
        $destDbRecord = DatabaseModel::where('name', $destDbName)->first();
        $rows = DB::connection('mysql_admin')->select(
            "SELECT id, name FROM `{$destDbName}`.`items` WHERE id = 42"
        );
        expect($rows)->not->toBeEmpty('Restored table/row not found in destination DB');
        expect($rows[0]->name)->toBe('restored-item');

    } finally {
        // Cleanup real MySQL objects.
        mysqlAdmin("DROP DATABASE IF EXISTS `{$srcDbName}`");

        if ($destDbRecord) {
            $mysqlDriver = new MysqlDriver;
            $deleteService = new DeleteDatabaseService($mysqlDriver);
            try {
                $deleteService->handle($destDbRecord);
            } catch (\Throwable) {
                $destDbRecord->delete();
                mysqlAdmin("DROP DATABASE IF EXISTS `{$destDbName}`");
            }
        } else {
            mysqlAdmin("DROP DATABASE IF EXISTS `{$destDbName}`");
        }

        @unlink($srcCnf);
        if ($driverTempPath && file_exists($driverTempPath)) {
            @unlink($driverTempPath);
        }
    }
})->group('system');

// ---------------------------------------------------------------------------
// Test 4: the privileged scripts reject argv flag-smuggling and bad identifiers
// before performing any privileged work (security hardening guards).
// ---------------------------------------------------------------------------
test('backup/restore scripts reject argv-smuggling and invalid identifiers', function () {
    $bin = base_path('laranode-scripts/bin');

    $run = function (array $args) use ($bin) {
        $script = array_shift($args);
        $cmd = escapeshellarg($bin.'/'.$script);
        foreach ($args as $a) {
            $cmd .= ' '.escapeshellarg($a);
        }
        $out = [];
        $code = 0;
        exec($cmd.' 2>&1', $out, $code);

        return $code;
    };

    // Leading-dash argument is rejected (argv flag smuggling) on every script.
    expect($run(['laranode-db-backup.sh', 'mysql', '-x', 'user', 'cnf', 'out']))->toBe(1);
    expect($run(['laranode-restore-db.sh', '-x', 'dump', 'db']))->toBe(1);
    expect($run(['laranode-backup-files.sh', '/tmp', '-x', 'root']))->toBe(1);
    expect($run(['laranode-restore-files.sh', '-x', '/tmp/dest', 'root']))->toBe(1);

    // Invalid identifiers (non-alphanumeric) are rejected before any tool runs.
    expect($run(['laranode-db-backup.sh', 'mysql', 'bad;name', 'user', 'cnf', 'out']))->toBe(1);
    expect($run(['laranode-restore-db.sh', 'cnf', 'dump', 'bad name']))->toBe(1);
    expect($run(['laranode-restore-files.sh', '/tmp/x.tgz', '/tmp/dest', 'bad/user']))->toBe(1);
})->group('system');
