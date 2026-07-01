<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedCronCommand implements ValidationRule
{
    // Shell / crontab metacharacters that would allow command injection.
    // Bare '&', '$', '\\' close denylist gaps; '%' is a crontab newline escape.
    private const SHELL_METACHARACTERS = [';', '|', '&', '>', '<', '$', '`', '\\', '%'];

    public function __construct(private readonly User $user) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute must be a non-empty command.');

            return;
        }

        // Reject control characters (newline/CR/tab/null/etc.) FIRST — these enable
        // crontab line-injection and bypass whitespace tokenisation of the path.
        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            $fail('The :attribute must not contain control characters.');

            return;
        }

        // Reject any shell / crontab metacharacters
        foreach (self::SHELL_METACHARACTERS as $meta) {
            if (str_contains($value, $meta)) {
                $fail('The :attribute must not contain shell metacharacters.');

                return;
            }
        }

        // Must start with "php " — no other executables in v1
        if (! str_starts_with($value, 'php ')) {
            $fail('The :attribute must start with "php" (only php commands are allowed in v1).');

            return;
        }

        // Reject flags that take a path argument or allow arbitrary code execution
        // php -r (run inline), php -f (run file via flag), and any other -<flag> before the path
        $parts = preg_split('/\s+/', $value, 3);
        // $parts[0] = 'php', $parts[1] = first argument
        if (count($parts) < 2) {
            $fail('The :attribute is incomplete.');

            return;
        }

        $firstArg = $parts[1] ?? '';

        // Any argument starting with '-' is a flag — reject it
        if (str_starts_with($firstArg, '-')) {
            $fail('The :attribute must not use php flags (e.g. -r, -f).');

            return;
        }

        // The path must be within the user's own homedir.
        // Reject any path containing '..' to prevent directory traversal bypass
        // (e.g. 'php /home/alice_ln/../other_ln/evil.php' would otherwise pass str_starts_with).
        if (str_contains($firstArg, '..')) {
            $fail('The :attribute path must not contain directory traversal sequences (..).');

            return;
        }

        $homedir = $this->user->homedir;

        if (! str_starts_with($firstArg, $homedir.'/')) {
            $fail("The :attribute path must be within your home directory ({$homedir}).");

            return;
        }
    }
}
