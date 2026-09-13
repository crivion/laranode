<?php

namespace App\Actions\Firewall;

use Illuminate\Support\Facades\Process;

/**
 * Returns the user-added UFW rules via `ufw show added`.
 *
 * Unlike `ufw status`, this lists staged rules even while UFW is INACTIVE,
 * so we can tell whether enabling the firewall would lock the operator out
 * (no SSH / no panel rule) BEFORE actually enabling it.
 */
class GetStagedUfwRulesAction
{
    /**
     * @return array<int, string> raw `ufw ...` rule lines (empty on failure)
     */
    public function execute(): array
    {
        $proc = Process::run(['sudo', 'ufw', 'show', 'added']);
        if ($proc->failed()) {
            return [];
        }

        $lines = preg_split("/\r?\n/", trim($proc->output())) ?: [];

        return array_values(array_filter(
            array_map('trim', $lines),
            fn ($l) => str_starts_with($l, 'ufw ')
        ));
    }
}
