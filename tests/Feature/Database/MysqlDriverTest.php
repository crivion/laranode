<?php

use App\Databases\DatabaseSpec;
use App\Databases\Drivers\MysqlDriver;
use App\Models\Database;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Injects a recording Mockery mock into the DatabaseManager's resolved connections,
 * bypassing connection resolution so no real DB is contacted.
 *
 * Returns the recorded calls array (by reference so it updates as calls are made).
 */
function bindMysqlAdminMock(array &$recorded): void
{
    $mock = Mockery::mock(Connection::class)->shouldIgnoreMissing();

    $mock->shouldReceive('statement')->andReturnUsing(function ($sql, $bindings = []) use (&$recorded) {
        $recorded[] = ['sql' => $sql, 'bindings' => $bindings];

        return true;
    });

    $mock->shouldReceive('selectOne')->andReturn(null);

    $manager = app('db');
    $prop = new ReflectionProperty(get_class($manager), 'connections');
    $prop->setAccessible(true);
    $connections = $prop->getValue($manager);
    $connections['mysql_admin'] = $mock;
    $prop->setValue($manager, $connections);
}

afterEach(function () {
    Mockery::close();

    // Remove the injected mock so subsequent tests get a real connection
    $manager = app('db');
    $prop = new ReflectionProperty(get_class($manager), 'connections');
    $prop->setAccessible(true);
    $connections = $prop->getValue($manager);
    unset($connections['mysql_admin']);
    $prop->setValue($manager, $connections);
});

test('capabilities returns MySQL label with users and charset/collation option fields', function () {
    $driver = new MysqlDriver;
    $caps = $driver->capabilities();

    expect($caps->label)->toBe('MySQL')
        ->and($caps->hasUsers)->toBeTrue()
        ->and($caps->optionFields)->toBe(['charset', 'collation']);
});

test('create records SQL with password in bindings not in sql string', function () {
    $recorded = [];
    bindMysqlAdminMock($recorded);

    $user = User::factory()->create();
    $spec = new DatabaseSpec(
        name: 'testdb_ln',
        dbUser: 'testuser_ln',
        password: 'super_secret_password_123',
        userId: $user->id,
        options: ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'],
    );

    $driver = new MysqlDriver;
    $driver->create($spec);

    // Password must never appear in any SQL string
    foreach ($recorded as $entry) {
        expect($entry['sql'])->not->toContain('super_secret_password_123');
    }

    // At least one statement must use IDENTIFIED BY ? with password in bindings
    $identifiedByStatements = array_filter(
        $recorded,
        fn ($e) => str_contains($e['sql'], 'IDENTIFIED BY ?')
    );

    expect($identifiedByStatements)->not->toBeEmpty();

    foreach ($identifiedByStatements as $entry) {
        expect($entry['bindings'])->toContain('super_secret_password_123');
    }
});

test('delete records DROP DATABASE and DROP USER statements', function () {
    $recorded = [];
    bindMysqlAdminMock($recorded);

    $user = User::factory()->create();
    $database = new Database([
        'name' => 'dropme_ln',
        'db_user' => 'dropuser_ln',
        'db_password' => encrypt('secret'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'engine' => 'mysql',
        'user_id' => $user->id,
    ]);

    $driver = new MysqlDriver;
    $driver->delete($database);

    $sqls = array_column($recorded, 'sql');

    $hasDropDb = collect($sqls)->contains(fn ($s) => str_contains($s, 'DROP DATABASE IF EXISTS'));
    $hasDropUser = collect($sqls)->contains(fn ($s) => str_contains($s, 'DROP USER IF EXISTS'));

    expect($hasDropDb)->toBeTrue()
        ->and($hasDropUser)->toBeTrue();
});

test('updatePassword records parameterized ALTER USER statement', function () {
    $recorded = [];
    bindMysqlAdminMock($recorded);

    $user = User::factory()->create();
    $database = new Database([
        'name' => 'mydb_ln',
        'db_user' => 'myuser_ln',
        'db_password' => encrypt('old_password'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'engine' => 'mysql',
        'user_id' => $user->id,
    ]);

    $driver = new MysqlDriver;
    $driver->updatePassword($database, 'new_super_secret_456');

    $alterStatements = array_filter(
        $recorded,
        fn ($e) => str_contains($e['sql'], 'ALTER USER') && str_contains($e['sql'], 'IDENTIFIED BY ?')
    );

    expect($alterStatements)->not->toBeEmpty();

    foreach ($alterStatements as $entry) {
        expect($entry['sql'])->not->toContain('new_super_secret_456');
        expect($entry['bindings'])->toContain('new_super_secret_456');
    }
});

test('create rejects an injection-laden database name before any SQL runs', function () {
    $recorded = [];
    bindMysqlAdminMock($recorded);

    $user = User::factory()->create();
    $spec = new DatabaseSpec(
        name: 'evil`; DROP DATABASE laranode; --',
        dbUser: 'safeuser_ln',
        password: 'whatever_pass_123',
        userId: $user->id,
        options: ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'],
    );

    expect(fn () => (new MysqlDriver)->create($spec))
        ->toThrow(InvalidArgumentException::class);

    // The unsafe identifier must be rejected before any statement is issued.
    expect($recorded)->toBeEmpty();
});

test('create rejects an injection-laden db user before any SQL runs', function () {
    $recorded = [];
    bindMysqlAdminMock($recorded);

    $user = User::factory()->create();
    $spec = new DatabaseSpec(
        name: 'safedb_ln',
        dbUser: 'evil`@localhost; --',
        password: 'whatever_pass_123',
        userId: $user->id,
        options: ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'],
    );

    expect(fn () => (new MysqlDriver)->create($spec))
        ->toThrow(InvalidArgumentException::class);

    expect($recorded)->toBeEmpty();
});
