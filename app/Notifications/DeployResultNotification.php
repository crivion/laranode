<?php

namespace App\Notifications;

use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Stub — will be dispatched from deploy-git-push (#7) sub-project.
 */
class DeployResultNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $repository, public bool $success) {}

    public function via(object $notifiable): array
    {
        $eventType = $this->success ? 'deploy.success' : 'deploy.failed';

        return NotificationService::resolveChannels($notifiable, $eventType);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event_type' => $this->success ? 'deploy.success' : 'deploy.failed',
            'repository' => $this->repository,
            'success' => $this->success,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->success ? 'succeeded' : 'failed';

        return (new MailMessage)
            ->subject("Deploy {$status}: {$this->repository}")
            ->line("Deployment of '{$this->repository}' has {$status}.");
    }

    public function toWebhook(object $notifiable): array
    {
        return [
            'event_type' => $this->success ? 'deploy.success' : 'deploy.failed',
            'repository' => $this->repository,
            'success' => $this->success,
        ];
    }
}
