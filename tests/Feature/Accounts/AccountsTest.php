<?php

use App\Models\User;

test('admin can see accounts page', function () {
    $user = User::factory()->isAdmin()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('accounts.index'));

    $response->assertOk();
});

test('admin can create accounts', function () {
    $user = User::factory()->isAdmin()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('accounts.store'), [
            'name' => 'Test User',
            'username' => 'test-user',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'ssh_access' => false,
            'role' => 'user',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('accounts.index'));

    $this->assertDatabaseHas('users', [
        'name' => 'Test User',
        'username' => 'test-user',
        'email' => 'test@example.com',
        'ssh_access' => false,
        'role' => 'user',
        'domain_limit' => null,
        'database_limit' => null,
    ]);
});

test('admin can impersonate other users', function () {
    $admin = User::factory()->isAdmin()->create();
    $user = User::factory()->isNotAdmin()->create();

    $response = $this
        ->actingAs($admin)
        ->get(route('accounts.impersonate', [
            'user' => $user
        ]));

    $response->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('non admin cannot impersonate other users', function () {
    $admin = User::factory()->isAdmin()->create();
    $user = User::factory()->isNotAdmin()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('accounts.impersonate', [
            'user' => $admin
        ]));

    $response->assertForbidden();
});


test('non admin cannot see accounts page', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('accounts.index'));

    $response->assertForbidden();
});

test('non admin cannot create accounts', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('accounts.store'), [
            'name' => 'Test User',
            'username' => 'test-user',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'ssh_access' => false,
            'role' => 'user',
        ]);

    $response->assertForbidden();
});
