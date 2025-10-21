<?php

namespace App\Actions\SSL;

use App\Models\Website;
use Illuminate\Support\Facades\Process;
use Exception;

class CheckWebsiteSslStatusAction
{
    /**
     * @return array{ssl_status:string, ssl_enabled:bool}
     */
    public function execute(Website $website): array
    {
        $result = Process::run([
            'sudo',
            config('laranode.laranode_bin_path') . '/laranode-ssl-manager.sh',
            'status',
            $website->url,
        ]);

        if ($result->failed()) {
            throw new Exception($result->errorOutput());
        }

        $sslStatus = trim($result->output());

        $website->update([
            'ssl_status' => $sslStatus,
            'ssl_enabled' => $sslStatus === 'active',
        ]);

        return [
            'ssl_status' => $sslStatus,
            'ssl_enabled' => $sslStatus === 'active',
        ];
    }
}
