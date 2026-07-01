<?php

use App\Databases\DatabaseSpec;
use App\Databases\Drivers\PostgresDriver;
use App\Databases\EngineManager;
use App\Models\Database;
use App\Models\User;
use App\Services\Database\CreateDatabaseService;
use App\Services\Database\DeleteDatabaseService;
use App\Services\Database\GetDatabasesWithStatsService;
use Illuminate\Support\Facades\DB;

/**
 * Real PostgreSQL integration tests.
 * Gate: LARANODE_SYSTEM_TESTS=1.
 *
 * Tests full create → index → delete lifecycle against the real PostgreSQL
 * instance running in the local-dev container.
 */
beforeEach(function () {
    if (! env('LARANODE_SYSTEM_TESTS')) {
        $this->markTestSkipped('LARANODE_SYSTEM_TESTS not set — skipping system integration tests.');
    }
});

test('postgres full lifecycle: create index delete with security assertions', function () {
    $user = User::factory()->create(['role' => 'user']);
    $admin = User::factory()->create(['role' => 'admin']);

    $uniqueSuffix = substr(md5(uniqid('', true)), 0, 8);
    $dbName = 'ln_pg_'.$uniqueSuffix;
    $dbUser = 'ln_pgu_'.$uniqueSuffix;
    $password = 'PgPass_'.$uniqueSuffix.'_secure';

    $spec = new DatabaseSpec(
        name: $dbName,
        dbUser: $dbUser,
        password: $password,
        userId: $user->id,
        options: ['encoding' => 'UTF8', 'locale' => 'en_US.UTF-8'],
    );

    $driver = new PostgresDriver;
    $createService = new CreateDatabaseService($driver);

    // ---- CREATE ----
    $record = $createService->handle($spec, 'postgres');

    expect($record->engine)->toBe('postgres');
    // Postgres rows have null charset and collation
    expect($record->charset)->toBeNull();
    expect($record->collation)->toBeNull();

    $this->assertDatabaseHas('databases', ['name' => $dbName, 'engine' => 'postgres']);

    // ---- SECURITY: REVOKE CONNECT FROM PUBLIC was applied ----
    // The pgsql_admin connection connects as laranode_pg_reader (a stats-reader role).
    // It should NOT be able to connect to the newly-created database since PUBLIC was revoked.
    // Only the specific dbUser should have connect access.
    $connectFailed = false;

    try {
        // Attempt connection to the new database as the stats-reader role
        // Using pgsql_admin connection config but targeting the new DB
        $pgsqlAdminConfig = config('database.connections.pgsql_admin');
        $testConfig = array_merge($pgsqlAdminConfig, ['database' => $dbName]);
        config(['database.connections.pgsql_admin_test' => $testConfig]);

        DB::connection('pgsql_admin_test')->selectOne('SELECT 1');
        DB::purge('pgsql_admin_test');
    } catch (\Exception $e) {
        $connectFailed = true;
        // Purge the failed connection
        try {
            DB::purge('pgsql_admin_test');
        } catch (\Exception) {
        }
    }

    expect($connectFailed)->toBeTrue('REVOKE CONNECT FROM PUBLIC should prevent the stats-reader role from connecting to the new database');

    // ---- SECURITY: Password not visible in process list during create ----
    // We spawn a background password-change operation and concurrently scan /proc/*/cmdline
    // to confirm the password never appears in any process argument list.
    // update-user-password reads the password from stdin (never argv), so no psql
    // process should carry the plaintext password in its command line.
    $monitorPassword = 'MonitorSecret_'.$uniqueSuffix.'_xyz987';
    $passwordFoundInProc = false;

    // Write a short monitor script that scans /proc/*/cmdline for the password
    // and writes "FOUND" to a temp file if it appears.
    $monitorOutput = sys_get_temp_dir().'/pg_proc_check_'.$uniqueSuffix.'.txt';
    $monitorScript = sys_get_temp_dir().'/pg_proc_monitor_'.$uniqueSuffix.'.sh';

    file_put_contents($monitorScript, <<<BASH
        #!/usr/bin/env bash
        FOUND=0
        for i in \$(seq 1 50); do
            for f in /proc/*/cmdline; do
                if strings "\$f" 2>/dev/null | grep -qF '{$monitorPassword}'; then
                    FOUND=1
                    break 2
                fi
            done
            sleep 0.05
        done
        echo "\$FOUND" > '{$monitorOutput}'
        BASH);
    chmod($monitorScript, 0755);

    // Start the monitor in the background
    $monitorPid = null;
    $descriptors = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
    $monitorProc = proc_open("bash {$monitorScript}", $descriptors, $pipes);
    if (is_resource($monitorProc)) {
        $status = proc_get_status($monitorProc);
        $monitorPid = $status['pid'];
    }

    // Run update-user-password — password travels via stdin only
    $scriptPath = config('laranode.laranode_bin_path').'/laranode-postgres.sh';
    $result = \Illuminate\Support\Facades\Process::pipe(function ($pipe) use ($monitorPassword, $dbUser, $scriptPath) {
        $pipe->command("printf '%s' ".escapeshellarg($monitorPassword));
        $pipe->command("sudo {$scriptPath} update-user-password ".escapeshellarg($dbUser));
    });

    // Wait for the monitor to finish
    if (is_resource($monitorProc)) {
        proc_close($monitorProc);
    }

    // Check monitor result
    if (file_exists($monitorOutput)) {
        $passwordFoundInProc = trim(file_get_contents($monitorOutput)) === '1';
        @unlink($monitorOutput);
    }
    @unlink($monitorScript);

    expect($passwordFoundInProc)->toBeFalse(
        'Password must not appear in /proc/*/cmdline — it must be passed via stdin, not as an argument'
    );

    // ---- INDEX: non-admin sees own row, admin sees all ----
    $manager = app(EngineManager::class);

    $this->actingAs($user);
    GetDatabasesWithStatsService::clearCache();
    $statsService = new GetDatabasesWithStatsService($manager);
    $results = $statsService->handle();

    $own = collect($results)->firstWhere('name', $dbName);
    expect($own)->not->toBeNull();
    expect($own['engine'])->toBe('postgres');
    expect($own['charset'] ?? null)->toBeNull();
    expect($own['collation'] ?? null)->toBeNull();

    // Admin sees the row too
    $this->actingAs($admin);
    GetDatabasesWithStatsService::clearCache();
    $adminService = new GetDatabasesWithStatsService($manager);
    $allResults = $adminService->handle();
    $found = collect($allResults)->firstWhere('name', $dbName);
    expect($found)->not->toBeNull();

    // ---- DELETE ----
    $deleteService = new DeleteDatabaseService($driver);
    $deleteService->handle($record);

    $this->assertDatabaseMissing('databases', ['name' => $dbName]);

    // Verify the Postgres DB is actually dropped (connecting as superuser)
    $dbExists = DB::connection('pgsql_admin')->selectOne(
        'SELECT 1 FROM pg_database WHERE datname = ?',
        [$dbName]
    );
    expect($dbExists)->toBeNull();
})->group('system');

test('stats on a postgres database that no longer exists returns zero size (no 3D000 crash)', function () {
    // Reproduces the Databases-page crash: a Database row whose underlying
    // PostgreSQL database was dropped. pg_database_size(name) raised SQLSTATE
    // 3D000 and took down the whole listing; stats() now scopes to existing
    // databases and returns 0 instead of throwing.
    $ghost = new Database;
    $ghost->engine = 'postgres';
    $ghost->name = 'ln_ghost_'.substr(md5(uniqid('', true)), 0, 8);

    $stats = (new PostgresDriver)->stats($ghost);

    expect($stats->sizeMb)->toBe(0.0);
    expect($stats->tableCount)->toBe(0);
})->group('system');
