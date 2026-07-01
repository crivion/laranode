<?php

namespace App\Notifications;

use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Stub — will be dispatched from backups (#9) sub-project.
 */
class BackupResultNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $backupName, public bool $success) {}

    public function via(object $notifiable): array
    {
        return NotificationService::resolveChannels($notifiable, 'backup.result');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event_type' => 'backup.result',
            'backup_name' => $this->backupName,
            'success' => $this->success,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->success ? 'succeeded' : 'failed';

        return (new MailMessage)
            ->subject("Backup {$status}: {$this->backupName}")
            ->line("Backup '{$this->backupName}' has {$status}.");
    }

    public function toWebhook(object $notifiable): array
    {
        return [
            'event_type' => 'backup.result',
            'backup_name' => $this->backupName,
            'success' => $this->success,
        ];
    }
}
