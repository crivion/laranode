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

        $parts = preg_split('/\s+/', trim($ruleSpec));
        $parts = array_filter($parts, fn($v) => $v !== '');
        $proc = Process::run(array_merge(['sudo', 'ufw', 'allow'], $parts));

        if ($proc->failed()) {
            throw new RuntimeException('UFW allow failed: ' . $proc->errorOutput());
        }
    }
}
