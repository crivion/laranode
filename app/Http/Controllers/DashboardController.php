<?php

namespace App\Http\Controllers;

use App\Services\SystemStatsService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
        // Get Top Sorting Option
        $topSort = Cache::get('ps_aux_sort_by', 'cpu');

        return view('dashboard/admin', ['topSort' => $topSort]);
    }

    public function setTopSort(Request $r)
    {
        $r->validate(['sortBy' => 'required|in:cpu,memory']);

        Cache::put('ps_aux_sort_by', $r->sortBy);

        return response()->noContent();
    }

    public function user()
    {
        return view('dashboard/user');
    }
}
