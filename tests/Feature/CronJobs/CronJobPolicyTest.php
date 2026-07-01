<?php

use App\Http\Requests\StoreCronJobRequest;
use App\Models\CronJob;
use App\Models\User;
use App\Policies\CronJobPolicy;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

// Helper: create a CronJob row without going through validation
function makeCronJob(User $owner): CronJob
{
    return CronJob::create([
        'user_id' => $owner->id,
        'schedule' => '* * * * *',
        'command' => 'php /home/'.$owner->systemUsername.'/artisan inspire',
    ]);
}

// ──────────────────────────────────────────────
// delete()
// ──────────────────────────────────────────────

test('admin can delete any cron job', function () {
    $admin = User::factory()->isAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();
    $job = makeCronJob($other);

    $policy = new CronJobPolicy;

    expect($policy->delete($admin, $job)->allowed())->toBeTrue();
});

test('non-admin can delete their own cron job', function () {
    $user = User::factory()->isNotAdmin()->create();
    $job = makeCronJob($user);

    $policy = new CronJobPolicy;

    expect($policy->delete($user, $job)->allowed())->toBeTrue();
});

test('non-admin cannot delete another users cron job', function () {
    $user = User::factory()->isNotAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();
    $job = makeCronJob($other);

    $policy = new CronJobPolicy;

    expect($policy->delete($user, $job)->allowed())->toBeFalse();
});

// ──────────────────────────────────────────────
// update()
// ──────────────────────────────────────────────

test('admin can update any cron job', function () {
    $admin = User::factory()->isAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();
    $job = makeCronJob($other);

    $policy = new CronJobPolicy;

    expect($policy->update($admin, $job)->allowed())->toBeTrue();
});

test('non-admin can update their own cron job', function () {
    $user = User::factory()->isNotAdmin()->create();
    $job = makeCronJob($user);

    $policy = new CronJobPolicy;

    expect($policy->update($user, $job)->allowed())->toBeTrue();
});

test('non-admin cannot update another users cron job', function () {
    $user = User::factory()->isNotAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();
    $job = makeCronJob($other);

    $policy = new CronJobPolicy;

    expect($policy->update($user, $job)->allowed())->toBeFalse();
});

// ──────────────────────────────────────────────
// Gate integration (via actingAs + can())
// ──────────────────────────────────────────────

test('Gate resolves delete policy for admin', function () {
    $admin = User::factory()->isAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();
    $job = makeCronJob($other);

    $this->actingAs($admin);
    expect($this->actingAs($admin)->app->make(\Illuminate\Contracts\Auth\Access\Gate::class)->inspect('delete', $job)->allowed())->toBeTrue();
});

test('Gate resolves delete policy for non-owner (denied)', function () {
    $user = User::factory()->isNotAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();
    $job = makeCronJob($other);

    $this->actingAs($user);
    expect($this->actingAs($user)->app->make(\Illuminate\Contracts\Auth\Access\Gate::class)->inspect('delete', $job)->allowed())->toBeFalse();
});

// ──────────────────────────────────────────────
// StoreCronJobRequest — validation rules
// ──────────────────────────────────────────────

function makeStoreRequest(User $user, array $data): \Illuminate\Validation\Validator
{
    $request = StoreCronJobRequest::create('/cron-jobs', 'POST', $data);
    $request->setUserResolver(fn () => $user);

    return Validator::make(
        $data,
        $request->rules(),
    );
}

test('StoreCronJobRequest passes with valid data', function () {
    $user = User::factory()->isNotAdmin()->create();
    $v = makeStoreRequest($user, [
        'schedule' => '* * * * *',
        'command' => 'php /home/'.$user->systemUsername.'/artisan inspire',
        'label' => 'My Job',
    ]);

    expect($v->fails())->toBeFalse();
});

