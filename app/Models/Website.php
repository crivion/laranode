<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Website extends Model
{

    protected $appends = ['fullDocumentRoot'];

    protected $casts = [
        'ssl_enabled' => 'boolean',
        'ssl_expires_at' => 'datetime',
        'ssl_generated_at' => 'datetime',
    ];

    protected $fillable = [
        'url',
        'document_root',
        'website_root',
        'php_version_id',
        'ssl_enabled',
        'ssl_status',
        'ssl_expires_at',
        'ssl_generated_at',
    ];

    public function getWebsiteRootAttribute(): string
    {
        return $this->user?->homedir . '/domains/' . $this->url;
    }

    // not using casts as it's not working in some scenarios
    public function getFullDocumentRootAttribute(): string
    {
        return $this->user?->homedir . '/domains/' . $this->url . $this->document_root;
    }

    public function scopeMine(Builder $query): Builder
    {
        $user = auth()->user();
        return $query->when($user && !$user->isAdmin(), fn($query) => $query->where('user_id', $user->id));
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->select(['id', 'username', 'role']);
    }

    public function phpVersion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PhpVersion::class);
    }

    /**
     * Check if SSL certificate is active and valid
     */
    public function isSslActive(): bool
    {
        return $this->ssl_enabled && $this->ssl_status === 'active';
    }

    /**
     * Check if SSL certificate is expired
     */
    public function isSslExpired(): bool
    {
        return $this->ssl_status === 'expired' || 
               ($this->ssl_expires_at && $this->ssl_expires_at->isPast());
    }

    /**
     * Get SSL status display text
     */
    public function getSslStatusText(): string
    {
        return match($this->ssl_status) {
            'active' => 'SSL Active',
            'expired' => 'SSL Expired',
            'pending' => 'SSL Pending',
            default => 'SSL Inactive'
        };
    }

    /**
     * Get SSL status color class for frontend
     */
    public function getSslStatusColor(): string
    {
        return match($this->ssl_status) {
            'active' => 'text-green-600',
            'expired' => 'text-red-600',
            'pending' => 'text-yellow-600',
            default => 'text-gray-500'
        };
    }
}
