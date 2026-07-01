<?php

namespace App\Jobs;

use App\Models\Operation;
use Illuminate\Support\Facades\Process;

class DbServiceException extends \Exception {}

class DbServiceOperationJob extends OperationJob
{
    public function __construct(
        Operation $operation,
        public string $engine,
        public string $action,
    ) {
        parent::__construct($operation);
    }

    protected function run(callable $emit): int
    {
        $emit("Running: systemctl {$this->action} {$this->engine}...");

        $result = Process::run(['sudo', config('laranode.laranode_bin_path').'/laranode-db-service.sh', $this->action, $this->engine]);

        $emit($result->output());

        if ($result->failed()) {
            throw new DbServiceException($result->errorOutput());
        }

        $emit("systemctl {$this->action} {$this->engine} completed.");

        return 0;
    }
}
