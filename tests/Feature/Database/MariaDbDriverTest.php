<?php

use App\Databases\DatabaseSpec;
use App\Databases\Drivers\MariaDbDriver;
use App\Models\Database;
use App\Models\User;
use Illuminate\Database\Connection;

/**
 * Injects a recording Mockery mock into the DatabaseManager's resolved connections
 * for the mariadb_admin connection.
 */
function bindMariaDbAdminMock(array &$recorded): void
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
    $connections['mariadb_admin'] = $mock;
    $prop->setValue($manager, $connections);
}

afterEach(function () {
    Mockery::close();

    $manager = app('db');
    $prop = new ReflectionProperty(get_class($manager), 'connections');
    $prop->setAccessible(true);
    $connections = $prop->getValue($manager);
    unset($connections['mariadb_admin']);
    $prop->setValue($manager, $connections);
});

test('capabilities returns MariaDB label with users and charset/collation option fields', function () {
    $driver = new MariaDbDriver;
    $caps = $driver->capabilities();

    expect($caps->label)->toBe('MariaDB')
        ->and($caps->hasUsers)->toBeTrue()
        ->and($caps->optionFields)->toBe(['charset', 'collation']);
});

test('connectionName returns mariadb_admin', function () {
    $driver = new MariaDbDriver;

    expect($driver->connectionName())->toBe('mariadb_admin');
});

test('create records SQL with password in bindings not in sql string', function () {
    $recorded = [];
    bindMariaDbAdminMock($recorded);

    $user = User::factory()->create();
    $spec = new DatabaseSpec(
        name: 'mariadb_testdb_ln',
        dbUser: 'mariadb_testuser_ln',
        password: 'mariadb_secret_password_999',
        userId: $user->id,
        options: ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'],
    );

    $driver = new MariaDbDriver;
    $driver->create($spec);

    foreach ($recorded as $entry) {
        expect($entry['sql'])->not->toContain('mariadb_secret_password_999');
    }

    $identifiedByStatements = array_filter(
        $recorded,
        fn ($e) => str_contains($e['sql'], 'IDENTIFIED BY ?')
    );

    expect($identifiedByStatements)->not->toBeEmpty();

    foreach ($identifiedByStatements as $entry) {
        expect($entry['bindings'])->toContain('mariadb_secret_password_999');
    }
});

test('delete records DROP DATABASE and DROP USER statements on mariadb_admin connection', function () {
    $recorded = [];
    bindMariaDbAdminMock($recorded);

    $user = User::factory()->create();
    $database = new Database([
        'name' => 'mariadb_dropme_ln',
        'db_user' => 'mariadb_dropuser_ln',
        'db_password' => encrypt('secret'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'engine' => 'mariadb',
        'user_id' => $user->id,
    ]);

    $driver = new MariaDbDriver;
    $driver->delete($database);

    $sqls = array_column($recorded, 'sql');

    $hasDropDb = collect($sqls)->contains(fn ($s) => str_contains($s, 'DROP DATABASE IF EXISTS'));
    $hasDropUser = collect($sqls)->contains(fn ($s) => str_contains($s, 'DROP USER IF EXISTS'));

    expect($hasDropDb)->toBeTrue()
        ->and($hasDropUser)->toBeTrue();
});
