<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Collection;

class NetworkHistoryService extends SarHistory
{

    public function runCommands(): array
    {
        return [
            'sar -n DEV -f ' . $this->sarFile,
            "awk '/^[0-9]{2}:[0-9]{2}:[0-9]{2}.+[0-9]+$/ {print $1,$2,$5,$6}'"
        ];
    }

    public function parseLines(Collection $metrics): Collection
    {
        if (!$metrics->count()) {
            return collect([]);
        }

        return $metrics->map(function ($stat) {
            return [
                'time' => $stat[0],
                'interface' => $stat[1],
                'rxkbs' => ((float)$stat[2] * 600) / 1024,
                'txkbs' => ((float)$stat[3] * 600) / 1024,
                'totalkbs' => ((float)($stat[2] + $stat[3]) * 600) / 1024
            ];
        });
    }
}
