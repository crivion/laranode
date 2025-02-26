<?php

namespace App\Services\Laranode;

use App\Models\Website;
use Illuminate\Support\Facades\Process;
use Exception;

class AddVhostEntryException extends Exception {}

class AddVhostEntryService
{
    private string $laranodeBinPath;
    private string $apacheVhostTemplate;

    public function __construct(private Website $website)
    {
        $this->laranodeBinPath = config('laranode.laranode_bin_path');
        $this->apacheVhostTemplate = config('laranode.apache_vhost_template');
    }

    public function handle(): void
    {
        # "Usage: $0 {system user} {domain} {documentRoot} {phpVersion} {template_file_path}"
        $createVhost = Process::run([
            'sudo',
            $this->laranodeBinPath . '/laranode-add-vhost.sh',
            $this->website->user->systemUsername,
            $this->website->url,
            $this->website->document_root,
            $this->website->phpVersion->version,
            $this->apacheVhostTemplate
        ]);

        if ($createVhost->failed()) {
            throw new AddVhostEntryException('Failed to create vhost: ' . $createVhost->errorOutput());
        }
    }
}
