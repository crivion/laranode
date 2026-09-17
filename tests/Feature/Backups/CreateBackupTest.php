<?php

use App\Jobs\RunBackupJob;
use App\Models\Database;
use App\Models\PhpVersion;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Queue;

test('each backup scope creates a pending row and dispatches a job', function (string $scope) {
    Queue::fake();
    $user = User::factory()->create();
    $website = Website::factory()->for($user)->for(PhpVersion::factory())->create();
    $database = Database::factory()->for($user)->create(['website_id' => $website->id]);
    $payload = ['scope' => $scope];
    if ($scope === 'website_files') {
        $payload['website_id'] = $website->id;
    }
    if ($scope === 'website_database') {
        $payload['database_id'] = $database->id;
    }

    $this->actingAs($user)->post(route('backups.store'), $payload)->assertRedirect();
    $backup = \App\Models\Backup::latest('id')->first();
    expect($backup->status)->toBe('pending')->and($backup->scope)->toBe($scope);
    Queue::assertPushed(RunBackupJob::class, fn ($job) => $job->backupId === $backup->id);
})->with(['account', 'website_files', 'website_database']);

test('users cannot back up targets owned by another account', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $website = Website::factory()->for($other)->for(PhpVersion::factory())->create();

    $this->actingAs($owner)->post(route('backups.store'), ['scope' => 'website_files', 'website_id' => $website->id])->assertForbidden();
    Queue::assertNothingPushed();
});
