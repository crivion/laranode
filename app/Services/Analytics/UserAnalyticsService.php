<?php

namespace App\Services\Analytics;

use App\Models\User;
use App\Models\UserResourceSnapshot;
use App\Models\UserSiteStat;
use App\Models\Website;
use Illuminate\Support\Collection;

class UserAnalyticsService
{
    /**
     * Get the resource snapshot history for a user (explicit where — no scopeMine).
     */
    public function getResourceHistory(User $user, int $days = 30): Collection
    {
        return UserResourceSnapshot::where('user_id', $user->id)
            ->where('snapshotted_at', '>=', now()->subDays($days))
            ->orderBy('snapshotted_at')
            ->get();
    }

    /**
     * Get per-site disk stats for a user (explicit where — no scopeMine).
     */
    public function getSiteStats(User $user, int $days = 30): Collection
    {
        return UserSiteStat::where('user_id', $user->id)
            ->with('website:id,url')
            ->where('snapshotted_at', '>=', now()->subDays($days))
            ->orderBy('snapshotted_at')
            ->get();
    }

    /**
     * Get quota summary for a user.
     *
     * User::databases() relation MUST exist (added in Task 1). Without it
     * $user->databases() throws a BadMethodCallException.
     */
    public function getQuotaSummary(User $user): array
    {
        return [
            'websites_count' => Website::where('user_id', $user->id)->count(),
            'websites_limit' => $user->domain_limit,
            'databases_count' => $user->databases()->count(),
            'databases_limit' => $user->database_limit,
        ];
    }

    /**
     * Get SSL overview for the user's sites.
     *
     * Returns ssl_expires_at as-is (nullable). No computation — the frontend
     * guards null before computing expiry warnings.
     */
    public function getSslOverview(User $user): Collection
    {
        return Website::where('user_id', $user->id)
            ->select(['id', 'url', 'ssl_enabled', 'ssl_status', 'ssl_expires_at'])
            ->get();
    }
}
