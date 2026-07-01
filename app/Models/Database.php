<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Database extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'db_user',
        'db_password',
        'charset',
        'collation',
        'engine',
        'user_id',
    ];

    protected $casts = [
        'db_password' => 'encrypted',
    ];

    /**
     * Get the user that owns the database.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the decrypted database password.
     * The 'encrypted' cast on db_password already decrypts on read.
     */
    public function getDecryptedPasswordAttribute(): string
    {
        return $this->db_password;
    }

    /**
     * Set the encrypted database password.
     */
    public function setPasswordAttribute(string $password): void
    {
        $this->attributes['db_password'] = encrypt($password);
    }

    public function scopeMine(Builder $query): Builder
    {
        $user = auth()->user();

        return $query->when($user && ! $user->isAdmin(), fn ($query) => $query->where('user_id', $user->id));
    }
}
