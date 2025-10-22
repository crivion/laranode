<?php

namespace App\Actions\Firewall;

use Illuminate\Support\Facades\Process;

class GetUfwStatusAction
{
    public function execute(): string
    {
        $bin = config('laranode.laranode_bin_path') . '/laranode-ufw.sh';
        $proc = Process::run(['sudo', $bin, 'status']);
        if ($proc->failed()) {
            return 'unknown';
        }
        return trim($proc->output());
    }
}
