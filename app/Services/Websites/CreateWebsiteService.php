<?php

namespace App\Services\Websites;

use App\Actions\Filemanager\EnsurePermissionsAction;
use App\Models\User;
use App\Models\Website;
use App\Services\Laranode\AddVhostEntryService;
use App\Services\Laranode\CreatePhpFpmPoolService;
use Exception;
use Illuminate\Support\Facades\Process;

class CreateWebsiteException extends Exception {}

class CreateWebsiteService
{
    private Website $website;
    public function __construct(private array $validated, private User $user) {}


    public function handle(): void
    {
        $this->website = $this->user->websites()->make($this->validated);

        // create document root directory and apply permissions
        $this->createDocumentRoot();

        // add apache vhost entry and fpm pool if required
        $this->addVhostEntry();

        // if all was successful, save website
        $this->website->save();
    }

    private function createDocumentRoot(): void
    {
        Process::run([
            'sudo',
            config('laranode.laranode_bin_path') . '/laranode-create-directory.sh',
            $this->website->fullDocumentRoot,
            $this->user->username,
        ]);
    }

    private function addVhostEntry(): void
    {
        (new CreatePhpFpmPoolService($this->website))->handle();
        (new AddVhostEntryService($this->website))->handle();
    }
}
