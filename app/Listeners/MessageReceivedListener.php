<?php

namespace App\Listeners;

use App\Events\SystemStatsEvent;
use App\Events\TopStatsEvent;
use Laravel\Reverb\Events\MessageReceived;

class MessageReceivedListener
{
    /**
     * Hook into the MessageReceived event so we can send stats via websockets
     * We don't like polling that's why
     */
    public function handle(MessageReceived $event)
    {

        $msg = json_decode($event->message);

        if ($msg->event == 'client-typing') {
            match ($msg->channel) {
                'private-systemstats' => (new SystemStatsEvent())->dispatch(),
                'private-topstats' => (new TopStatsEvent())->dispatch(),
                default => ''
            };
        }
    }
}
