<?php

namespace App\Providers;

use App\Actions\Filemanager\CreateFileAction;
use App\Actions\Filemanager\DeleteFilesAction;
use App\Actions\Filemanager\GetDirectoryContentsAction;
use App\Actions\Filemanager\GetFileContentsAction;
use App\Actions\Filemanager\PasteFilesAction;
use App\Actions\Filemanager\RenameFileAction;
use App\Actions\Filemanager\UpdateFileContentsAction;
use App\Filesystem\SymlinkSafeLocalAdapter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
                if (! Auth::check()) {
                    return null;
                }

                $userHome = Auth::user()->homedir;

                Config::set('laranode.user_base_path', $userHome);

                // writes must not follow a symlink out of the home - see the adapter.
                // SKIP_LINKS omits symlinks from a listing; DISALLOW_LINKS threw
                // instead, so a single link made the whole directory unbrowsable
                // (a tenant's own public/storage link was enough). Containment no
                // longer rests on this flag - every mutation goes through the
                // O_NOFOLLOW helper, which refuses a link whether it is listed or not.
                $adapter = new SymlinkSafeLocalAdapter(
                    $userHome,
                    LOCK_EX,
                    SymlinkSafeLocalAdapter::SKIP_LINKS,
                    Auth::user()->systemUsername,
                    config('laranode.laranode_bin_path'),
                    config('laranode.max_editable_file_size'),
                );

                return new Filesystem($adapter);
            });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        if (Auth::check()) {
            $user = Auth::user();
            Config::set('laranode.user_base_path', $user->homedir);
        }
    }
}
