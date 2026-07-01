<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Operation extends Model
{
    use MassPrunable;

    protected $fillable = [
        'user_id', 'type', 'target', 'status', 'output', 'exit_code', 'started_at', 'finished_at',
    ];

    protected $attributes = [
        'status' => 'queued',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'exit_code' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeMine(Builder $query): Builder
    {
        $user = auth()->user();
        return $query->when($user && ! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id));
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(30));
    }

    public function markRunning(): void
    {
        $this->update(['status' => 'running', 'started_at' => now()]);
        \App\Events\OperationUpdated::dispatch($this, 'status');
    }

    public function appendOutput(string $line): void
    {
        $this->update(['output' => ($this->output ?? '') . $line . "\n"]);
        \App\Events\OperationUpdated::dispatch($this, 'line', $line);
    }

    public function markFinished(int $exitCode): void
    {
        $this->update([
            'status' => $exitCode === 0 ? 'succeeded' : 'failed',
            'exit_code' => $exitCode,
            'finished_at' => now(),
        ]);
        \App\Events\OperationUpdated::dispatch($this, 'status');
    }
}
