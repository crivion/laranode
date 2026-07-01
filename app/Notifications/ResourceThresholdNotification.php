<?php

namespace App\Notifications;

use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Stub — will be dispatched from monitoring-alerts (#11) sub-project.
 */
class ResourceThresholdNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $resource, public float $value, public float $threshold) {}

    public function via(object $notifiable): array
    {
        return NotificationService::resolveChannels($notifiable, 'resource.threshold');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event_type' => 'resource.threshold',
            'resource' => $this->resource,
            'value' => $this->value,
            'threshold' => $this->threshold,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Resource threshold exceeded: {$this->resource}")
            ->line("Resource {$this->resource} exceeded threshold: {$this->value} > {$this->threshold}.");
    }

    public function toWebhook(object $notifiable): array
    {
        return [
            'event_type' => 'resource.threshold',
            'resource' => $this->resource,
            'value' => $this->value,
            'threshold' => $this->threshold,
        ];
    }
}
