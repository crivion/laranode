<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class BackupDestination extends Model
{
    protected $fillable = ['user_id', 'name', 'driver', 'config', 'is_default'];

    protected $hidden = ['config'];

    protected $casts = ['config' => 'encrypted:array', 'is_default' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function disk(): FilesystemAdapter
    {
        $config = $this->config ?? [];

        return match ($this->driver) {
            'local' => Storage::build(['driver' => 'local', 'root' => config('laranode.backup_path'), 'throw' => true]),
            's3' => Storage::build(['driver' => 's3', 'throw' => true, ...$config]),
            'sftp' => Storage::build([
                'driver' => 'sftp',
                'throw' => true,
                ...$config,
                'port' => (int) ($config['port'] ?? 22),
            ]),
            default => throw new InvalidArgumentException("Unsupported backup driver: {$this->driver}"),
        };
    }
}
