<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        $schedule->command('model:prune', ['--model' => [
            \App\Models\Operation::class,
            \App\Models\Backup::class,
            \App\Models\UserResourceSnapshot::class,
            \App\Models\UserSiteStat::class,
        ]])->daily();
        $schedule->job(new \App\Jobs\RunScheduledBackupsJob)->everyMinute();

        $schedule->call(new \App\Actions\SSL\SendSslExpiryNotificationsAction)
            ->dailyAt('08:00')
            ->description('ssl.expiring notifications');

        $schedule->call(function () {
            \App\Models\User::chunkById(50, function ($users) {
                foreach ($users as $user) {
                    $operation = \App\Models\Operation::create([
                        'user_id' => $user->id,
                        'type' => 'analytics.resource-rollup',
                        'target' => $user->username,
                    ]);
                    \App\Jobs\Analytics\RollupUserResourceSnapshotJob::dispatch($operation, $user);
                }
            });
        })->daily()->name('analytics.resource-rollup');

        $schedule->call(function () {
            \App\Models\User::chunkById(50, function ($users) {
                foreach ($users as $user) {
                    $operation = \App\Models\Operation::create([
                        'user_id' => $user->id,
                        'type' => 'analytics.site-rollup',
                        'target' => $user->username,
                    ]);
                    \App\Jobs\Analytics\RollupSiteStatsJob::dispatch($operation, $user);
                }
            });
        })->hourly()->name('analytics.site-rollup');
    })
    ->create();
