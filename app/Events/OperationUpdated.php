<?php

namespace App\Events;

use App\Models\Operation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OperationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Operation $operation,
        public string $kind,          // 'status' | 'line'
        public ?string $line = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('operations.' . $this->operation->user_id);
    }

    public function broadcastAs(): string
    {
        return 'OperationUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'operationId' => $this->operation->id,
            'kind' => $this->kind,
            'status' => $this->operation->status,
            'line' => $this->line,
            'exitCode' => $this->operation->exit_code,
        ];
    }
}
