<?php

use App\Actions\Backups\BuildDefaultsFileAction;
use App\Jobs\RunRestoreJob;
use App\Models\Backup;
use App\Models\PhpVersion;
use App\Models\User;
use App\Models\Website;
use App\Services\Backups\BackupAccountService;
use App\Services\Backups\CreateBackupService;
use App\Services\Backups\RestoreBackupService;
use Illuminate\Support\Facades\Process;
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

function restoreServiceWithoutSnapshot(): RestoreBackupService
{
    $backups = Mockery::mock(BackupAccountService::class);
    $backups->shouldReceive('handle');

    return new RestoreBackupService(
        app(CreateBackupService::class),
        $backups,
        app(BuildDefaultsFileAction::class),
    );
}

function restorableBackup(User $user, array $websites): Backup
{
    $path = 'restore-test-'.uniqid();
    config(['laranode.backup_path' => sys_get_temp_dir()]);
    @mkdir(sys_get_temp_dir().'/'.$path, 0770, true);
    file_put_contents(sys_get_temp_dir().'/'.$path.'/manifest.json', '{}');

    return Backup::create([
        'user_id' => $user->id,
        'scope' => 'account',
        'kind' => 'manual',
        'status' => 'running',
        'path' => $path,
        'manifest' => ['account' => ['system_username' => $user->systemUsername], 'websites' => $websites],
    ]);
}

test('restore reloads the php-fpm version that serves the site now, not the one in the manifest', function () {
    Process::fake();
    $user = User::factory()->create();
    $website = Website::factory()->for($user)->create([
        'php_version_id' => PhpVersion::factory()->create(['version' => '8.5'])->id,
    ]);
    $backup = restorableBackup($user, [
        ['url' => $website->url, 'php_version' => '8.3', 'archive' => 'files/'.$website->url.'.tar.gz'],
    ]);

    restoreServiceWithoutSnapshot()->handle($backup, $user);

    Process::assertRan(fn ($process) => end($process->command) === '8.5' && in_array('reload', $process->command, true));
    Process::assertNotRan(fn ($process) => in_array('reload', $process->command, true) && end($process->command) === '8.3');
    expect($backup->refresh()->restored_at)->not->toBeNull();
});

test('restore reloads each php-fpm version once and falls back to the manifest for deleted sites', function () {
    Process::fake();
    $user = User::factory()->create();
    $php = PhpVersion::factory()->create(['version' => '8.4']);
    $sites = Website::factory()->for($user)->count(2)->create(['php_version_id' => $php->id]);
    $backup = restorableBackup($user, [
        ['url' => $sites[0]->url, 'php_version' => '8.4', 'archive' => 'a.tar.gz'],
        ['url' => $sites[1]->url, 'php_version' => '8.4', 'archive' => 'b.tar.gz'],
        ['url' => 'gone.example', 'php_version' => '8.2', 'archive' => 'c.tar.gz'],
    ]);

    restoreServiceWithoutSnapshot()->handle($backup, $user);

    Process::assertRanTimes(fn ($process) => in_array('reload', $process->command, true) && end($process->command) === '8.4', 1);
    Process::assertRanTimes(fn ($process) => in_array('reload', $process->command, true) && end($process->command) === '8.2', 1);
});

test('a failed php-fpm reload fails the restore job with a clear message', function () {
    Process::fake([
        '*laranode-php-service.sh*' => Process::result(exitCode: 1),
        '*' => Process::result(),
    ]);
    $user = User::factory()->create();
    $website = Website::factory()->for($user)->create();
    $backup = restorableBackup($user, [
        ['url' => $website->url, 'php_version' => '8.4', 'archive' => 'a.tar.gz'],
    ]);

    expect(fn () => restoreServiceWithoutSnapshot()->handle($backup, $user))
        ->toThrow(RuntimeException::class, 'Files were restored, but reloading PHP-FPM failed for PHP 8.4');
    expect($backup->refresh()->restored_at)->not->toBeNull();
});
