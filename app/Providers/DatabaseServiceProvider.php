<?php

namespace App\Providers;

use App\Databases\EngineManager;
use Illuminate\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register EngineManager as a singleton so memoization persists
        // for the lifetime of a single request.
        $this->app->singleton(EngineManager::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
