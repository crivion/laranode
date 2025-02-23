<?php

namespace App\Actions\Filemanager;

use Illuminate\Support\Facades\Process;

class EnsurePermissionsAction
{

    public function execute(string $path, string $systemUser): void
    {
        Process::run([
            'sudo',
            config('laranode.laranode_bin_path') . '/laranode-file-permissions.sh',
            $path,
            $systemUser,
        ]);
    }
}
