<?php

namespace App\Actions\Firewall;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * One-click safe baseline: stage allow rules that keep the operator reachable
 * (SSH + HTTP + HTTPS + the panel's own port), then enable UFW. This is the
 * lockout-proof way to turn the firewall on.
 *
 * SSH is allowed from anywhere by default (the whole point is to NOT lock out).
 * Optionally restrict SSH to a single validated IP instead.
 */
class SafeSetupFirewallAction
{
    public function __construct(private ToggleUfwAction $toggle = new ToggleUfwAction) {}

    public function execute(int $panelPort, ?string $sshFromIp = null): void
    {
        // SSH: from anywhere, or restricted to a validated IP
        if ($sshFromIp !== null && $sshFromIp !== '') {
            if (filter_var($sshFromIp, FILTER_VALIDATE_IP) === false) {
                throw new RuntimeException('Invalid IP address for SSH restriction.');
            }
            $this->allow(['from', $sshFromIp, 'to', 'any', 'port', '22', 'proto', 'tcp']);
        } else {
            $this->allow(['22/tcp']);
        }

        // Web: panel + websites
        $this->allow(['80/tcp']);
        $this->allow(['443/tcp']);

        if (! in_array($panelPort, [80, 443], true)) {
            $this->assertPort($panelPort);
            $this->allow(["{$panelPort}/tcp"]);
        }

        $this->toggle->execute(true);
    }

    /**
     * @param  array<int, string>  $spec
     */
    private function allow(array $spec): void
    {
        $proc = Process::run(array_merge(['sudo', 'ufw', 'allow'], $spec));
        if ($proc->failed()) {
            throw new RuntimeException('UFW allow failed: '.$proc->errorOutput());
        }
    }

    private function assertPort(int $port): void
    {
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Invalid panel port.');
        }
    }
}
