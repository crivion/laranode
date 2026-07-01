<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CronJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'schedule',
        'command',
        'label',
        'active',
    ];

    protected $attributes = [
        'active' => true,
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeMine(Builder $query): Builder
    {
        $user = auth()->user();

        return $query->when($user && ! $user->isAdmin(), fn ($query) => $query->where('user_id', $user->id));
    }
}
