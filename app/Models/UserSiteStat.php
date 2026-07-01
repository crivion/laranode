<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSiteStat extends Model
{
    use MassPrunable;

    protected $fillable = [
        'website_id',
        'user_id',
        'snapshotted_at',
        'disk_bytes',
    ];

    protected $casts = [
        'snapshotted_at' => 'datetime',
        'disk_bytes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function prunable(): Builder
    {
        return static::where('snapshotted_at', '<', now()->subDays(90));
    }
}
