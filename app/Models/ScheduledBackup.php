<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledBackup extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'target',
        'storage',
        'disk_name',
        'cron_expression',
        'retention_count',
        's3_key',
        's3_secret',
        's3_region',
        's3_bucket',
        's3_endpoint',
        'enabled',
        'last_run_at',
    ];

    protected $attributes = [
        'cron_expression' => '0 2 * * *',
        'retention_count' => 7,
        'enabled' => true,
    ];

    protected $casts = [
        's3_key' => 'encrypted',
        's3_secret' => 'encrypted',
        'enabled' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    // Never expose S3 credentials in JSON / Inertia props.
    protected $hidden = [
        's3_key',
        's3_secret',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeMine(Builder $query): Builder
    {
        $user = auth()->user();

        return $query->when($user && ! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id));
    }
}
