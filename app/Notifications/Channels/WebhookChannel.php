<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $url = $notifiable->webhook_url;

        if (empty($url)) {
            return;
        }

        $parsed = parse_url($url);
        if (! in_array($parsed['scheme'] ?? '', ['http', 'https'], true)) {
            return;
        }

        $host = $parsed['host'] ?? '';

        // IPv6 literal hosts are wrapped in brackets — strip them
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        $ip = gethostbyname($host);

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            Log::warning('WebhookChannel blocked SSRF attempt', ['url' => $url]);

            return;
        }

        try {
            Http::timeout(10)->post($url, $notification->toWebhook($notifiable));
        } catch (\Throwable $e) {
            Log::warning('WebhookChannel delivery failed', ['url' => $url, 'error' => $e->getMessage()]);
        }
    }
}
