<?php

namespace App\Services\Websites;

use Exception;
use Illuminate\Support\Facades\Process;

class InstallRuntimeException extends Exception {}

class InstallRuntimeService
{
    /**
     * Ensure the given runtime binary is installed on the host.
     * Calls the privileged laranode-runtime-install.sh script.
     *
     * @throws InstallRuntimeException if the script exits non-zero.
     */
    public function ensureInstalled(string $runtime, callable $emit): void
    {
        $emit("Installing {$runtime} runtime...");

        $result = Process::run([
            'sudo',
            config('laranode.laranode_bin_path').'/laranode-runtime-install.sh',
            $runtime,
        ]);

        if ($result->failed()) {
            throw new InstallRuntimeException(
                "Failed to install {$runtime} runtime: ".$result->errorOutput()
            );
        }
    }
}
