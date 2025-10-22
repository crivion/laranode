<?php

namespace App\Actions\Firewall;

class BuildUfwRuleSpecAction
{
    public function execute(string $protocol, string $from, string $to, int $port, ?string $comment = null): string
    {
        $protocol = strtolower(trim($protocol));
        $from = trim($from);
        $to = trim($to);
        $port = (int) $port;
        $comment = trim((string) ($comment ?? ''));

        $parts = [
            'proto ' . $protocol,
            'from ' . $from,
            'to ' . $to,
            'port ' . $port,
        ];

        $spec = implode(' ', $parts);

        if ($comment !== '') {
            $commentEscaped = str_replace("'", "\\'", $comment);
            $spec .= " comment '" . $commentEscaped . "'";
        }

        return $spec;
    }
}
