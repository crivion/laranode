<?php

namespace App\Actions\SSL;

use App\Models\Website;
use Illuminate\Support\Facades\Process;
use Exception;

class GenerateWebsiteSslAction
{
    public function execute(Website $website, string $email): void
    {
        // Update status to pending and mark enabled
        $website->update([
            'ssl_status' => 'pending',
            'ssl_enabled' => true,
        ]);

        $result = Process::run([
            'sudo',
            config('laranode.laranode_bin_path') . '/laranode-ssl-manager.sh',
            'generate',
            $website->url,
            $email,
            $website->fullDocumentRoot,
        ]);

        if ($result->failed()) {
            $website->update([
                'ssl_status' => 'inactive',
                'ssl_enabled' => false,
            ]);
            throw new Exception($result->errorOutput());
        }

        // verify status after generation
        $statusResult = Process::run([
            'sudo',
            config('laranode.laranode_bin_path') . '/laranode-ssl-manager.sh',
            'status',
            $website->url,
        ]);

        $sslStatus = trim($statusResult->output());

        $website->update([
            'ssl_status' => $sslStatus === 'active' ? 'active' : 'inactive',
            'ssl_generated_at' => now(),
            'ssl_expires_at' => $sslStatus === 'active' ? now()->addDays(90) : null,
        ]);
    }
}
