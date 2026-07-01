<?php

use App\Models\User;
use App\Rules\AllowedCronCommand;

// Pure unit tests — call the rule's validate() method directly without the container.

function makeTestUser(string $username): User
{
    $user = new User;
    $user->username = $username;

    return $user;
}

function validateCronCommandDirect(string $command, string $username = 'alice'): bool
{
    $user = makeTestUser($username);
    $rule = new AllowedCronCommand($user);
    $failed = false;
    $rule->validate('command', $command, function () use (&$failed) {
        $failed = true;
    });

    return ! $failed;
}

// ---- ACCEPTED commands ----

dataset('valid_cron_commands', [
    'php artisan command' => ['php /home/alice_ln/artisan inspire', 'alice'],
    'php artisan with args' => ['php /home/alice_ln/artisan queue:work --tries=3', 'alice'],
    'php script in homedir' => ['php /home/alice_ln/scripts/run.php', 'alice'],
    'php artisan at subdir' => ['php /home/alice_ln/panel/artisan migrate', 'alice'],
    'bob user own homedir' => ['php /home/bob_ln/artisan schedule:run', 'bob'],
]);

test('AllowedCronCommand accepts valid commands', function (string $command, string $username) {
    expect(validateCronCommandDirect($command, $username))->toBeTrue();
})->with('valid_cron_commands');

// ---- REJECTED commands ----

dataset('blocked_cron_commands', [
    'wget https url' => ['wget https://example.com/script.sh', 'alice'],
    'curl https url' => ['curl https://example.com -o /tmp/x', 'alice'],
    'php -r inline code' => ["php -r 'echo 1;'", 'alice'],
    'php -f flag with path' => ['php -f /home/alice_ln/run.php', 'alice'],
    'php outside own homedir' => ['php /home/other_ln/artisan inspire', 'alice'],
    'php semicolon injection' => ['php /home/alice_ln/artisan inspire; rm -rf /', 'alice'],
    'php ampersand chain' => ['php /home/alice_ln/artisan inspire && wget x', 'alice'],
    'php pipe' => ['php /home/alice_ln/artisan inspire | cat', 'alice'],
    'php redirect' => ['php /home/alice_ln/artisan inspire > /tmp/out', 'alice'],
    'php backtick' => ['php /home/alice_ln/artisan `id`', 'alice'],
    'php dollar paren subshell' => ['php /home/alice_ln/artisan $(id)', 'alice'],
    'rm -rf /' => ['rm -rf /', 'alice'],
    'bash script' => ['bash /home/alice_ln/run.sh', 'alice'],
    'python script' => ['python /home/alice_ln/run.py', 'alice'],
    'node script' => ['node /home/alice_ln/run.js', 'alice'],
    'php path /etc/ escape' => ['php /etc/passwd', 'alice'],
    'php leading dash arg' => ['php --version', 'alice'],
    'empty command' => ['', 'alice'],
    'php or-chain' => ['php /home/alice_ln/artisan || true', 'alice'],
    'php lt redirect' => ['php /home/alice_ln/artisan < /etc/passwd', 'alice'],
    'path traversal dotdot' => ['php /home/alice_ln/../other_ln/evil.php', 'alice'],
    'path traversal to etc' => ['php /home/alice_ln/../../etc/passwd', 'alice'],
    'newline injection' => ["php /home/alice_ln/artisan inspire\nrm -rf /", 'alice'],
    'carriage return injection' => ["php /home/alice_ln/artisan\rrm", 'alice'],
    'null byte injection' => ["php /home/alice_ln/artisan\x00rm", 'alice'],
    'tab control char' => ["php\t/home/alice_ln/artisan", 'alice'],
    'bare ampersand background' => ['php /home/alice_ln/artisan inspire &', 'alice'],
    'bare dollar variable' => ['php /home/alice_ln/artisan $HOME', 'alice'],
    'backslash escape' => ['php /home/alice_ln/artisan\\x', 'alice'],
    'crontab percent newline' => ['php /home/alice_ln/artisan inspire%rm', 'alice'],
]);

test('AllowedCronCommand blocks disallowed commands', function (string $command, string $username) {
    expect(validateCronCommandDirect($command, $username))->toBeFalse();
})->with('blocked_cron_commands');
