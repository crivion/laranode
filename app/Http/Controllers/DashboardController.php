<?php

namespace App\Http\Controllers;

use App\Models\Database;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $r)
    {
        if ($r->user()->isAdmin()) {
            return to_route('dashboard.admin');
        }

        return to_route('dashboard.user');
    }

    public function admin()
    {
        $initialStats = Cache::get('dashboard_stats_last_known', []);

        return Inertia::render('Dashboard/Admin/AdminDashboard', compact('initialStats'));
    }

    public function getTopSort()
    {
        return ['sortBy' => Cache::get('ps_aux_sort_by', 'cpu')];
    }

    public function setTopSort(Request $r)
    {
        $r->validate(['sortBy' => 'required|in:cpu,memory']);

        Cache::put('ps_aux_sort_by', $r->sortBy);

        return ['sortBy' => $r->sortBy];
    }

    /**
     * Re-probe the host for a GPU on demand (the dashboard cogwheel). Detection
     * otherwise only runs at install, so a GPU added later isn't missed.
     */
    public function rescanGpu(\App\Services\Dashboard\GpuStatsService $gpu): \Illuminate\Http\RedirectResponse
    {
        $profile = $gpu->detect();

        session()->flash('success', $profile['detected']
            ? "Detected {$profile['vendor']} GPU: {$profile['name']}."
            : 'No GPU detected on this server.');

        return back();
    }

    public function user()
    {
        $user = auth()->user();
        $websitesCount = Website::mine()->count();
        $databasesCount = Database::mine()->count();
        $websitesLimit = $user->domain_limit;
        $databasesLimit = $user->database_limit;

        $manager = app('impersonate');
        $isImpersonating = $manager->isImpersonating();

        return Inertia::render('Dashboard/User/UserDashboard', compact(
            'websitesCount', 'websitesLimit', 'databasesCount', 'databasesLimit', 'isImpersonating'
        ));
    }
}
