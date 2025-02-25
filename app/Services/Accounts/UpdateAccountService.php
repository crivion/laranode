<?php

namespace App\Services\Accounts;

use App\Models\User;
use Illuminate\Support\Facades\Process;
use Exception;

class UpdateAccountException extends Exception {}

class UpdateAccountService
{

    private string $laranodeBinPath;

    public function __construct(private User $account, private array $validated)
    {
        $this->laranodeBinPath = config('laranode.laranode_bin_path');
    }

    public function handle(): void
    {
        $this->updateUserShell($this->account->ssh_access);

        $this->account->update($this->validated);

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
            Process::run([
                'sudo',
                $this->laranodeBinPath . '/laranode-update-sh-password.sh',
                $this->account->systemUsername,
                $this->validated['new_password'],
            ]);
        }
    }

    private function updateUserShell(): void
    {

        if ($this->account->ssh_access != $this->validated['ssh_access']) {
            if ($this->validated['ssh_access']) {
                Process::run([
                    'sudo',
                    $this->laranodeBinPath . '/laranode-update-sh-access.sh',
                    $this->account->systemUsername,
                    'yes',
                ]);
            } else {
                Process::run([
                    'sudo',
                    $this->laranodeBinPath . '/laranode-update-sh-access.sh',
                    $this->account->systemUsername,
                    'no',
                ]);
            }
        }
    }
}
