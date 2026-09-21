<?php

namespace App\Rules;

use App\Actions\MySQL\GetCharsetsAndCollationsAction;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Charset and collation are written into CREATE/ALTER DATABASE as keywords,
 * so they can't be bound as parameters. Only accept a pair the server
 * actually reports, which also guarantees they are plain identifiers.
 */
class SupportedCollation implements ValidationRule
{
    public function __construct(private mixed $charset) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $supported = collect(app(GetCharsetsAndCollationsAction::class)->execute()['collations'])
            ->contains(fn (array $collation) => $collation['name'] === $value && $collation['charset'] === $this->charset);

        if (! $supported) {
            $fail('The selected collation is not supported for this character set.');
        }
    }
}
