<?php

use App\Jobs\RunRestoreJob;
use App\Models\Backup;
use App\Models\User;
use App\Services\Backups\RestoreBackupService;
use Illuminate\Support\Facades\Queue;

test('restore requires the exact typed confirmation and dispatches asynchronously', function () {
    Queue::fake();
    $user = User::factory()->create();
    $backup = Backup::create(['user_id' => $user->id, 'scope' => 'account', 'kind' => 'manual', 'status' => 'completed', 'manifest' => ['account' => ['username' => $user->username]]]);

    $this->actingAs($user)->post(route('backups.restore', $backup), ['confirm' => 'wrong'])->assertSessionHasErrors('confirm');
    $this->actingAs($user)->post(route('backups.restore', $backup), ['confirm' => $user->username])->assertRedirect();
    expect($backup->refresh()->restore_attempts)->toBe(0);
    Queue::assertPushed(RunRestoreJob::class, fn ($job) => $job->backupId === $backup->id && $job->actorId === $user->id);
});

test('completed backups are not subject to the failed restore retry limit', function () {
    Queue::fake();
    $user = User::factory()->create();
    $backup = Backup::create([
        'user_id' => $user->id,
        'scope' => 'account',
        'kind' => 'manual',
        'status' => 'completed',
        'restore_attempts' => 3,
        'manifest' => ['account' => ['username' => $user->username]],
    ]);

    $this->actingAs($user)->post(route('backups.restore', $backup), ['confirm' => $user->username])->assertRedirect();
    expect($backup->refresh()->restore_attempts)->toBe(3);
    Queue::assertPushed(RunRestoreJob::class, 1);
});

test('a failed restore can be retried until three attempts have been used', function () {
    Queue::fake();
    $user = User::factory()->create();
    $backup = Backup::create([
        'user_id' => $user->id,
        'scope' => 'account',
        'kind' => 'manual',
        'status' => 'failed',
        'restore_attempts' => 2,
        'manifest' => ['account' => ['username' => $user->username]],
    ]);

    $this->actingAs($user)->post(route('backups.restore', $backup), ['confirm' => $user->username])->assertRedirect();
    expect($backup->refresh()->restore_attempts)->toBe(3);
    Queue::assertPushed(RunRestoreJob::class, 1);

    $backup->update(['status' => 'failed']);
    $this->actingAs($user)->post(route('backups.restore', $backup), ['confirm' => $user->username])->assertSessionHasErrors('confirm');
    expect($backup->refresh()->restore_attempts)->toBe(3);
    Queue::assertPushed(RunRestoreJob::class, 1);
});

test('a successful restore resets the failed retry budget', function () {
    $user = User::factory()->create();
    $backup = Backup::create([
        'user_id' => $user->id,
        'scope' => 'account',
        'kind' => 'manual',
        'status' => 'pending',
        'restore_attempts' => 2,
        'manifest' => ['account' => ['username' => $user->username]],
    ]);
    $service = Mockery::mock(RestoreBackupService::class);
    $service->shouldReceive('handle')->once();

    (new RunRestoreJob($backup->id, $user->id))->handle($service);

    expect($backup->refresh()->status)->toBe('completed')
        ->and($backup->restore_attempts)->toBe(0);
});
