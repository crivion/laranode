<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'event_type',
        'channel',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Opt-out model: missing row = enabled.
     * Returns true on any DB error (fail-open).
     */
    public static function isEnabled(int $userId, string $eventType, string $channel): bool
    {
        try {
            $pref = static::where('user_id', $userId)
                ->where('event_type', $eventType)
                ->where('channel', $channel)
                ->first();

            if ($pref === null) {
                return true;
            }

            return (bool) $pref->enabled;
        } catch (\Throwable $e) {
            Log::warning('NotificationPreference::isEnabled error', [
                'user_id' => $userId,
                'event_type' => $eventType,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return true;
        }
    }
}
