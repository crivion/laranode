<?php

namespace App\Notifications;

use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Stub — will be dispatched from security-fail2ban (#6) sub-project.
 */
class Fail2banBanNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $ip, public string $jail) {}

    public function via(object $notifiable): array
    {
        return NotificationService::resolveChannels($notifiable, 'fail2ban.ban');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event_type' => 'fail2ban.ban',
            'ip' => $this->ip,
            'jail' => $this->jail,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Fail2ban: IP {$this->ip} banned")
            ->line("IP {$this->ip} has been banned in jail {$this->jail}.");
    }

    public function toWebhook(object $notifiable): array
    {
        return [
            'event_type' => 'fail2ban.ban',
            'ip' => $this->ip,
            'jail' => $this->jail,
        ];
    }
}
