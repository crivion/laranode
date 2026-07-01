<?php

namespace App\Services\Websites;

use App\Models\Website;

class PortAllocatorService
{
    private const PORT_MIN = 9100;

    private const PORT_MAX = 9499;

    /**
     * Return the lowest port in 9100–9499 not held by any other website.
     *
     * No lock is acquired in v1 — a concurrent allocation may pick the same port
     * (documented concurrency risk; v2 adds lockForUpdate).
     * The caller must persist runtime_port ONLY after systemctl start succeeds
     * so that a failed start leaves runtime_port null in the DB.
     *
     * @throws \RuntimeException when all 400 ports are occupied by other websites.
     */
    public function allocate(Website $excludeWebsite): int
    {
        $used = Website::whereNotNull('runtime_port')
            ->where('id', '!=', $excludeWebsite->id)
            ->pluck('runtime_port')
            ->toArray();

        $usedSet = array_flip($used);

        for ($port = self::PORT_MIN; $port <= self::PORT_MAX; $port++) {
            if (! isset($usedSet[$port])) {
                return $port;
            }
        }

        throw new \RuntimeException('No available runtime ports in range 9100–9499.');
    }
}
