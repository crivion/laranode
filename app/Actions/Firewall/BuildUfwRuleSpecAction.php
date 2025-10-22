<?php

namespace App\Actions\Firewall;

class BuildUfwRuleSpecAction
{
    public function execute(string $direction, string $protocol, string $from, string $to, int $port): string
    {
        $direction = strtolower(trim($direction));
        $protocol = strtolower(trim($protocol));
        $from = trim($from);
        $to = trim($to);
        $port = (int) $port;

        $parts = [
            $direction,
            'proto ' . $protocol,
            'from ' . $from,
            'to ' . $to,
            'port ' . $port,
        ];

        return implode(' ', $parts);
    }
}
