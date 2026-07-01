<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class Backup extends Model
{
    use HasFactory;
    use MassPrunable;

    protected $fillable = [
        'user_id',
        'operation_id',
        'type',
        'target',
        'storage',
        'disk_name',
        's3_key',
        's3_secret',
        's3_region',
        's3_bucket',
        's3_endpoint',
        'path',
        'size_bytes',
        'status',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        's3_key' => 'encrypted',
        's3_secret' => 'encrypted',
    ];

    // Never expose S3 credentials in JSON / Inertia props.
    protected $hidden = [
        's3_key',
        's3_secret',
    ];

    /**
     * Build the Laravel filesystem disk config array for S3 backups.
     * Returns null when this backup uses local storage (no S3 creds stored).
     *
     * @return array<string,mixed>|null
     */
    public function s3DiskConfig(): ?array
    {
        if ($this->storage !== 's3' || ! $this->s3_key) {
            return null;
        }

        return [
            'driver' => 's3',
            'key' => $this->s3_key,
            'secret' => $this->s3_secret,
            'region' => $this->s3_region ?? 'us-east-1',
            'bucket' => $this->s3_bucket ?? '',
            'url' => $this->s3_endpoint ?: null,
            'endpoint' => $this->s3_endpoint ?: null,
            'use_path_style_endpoint' => ! empty($this->s3_endpoint),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function scopeMine(Builder $query): Builder
    {
        $user = auth()->user();

        return $query->when($user && ! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id));
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(90));
    }

    /**
     * Hook called before each model is mass-pruned.
     * Deletes the backup file from its disk so we don't leave orphaned files.
     */
    public function pruning(): void
    {
        if ($this->disk_name && $this->path) {
            // Re-register the S3 disk in the scheduler/worker process before deleting.
            $s3Config = $this->s3DiskConfig();
            if ($s3Config !== null) {
                Config::set("filesystems.disks.{$this->disk_name}", $s3Config);
            }
            Storage::disk($this->disk_name)->delete($this->path);
        }
    }
}
