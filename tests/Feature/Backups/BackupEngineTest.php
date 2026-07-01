<?php

use App\Backup\BackupEngineManager;
use App\Backup\Drivers\MysqlBackupDriver;
use App\Backup\Drivers\PostgresBackupDriver;
use App\Contracts\Backup\BackupEngineDriver;
use Illuminate\Support\Facades\Process;

// ─────────────────────────────────────────────────────────────────────────────
// BackupEngineManager
// ─────────────────────────────────────────────────────────────────────────────

test('BackupEngineManager resolves MysqlBackupDriver for mysql engine', function () {
    $manager = new BackupEngineManager;

    expect($manager->for('mysql'))->toBeInstanceOf(MysqlBackupDriver::class);
});

test('BackupEngineManager resolves MysqlBackupDriver for unknown engine (fallback)', function () {
    $manager = new BackupEngineManager;

    expect($manager->for('oracle'))->toBeInstanceOf(MysqlBackupDriver::class)
        ->and($manager->for(null))->toBeInstanceOf(MysqlBackupDriver::class)
        ->and($manager->for(''))->toBeInstanceOf(MysqlBackupDriver::class);
});

test('BackupEngineManager resolves PostgresBackupDriver for postgres engine', function () {
    $manager = new BackupEngineManager;

    expect($manager->for('postgres'))->toBeInstanceOf(PostgresBackupDriver::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// MysqlBackupDriver
// ─────────────────────────────────────────────────────────────────────────────

test('MysqlBackupDriver calls dump script via Process and returns temp path', function () {
    Process::fake([
        '*laranode-db-backup.sh*' => Process::result(output: '', exitCode: 0),
    ]);

    $driver = new MysqlBackupDriver;
    $emitted = [];
    $result = $driver->dump('mydb_ln', 'myuser_ln', '/tmp/test.cnf', function (string $line) use (&$emitted) {
        $emitted[] = $line;
    });

    // Returns a path string (temp file path)
    expect($result)->toBeString()->toContain('laranode-db-backup-');

    // Progress was emitted
    expect($emitted)->not->toBeEmpty();

    // Assert the sudo + script were called
    Process::assertRan(function (\Illuminate\Process\PendingProcess $process) {
        $cmd = $process->command;

        return is_array($cmd)
            && ($cmd[0] ?? '') === 'sudo'
            && str_contains($cmd[1] ?? '', 'laranode-db-backup.sh')
            && ($cmd[2] ?? '') === 'mysql';
    });
});

test('MysqlBackupDriver throws RuntimeException on nonzero exit code', function () {
    Process::fake([
        '*laranode-db-backup.sh*' => Process::result(
            output: '',
            exitCode: 1,
            errorOutput: 'mysqldump: error connecting to DB'
        ),
    ]);

    $driver = new MysqlBackupDriver;

    expect(fn () => $driver->dump('mydb_ln', 'myuser_ln', '/tmp/test.cnf', fn () => null))
        ->toThrow(RuntimeException::class, 'DB dump failed:');
});

// ─────────────────────────────────────────────────────────────────────────────
// Interface contract: NullBackupDriver (inline test double)
// ─────────────────────────────────────────────────────────────────────────────

test('a class implementing BackupEngineDriver satisfies the interface contract', function () {
    $null = new class implements BackupEngineDriver
    {
        public function dump(string $dbName, string $dbUser, string $cnfFile, callable $emit): string
        {
            $emit('noop');

            return '/dev/null';
        }
    };

    expect($null)->toBeInstanceOf(BackupEngineDriver::class);

    $emitted = [];
    $path = $null->dump('db', 'user', '/cnf', function (string $line) use (&$emitted) {
        $emitted[] = $line;
    });

    expect($path)->toBe('/dev/null')
        ->and($emitted)->toBe(['noop']);
});
