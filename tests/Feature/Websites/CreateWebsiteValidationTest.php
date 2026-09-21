<?php

use App\Models\PhpVersion;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    config()->set('app.key', str_repeat('a', 32));
    config()->set('laranode.demo.enabled', false);
    Process::fake();

    $this->user = User::factory()->create();
    $this->phpVersion = PhpVersion::factory()->create();
});

function createWebsite(array $overrides = [])
{
    return test()->actingAs(test()->user)->post(route('websites.store'), array_merge([
        'url' => 'example.test',
        'document_root' => '/public',
        'php_version_id' => test()->phpVersion->id,
    ], $overrides));
}

test('document roots that could escape or inject into root helpers are rejected', function (string $documentRoot) {
    createWebsite(['document_root' => $documentRoot])->assertSessionHasErrors('document_root');

    Process::assertNothingRan();
    expect(Website::count())->toBe(0);
})->with([
    'sed command injection (GHSA-38vh-2qjr-xpmg)' => '#g;e touch /tmp/laranode-root-vhost-marker;#',
    'parent traversal' => '/../../../etc',
    'nested traversal' => '/public/../../other',
    'current directory segment' => '/./public',
    'hidden segment' => '/.git',
    'relative path' => 'public',
    'double slash' => '//public',
    'apache directive injection' => "/public\nInclude /etc/passwd",
    'space' => '/my site',
    'sed replacement metacharacter' => '/a&b',
    'shell metacharacters' => '/$(id)',
    'quotes' => '/pub"lic',
]);

test('plain document roots are accepted', function (string $documentRoot) {
    createWebsite(['document_root' => $documentRoot])->assertSessionHasNoErrors();

    expect(Website::first()->document_root)->toBe($documentRoot);
})->with(['/', '/public', '/public/', '/web/dist', '/my-app_v2.1/public']);
