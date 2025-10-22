<?php

namespace App\Actions\Firewall;

use Illuminate\Support\Facades\Process;

class GetUfwStatusAction
{
    public function execute(): string
    {
        $proc = Process::run(['sudo', 'ufw', 'status']);
        if ($proc->failed()) {
            return 'unknown';
        }
        $out = trim($proc->output());
        $lines = preg_split("/\r?\n/", $out);
        return trim($lines[0] ?? $out);
    }
}
