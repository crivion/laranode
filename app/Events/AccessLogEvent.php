<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Process;

class AccessLogEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('topstats'),
        ];
    }

    public function broadcastWith(): array
    {
        return $this->processLog();
    }

    public function processLog()
    {
        $command = Process::run('tail -n 25 /var/log/apache2/access.log');
        $output = $command->output();
        $lines = explode("\n", $output);

        // process lines
        $lines = array_filter(array_reverse($lines));
        $parsedLogs = [];

        foreach ($lines as $line) {
            $pattern = '/\[(.*?)\] ([\d:.a-fA-F]+) - - \[(.*?)\] (.*?) "(.*?)" (\d+) (\d+) "(.*?)" "(.*?)"/';
            if (preg_match($pattern, $line, $matches)) {
                $requestParts = explode(' ', $matches[5]);
                $reqType = $requestParts[0];
                $reqPath = isset($requestParts[1]) ? $requestParts[1] : '';
                $reqFull = $matches[4] . $reqPath;

                $parsedLogs[] = [
                    'country' => strtolower($matches[1]),
                    'ip' => $matches[2],
                    'date' => $matches[3],
                    'host' => $matches[4],
                    'request' => $matches[5],
                    'req_type' => $reqType,
                    'req_full' => $reqFull,
                    'status' => $matches[6],
                    'bytes' => $matches[7] > 0 ? number_format($matches[7] / 1024, 2) : $matches[7],
                    'referer' => $matches[8],
                    'short_ua' => $this->shortBrowserName($matches[9]),
                    'user_agent' => $matches[9],
                ];
            }
        }

        return $parsedLogs;
    }

    public function shortBrowserName($userAgent)
    {
        $browsers = [
            'Chrome' => 'Chrome',
            'Firefox' => 'Firefox',
            'Safari' => 'Safari',
            'Edge' => 'Edge',
            'Trident' => 'Internet Explorer',
            'Opera' => 'Opera',
            'OPR' => 'Opera',
            'UCBrowser' => 'UC Browser',
            'SamsungBrowser' => 'Samsung Browser',
            'Bot' => 'Bot/Crawler'
        ];

        foreach ($browsers as $key => $name) {
            if (stripos($userAgent, $key) !== false) {
                return $name;
            }
        }

        return 'Unknown';
    }
}
