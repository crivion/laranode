<?php

use App\Models\CronJob;
use App\Models\User;
use Illuminate\Database\QueryException;

test('CronJob belongs to user', function () {
    $user = User::factory()->create();
    $job = CronJob::create([
        'user_id' => $user->id,
        'schedule' => '* * * * *',
        'command' => 'php /home/testuser_ln/artisan inspire',
    ]);

    expect($job->user->is($user))->toBeTrue();
});

test('CronJob active defaults to true', function () {
    $user = User::factory()->create();
    $job = CronJob::create([
        'user_id' => $user->id,
        'schedule' => '0 * * * *',
        'command' => 'php /home/testuser_ln/artisan inspire',
    ]);

    expect($job->active)->toBeTrue();
});

test('CronJob scopeMine: non-admin sees only own jobs', function () {
    $user = User::factory()->isNotAdmin()->create();
    $other = User::factory()->isNotAdmin()->create();

    CronJob::create([
        'user_id' => $user->id,
        'schedule' => '* * * * *',
        'command' => 'php /home/user1_ln/artisan inspire',
    ]);

    CronJob::create([
        'user_id' => $other->id,
        'schedule' => '* * * * *',
        'command' => 'php /home/user2_ln/artisan inspire',
    ]);

    $this->actingAs($user);
    expect(CronJob::mine()->count())->toBe(1);
});

test('CronJob scopeMine: admin sees all jobs', function () {
    $admin = User::factory()->isAdmin()->create();
    $user1 = User::factory()->isNotAdmin()->create();
    $user2 = User::factory()->isNotAdmin()->create();

    CronJob::create([
        'user_id' => $user1->id,
        'schedule' => '* * * * *',
        'command' => 'php /home/user1_ln/artisan inspire',
    ]);

    CronJob::create([
        'user_id' => $user2->id,
        'schedule' => '* * * * *',
        'command' => 'php /home/user2_ln/artisan inspire',
    ]);

    $this->actingAs($admin);
    expect(CronJob::mine()->count())->toBe(2);
});

test('CronJob unique constraint rejects duplicate user_id+schedule+command', function () {
    $user = User::factory()->create();

    CronJob::create([
        'user_id' => $user->id,
        'schedule' => '0 0 * * *',
        'command' => 'php /home/testuser_ln/artisan inspire',
    ]);

    expect(fn () => CronJob::create([
        'user_id' => $user->id,
        'schedule' => '0 0 * * *',
        'command' => 'php /home/testuser_ln/artisan inspire',
    ]))->toThrow(QueryException::class);
});
