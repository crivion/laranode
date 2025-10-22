<?php

namespace App\Actions\Firewall;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class ToggleUfwAction
{
    public function execute(bool $enable): string
    {
        if ($enable) {
            $proc = Process::run(['sudo', 'ufw', '--force', 'enable']);
        } else {
            // disable may prompt; confirm automatically
            $proc = Process::run(['bash', '-lc', 'yes | sudo ufw disable']);
        }
        if ($proc->failed()) {
            throw new RuntimeException('UFW toggle failed: ' . $proc->errorOutput());
        }
        return trim($proc->output());
    }
}p
