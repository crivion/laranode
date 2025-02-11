<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;

class MemoryHistoryService extends SarHistory
{

    public function runCommands(): array
    {
        return [
            'sar -r -f ' . $this->sarFile,
            "awk '/^[0-9]/ {print $1,$2,$3,$4,$5}'"
        ];
    }

    public function parseLines(Collection $metrics): Collection
    {
        if (!$metrics->count()) {
            return collect([]);
        }

        // dismiss first (headers)
        /*$metrics = $metrics->slice(1);*/

        return $metrics->map(function ($stat) {

            $gb = 1024 * 1024;

            return [
                'time' => $stat[0],
                'avail' => (int)$stat[2] / $gb,
                'used' => (int)$stat[3] / $gb,
                'percent' => (float)$stat[4]
            ];
        });
    }
}
