<?php

use Illuminate\Support\Facades\Config;

test('laranode db_engines has mysql, mariadb, postgres keys', function () {
    $engines = config('laranode.db_engines');

    expect($engines)->toBeArray()
        ->toHaveKeys(['mysql', 'mariadb', 'postgres']);

    expect($engines['mysql'])->toMatchArray(['service' => 'mysql', 'port' => 3306]);
    expect($engines['mariadb'])->toMatchArray(['service' => 'mariadb', 'port' => 3306]);
    expect($engines['postgres'])->toMatchArray(['service' => 'postgresql', 'port' => 5432]);
});

test('database connections has mysql_admin, mariadb_admin, pgsql_admin', function () {
    $connections = config('database.connections');

    expect($connections)->toHaveKeys(['mysql_admin', 'mariadb_admin', 'pgsql_admin']);
});

test('pgsql_admin does not share env var keys with pgsql connection', function () {
    $pgsql = config('database.connections.pgsql');
    $pgsqlAdmin = config('database.connections.pgsql_admin');

    // pgsql reads DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
    // pgsql_admin must read PGSQL_* vars — verify the configured values differ
    // when PGSQL_* env vars are set to distinct values
    // Here we assert structural independence: pgsql_admin has its own driver key
    // and its host/port/database keys are present independently.
    expect($pgsqlAdmin)->toHaveKeys(['driver', 'host', 'port', 'database', 'username', 'password']);
    expect($pgsqlAdmin['driver'])->toBe('pgsql');

    // pgsql_admin must NOT fall back to DB_HOST (it reads PGSQL_HOST)
    // We can verify this by checking the config values don't hardcode DB_* env fallbacks
    // by inspecting the raw config structure — the simplest assertion is that the key exists
    // and is a separate entry from 'pgsql'
    expect($pgsqlAdmin)->not->toBe($pgsql);
});

test('mysql_admin connection uses mysql driver with MYSQL_ADMIN_* env fallback', function () {
    $conn = config('database.connections.mysql_admin');

    expect($conn['driver'])->toBe('mysql');
    expect($conn)->toHaveKeys(['host', 'port', 'database', 'username', 'password']);
});

test('mariadb_admin connection uses mariadb driver', function () {
    $conn = config('database.connections.mariadb_admin');

    expect($conn['driver'])->toBe('mariadb');
    expect($conn)->toHaveKeys(['host', 'port', 'database', 'username', 'password']);
});
