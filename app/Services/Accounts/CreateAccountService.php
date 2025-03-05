<?php

namespace App\Services\Accounts;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Process;
use Exception;

class CreateAccountException extends Exception {}

class CreateAccountService
{
    private string $laranodeBinPath;
    private string $systemUsername;

    public function __construct(private array $validated)
    {
        // path to laranode user manager bin|ssh script
        $this->laranodeBinPath = config('laranode.laranode_bin_path');

        // appends _ln to all users to avoid all sort of issues (conflicts, control, security, files, etc.)
        $this->systemUsername = $validated['username'] . '_ln';
    }

    public function handle(): void
    {
        // create system user
        $this->createSystemUser();

        // only after that add the user to the database
        $user = User::create($this->validated);
        event(new Registered($user));

        // notify user if requested
        // TODO: implement notification (mail)
        if ($this->validated['notify']) {
            \Illuminate\Support\Facades\Log::info('Would notify ' . $user->email);
        }
    }

    private function createSystemUser(): void
    {

        $createUser = Process::run([
            'sudo',
            $this->laranodeBinPath . '/laranode-user-manager.sh',
            'create',
            $this->systemUsername,
            $this->validated['ssh_access'] ? 'yes' : 'no',
            $this->validated['ssh_access'] ? $this->validated['password'] : null,
        ]);

        if ($createUser->failed()) {
            throw new CreateAccountException('Failed to create system user: ' . $createUser->errorOutput());
        }
    }
}
