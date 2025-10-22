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
        if (ctype_digit($idOrSpec)) {
            $cmd = 'yes | sudo ufw delete ' . (int) $idOrSpec;
        } else {
            $cmd = 'yes | sudo ufw delete ' . escapeshellarg($idOrSpec);
        }
        $proc = Process::run(['bash', '-lc', $cmd]);
        if ($proc->failed()) {
            throw new RuntimeException('UFW delete failed: ' . $proc->errorOutput());
        }
    }
}
