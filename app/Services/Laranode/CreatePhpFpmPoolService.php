<?php

namespace App\Services\Laranode;

use Illuminate\Support\Facades\Process;
use Exception;

class CreatePhpFpmPoolException extends Exception {}

class CreatePhpFpmPoolService
{
    private string $laranodeBinPath;
    private string $phpFpmPoolTemplate;

    public function __construct(private string $systemUser, private string $phpVersion)
    {
        // path to laranode user manager bin|ssh script
        $this->laranodeBinPath = config('laranode.laranode_bin_path');

        // path to php-fpm pool template
        $this->phpFpmPoolTemplate = config('laranode.php_fpm_pool_template');
    }

    public function handle(): void
    {
        $createPhpFpmPool = Process::run([
            'sudo',
            $this->laranodeBinPath . '/laranode-add-php-fpm-pool.sh',
            $this->systemUser,
            '7.4', // @TODO: remove this and replace with $this->phpVersion
            $this->phpFpmPoolTemplate
            /*$this->phpVersion,*/
        ]);

        if ($createPhpFpmPool->failed()) {
            throw new CreatePhpFpmPoolException('Failed to create PHP-FPM pool: ' . $createPhpFpmPool->errorOutput());
        }
    }
}
