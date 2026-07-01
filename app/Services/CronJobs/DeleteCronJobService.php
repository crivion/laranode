<?php

namespace App\Services\CronJobs;

use App\Models\CronJob;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Process;

class DeleteCronJobException extends Exception {}

class DeleteCronJobService
{
    public function handle(User $user, CronJob $excludeJob): void
    {
        // Set umask to 0177 so tempnam() creates the file at 0600 atomically.
        // This eliminates the TOCTOU window between creation and chmod.
        $oldUmask = umask(0177);
        $tmpFile = tempnam(sys_get_temp_dir(), 'laranode_cron_');
        umask($oldUmask);

        try {

            // Exclude the job being deleted from the re-synced crontab
            $jobs = CronJob::where('user_id', $user->id)
                ->where('active', true)
                ->where('id', '!=', $excludeJob->id)
                ->get();

            $lines = $jobs->map(fn ($job) => $job->schedule."\t".$job->command)->implode("\n");

            file_put_contents($tmpFile, $lines);

            $result = Process::run([
                'sudo',
                config('laranode.laranode_bin_path').'/laranode-cron.sh',
                'set',
                $user->systemUsername,
                $tmpFile,
            ]);

            if ($result->failed()) {
                throw new DeleteCronJobException(
                    'laranode-cron.sh set failed: '.$result->errorOutput()
                );
            }
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }
}
