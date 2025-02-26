<?php

namespace App\Services\Accounts;

use App\Models\User;
use Illuminate\Support\Facades\Process;
use Exception;

class DeleteAccountException extends Exception {}

class DeleteAccountService
{
    private string $laranodeBinPath;

    public function __construct(private User $user)
    {
        // path to laranode user manager bin|ssh script
        $this->laranodeBinPath = config('laranode.laranode_bin_path');
    }

    public function handle(): void
    {
        // delete php-fpm pools
        $this->deletePhpFpmPools();

        // wait for pools to be deleted && fpm to restart
        sleep(1);

        // delete system user
        $this->deleteSystemUser();

        // remove user from database
        User::findOrFail($this->user->id)->delete();
    }

    private function deleteSystemUser(): void
    {
        $deleteUser = Process::run([
            'sudo',
            $this->laranodeBinPath . '/laranode-user-manager.sh',
            'delete',
            $this->user->systemUsername,
        ]);

        if ($deleteUser->failed()) {
            throw new DeleteAccountException('Failed to delete system user: ' . $deleteUser->errorOutput());
        }
    }

    private function deletePhpFpmPools(): void
    {
        $deletePhpFpmPool = Process::run([
            'sudo',
            $this->laranodeBinPath . '/laranode-remove-php-fpm-pool.sh',
            $this->user->systemUsername,
        ]);

        if ($deletePhpFpmPool->failed()) {
            throw new DeleteAccountException('Failed to delete PHP-FPM pool: ' . $deletePhpFpmPool->errorOutput());
        }
    }

    // TODO: HIGHLY CRUCIAL: implement delete websites from db and virtual hosts!!!!!!!!!
    private function deleteWebsites(): void {}

    // @TODO: implement delete all DB's of this user
    private function deleteDatabases(): void {}
}
