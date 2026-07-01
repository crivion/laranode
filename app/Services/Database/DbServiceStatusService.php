<?php

namespace App\Services\Database;

use App\Databases\EngineManager;

class DbServiceStatusService
{
    public function __construct(private EngineManager $engineManager) {}

    /**
     * Return the service control status for every configured DB engine.
     *
     * Delegates detection to EngineManager::available() (already memoized).
     *
     * @return array<string, array{service: string, active: bool}>
     */
    public function handle(): array
    {
        $available = $this->engineManager->available();
        $engines = config('laranode.db_engines', []);
        $statuses = [];

        foreach ($engines as $key => $config) {
            $statuses[$key] = [
                'service' => $config['service'],
                'active' => isset($available[$key]),
            ];
        }

        return $statuses;
    }
}
