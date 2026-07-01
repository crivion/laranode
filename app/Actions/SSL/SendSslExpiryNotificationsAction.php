<?php

namespace App\Actions\SSL;

use App\Models\Website;
use App\Notifications\SslExpiringNotification;
use App\Services\Notifications\NotificationService;

class SendSslExpiryNotificationsAction
{
    public function __invoke(): void
    {
        Website::where('ssl_enabled', true)
            ->whereNotNull('ssl_expires_at')
            ->with('user')
            ->get()
            ->each(function (Website $website) {
                $days = (int) ceil(now()->diffInDays($website->ssl_expires_at, false));

                if ($days === 7 || $days === 14) {
                    $user = $website->user;

                    if ($user) {
                        NotificationService::dispatch($user, new SslExpiringNotification($website));
                    }
                }
            });
    }
}
