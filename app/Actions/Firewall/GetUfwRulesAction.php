<?php

namespace App\Actions\Firewall;

use Illuminate\Support\Facades\Process;

class GetUfwRulesAction
{
    public function execute(): array
    {
        $bin = config('laranode.laranode_bin_path') . '/laranode-ufw.sh';
        $proc = Process::run(['sudo', $bin, 'list']);
        if ($proc->failed()) {
            return [];
        }

        $lines = preg_split("/\r?\n/", trim($proc->output()));
        $rules = [];
        foreach ($lines as $line) {
            if (!preg_match('/^\s*\[(\s*\d+)\]\s+(.+?)\s+(ALLOW|DENY)\s+(IN|OUT)\s+(.+)$/i', $line, $m)) {
                continue;
            }
            $number = (int) trim($m[1]);
            $service = trim($m[2]);
            $action = strtoupper(trim($m[3]));
            $direction = strtoupper(trim($m[4]));
            $from = trim($m[5]);

            $rules[] = [
                'number' => $number,
                'service' => $service,
                'action' => $action,
                'direction' => $direction,
                'from' => $from,
            ];
        }
        return $rules;
    }
}
