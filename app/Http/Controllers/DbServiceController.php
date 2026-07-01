<?php

namespace App\Http\Controllers;

use App\Databases\EngineManager;
use App\Http\Requests\DbServiceRequest;
use App\Jobs\DbServiceOperationJob;
use App\Models\Operation;
use App\Services\Database\DbServiceStatusService;
use Illuminate\Http\JsonResponse;

class DbServiceController extends Controller
{
    public function __construct(private EngineManager $engineManager) {}

    public function action(DbServiceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $engine = $validated['engine'];
        $action = $validated['action'];

        $service = config('laranode.db_engines')[$engine]['service'];

        $operation = Operation::create([
            'user_id' => $request->user()->id,
            'type' => "db.service.{$action}",
            'target' => "{$engine}:{$service}",
            'status' => 'queued',
        ]);

        DbServiceOperationJob::dispatch($operation, $engine, $action);

        return response()->json(['operation_id' => $operation->id]);
    }

    public function status(): JsonResponse
    {
        $statuses = (new DbServiceStatusService($this->engineManager))->handle();

        return response()->json(['statuses' => $statuses]);
    }
}
