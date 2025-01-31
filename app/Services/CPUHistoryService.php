<?php

namespace App\Services;

use Illuminate\Support\Collection;

class CPUHistoryService extends SarHistory
{

    public function runCommands(): array
    {
        return [
            'sar -u -f ' . $this->sarFile,
            "awk '/^[0-9]{2}:[0-9]{2}:[0-9]{2}.+[0-9]+$/ {print $1,$3,$5,$8}'"
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
                'user' => (float)$stat[1],
                'system' => (float)$stat[2],
                'idle' => (float)$stat[3],
                'total' => (float)(100.00 - $stat[3])
            ];
        });
    }
}
