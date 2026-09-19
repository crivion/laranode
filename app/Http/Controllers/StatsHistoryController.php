<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\CPUHistoryService;
use App\Services\Dashboard\MemoryHistoryService;
use App\Services\Dashboard\NetworkHistoryService;
use App\Support\DemoData;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class StatsHistoryController extends Controller
{
    public function cpuAndMemory(Request $r)
    {
        if (config('laranode.demo.enabled')) {
            $history = DemoData::history();

            return Inertia::render('Stats/History', [
                'selectedDate' => now()->format('jS F Y'),
                'cpuStats' => $history['cpu'],
                'memoryStats' => $history['memory'],
                'networkStats' => $history['network'],
                'sarFiles' => [],
                'error' => false,
            ]);
        }

        $cpuData = (new CPUHistoryService($r->report))->getStats();
        $memoryData = (new MemoryHistoryService($r->report))->getStats();
        $networkData = (new NetworkHistoryService($r->report))->getStats();

        $cpuStats = [];
        $memoryStats = [];
        $networkStats = [];
        $sarFiles = [];
        $error = false;

        if (isset($cpuData['error']) || isset($memoryData['error'])) {
            $error = $cpuData;
        }

        if ($r->filled('report')) {
            $sarReport = date('Y-m').'-'.str_replace(['sa', 'sa0'], ['', ''], $r->report);
            $selectedDate = Carbon::parse($sarReport)->format('jS F Y');
        } else {
            $selectedDate = Carbon::now()->format('jS F Y');
        }

        $sarFiles = $cpuData['sarFiles'];
        $cpuStats = $cpuData['metrics'];
        $memoryStats = $memoryData['metrics'];
        $networkStats = $networkData['metrics'];

        return Inertia::render('Stats/History', [
            'selectedDate' => $selectedDate,
            'cpuStats' => $cpuStats,
            'memoryStats' => $memoryStats,
            'networkStats' => $networkStats,
            'sarFiles' => $sarFiles,
            'error' => $error,
        ]);
    }
}
