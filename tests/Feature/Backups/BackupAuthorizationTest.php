<?php

use App\Models\Backup;
use App\Models\User;

test('owners see only their backups and cannot operate on another users backup', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $own = Backup::create(['user_id' => $owner->id, 'scope' => 'account', 'kind' => 'manual', 'status' => 'completed', 'manifest' => ['account' => ['username' => $owner->username]]]);
    $foreign = Backup::create(['user_id' => $other->id, 'scope' => 'account', 'kind' => 'manual', 'status' => 'completed', 'manifest' => ['account' => ['username' => $other->username]]]);

    $this->actingAs($owner)->get(route('backups.index'))->assertOk()->assertInertia(fn ($page) => $page->has('backups', 1)->where('backups.0.id', $own->id));
    $this->actingAs($owner)->get(route('backups.download', $foreign))->assertForbidden();
    $this->actingAs($owner)->post(route('backups.restore', $foreign), ['confirm' => $other->username])->assertForbidden();
    $this->actingAs($owner)->delete(route('backups.destroy', $foreign))->assertForbidden();
});

test('admins can list all backups', function () {
    $admin = User::factory()->isAdmin()->create();
    $user = User::factory()->create();
    Backup::create(['user_id' => $user->id, 'scope' => 'account', 'kind' => 'manual', 'status' => 'completed']);
    $this->actingAs($admin)->get(route('backups.index'))->assertOk()->assertInertia(fn ($page) => $page->has('backups', 1));
});
