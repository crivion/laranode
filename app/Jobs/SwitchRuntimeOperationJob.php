<?php

namespace App\Jobs;

use App\Models\Operation;
use App\Models\Website;
use App\Services\Websites\SwitchRuntimeService;

class SwitchRuntimeOperationJob extends OperationJob
{
    public function __construct(Operation $operation, public Website $website, public string $runtime)
    {
        parent::__construct($operation);
        $this->notifyUser = $website->user;
    }

    protected function run(callable $emit): int
    {
        // Refresh user relation after queue deserialization (FIXED: review fix #10).
        $this->website->load('user');

        (new SwitchRuntimeService($this->website, $this->runtime, $emit))->handle();

        return 0;
    }
}
