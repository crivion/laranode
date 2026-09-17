<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupSchedule extends Model
{
    protected $fillable = [
        'user_id', 'website_id', 'database_id', 'destination_id', 'scope', 'frequency',
        'run_at', 'day_of_week', 'day_of_month', 'retention_count', 'enabled',
        'last_run_at', 'next_run_at',
    ];

    protected $casts = ['enabled' => 'boolean', 'last_run_at' => 'datetime', 'next_run_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function database(): BelongsTo
    {
        return $this->belongsTo(Database::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(BackupDestination::class, 'destination_id');
    }

    public function scopeMine(Builder $query): Builder
    {
        $user = auth()->user();

        return $query->when($user && ! $user->isAdmin(), fn (Builder $query) => $query->where('user_id', $user->id));
    }

    public function calculateNextRun(?Carbon $from = null): Carbon
    {
        $from ??= now();
        [$hour, $minute] = array_map('intval', explode(':', $this->run_at));
        $next = $from->copy()->setTime($hour, $minute);

        if ($this->frequency === 'daily') {
            return $next->lte($from) ? $next->addDay() : $next;
        }

        if ($this->frequency === 'weekly') {
            $days = ((int) $this->day_of_week - $next->dayOfWeek + 7) % 7;
            $next->addDays($days);

            return $next->lte($from) ? $next->addWeek() : $next;
        }

        $day = min((int) $this->day_of_month, $next->daysInMonth);
        $next->day($day);
        if ($next->lte($from)) {
            $next->addMonthNoOverflow()->day(min((int) $this->day_of_month, $next->daysInMonth));
        }

        return $next;
    }
}
