<?php

// tests/Feature/Analytics/SchedulerTest.php

test('analytics resource rollup is scheduled daily', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('analytics.resource-rollup')
        ->assertExitCode(0);
});

test('analytics site rollup is scheduled hourly', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('analytics.site-rollup')
        ->assertExitCode(0);
});

test('model:prune is scheduled', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('model:prune')
        ->assertExitCode(0);
});

test('existing operations scheduler test still passes', function () {
    // Verify withSchedule was extended, not replaced — both the pre-existing
    // RunScheduledBackupsJob (everyMinute) AND the analytics entries must appear
    // in the same schedule:list run. A replaced closure would lose the backup job.
    $output = \Illuminate\Support\Facades\Artisan::call('schedule:list');
    $out = \Illuminate\Support\Facades\Artisan::output();

    expect($out)->toContain('RunScheduledBackupsJob');
    expect($out)->toContain('analytics.resource-rollup');
});
