<?php

namespace App\Jobs;

use App\Actions\SSL\GenerateWebsiteSslAction;
use App\Models\Operation;
use App\Models\Website;

class GenerateSslOperationJob extends OperationJob
{
    public function __construct(Operation $operation, public Website $website, public string $email)
    {
        parent::__construct($operation);
        $this->notifyUser = $website->user;
    }

    protected function run(callable $emit): int
    {
        $emit("Generating SSL certificate for {$this->website->url}...");
        (new GenerateWebsiteSslAction)->execute($this->website, $this->email, $emit);
        $emit('SSL certificate issued.');

        return 0; // GenerateWebsiteSslAction throws on failure -> base marks failed
    }
}
