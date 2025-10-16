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

        $laranodeBinPath = config('laranode.laranode_bin_path');

        // Ensure there's a php-fpm pool for the new PHP version
        (new CreatePhpFpmPoolService($this->website))->handle();

        Process::run([
            'sudo',
            $laranodeBinPath . '/laranode-update-php-version.sh',
            $this->website->url,
            $this->website->phpVersion->version,
            $phpVersion->version,
        ]);


        // update website with the selected active PHP version
        $this->website->update([
            'php_version_id' => $phpVersion->id,
        ]);
    }
}


