<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhpVersion extends Model
{
    /** @use HasFactory<\Database\Factories\PhpVersionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }
}
