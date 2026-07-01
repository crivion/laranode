<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCronExpression implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute must be a valid 5-field cron expression.');

            return;
        }

        $fields = preg_split('/\s+/', trim($value));

        if (count($fields) !== 5) {
            $fail('The :attribute must contain exactly 5 fields (minute hour dom month dow).');

            return;
        }

        [$minute, $hour, $dom, $month, $dow] = $fields;

        $checks = [
            'minute' => [$minute, 0, 59],
            'hour' => [$hour,   0, 23],
            'dom' => [$dom,    1, 31],
            'month' => [$month,  1, 12],
            'dow' => [$dow,    0, 7],
        ];

        foreach ($checks as $name => [$field, $min, $max]) {
            if (! $this->validateField($field, $min, $max)) {
                $fail("The :attribute has an invalid {$name} field: {$field}.");

                return;
            }
        }
    }

    private function validateField(string $field, int $min, int $max): bool
    {
        // Comma-separated list: each part must be valid
        if (str_contains($field, ',')) {
            foreach (explode(',', $field) as $part) {
                if (! $this->validatePart($part, $min, $max)) {
                    return false;
                }
            }

            return true;
        }

        return $this->validatePart($field, $min, $max);
    }

    private function validatePart(string $part, int $min, int $max): bool
    {
        // Wildcard: *
        if ($part === '*') {
            return true;
        }

        // Step: */n or range/n
        if (str_contains($part, '/')) {
            [$base, $step] = explode('/', $part, 2);

            if (! ctype_digit($step) || (int) $step === 0) {
                return false;
            }

            // Base can be * or a range
            if ($base === '*') {
                return true;
            }

            return $this->validateRange($base, $min, $max);
        }

        // Range: n-m
        if (str_contains($part, '-')) {
            return $this->validateRange($part, $min, $max);
        }

        // Plain number
        if (! ctype_digit($part)) {
            return false;
        }

        $n = (int) $part;

        return $n >= $min && $n <= $max;
    }

    private function validateRange(string $range, int $min, int $max): bool
    {
        if (! str_contains($range, '-')) {
            return false;
        }

        [$start, $end] = explode('-', $range, 2);

        if (! ctype_digit($start) || ! ctype_digit($end)) {
            return false;
        }

        $s = (int) $start;
        $e = (int) $end;

        return $s >= $min && $e <= $max && $s <= $e;
    }
}
