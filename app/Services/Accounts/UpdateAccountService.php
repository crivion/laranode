<?php

namespace App\Services\Accounts;

use App\Models\PhpVersion;
use App\Models\User;
use App\Services\Laranode\CreatePhpFpmPoolService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Process;
use Exception;

class UpdateAccountException extends Exception {}

class UpdateAccountService
{
    public function __construct(private User $account, private array $validated) {}

    public function handle(): void
    {
        $this->account->update($this->validated);

        $this->updateUserShell($this->account->ssh_access);

        $this->updatePasswordIfRequested();
    }

    private function updatePasswordIfRequested(): void
    {
        if (!empty($this->validated['new_password'])) {
            $this->account->password = $this->validated['new_password'];
            $this->account->save();

            $this->updateSystemUserPasssword();
        }
    }

    private function updateSystemUserPasssword(): void
    {
        $this->account->refresh();

        if ($this->account->ssh_access) {
            // TODO: call laranode password update manager
        }
    }

    private function updateUserShell(): void
    {
        // TODO: call laranode user manager
        if ($this->account->ssh_access != $this->validated['ssh_access']) {
            if ($this->validated['ssh_access']) {
                // add shell access
            } else {
                // remove shell access
            }
        }
    }
}
