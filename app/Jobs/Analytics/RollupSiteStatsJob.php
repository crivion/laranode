<?php

namespace App\Jobs\Analytics;

use App\Jobs\OperationJob;
use App\Models\Operation;
use App\Models\User;
use App\Services\Analytics\UserSiteStatsService;

class RollupSiteStatsJob extends OperationJob
{
    public function __construct(Operation $operation, public User $user)
    {
        parent::__construct($operation);
    }

    protected function run(callable $emit): int
    {
        (new UserSiteStatsService)->collect($this->user, $emit);

        return 0;
    }
}
