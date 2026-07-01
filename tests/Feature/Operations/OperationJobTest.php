<?php // tests/Feature/Operations/OperationJobTest.php

use App\Jobs\OperationJob;
use App\Models\Operation;
use App\Models\User;

// inline test-double subclasses
class SucceedingOperationJob extends OperationJob {
    protected function run(callable $emit): int {
        $emit('doing work'); $emit('more work');
        return 0;
    }
}
class ThrowingOperationJob extends OperationJob {
    protected function run(callable $emit): int {
        $emit('starting');
        throw new \RuntimeException('boom');
    }
}

test('a succeeding job drives the operation to succeeded with captured output', function () {
    $op = Operation::create(['user_id' => User::factory()->create()->id, 'type' => 'demo']);

    (new SucceedingOperationJob($op))->handle();

    $op->refresh();
    expect($op->status)->toBe('succeeded')
        ->and($op->exit_code)->toBe(0)
        ->and($op->output)->toBe("doing work\nmore work\n")
        ->and($op->started_at)->not->toBeNull()
        ->and($op->finished_at)->not->toBeNull();
});

test('a throwing job marks the operation failed, records the error, and rethrows', function () {
    $op = Operation::create(['user_id' => User::factory()->create()->id, 'type' => 'demo']);

    expect(fn () => (new ThrowingOperationJob($op))->handle())
        ->toThrow(\RuntimeException::class);

    $op->refresh();
    expect($op->status)->toBe('failed')
        ->and($op->exit_code)->toBe(1)
        ->and($op->output)->toContain('starting')
        ->and($op->output)->toContain('ERROR: boom');
});
