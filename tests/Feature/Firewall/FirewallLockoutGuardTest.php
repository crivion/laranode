<?php

use App\Models\User;
use Illuminate\Support\Facades\Process;

// The firewall must never be enabled without SSH + panel/web allow rules,
// otherwise the operator is locked out of the server. The guard protects the
// HTTP endpoint directly (not just the UI).

function cmdString($process): string
{
    return is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;
}

function fakeUfw(string $addedOutput): void
{
    // A closure fake is needed because Laravel's pattern-map matching casts an
    // array command to the literal string "Array", so 'sudo ufw show added'
    // would never match our array-form Process::run([...]) calls.
    Process::fake(function ($process) use ($addedOutput) {
        return str_contains(cmdString($process), 'ufw show added')
            ? Process::result(output: $addedOutput)
            : Process::result(output: 'ok');
    });
}

test('enabling is refused when no SSH rule is staged (no lockout)', function () {
    fakeUfw("Added user rules:\nufw allow 80/tcp\n");

    $admin = User::factory()->isAdmin()->create();

    $response = $this->actingAs($admin)
        ->post(route('firewall.toggle'), ['enabled' => true]);

    $response->assertRedirect(route('firewall.index'));
    $response->assertSessionHas('error');

    // ufw enable must NOT have run
    Process::assertDidntRun(fn ($p) => cmdString($p) === 'sudo ufw --force enable');
});

test('enabling is refused when no panel/web rule is staged', function () {
    fakeUfw("Added user rules:\nufw allow 22/tcp\n");

    $admin = User::factory()->isAdmin()->create();

    $response = $this->actingAs($admin)
        ->post(route('firewall.toggle'), ['enabled' => true]);

    $response->assertSessionHas('error');
    Process::assertDidntRun(fn ($p) => cmdString($p) === 'sudo ufw --force enable');
});

test('enabling succeeds when SSH and web rules are both staged', function () {
    fakeUfw("Added user rules:\nufw allow 22/tcp\nufw allow 80/tcp\n");

    $admin = User::factory()->isAdmin()->create();

    $response = $this->actingAs($admin)
        ->post(route('firewall.toggle'), ['enabled' => true]);

    $response->assertSessionHas('success');
    Process::assertRan(fn ($p) => cmdString($p) === 'sudo ufw --force enable');
});

test('disabling is always allowed regardless of staged rules', function () {
    fakeUfw("Added user rules:\n");

    $admin = User::factory()->isAdmin()->create();

    $response = $this->actingAs($admin)
        ->post(route('firewall.toggle'), ['enabled' => false]);

    $response->assertSessionHas('success');
    $response->assertSessionMissing('error');
});

test('safe setup stages SSH + HTTP + HTTPS allow rules and then enables', function () {
    Process::fake(['*' => Process::result(output: 'ok')]);

    $admin = User::factory()->isAdmin()->create();

    $this->actingAs($admin)
        ->post(route('firewall.safe-setup'))
        ->assertSessionHas('success');

    Process::assertRan(fn ($p) => cmdString($p) === 'sudo ufw allow 22/tcp');
    Process::assertRan(fn ($p) => cmdString($p) === 'sudo ufw allow 80/tcp');
    Process::assertRan(fn ($p) => cmdString($p) === 'sudo ufw allow 443/tcp');
    Process::assertRan(fn ($p) => cmdString($p) === 'sudo ufw --force enable');
});

test('safe setup can restrict SSH to a validated IP', function () {
    Process::fake(['*' => Process::result(output: 'ok')]);

    $admin = User::factory()->isAdmin()->create();

    $this->actingAs($admin)
        ->post(route('firewall.safe-setup'), ['ssh_from_ip' => '203.0.113.7'])
        ->assertSessionHas('success');

    Process::assertRan(fn ($p) => cmdString($p) === 'sudo ufw allow from 203.0.113.7 to any port 22 proto tcp');
    // it must NOT also open SSH to the world in that case
    Process::assertDidntRun(fn ($p) => cmdString($p) === 'sudo ufw allow 22/tcp');
});

test('safe setup rejects an invalid IP', function () {
    Process::fake(['*' => Process::result(output: 'ok')]);

    $admin = User::factory()->isAdmin()->create();

    $this->actingAs($admin)
        ->post(route('firewall.safe-setup'), ['ssh_from_ip' => 'not-an-ip'])
        ->assertSessionHasErrors('ssh_from_ip');

    Process::assertDidntRun(fn ($p) => cmdString($p) === 'sudo ufw --force enable');
});

test('non-admin cannot run safe setup', function () {
    Process::fake(['*' => Process::result(output: 'ok')]);

    $user = User::factory()->isNotAdmin()->create();

    $this->actingAs($user)
        ->post(route('firewall.safe-setup'))
        ->assertForbidden();
});
