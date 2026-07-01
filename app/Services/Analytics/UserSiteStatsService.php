<?php

namespace App\Services\Analytics;

use App\Models\User;
use App\Models\UserSiteStat;
use App\Models\Website;
use Illuminate\Support\Facades\Process;

class UserSiteStatsService
{
    /**
     * Collect per-site disk stats for the given user.
     *
     * Runs `du -sb` on each site's directory. The site root is computed from
     * the $user parameter directly (not via $site->websiteRoot accessor, which
     * requires an eager-loaded user relation to avoid null homedir).
     *
     * @throws \RuntimeException if `du` fails for any site.
     */
    public function collect(User $user, callable $emit): void
    {
        $sites = Website::where('user_id', $user->id)->get();

        foreach ($sites as $site) {
            // Build path from $user->homedir to avoid null-homedir bug in
            // $site->websiteRoot when the user relation is not eager-loaded.
            $siteRoot = $user->homedir.'/domains/'.$site->url;

            $emit('Collecting disk usage for site: '.$site->url);

            $duResult = Process::run(['du', '-sb', $siteRoot]);

            if ($duResult->failed()) {
                throw new \RuntimeException(
                    'du failed for site '.$site->url.': '.$duResult->errorOutput()
                );
            }

            // Output format: "12345\t/home/username_ln/domains/example.com"
            $diskBytes = (int) explode("\t", trim($duResult->output()))[0];

            UserSiteStat::create([
                'website_id' => $site->id,
                'user_id' => $user->id,
                'snapshotted_at' => now(),
                'disk_bytes' => $diskBytes,
            ]);

            $emit('Site stat recorded for: '.$site->url);
        }
    }
}
