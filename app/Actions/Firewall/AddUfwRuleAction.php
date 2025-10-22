<?php

namespace App\Actions\Firewall;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class AddUfwRuleAction
{
    public function execute(string $ruleSpec): void
    {
        $ruleSpec = trim($ruleSpec);
        if ($ruleSpec === '') {
            throw new RuntimeException('Empty rule spec');
        }
        $proc = Process::run(['bash', '-lc', 'sudo ufw allow ' . escapeshellarg($ruleSpec)]);
        if ($proc->failed()) {
            throw new RuntimeException('UFW allow failed: ' . $proc->errorOutput());
        }
    }
}