test('StoreCronJobRequest rejects missing schedule', function () {
    $user = User::factory()->isNotAdmin()->create();
    $v = makeStoreRequest($user, [
        'command' => 'php /home/'.$user->systemUsername.'/artisan inspire',
    ]);

    expect($v->fails())->toBeTrue()
        ->and($v->errors()->has('schedule'))->toBeTrue();
});

test('StoreCronJobRequest rejects invalid cron expression', function () {
    $user = User::factory()->isNotAdmin()->create();
    $v = makeStoreRequest($user, [
        'schedule' => 'not-a-cron',
        'command' => 'php /home/'.$user->systemUsername.'/artisan inspire',
    ]);

    expect($v->fails())->toBeTrue()
        ->and($v->errors()->has('schedule'))->toBeTrue();
});

test('StoreCronJobRequest rejects disallowed command (wget)', function () {
    $user = User::factory()->isNotAdmin()->create();
    $v = makeStoreRequest($user, [
        'schedule' => '* * * * *',
        'command' => 'wget https://example.com',
    ]);

    expect($v->fails())->toBeTrue()
        ->and($v->errors()->has('command'))->toBeTrue();
});

test('StoreCronJobRequest rejects command outside users homedir', function () {
    $user = User::factory()->isNotAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();
    $v = makeStoreRequest($user, [
        'schedule' => '* * * * *',
        'command' => 'php /home/'.$other->systemUsername.'/artisan inspire',
    ]);

    expect($v->fails())->toBeTrue()
        ->and($v->errors()->has('command'))->toBeTrue();
});

test('StoreCronJobRequest label is optional', function () {
    $user = User::factory()->isNotAdmin()->create();
    $v = makeStoreRequest($user, [
        'schedule' => '0 2 * * *',
        'command' => 'php /home/'.$user->systemUsername.'/artisan inspire',
    ]);

    expect($v->fails())->toBeFalse();
});

test('StoreCronJobRequest rejects at the 50-job cap (through real withValidator pipeline)', function () {
    $user = User::factory()->isNotAdmin()->create();

    // Create 50 jobs directly (at the cap)
    for ($i = 0; $i < 50; $i++) {
        CronJob::create([
            'user_id' => $user->id,
            'schedule' => '* * * * *',
            'command' => 'php /home/'.$user->systemUsername.'/artisan inspire'.$i,
        ]);
    }

    // Build the FormRequest and wire it to the container — this is the real
    // pipeline: setContainer() → validateResolved() → getValidatorInstance()
    // → withValidator() (our after-hook) → fails() → failedValidation() throws.
    $request = StoreCronJobRequest::create('/cron-jobs', 'POST', [
        'schedule' => '* * * * *',
        'command' => 'php /home/'.$user->systemUsername.'/artisan inspire-new',
    ]);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setUserResolver(fn () => $user);

    $caught = null;
    try {
        $request->validateResolved();
    } catch (ValidationException $e) {
        $caught = $e;
    }

    expect($caught)->not->toBeNull('Expected a ValidationException for the 50-job cap')
        ->and($caught->errors())->toHaveKey('command');
});

test('StoreCronJobRequest allows the 49th job (below the cap)', function () {
    $user = User::factory()->isNotAdmin()->create();

    // Create 49 jobs — one below the cap
    for ($i = 0; $i < 49; $i++) {
        CronJob::create([
            'user_id' => $user->id,
            'schedule' => '* * * * *',
            'command' => 'php /home/'.$user->systemUsername.'/artisan inspire'.$i,
        ]);
    }

    $request = StoreCronJobRequest::create('/cron-jobs', 'POST', [
        'schedule' => '0 2 * * *',
        'command' => 'php /home/'.$user->systemUsername.'/artisan inspire-new',
    ]);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setUserResolver(fn () => $user);

    // Must NOT throw — 49 jobs is below the 50-job cap.
    $threw = false;
    try {
        $request->validateResolved();
    } catch (ValidationException $e) {
        $threw = true;
    }

    expect($threw)->toBeFalse();
});
