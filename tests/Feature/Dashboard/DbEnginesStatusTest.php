<?php

use App\Services\Dashboard\SystemStatsService;
use Illuminate\Support\Facades\Process;

/**
 * Returns a Process::fake() closure that maps systemctl is-active calls
 * to active/inactive based on the provided service map.
 *
 * @param  array<string, bool>  $services  service-name => isActive
 */
function makeDbEnginesFake(array $services): \Closure
{
    return function (\Illuminate\Process\PendingProcess $process) use ($services) {
        $cmd = $process->command;

        if (! is_array($cmd)) {
            return Process::result(output: 'inactive', exitCode: 1);
        }

        // systemctl is-active <service>
        if (($cmd[0] ?? '') === 'systemctl' && ($cmd[1] ?? '') === 'is-active') {
            $service = $cmd[2] ?? '';
            if (array_key_exists($service, $services) && $services[$service]) {
                return Process::result(output: 'active', exitCode: 0);
            }

            return Process::result(output: 'inactive', exitCode: 1);
        }

        // systemctl status <service> — used by getServiceStatus()
        // Return minimal awk-parseable output
        if (($cmd[0] ?? '') === 'systemctl' && ($cmd[1] ?? '') === 'status') {
            return Process::result(output: '');
        }

        return Process::result(output: 'inactive', exitCode: 1);
    };
}

/**
 * All candidate service names used by EngineManager::available().
 * Primary: mysql, mariadb, postgresql
 * Extra candidates: postgresql@16-main
 */
$allCandidates = [
    'mysql' => false,
    'mariadb' => false,
    'postgresql' => false,
    'postgresql@16-main' => false,
];

test('getDbEnginesStatus returns mysql key when only mysql is active', function () use ($allCandidates) {
    $services = array_merge($allCandidates, ['mysql' => true]);

    Process::fake(makeDbEnginesFake($services));

    $service = new SystemStatsService;
    $result = $service->getDbEnginesStatus();

    expect($result)->toHaveKey('mysql')
        ->and($result['mysql'])->toHaveKeys(['pid', 'memory', 'cpuTime', 'uptime']);
});

test('getDbEnginesStatus returns both mysql and postgres keys when both are active', function () use ($allCandidates) {
    $services = array_merge($allCandidates, [
        'mysql' => true,
        'postgresql' => true,
    ]);

    Process::fake(makeDbEnginesFake($services));

    $service = new SystemStatsService;
    $result = $service->getDbEnginesStatus();

    expect($result)->toHaveKey('mysql')
        ->and($result)->toHaveKey('postgres');
});

test('getAllStats has dbEngines key and no top-level mysql key', function () use ($allCandidates) {
    $services = array_merge($allCandidates, ['mysql' => true]);

    // getAllStats calls many Process commands — fake everything broadly
    Process::fake(function (\Illuminate\Process\PendingProcess $process) use ($services) {
        $cmd = $process->command;

        if (is_array($cmd)) {
            // systemctl is-active
            if (($cmd[0] ?? '') === 'systemctl' && ($cmd[1] ?? '') === 'is-active') {
                $service = $cmd[2] ?? '';
                if (array_key_exists($service, $services) && $services[$service]) {
                    return Process::result(output: 'active', exitCode: 0);
                }

                return Process::result(output: 'inactive', exitCode: 1);
            }
        }

        // String commands: top, free, df, uptime, ps, who, systemctl status, etc.
        return Process::result(output: '');
    });

    // getNetworkStats reads /proc/net/dev — skip via empty pipe result above
    $service = new SystemStatsService;
    $result = $service->getAllStats();

    expect($result)->toHaveKey('dbEngines')
        ->and($result)->not->toHaveKey('mysql');
});
