<?php

namespace App\Services\Analytics;

use App\Models\User;
use App\Models\UserResourceSnapshot;
use Illuminate\Support\Facades\Process;

class UserResourceSnapshotService
{
    /**
     * Collect a resource snapshot for the given user.
     *
     * Runs `du -sb` on the user's homedir to get total disk bytes.
     * Runs `wc -l` on the Apache access log to get request count (nullable — missing log is not an error).
     *
     * @throws \RuntimeException if `du` fails (exit code != 0).
     */
    public function collect(User $user, callable $emit): void
    {
        $emit('Collecting disk usage for user: '.$user->username);

        $duResult = Process::run(['du', '-sb', $user->homedir]);

        if ($duResult->failed()) {
            throw new \RuntimeException(
                'du failed for user '.$user->username.': '.$duResult->errorOutput()
            );
        }

        // Output format: "12345\t/home/username_ln"
        $diskBytes = (int) explode("\t", trim($duResult->output()))[0];

        $emit('Collecting Apache request count for user: '.$user->username);

        $logPath = $user->homedir.'/logs/apache-access.log';
        $wcResult = Process::run(['wc', '-l', $logPath]);

        $requestCount = null;
        if ($wcResult->successful()) {
            // Output format: "77 /home/username_ln/logs/apache-access.log"
            $requestCount = (int) trim(explode(' ', trim($wcResult->output()))[0]);
        }

        UserResourceSnapshot::create([
            'user_id' => $user->id,
            'snapshotted_at' => now(),
            'disk_bytes' => $diskBytes,
            'apache_request_count' => $requestCount,
        ]);

        $emit('Snapshot recorded for user: '.$user->username);
    }
}
