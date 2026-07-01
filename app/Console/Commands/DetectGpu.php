<?php

namespace App\Console\Commands;

use App\Services\Dashboard\GpuStatsService;
use Illuminate\Console\Command;

class DetectGpu extends Command
{
    protected $signature = 'laranode:detect-gpu';

    protected $description = 'Detect a GPU once and persist the result (run at install or on rescan)';

    public function handle(GpuStatsService $gpu): int
    {
        $profile = $gpu->detect();

        if ($profile['detected']) {
            $this->info("Detected {$profile['vendor']} GPU: {$profile['name']} (via {$profile['tool']})");
        } else {
            $this->line('No GPU detected — GPU stats disabled until a manual rescan.');
        }

        return self::SUCCESS;
    }
}
