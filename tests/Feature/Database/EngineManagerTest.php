<?php

use App\Databases\Drivers\MariaDbDriver;
use App\Databases\Drivers\MysqlDriver;
use App\Databases\Drivers\PostgresDriver;
use App\Databases\EngineManager;
use Illuminate\Support\Facades\Process;

/**
 * Returns a Process::fake() closure that maps service names to active/inactive.
 *
 * @param  array<string, bool>  $services  service-name => isActive
 */
function makeSystemctlFake(array $services): \Closure
{
    return function (\Illuminate\Process\PendingProcess $process) use ($services) {
        $cmd = $process->command;

        // We expect ['systemctl', 'is-active', $service]
        if (! is_array($cmd) || ($cmd[0] ?? '') !== 'systemctl' || ($cmd[1] ?? '') !== 'is-active') {
            return Process::result(output: 'inactive', exitCode: 1);
        }

        $service = $cmd[2] ?? '';

        if (array_key_exists($service, $services) && $services[$service]) {
            return Process::result(output: 'active', exitCode: 0);
        }

        return Process::result(output: 'inactive', exitCode: 1);
    };
}

beforeEach(function () {
    // Reset singleton so each test gets a fresh instance
    app()->forgetInstance(EngineManager::class);
});

test('available returns only active engines per faked Process', function () {
    Process::fake(makeSystemctlFake([
        'mysql' => true,
        'mariadb' => false,
        'postgresql' => false,
        'postgresql@16-main' => false,
    ]));

    $manager = app(EngineManager::class);
    $available = $manager->available();

    expect($available)->toHaveKey('mysql')
        ->and($available)->not->toHaveKey('mariadb')
        ->and($available)->not->toHaveKey('postgres');
});

test('available returns empty array when all services are inactive', function () {
    Process::fake(makeSystemctlFake([
        'mysql' => false,
        'mariadb' => false,
        'postgresql' => false,
        'postgresql@16-main' => false,
    ]));

    $manager = app(EngineManager::class);
    $available = $manager->available();

    expect($available)->toBe([]);
});

test('for returns correct driver class for known engines', function () {
    $manager = app(EngineManager::class);

    expect($manager->for('mysql'))->toBeInstanceOf(MysqlDriver::class)
        ->and($manager->for('mariadb'))->toBeInstanceOf(MariaDbDriver::class)
        ->and($manager->for('postgres'))->toBeInstanceOf(PostgresDriver::class);
});

test('for throws InvalidArgumentException for unknown engine', function () {
    $manager = app(EngineManager::class);

    expect(fn () => $manager->for('oracle'))->toThrow(\InvalidArgumentException::class);
});

test('for with null or empty string returns MysqlDriver', function () {
    $manager = app(EngineManager::class);

    expect($manager->for(null))->toBeInstanceOf(MysqlDriver::class)
        ->and($manager->for(''))->toBeInstanceOf(MysqlDriver::class);
});

test('mariadb detection: mariadb active includes mariadb but not mysql', function () {
    Process::fake(makeSystemctlFake([
        'mysql' => false,
        'mariadb' => true,
        'postgresql' => false,
        'postgresql@16-main' => false,
    ]));

    $manager = app(EngineManager::class);
    $available = $manager->available();

    expect($available)->toHaveKey('mariadb')
        ->and($available)->not->toHaveKey('mysql')
        ->and($available)->not->toHaveKey('postgres');
});

test('postgres active via versioned unit postgresql@16-main', function () {
    Process::fake(makeSystemctlFake([
        'mysql' => false,
        'mariadb' => false,
        'postgresql' => false,
        'postgresql@16-main' => true,
    ]));

    $manager = app(EngineManager::class);
    $available = $manager->available();

    expect($available)->toHaveKey('postgres')
        ->and($available)->not->toHaveKey('mysql')
        ->and($available)->not->toHaveKey('mariadb');
});

test('available is memoized: second call returns same result without re-running Process', function () {
    $invocationCount = 0;

    Process::fake(function (\Illuminate\Process\PendingProcess $process) use (&$invocationCount) {
        $cmd = $process->command;

        if (is_array($cmd) && ($cmd[0] ?? '') === 'systemctl') {
            $invocationCount++;
        }

        return Process::result(output: 'inactive', exitCode: 1);
    });

    $manager = app(EngineManager::class);

    $first = $manager->available();
    $second = $manager->available();

    // Both calls should return equal values
    expect($second)->toBe($first);

    // systemctl was only called for the FIRST available() call —
    // 4 invocations total (mysql + mariadb + postgresql + postgresql@16-main)
    expect($invocationCount)->toBe(4);
});
