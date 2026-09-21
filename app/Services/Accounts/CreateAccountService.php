<?php

namespace App\Services\Accounts;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;
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
        // 'notify' is nullable in CreateAccountRequest, so validated() omits it
        // entirely when the form does not send it
        if ($this->validated['notify'] ?? false) {
            Log::info('Would notify ' . $user->email);
        }

        // last, because it takes the web process down with it shortly after
        $this->reloadWebProcessGroups();
    }

    /**
     * laranode-user-manager.sh adds www-data to the new user's group so the panel
     * can reach their home, but a process's supplementary groups are fixed when it
     * starts - usermod does not reach the PHP-FPM workers already running. Until
     * they are replaced the new account's 770 homedir is unreadable to the panel,
     * and the file manager shows an empty directory rather than an error.
     */
    private function reloadWebProcessGroups(): void
    {
        // the pool serving this request is the one that needs the new group, and
        // it is the one running this code - so ask PHP which version it is rather
        // than guessing at a configured default
        $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

        // the script backgrounds the restart behind a short sleep, so the response
        // to this request still gets out
        $restart = Process::run([
            'sudo',
            $this->laranodeBinPath . '/laranode-restart-php-fpm.sh',
            $phpVersion,
        ]);

        if ($restart->failed()) {
            // the account exists and works once PHP-FPM restarts by any means, so
            // this is worth a warning rather than failing the creation
            Log::warning('Created the account but could not restart php' . $phpVersion . '-fpm; its home '
                . 'stays unreadable to the panel until PHP-FPM restarts: ' . $restart->errorOutput());
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
