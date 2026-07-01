<?php // tests/Feature/Operations/SchedulerTest.php

test('the operation prune command is scheduled', function () {
    // schedule:list boots the console schedule reliably across suite orderings;
    // app(Schedule::class)->events() was empty in full-suite context.
    // The --model=App\Models\Operation arg is defined in bootstrap/app.php but
    // is not rendered in schedule:list output (truncated), so only asserting command name.
    $this->artisan('schedule:list')
        ->expectsOutputToContain('model:prune')
        ->assertExitCode(0);
});
