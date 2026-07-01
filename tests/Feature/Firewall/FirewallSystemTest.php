<?php

// tests/Feature/Firewall/FirewallSystemTest.php
//
// Real UFW integration, gated behind LARANODE_SYSTEM_TESTS=1.
// Verifies the lockout-safety read path against a real `ufw` binary by STAGING
// allow rules while UFW is inactive (harmless — staged rules take effect only on
// enable) and reading them back. It deliberately never runs `ufw enable`, which
// could sever connectivity inside a container/VPS mid-test.
//
//   LARANODE_SYSTEM_TESTS=1 php artisan test --filter=FirewallSystemTest
//
// Pre-requisites: ufw installed; the process user may run `sudo ufw`.

use App\Actions\Firewall\FirewallSafety;
use App\Actions\Firewall\GetStagedUfwRulesAction;
use Illuminate\Support\Facades\Process;

function ufw(array $args): \Illuminate\Contracts\Process\ProcessResult
{
    return Process::run(array_merge(['sudo', 'ufw'], $args));
}

beforeEach(function () {
    if (! env('LARANODE_SYSTEM_TESTS')) {
        $this->markTestSkipped('LARANODE_SYSTEM_TESTS not set — skipping system integration tests.');
    }

    if (Process::run(['bash', '-lc', 'command -v ufw'])->failed()) {
        $this->markTestSkipped('ufw not installed.');
    }

    // Never touch an already-active firewall.
    $status = Process::run(['sudo', 'ufw', 'status'])->output();
    if (str_contains(strtolower($status), 'status: active')) {
        $this->markTestSkipped('UFW is active — refusing to mutate rules in a system test.');
    }
});

afterEach(function () {
    // Best-effort cleanup of anything we staged (ignore "non-existent rule").
    ufw(['delete', 'allow', '22/tcp']);
    ufw(['delete', 'allow', '80/tcp']);
});

test('staged allow rules are read back and judged safe by the real ufw', function () {
    ufw(['allow', '22/tcp']);
    ufw(['allow', '80/tcp']);

    $staged = (new GetStagedUfwRulesAction)->execute();

    expect(FirewallSafety::coversSsh($staged))->toBeTrue();
    expect(FirewallSafety::coversWeb($staged, 80))->toBeTrue();
    expect(FirewallSafety::missingProtections($staged, 80))->toBe([]);
});

test('removing the SSH rule makes the real config unsafe to enable', function () {
    ufw(['allow', '22/tcp']);
    ufw(['allow', '80/tcp']);
    ufw(['delete', 'allow', '22/tcp']);

    $staged = (new GetStagedUfwRulesAction)->execute();

    expect(FirewallSafety::coversSsh($staged))->toBeFalse();
    expect(FirewallSafety::missingProtections($staged, 80))->not->toBe([]);
});
