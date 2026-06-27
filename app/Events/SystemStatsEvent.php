<?php

namespace App\Events;

use App\Services\Dashboard\SystemStatsService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SystemStatsEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public array $stats = [])
    {
        $this->stats = (new SystemStatsService)->getAllStats();
        Cache::put('dashboard_stats_last_known', $this->stats, 90);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('systemstats'),
        ];
    }

    public function broadcastWith()
    {
        return $this->stats;
    }
}
