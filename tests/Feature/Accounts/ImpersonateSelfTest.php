<?php

use App\Models\User;

test('admin cannot impersonate themselves (self-impersonation returns 403)', function () {
    $admin = User::factory()->isAdmin()->create();

    $response = $this
        ->actingAs($admin)
        ->get(route('accounts.impersonate', $admin));

    $response->assertForbidden();
});

test('admin can impersonate a non-admin user (returns redirect)', function () {
    $admin = User::factory()->isAdmin()->create();
    $user = User::factory()->isNotAdmin()->create();

    $response = $this
        ->actingAs($admin)
        ->get(route('accounts.impersonate', $user));

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

test('admin cannot impersonate another admin (canBeImpersonated blocks it, returns 403)', function () {
    $admin = User::factory()->isAdmin()->create();
    $otherAdmin = User::factory()->isAdmin()->create();

    $response = $this
        ->actingAs($admin)
        ->get(route('accounts.impersonate', $otherAdmin));

    $response->assertForbidden();
});

test('non-admin cannot impersonate any user (middleware returns 403)', function () {
    $nonAdmin = User::factory()->isNotAdmin()->create();
    $target = User::factory()->isAdmin()->create();

    $response = $this
        ->actingAs($nonAdmin)
        ->get(route('accounts.impersonate', $target));

    $response->assertForbidden();
});
