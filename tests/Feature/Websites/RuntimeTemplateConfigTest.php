<?php

// tests/Feature/Websites/RuntimeTemplateConfigTest.php
//
// Verifies Task 5 acceptance criteria:
// (1) Both template files exist with correct placeholder tokens.
// (2) ACME ProxyPass exception appears before the catch-all ProxyPass in the FrankenPHP template.
// (3) config('laranode.apache_vhost_frankenphp_template') returns the correct path.
// (4) config('laranode.apache_vhost_template') still returns the original FPM path.

// ---------------------------------------------------------------------------
// FrankenPHP Apache vhost template
// ---------------------------------------------------------------------------

test('frankenphp apache vhost template file exists', function () {
    $path = config('laranode.apache_vhost_frankenphp_template');

    expect(file_exists($path))->toBeTrue();
});

test('frankenphp apache vhost template contains required placeholder tokens', function () {
    $content = file_get_contents(config('laranode.apache_vhost_frankenphp_template'));

    expect($content)
        ->toContain('{domain}')
        ->toContain('{user}')
        ->toContain('{document_root}')
        ->toContain('{port}');
});

test('frankenphp apache vhost template does not contain php-fpm placeholder', function () {
    $content = file_get_contents(config('laranode.apache_vhost_frankenphp_template'));

    // FrankenPHP template must not contain FPM-specific tokens
    expect($content)->not->toContain('{phpVersion}');
});

test('ACME ProxyPass exception appears before catch-all ProxyPass in frankenphp template', function () {
    $content = file_get_contents(config('laranode.apache_vhost_frankenphp_template'));

    $acmePos = strpos($content, 'ProxyPass /.well-known/acme-challenge/ !');
    $catchallPos = strpos($content, 'ProxyPass / http://');

    expect($acmePos)->not->toBeFalse('ACME ProxyPass exception must be present');
    expect($catchallPos)->not->toBeFalse('Catch-all ProxyPass must be present');
    expect($acmePos)->toBeLessThan($catchallPos, 'ACME exception must appear before catch-all ProxyPass');
});

test('frankenphp apache vhost template contains ProxyPreserveHost directive', function () {
    $content = file_get_contents(config('laranode.apache_vhost_frankenphp_template'));

    expect($content)->toContain('ProxyPreserveHost On');
});

test('frankenphp apache vhost template contains ProxyPassReverse directive', function () {
    $content = file_get_contents(config('laranode.apache_vhost_frankenphp_template'));

    expect($content)->toContain('ProxyPassReverse / http://127.0.0.1:{port}/');
});

// ---------------------------------------------------------------------------
// FrankenPHP systemd unit template
// ---------------------------------------------------------------------------

test('frankenphp systemd unit template file exists', function () {
    $templateDir = base_path('laranode-scripts/templates');
    $path = $templateDir.'/laranode-frankenphp.service.template';

    expect(file_exists($path))->toBeTrue();
});

test('frankenphp systemd unit template contains required placeholder tokens', function () {
    $content = file_get_contents(base_path('laranode-scripts/templates/laranode-frankenphp.service.template'));

    expect($content)
        ->toContain('{user}')
        ->toContain('{domain}')
        ->toContain('{document_root}')
        ->toContain('{port}');
});

test('frankenphp systemd unit template uses php-server mode (not worker mode)', function () {
    $content = file_get_contents(base_path('laranode-scripts/templates/laranode-frankenphp.service.template'));

    // v1 uses php-server mode; worker mode is not supported in v1
    expect($content)->toContain('php-server');
    expect($content)->not->toContain('worker');
});

test('frankenphp systemd unit template has correct ExecStart with listen flag', function () {
    $content = file_get_contents(base_path('laranode-scripts/templates/laranode-frankenphp.service.template'));

    expect($content)->toContain('frankenphp php-server --listen 127.0.0.1:{port}');
});

test('frankenphp systemd unit template has SyslogIdentifier with domain placeholder', function () {
    $content = file_get_contents(base_path('laranode-scripts/templates/laranode-frankenphp.service.template'));

    expect($content)->toContain('SyslogIdentifier=laranode-frankenphp-{domain}');
});

test('frankenphp systemd unit template has Restart=on-failure', function () {
    $content = file_get_contents(base_path('laranode-scripts/templates/laranode-frankenphp.service.template'));

    expect($content)->toContain('Restart=on-failure');
});

// ---------------------------------------------------------------------------
// Config keys
// ---------------------------------------------------------------------------

test('config laranode.apache_vhost_frankenphp_template returns correct path', function () {
    $expected = base_path('laranode-scripts/templates/apache-vhost-frankenphp.template');

    expect(config('laranode.apache_vhost_frankenphp_template'))->toBe($expected);
});

test('config laranode.apache_vhost_template still returns original FPM template path', function () {
    $expected = base_path('laranode-scripts/templates/apache-vhost.template');

    expect(config('laranode.apache_vhost_template'))->toBe($expected);
});

test('original FPM apache vhost template file still exists', function () {
    expect(file_exists(config('laranode.apache_vhost_template')))->toBeTrue();
});

test('FPM and FrankenPHP template paths are different files', function () {
    expect(config('laranode.apache_vhost_template'))
        ->not->toBe(config('laranode.apache_vhost_frankenphp_template'));
});
