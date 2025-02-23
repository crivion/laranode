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
        $this->laranodeBinPath = '/usr/local/bin/laranode';
    }

    public function handle(): void
    {
        // delete system user
        $this->deleteSystemUser();

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

    // @TODO: implement delete all DB's of this user
    private function deleteDatabases(): void {}

    // @TODO: implement delete user php-fpm pools
    private function deletePhpFpmPools(): void {}
}
