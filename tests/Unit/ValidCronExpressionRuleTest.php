<?php

use App\Rules\ValidCronExpression;

// Pure unit test — does not need the Laravel app container.
// We call the rule's validate() method directly, capturing failures via closure.

function validateCronExpressionDirect(string $value): bool
{
    $rule = new ValidCronExpression;
    $failed = false;
    $rule->validate('schedule', $value, function () use (&$failed) {
        $failed = true;
    });

    return ! $failed;
}

// ---- ACCEPTED expressions ----

dataset('valid_cron_expressions', [
    'every minute' => ['* * * * *'],
    'at minute 0' => ['0 * * * *'],
    'at 02:00 every day' => ['0 2 * * *'],
    'mondays at midnight' => ['0 0 * * 1'],
    'first of month midnight' => ['0 0 1 * *'],
    'at 59:23' => ['59 23 * * *'],
    'step on minutes' => ['*/5 * * * *'],
    'step on hours' => ['0 */2 * * *'],
    'range on dom' => ['0 0 1-15 * *'],
    'comma list on dow' => ['0 0 * * 1,3,5'],
    'range + step' => ['0 0 * * 0-6/2'],
    'dow 7 (sunday alt)' => ['0 0 * * 7'],
    'month range' => ['0 0 1 1-6 *'],
    'minute range' => ['30-45 * * * *'],
    'all stars' => ['* * * * *'],
]);

test('ValidCronExpression accepts valid expressions', function (string $expr) {
    expect(validateCronExpressionDirect($expr))->toBeTrue();
})->with('valid_cron_expressions');

// ---- REJECTED expressions ----

dataset('invalid_cron_expressions', [
    'only 4 fields no trail' => ['* * * *'],
    '6 fields' => ['* * * * * *'],
    'minute out of range' => ['60 * * * *'],
    'hour out of range' => ['0 24 * * *'],
    'dom out of range' => ['0 0 0 * *'],
    'month out of range' => ['0 0 1 13 *'],
    'non-numeric garbage' => ['abc * * * *'],
    'empty string' => [''],
    'invalid step zero' => ['*/0 * * * *'],
    'range reversed' => ['10-5 * * * *'],
    'negative minute' => ['-1 * * * *'],
]);

test('ValidCronExpression rejects invalid expressions', function (string $expr) {
    expect(validateCronExpressionDirect($expr))->toBeFalse();
})->with('invalid_cron_expressions');
