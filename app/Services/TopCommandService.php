<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

class TopCommandService
{
    public function run()
    {

        $psAux = "ps aux --sort=-%cpu";

        if ('memory' == Cache::get('ps_aux_sort_by')) {
            $psAux = "ps aux --sort=-%mem";
        }

        $process = Process::pipe([
            $psAux,
            "awk '{print $1, $3, $4, $2, substr($0, index($0,$11))}'",
            "head -n 26"
        ]);

        if ($process->failed()) {
            return ['error' => $process->errorOutput(), 'processes' => []];
        }

        $output = $process->output();
        $lines = explode("\n", trim($output));
        $lines = array_slice(array_filter($lines), 1);
        $processes = [];

        foreach ($lines as $process) {
            $processItems = explode(' ', $process);
            [$user, $cpu, $mem, $pid, $mainCmd] = $processItems;

            $processes[] = [
                'user' => $user,
                'cpu' => $cpu,
                'mem' => $mem,
                'pid' => $pid,
                'mainCmd' => $mainCmd,
                'restOfCmd' => array_slice($processItems, 5)
            ];
        }

        return $processes;
    }
}
