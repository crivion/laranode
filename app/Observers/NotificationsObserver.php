<?php

namespace App\Observers;

use App\Events\NotificationCreated;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;

class NotificationsObserver
{
    public function created(DatabaseNotification $notification): void
    {
        $notifiable = $notification->notifiable;

        if (! $notifiable) {
            return;
        }

        $unreadCount = $notifiable->unreadNotifications()->count();

        try {
            NotificationCreated::dispatch($notifiable->id, $unreadCount);
        } catch (\Throwable $e) {
            Log::warning('NotificationsObserver: failed to broadcast NotificationCreated', [
                'error' => $e->getMessage(),
                'notifiable_id' => $notifiable->id,
            ]);
        }
    }
}
