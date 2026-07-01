<?php

use App\Models\User;
use Illuminate\Support\Facades\Process;

// Shared stub helper – returns a Process::fake() map that stubs both the
// php-list.sh call (returning the provided JSON) and the php-install.sh call.
function phpListStub(string $json): array
{
    return [
        '*laranode-php-list.sh*' => Process::result($json),
        '*laranode-php-install.sh*' => Process::result('PHP 8.3 installed successfully'),
    ];
}

test('POST php.install with already-installed version returns 409', function () {
    Process::fake(phpListStub('[{"version":"8.4","status":"active","enabled":true}]'));

    $admin = User::factory()->isAdmin()->create();

    $response = $this
        ->actingAs($admin)
        ->postJson(route('php.install'), ['version' => '8.4']);

    $response->assertStatus(409)
        ->assertJson([
            'success' => false,
            'message' => 'PHP 8.4 is already installed',
        ]);
});

test('POST php.install with non-installed version calls install script and returns 200', function () {
    Process::fake(phpListStub('[{"version":"8.4","status":"active","enabled":true}]'));

    $admin = User::factory()->isAdmin()->create();

    $response = $this
        ->actingAs($admin)
        ->postJson(route('php.install'), ['version' => '8.3']);

    // Install script was called
    Process::assertRan(fn ($process) =>
        str_contains(implode(' ', $process->command), 'laranode-php-install.sh')
    );

    $response->assertStatus(200)
        ->assertJson(['success' => true]);
});

test('POST php.install with invalid version format returns 422', function () {
    $admin = User::factory()->isAdmin()->create();

    $response = $this
        ->actingAs($admin)
        ->postJson(route('php.install'), ['version' => 'invalid']);

    $response->assertStatus(422);
});
