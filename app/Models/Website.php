<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Website extends Model
{

    protected $fillable = [
        'url',
        'document_root',
        'php_version_id',
    ];

    public function scopeMine(Builder $query): Builder
    {
        return $query->when(!auth()->user()->isAdmin(), fn($query) => $query->where('user_id', auth()->id()));
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->select(['id', 'username', 'role']);
    }

    public function phpVersion(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PhpVersion::class);
    }
}
