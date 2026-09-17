<?php

use App\Models\BackupDestination;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('destination configuration is encrypted at rest and only its secret is hidden from inertia', function () {
    $admin = User::factory()->isAdmin()->create();
    $destination = BackupDestination::create(['name' => 'S3', 'driver' => 's3', 'config' => ['key' => 'plain-key', 'secret' => 'plain-secret', 'region' => 'fra1'], 'is_default' => false]);
    $raw = DB::table('backup_destinations')->where('id', $destination->id)->value('config');
    expect($raw)->not->toContain('plain-secret');
    $this->actingAs($admin)->get(route('backup-destinations.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('destinations.0.config.key', 'plain-key')
        ->where('destinations.0.config.region', 'fra1')
        ->missing('destinations.0.config.secret'));
});

test('sftp edit configuration includes all values except the password', function () {
    $admin = User::factory()->isAdmin()->create();
    BackupDestination::create([
        'name' => 'SFTP',
        'driver' => 'sftp',
        'config' => ['host' => 'backup.example.com', 'port' => '2222', 'username' => 'backup', 'password' => 'plain-password', 'root' => '/backups'],
        'is_default' => false,
    ]);

    $this->actingAs($admin)->get(route('backup-destinations.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('destinations.0.config.host', 'backup.example.com')
        ->where('destinations.0.config.port', '2222')
        ->where('destinations.0.config.username', 'backup')
        ->where('destinations.0.config.root', '/backups')
        ->missing('destinations.0.config.password'));
});

test('sftp ports submitted as strings are normalized for the filesystem adapter', function () {
    $destination = new BackupDestination([
        'name' => 'SFTP',
        'driver' => 'sftp',
        'config' => [
            'host' => '127.0.0.1',
            'port' => '2222',
            'username' => 'backup',
            'password' => 'secret',
        ],
    ]);

    expect(fn () => $destination->disk())->not->toThrow(TypeError::class);
});
