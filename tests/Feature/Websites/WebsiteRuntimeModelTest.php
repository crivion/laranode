<?php

// tests/Feature/Websites/WebsiteRuntimeModelTest.php

use App\Models\Website;

test('website defaults to php-fpm runtime with null port', function () {
    $website = Website::factory()->create();

    expect($website->runtime)->toBe('php-fpm')
        ->and($website->runtime_port)->toBeNull();
});

test('runtime_port cast to integer when set', function () {
    $website = Website::factory()->create(['runtime_port' => 9100]);

    expect($website->runtime_port)->toBe(9100)
        ->and(gettype($website->runtime_port))->toBe('integer');
});

test('runtime_label returns FrankenPHP for frankenphp runtime', function () {
    $website = Website::factory()->make();
    $website->runtime = 'frankenphp';

    expect($website->runtime_label)->toBe('FrankenPHP');
});

test('runtime_label returns Swoole (Octane) for swoole runtime', function () {
    $website = Website::factory()->make();
    $website->runtime = 'swoole';

    expect($website->runtime_label)->toBe('Swoole (Octane)');
});

test('runtime_label returns PHP-FPM for php-fpm runtime', function () {
    $website = Website::factory()->make();
    $website->runtime = 'php-fpm';

    expect($website->runtime_label)->toBe('PHP-FPM');
});

test('runtime and runtime_port are fillable', function () {
    $website = Website::factory()->create([
        'runtime' => 'frankenphp',
        'runtime_port' => 9200,
    ]);

    expect($website->runtime)->toBe('frankenphp')
        ->and($website->runtime_port)->toBe(9200);
});

test('runtime_label serializes under the snake_case key the frontend reads', function () {
    $website = Website::factory()->make();
    $website->runtime = 'frankenphp';

    $array = $website->toArray();

    // The Inertia/JSON payload key MUST be snake_case 'runtime_label' (the React
    // page reads website.runtime_label), NOT camelCase 'runtimeLabel'.
    expect($array)->toHaveKey('runtime_label')
        ->and($array['runtime_label'])->toBe('FrankenPHP')
        ->and($array)->not->toHaveKey('runtimeLabel');
});
