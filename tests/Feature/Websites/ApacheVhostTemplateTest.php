<?php

// Tenant vhosts must not let a tenant symlink files from outside its home
// (such as the panel .env) into its document root and have Apache serve them.
test('the tenant vhost template only follows symlinks whose owner matches the target', function () {
    $template = file_get_contents(config('laranode.apache_vhost_template'));

    expect($template)
        ->toContain('Options Indexes SymLinksIfOwnerMatch')
        ->not->toContain('FollowSymLinks')
        ->not->toMatch('/AllowOverride\s+All\b/');
});

test('a tenant .htaccess cannot switch FollowSymLinks back on', function () {
    $template = file_get_contents(config('laranode.apache_vhost_template'));

    preg_match('/AllowOverride .*Options=([\w,]+)/', $template, $matches);

    expect($matches[1] ?? null)->not->toBeNull()
        ->and(explode(',', $matches[1]))->not->toContain('FollowSymLinks')->not->toContain('All');
});
