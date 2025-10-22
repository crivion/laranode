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
        $bin = config('laranode.laranode_bin_path') . '/laranode-ufw.sh';
        $proc = Process::run(['sudo', $bin, 'allow', $ruleSpec]);
        if ($proc->failed()) {
            throw new RuntimeException('UFW allow failed: ' . $proc->errorOutput());
        }
    }
}
