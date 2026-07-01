<?php

namespace App\Notifications;

use App\Models\Website;
use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SslExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Website $website) {}

    public function via(object $notifiable): array
    {
        return NotificationService::resolveChannels($notifiable, 'ssl.expiring');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event_type' => 'ssl.expiring',
            'website_id' => $this->website->id,
            'url' => $this->website->url,
            'ssl_expires_at' => $this->website->ssl_expires_at?->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("SSL certificate expiring soon: {$this->website->url}")
            ->line("Your SSL certificate for {$this->website->url} is expiring soon.")
            ->line('Expiry: '.($this->website->ssl_expires_at?->toDateString() ?? 'unknown'));
    }

    public function toWebhook(object $notifiable): array
    {
        return [
            'event_type' => 'ssl.expiring',
            'website_id' => $this->website->id,
            'url' => $this->website->url,
            'ssl_expires_at' => $this->website->ssl_expires_at?->toIso8601String(),
        ];
    }
}
