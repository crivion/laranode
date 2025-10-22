<?php

namespace App\Actions\Firewall;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class ToggleUfwAction
{
    public function execute(bool $enable): string
    {
        $bin = config('laranode.laranode_bin_path') . '/laranode-ufw.sh';
        $cmd = $enable ? 'enable' : 'disable';
        $proc = Process::run(['sudo', $bin, $cmd]);
        if ($proc->failed()) {
            throw new RuntimeException('UFW toggle failed: ' . $proc->errorOutput());
        }
        return trim($proc->output());
    }
}
