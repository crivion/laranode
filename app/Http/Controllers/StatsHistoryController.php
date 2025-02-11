<?php

namespace App\Http\Controllers;

use App\Services\CPUHistoryService;
use App\Services\MemoryHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class StatsHistoryController extends Controller
{
    public function cpuAndMemory(Request $r)
    {

        $cpuData = (new CPUHistoryService($r->report))->getStats();
        $memoryData = (new MemoryHistoryService($r->report))->getStats();

        $cpuStats    = [];
        $memoryStats = [];
        $sarFiles    = [];
        $error       = false;

        if (isset($cpuData['error']) || isset($memoryData['error'])) {
            $error = $cpuData;
        }

        if ($r->filled('report')) {
            $sarReport = date('Y-m') . '-' . str_replace(['sa', 'sa0'], ['', ''], $r->report);
            $selectedDate  = Carbon::parse($sarReport)->format('jS F Y');
        } else {
            $selectedDate = Carbon::now()->format('jS F Y');
        }

        $sarFiles      = $cpuData['sarFiles'];
        $cpuStats      = $cpuData['metrics'];
        $memoryStats   = $memoryData['metrics'];

        return Inertia::render('Stats/History', [
            'selectedDate' => $selectedDate,
            'cpuStats'     => $cpuStats,
            'memoryStats'  => $memoryStats,
            'sarFiles'     => $sarFiles,
            'error'        => $error
        ]);
    }
}
