<?php

namespace App\Databases;

use App\Contracts\DatabaseEngineDriver;
use App\Databases\Drivers\MariaDbDriver;
use App\Databases\Drivers\MysqlDriver;
use App\Databases\Drivers\PostgresDriver;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

class EngineManager
{
    /**
     * Memoized result of available() — computed once per request lifetime.
     *
     * @var array<string, string>|null
     */
    private ?array $cachedAvailable = null;

    /**
     * Extra candidate service names to try for engines that may use versioned unit names.
     *
     * @var array<string, string[]>
     */
    private array $extraCandidates = [
        'postgres' => ['postgresql@16-main'],
    ];

    /**
     * Return a map of engine key => service name for every active engine.
     *
     * Checks systemctl is-active for each configured engine. For the postgres
     * engine, also tries versioned unit names (e.g. postgresql@16-main) so the
     * Ubuntu default install is detected regardless of whether the generic alias
     * or the versioned unit is active.
     *
     * Result is memoized for the lifetime of this instance (one per request).
     *
     * @return array<string, string>
     */
    public function available(): array
    {
        if ($this->cachedAvailable !== null) {
            return $this->cachedAvailable;
        }

        $engines = config('laranode.db_engines', []);
        $active = [];

        foreach ($engines as $key => $config) {
            $candidates = [$config['service']];

            if (isset($this->extraCandidates[$key])) {
                foreach ($this->extraCandidates[$key] as $extra) {
                    $candidates[] = $extra;
                }
            }

            foreach ($candidates as $service) {
                $result = Process::run(['systemctl', 'is-active', $service]);

                if (trim($result->output()) === 'active') {
                    $active[$key] = $service;
                    break; // one active candidate is enough
                }
            }
        }

        $this->cachedAvailable = $active;

        return $this->cachedAvailable;
    }

    /**
     * Resolve the driver for the given engine key.
     *
     * Passing null or empty string falls back to the MySQL driver (legacy default).
     *
     * @throws InvalidArgumentException for unrecognized non-empty engine keys.
     */
    public function for(?string $engine): DatabaseEngineDriver
    {
        if ($engine === null || $engine === '') {
            return new MysqlDriver;
        }

        return match ($engine) {
            'mysql' => new MysqlDriver,
            'mariadb' => new MariaDbDriver,
            'postgres' => new PostgresDriver,
            default => throw new InvalidArgumentException("Unknown database engine: {$engine}"),
        };
    }
}
