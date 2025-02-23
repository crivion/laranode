<?php

namespace App\Models;

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
}
