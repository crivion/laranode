<?php

use App\Models\Operation;
use App\Models\User;

test('an admin can view the operations audit page', function () {
    $admin = User::factory()->isAdmin()->create();
    Operation::create(['user_id' => $admin->id, 'type' => 'ssl.generate', 'target' => 'demo.test']);

    $this->actingAs($admin)
        ->get(route('operations.index'))
        ->assertOk();
});

test('a non-admin cannot view the operations audit page', function () {
    $user = User::factory()->isNotAdmin()->create();

    $this->actingAs($user)
        ->get(route('operations.index'))
        ->assertForbidden();
});
