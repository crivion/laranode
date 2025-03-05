<?php

namespace App\Services\Laranode;

use App\Models\Website;
use Illuminate\Support\Facades\Process;
use Exception;

class CreatePhpFpmPoolException extends Exception {}

class CreatePhpFpmPoolService
{
    private string $laranodeBinPath;
    private string $phpFpmPoolTemplate;

    public function __construct(private Website $website)
    {
        // path to laranode user manager bin|ssh script
        $this->laranodeBinPath = config('laranode.laranode_bin_path');

        // path to php-fpm pool template
        $this->phpFpmPoolTemplate = config('laranode.php_fpm_pool_template');
    }

    public function handle(): void
    {
        # "Usage: $0 {system user} {php version} {template_file_path}"
        $createPhpFpmPool = Process::run([
            'sudo',
            $this->laranodeBinPath . '/laranode-add-php-fpm-pool.sh',
            $this->website->user->username,
            $this->website->phpVersion->version,
            $this->phpFpmPoolTemplate
        ]);

        if ($createPhpFpmPool->failed()) {
            throw new CreatePhpFpmPoolException('Failed to create PHP-FPM pool: ' . $createPhpFpmPool->errorOutput());
        }
    }
}
