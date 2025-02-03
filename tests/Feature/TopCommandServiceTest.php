<?php

use App\Services\TopCommandService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

it('returns a sorted process list by CPU', function () {
    Process::preventStrayProcesses();

    Process::shouldReceive('pipe')
        ->once()
        ->andReturnSelf()
        ->shouldReceive('failed')
        ->once()
        ->andReturn(false)
        ->shouldReceive('output')
        ->once()
        ->andReturn("USER CPU MEM PID CMD\nroot 10.5 20.1 1234 /usr/bin/php artisan serve");

    $service = new TopCommandService();
    $result = $service->run();

    expect($result)->toHaveCount(1)
        ->and($result[0])->toMatchArray([
            'user' => 'root',
            'cpu' => '10.5',
            'mem' => '20.1',
            'pid' => '1234',
            'mainCmd' => '/usr/bin/php',
            'restOfCmd' => ['artisan', 'serve']
        ]);
});

it('returns a sorted process list by memory', function () {
    Process::preventStrayProcesses();

    Cache::shouldReceive('get')
        ->with('ps_aux_sort_by')
        ->andReturn('memory');

    Process::shouldReceive('pipe')
        ->once()
        ->andReturnSelf()
        ->shouldReceive('failed')
        ->once()
        ->andReturn(false)
        ->shouldReceive('output')
        ->once()
        ->andReturn("USER CPU MEM PID CMD\nwww-data 5.2 50.3 5678 /usr/bin/nginx -g daemon off;");

    $service = new TopCommandService();
    $result = $service->run();

    expect($result)->toHaveCount(1)
        ->and($result[0])->toMatchArray([
            'user' => 'www-data',
            'cpu' => '5.2',
            'mem' => '50.3',
            'pid' => '5678',
            'mainCmd' => '/usr/bin/nginx',
            'restOfCmd' => ['-g', 'daemon', 'off;']
        ]);
});

it('handles process failure', function () {
    Process::preventStrayProcesses();

    Process::shouldReceive('pipe')
        ->once()
        ->andReturnSelf()
        ->shouldReceive('failed')
        ->once()
        ->andReturn(true)
        ->shouldReceive('errorOutput')
        ->once()
        ->andReturn('Command failed');

    $service = new TopCommandService();
    $result = $service->run();

    expect($result)->toMatchArray([
        'error' => 'Command failed',
        'processes' => []
    ]);
});
