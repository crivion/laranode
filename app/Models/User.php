<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Lab404\Impersonate\Models\Impersonate;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    use Impersonate;

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
        'ssh_access'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
        ];
    }

    /**
     * @return bool
     */
    public function canImpersonate()
    {
        return $this->isAdmin();
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
     * @return string
     */
    public function getHomedirAttribute(): string
    {
        return '/home/' . $this->systemUsername;
    }

    /**
     * not using casts as it's not working in some scenarios
     * @return string
     */
    public function getSystemUsernameAttribute(): string
    {
        return $this->username;
    }

    public function websites(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Website::class);
    }
}
