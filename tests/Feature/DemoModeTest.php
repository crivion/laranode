<?php

use App\Models\User;
use App\Models\Website;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('app.key', str_repeat('a', 32));
    config()->set('laranode.demo.enabled', true);
    config()->set('laranode.demo.email', 'demo@laranode.test');
});

test('a visitor can enter the limited demo with one click', function () {
    $response = $this->post(route('demo.login'));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
    expect(auth()->user()->email)->toBe('demo@laranode.test');
    expect(Website::where('url', 'demo.laranode.test')->exists())->toBeTrue();
});

test('demo mode simulates mutations without changing data', function () {
    $user = User::factory()->isAdmin()->create();

    $response = $this->actingAs($user)->post(route('websites.store'), [
        'url' => 'should-not-exist.test',
        'document_root' => '/public',
        'php_version_id' => 999,
    ], ['X-Inertia' => 'true']);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Demo only: action simulated. No server or stored data was changed.');
    expect(Website::where('url', 'should-not-exist.test')->exists())->toBeFalse();
});

test('demo mode serves simulated host data', function () {
    $user = User::factory()->isAdmin()->create();

    $this->actingAs($user)
        ->get(route('firewall.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Firewall/Index')
            ->where('status', 'Status: active')
            ->has('rules', 3));

    $this->actingAs($user)
        ->get(route('php.list'))
        ->assertOk()
        ->assertJsonPath('0.version', '8.4');

    $this->actingAs($user)
        ->get(route('filemanager.getDirectorContents', ['path' => '/']))
        ->assertOk()
        ->assertJsonPath('files.0.path', 'domains');
});

test('demo-only entry point is hidden on normal installations', function () {
    config()->set('laranode.demo.enabled', false);

    $this->post(route('demo.login'))->assertNotFound();
});
