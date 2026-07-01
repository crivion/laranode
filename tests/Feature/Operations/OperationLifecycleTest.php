<?php // tests/Feature/Operations/OperationLifecycleTest.php

use App\Events\OperationUpdated;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('markRunning sets running + broadcasts a status event', function () {
    Event::fake([OperationUpdated::class]);
    $op = Operation::create(['user_id' => User::factory()->create()->id, 'type' => 't']);

    $op->markRunning();

    expect($op->fresh()->status)->toBe('running')
        ->and($op->fresh()->started_at)->not->toBeNull();
    Event::assertDispatched(OperationUpdated::class, fn ($e) =>
        $e->operation->is($op) && $e->kind === 'status');
});

test('appendOutput accumulates lines + broadcasts each line', function () {
    Event::fake([OperationUpdated::class]);
    $op = Operation::create(['user_id' => User::factory()->create()->id, 'type' => 't']);

    $op->appendOutput('line one');
    $op->appendOutput('line two');

    expect($op->fresh()->output)->toBe("line one\nline two\n");
    Event::assertDispatchedTimes(OperationUpdated::class, 2);
    Event::assertDispatched(OperationUpdated::class, fn ($e) => $e->kind === 'line' && $e->line === 'line two');
});

test('markFinished maps exit code to status + broadcasts', function () {
    Event::fake([OperationUpdated::class]);
    $ok = Operation::create(['user_id' => User::factory()->create()->id, 'type' => 't']);
    $bad = Operation::create(['user_id' => User::factory()->create()->id, 'type' => 't']);

    $ok->markFinished(0);
    $bad->markFinished(1);

    expect($ok->fresh()->status)->toBe('succeeded')
        ->and($ok->fresh()->exit_code)->toBe(0)
        ->and($ok->fresh()->finished_at)->not->toBeNull()
        ->and($bad->fresh()->status)->toBe('failed');
    Event::assertDispatchedTimes(OperationUpdated::class, 2);
});

test('the event carries the agreed payload + channel', function () {
    User::factory()->count(7)->create(); // ensure user with id=7 exists for FK
    $op = Operation::create(['user_id' => 7, 'type' => 't']);
    $event = new OperationUpdated($op, 'line', 'hello');

    expect($event->broadcastAs())->toBe('OperationUpdated')
        ->and($event->broadcastWith())->toMatchArray([
            'operationId' => $op->id, 'kind' => 'line', 'status' => 'queued', 'line' => 'hello',
        ])
        ->and($event->broadcastOn()->name)->toBe('private-operations.7');
});
