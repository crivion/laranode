<?php

use App\Actions\Firewall\FirewallSafety;
use Illuminate\Support\Facades\Config;

// Pure parsing/decision logic that keeps the operator from locking themselves
// out — it must correctly read `ufw show added` lines.

test('coversSsh recognises numeric port 22, the OpenSSH profile and from-ip rules', function () {
    expect(FirewallSafety::coversSsh(['ufw allow 22/tcp']))->toBeTrue();
    expect(FirewallSafety::coversSsh(['ufw allow OpenSSH']))->toBeTrue();
    expect(FirewallSafety::coversSsh(['ufw allow from 203.0.113.5 to any port 22']))->toBeTrue();

    expect(FirewallSafety::coversSsh(['ufw allow 80/tcp']))->toBeFalse();
    expect(FirewallSafety::coversSsh([]))->toBeFalse();
});

test('coversWeb recognises 80, 443, the panel port and the Apache profile', function () {
    expect(FirewallSafety::coversWeb(['ufw allow 80/tcp'], 80))->toBeTrue();
    expect(FirewallSafety::coversWeb(['ufw allow 443/tcp'], 80))->toBeTrue();
    expect(FirewallSafety::coversWeb(['ufw allow Apache Full'], 80))->toBeTrue();
    // a non-standard panel port must be honoured
    expect(FirewallSafety::coversWeb(['ufw allow 8443/tcp'], 8443))->toBeTrue();

    expect(FirewallSafety::coversWeb(['ufw allow 22/tcp'], 80))->toBeFalse();
    expect(FirewallSafety::coversWeb([], 80))->toBeFalse();
});

test('coveredPorts extracts every numeric port form', function () {
    $lines = [
        'ufw allow 22/tcp',
        'ufw allow 80',
        'ufw allow from 1.2.3.4 to any port 8443',
    ];

    expect(FirewallSafety::coveredPorts($lines))
        ->toEqualCanonicalizing([22, 80, 8443]);
});

test('missingProtections is empty only when SSH and web are both covered', function () {
    $safe = ['ufw allow 22/tcp', 'ufw allow 80/tcp'];
    expect(FirewallSafety::missingProtections($safe, 80))->toBe([]);

    $noSsh = ['ufw allow 80/tcp'];
    expect(FirewallSafety::missingProtections($noSsh, 80))->toHaveCount(1);

    expect(FirewallSafety::missingProtections([], 80))->toHaveCount(2);
});

test('panelHttpPort derives the port from APP_URL', function () {
    Config::set('app.url', 'http://localhost');
    expect(FirewallSafety::panelHttpPort())->toBe(80);

    Config::set('app.url', 'https://panel.example.com');
    expect(FirewallSafety::panelHttpPort())->toBe(443);

    Config::set('app.url', 'http://panel.example.com:8443');
    expect(FirewallSafety::panelHttpPort())->toBe(8443);
});
