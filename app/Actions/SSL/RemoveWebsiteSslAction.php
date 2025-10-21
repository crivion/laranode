<?php

namespace App\Actions\SSL;

use App\Models\Website;
use Illuminate\Support\Facades\Process;
use Exception;

class RemoveWebsiteSslAction
{
    public function execute(Website $website): void
    {
        $result = Process::run([
            'sudo',
            config('laranode.laranode_bin_path') . '/laranode-ssl-manager.sh',
            'remove',
            $website->url,
        ]);

        if ($result->failed()) {
            throw new Exception($result->errorOutput());
        }

        $website->update([
            'ssl_enabled' => false,
            'ssl_status' => 'inactive',
            'ssl_expires_at' => null,
            'ssl_generated_at' => null,
        ]);
    }
}
