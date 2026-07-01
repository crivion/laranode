<?php

namespace App\Notifications;

use App\Models\Website;
use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Stub — class exists with correct interface, but no dispatch seam wired yet.
 * Will be dispatched from GenerateSslOperationJob in a follow-up task.
 */
class SslIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Website $website) {}

    public function via(object $notifiable): array
    {
        return NotificationService::resolveChannels($notifiable, 'ssl.issued');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event_type' => 'ssl.issued',
            'website_id' => $this->website->id,
            'url' => $this->website->url,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("SSL certificate issued: {$this->website->url}")
            ->line("Your SSL certificate for {$this->website->url} has been issued successfully.");
    }

    public function toWebhook(object $notifiable): array
    {
        return [
            'event_type' => 'ssl.issued',
            'website_id' => $this->website->id,
            'url' => $this->website->url,
        ];
    }
}
