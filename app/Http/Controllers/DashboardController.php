<?php

namespace App\Http\Controllers;

use App\Services\SystemStatsService;

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
        return Inertia::render('Dashboard/Admin/AdminDashboard');
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

    public function user()
    {
        return Inertia::render('Dashboard/User/UserDashboard');
    }
}
