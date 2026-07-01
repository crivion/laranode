<?php

namespace App\Http\Controllers;

use App\Services\Analytics\UserAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $service = new UserAnalyticsService;

        return Inertia::render('Analytics/Index', [
            'resourceHistory' => $service->getResourceHistory($user),
            'siteStats' => $service->getSiteStats($user),
            'quotaSummary' => $service->getQuotaSummary($user),
            'sslOverview' => $service->getSslOverview($user),
        ]);
    }
}
