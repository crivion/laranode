<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backup extends Model
{
    use HasFactory;

    public const MAX_RESTORE_ATTEMPTS = 3;

    protected $fillable = [
        'user_id', 'website_id', 'database_id', 'destination_id', 'scope', 'kind',
        'status', 'path', 'size_bytes', 'manifest', 'error', 'expires_at',
        'started_at', 'finished_at', 'restored_at', 'restored_by', 'restore_attempts',
    ];

    protected $casts = [
        'manifest' => 'array',
        'expires_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'restored_at' => 'datetime',
        'restore_attempts' => 'integer',
    ];

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

    public function restoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }

    public function scopeMine(Builder $query): Builder
    {
        $user = auth()->user();

        return $query->when($user && ! $user->isAdmin(), fn (Builder $query) => $query->where('user_id', $user->id));
    }

    public function confirmationName(): string
    {
        return match ($this->scope) {
            'website_files' => $this->manifest['websites'][0]['url'] ?? $this->website?->url ?? '',
            'website_database' => $this->manifest['databases'][0]['name'] ?? $this->database?->name ?? '',
            default => $this->manifest['account']['username'] ?? $this->user?->username ?? '',
        };
    }

    public function restoreAttemptsRemaining(): int
    {
        return max(0, self::MAX_RESTORE_ATTEMPTS - $this->restore_attempts);
    }

    public function canAttemptRestore(): bool
    {
        if (! filled($this->manifest)) {
            return false;
        }

        return $this->status === 'completed'
            || ($this->status === 'failed' && $this->restoreAttemptsRemaining() > 0);
    }
}
