<?php

namespace App\Actions\Firewall;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class AddUfwDenyRuleAction
{
    public function execute(string $ruleSpec): void
    {
        $ruleSpec = trim($ruleSpec);
        if ($ruleSpec === '') {
            throw new RuntimeException('Empty rule spec');
        }
        $proc = Process::run(['bash', '-lc', 'sudo ufw deny ' . escapeshellarg($ruleSpec)]);
        if ($proc->failed()) {
            throw new RuntimeException('UFW deny failed: ' . $proc->errorOutput());
        }
    }
}
