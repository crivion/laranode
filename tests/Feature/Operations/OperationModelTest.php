<?php // tests/Feature/Operations/OperationModelTest.php

use App\Models\Operation;
use App\Models\User;

test('an operation belongs to a user and defaults to queued', function () {
    $user = User::factory()->create();
    $op = Operation::create([
        'user_id' => $user->id,
        'type' => 'demo.run',
        'target' => 'example.test',
    ]);

    expect($op->status)->toBe('queued')
        ->and($op->user->is($user))->toBeTrue();
});

test('scopeMine restricts non-admins to their own operations', function () {
    $admin = User::factory()->isAdmin()->create();
    $user = User::factory()->isNotAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();

    Operation::create(['user_id' => $user->id, 'type' => 't']);
    Operation::create(['user_id' => $other->id, 'type' => 't']);

    $this->actingAs($user);
    expect(Operation::mine()->count())->toBe(1);

    $this->actingAs($admin);
    expect(Operation::mine()->count())->toBe(2);
});

test('prunable targets operations older than 30 days', function () {
    $old = Operation::create(['user_id' => User::factory()->create()->id, 'type' => 't']);
    $old->forceFill(['created_at' => now()->subDays(31)])->save();
    Operation::create(['user_id' => User::factory()->create()->id, 'type' => 't']);

    expect((new Operation)->prunable()->count())->toBe(1);
});
