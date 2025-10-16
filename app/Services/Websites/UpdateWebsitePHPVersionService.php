<?php

namespace App\Services\Websites;

use App\Services\Laranode\AddVhostEntryService;
use App\Services\Laranode\CreatePhpFpmPoolService;
use App\Models\PhpVersion;
use App\Models\Website;
use Illuminate\Support\Facades\Process;

class UpdateWebsitePHPVersionService
{
    public function __construct(private Website $website, private int $phpVersionId) {}

    public function handle(): void
    {
        // ensure selected PHP version is active
        $phpVersion = PhpVersion::active()->findOrFail($this->phpVersionId);

        // update website with the selected active PHP version
        $this->website->update([
            'php_version_id' => $phpVersion->id,
        ]);

        // Ensure PHP-FPM pool and vhost are updated via a single script for idempotency
        $laranodeBinPath = config('laranode.laranode_bin_path');
        $phpPoolTemplate = config('laranode.php_fpm_pool_template');
        $apacheVhostTemplate = config('laranode.apache_vhost_template');

        Process::run([
            'sudo',
            $laranodeBinPath . '/laranode-update-php-version.sh',
            $this->website->user->systemUsername,
            $this->website->url,
            $this->website->document_root,
            $phpVersion->version,
            $phpPoolTemplate,
            $apacheVhostTemplate,
        ]);
    }
}


