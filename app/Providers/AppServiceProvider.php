<?php

namespace App\Providers;

use App\Actions\Filemanager\CreateFileAction;
use App\Actions\Filemanager\DeleteFilesAction;
use App\Actions\Filemanager\GetDirectoryContentsAction;
use App\Actions\Filemanager\GetFileContentsAction;
use App\Actions\Filemanager\PasteFilesAction;
use App\Actions\Filemanager\RenameFileAction;
use App\Actions\Filemanager\UpdateFileContentsAction;
use App\Models\Option;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Set User Filesystem Path
        // also used in GetFileContentsAction
        // @todo: change to actual user path
        Config::set('laranode.user_base_path', base_path());

        // Set Laranode Filemanager Classes
        $laranodeFileManagerClasses = [
            GetDirectoryContentsAction::class,
            GetFileContentsAction::class,
            CreateFileAction::class,
            RenameFileAction::class,
            UpdateFileContentsAction::class,
            PasteFilesAction::class,
            DeleteFilesAction::class,
        ];

        $this->app->when($laranodeFileManagerClasses)
            ->needs(Filesystem::class)
            ->give(function () {
                if (!Auth::check()) return null;
                $adapter = new LocalFilesystemAdapter(Config::get('laranode.user_base_path'), null, LOCK_EX, LocalFilesystemAdapter::DISALLOW_LINKS);
                return new Filesystem($adapter);
            });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        URL::forceScheme('https');
    }
}
