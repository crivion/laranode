<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Lab404\Impersonate\Models\Impersonate;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    use Impersonate;
    use Notifiable;

    public $appends = ['homedir', 'systemUsername'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'domain_limit',
        'database_limit',
        'ssh_access',
        'webhook_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'webhook_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ssh_access' => 'boolean',
            'webhook_url' => 'encrypted',
        ];
    }

    /**
     * @return bool
     */
    public function canImpersonate()
    {
        return $this->isAdmin();
    }

    public function canBeImpersonated(): bool
    {
        return ! $this->isAdmin();
    }

    /**
     * @return bool
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * not using casts as it's not working in some scenarios
     */
    public function getHomedirAttribute(): string
    {
        return '/home/'.$this->systemUsername;
    }

    /**
     * not using casts as it's not working in some scenarios
     */
    public function getSystemUsernameAttribute(): string
    {
        return $this->username.'_ln';
    }

    public function websites(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function databases(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Database::class);
    }
}
