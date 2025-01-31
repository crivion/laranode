<?php

namespace App\Http\Controllers;

use App\Services\CPUHistoryService;
use App\Services\MemoryHistoryService;
use Illuminate\Http\Request;

class StatsHistoryController extends Controller
{
    public function cpuAndMemory(Request $r)
    {
        $cpuData = (new CPUHistoryService($r->report))->getStats();
        $memoryData = (new MemoryHistoryService($r->report))->getStats();

        if (isset($cpuData['error']) || isset($memoryData['error'])) {
            $error = $cpuData;
            return view('error-message', compact('error'));
        }

        $sarFiles      = $cpuData['sarFiles'];
        $cpuStats      = $cpuData['metrics'];
        $memoryStats   = $memoryData['metrics'];

        return view('stats.history', compact('cpuStats', 'sarFiles', 'memoryStats'));
    }
}
