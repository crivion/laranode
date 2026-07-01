<?php

namespace App\Services\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\Channels\WebhookChannel;
use Illuminate\Notifications\Notification;

class NotificationService
{
    private const CHANNEL_MAP = [
        'database' => 'database',
        'mail' => 'mail',
        'webhook' => WebhookChannel::class,
    ];

    /**
     * Resolve which channels are enabled for a given user + event type.
     * Channel preferences use short aliases (database / mail / webhook).
     * Returns channel driver names / class names that Laravel's Notification system understands.
     */
    public static function resolveChannels(object $notifiable, string $eventType): array
    {
        return array_values(array_filter(
            array_values(self::CHANNEL_MAP),
            function ($driver) use ($notifiable, $eventType) {
                $alias = array_search($driver, self::CHANNEL_MAP);

                return NotificationPreference::isEnabled($notifiable->id, $eventType, $alias);
            }
        ));
    }

    /**
     * Dispatch a notification with preference filtering applied.
     * Call this from every event source instead of $user->notify() directly.
     */
    public static function dispatch(User $user, Notification $notification): void
    {
        $user->notify($notification);
    }
}
