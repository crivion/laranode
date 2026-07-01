<?php

namespace App\Notifications;

use App\Models\Operation;
use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OperationFinishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Operation $operation) {}

    public function via(object $notifiable): array
    {
        $eventType = $this->operation->status === 'failed'
            ? 'operation.failed'
            : 'operation.finished';

        return NotificationService::resolveChannels($notifiable, $eventType);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event_type' => $this->operation->status === 'failed' ? 'operation.failed' : 'operation.finished',
            'operation_id' => $this->operation->id,
            'type' => $this->operation->type,
            'status' => $this->operation->status,
            'exit_code' => $this->operation->exit_code,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->operation->status === 'failed' ? 'failed' : 'finished';

        return (new MailMessage)
            ->subject("Operation {$status}: {$this->operation->type}")
            ->line("Your operation '{$this->operation->type}' has {$status}.")
            ->line('Exit code: '.($this->operation->exit_code ?? 'N/A'));
    }

    public function toWebhook(object $notifiable): array
    {
        return [
            'event_type' => $this->operation->status === 'failed' ? 'operation.failed' : 'operation.finished',
            'operation_id' => $this->operation->id,
            'type' => $this->operation->type,
            'status' => $this->operation->status,
            'exit_code' => $this->operation->exit_code,
        ];
    }
}
