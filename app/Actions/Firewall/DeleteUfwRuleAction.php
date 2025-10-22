<?php

namespace App\Actions\Firewall;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class DeleteUfwRuleAction
{
    public function execute(string $idOrSpec): void
    {
        $idOrSpec = trim($idOrSpec);
        if ($idOrSpec === '') {
            throw new RuntimeException('Empty rule id/spec');
        }
        $bin = config('laranode.laranode_bin_path') . '/laranode-ufw.sh';
        $proc = Process::run(['sudo', $bin, 'delete', $idOrSpec]);
        if ($proc->failed()) {
            throw new RuntimeException('UFW delete failed: ' . $proc->errorOutput());
        }
    }
}
