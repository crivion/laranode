<?php

namespace App\Providers;

use App\Models\Backup;
use App\Models\CronJob;
use App\Models\Database;
use App\Models\ScheduledBackup;
use App\Models\Website;
use App\Policies\BackupPolicy;
use App\Policies\CronJobPolicy;
use App\Policies\DatabasePolicy;
use App\Policies\ScheduledBackupPolicy;
use App\Policies\WebsitePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Website::class => WebsitePolicy::class,
        Database::class => DatabasePolicy::class,
        Backup::class => BackupPolicy::class,
        ScheduledBackup::class => ScheduledBackupPolicy::class,
        CronJob::class => CronJobPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
