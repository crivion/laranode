<?php

use App\Actions\Filemanager\GetDirectoryContentsAction;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;

beforeEach(function () {
    $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    $this->action = new GetDirectoryContentsAction($this->filesystem);

    // Set up a test directory structure
    $this->filesystem->write('file1.txt', 'content');
    $this->filesystem->write('file2.txt', 'content');
    $this->filesystem->createDirectory('folder1');
    $this->filesystem->write('folder1/inside1.txt', 'content');
    $this->filesystem->createDirectory('folder1/subfolder');
    $this->filesystem->write('folder1/subfolder/deep.txt', 'content');
});

test('it lists contents of root directory', function () {
    $response = $this->action->execute(null);
    $content = json_decode($response->getContent(), true);

    expect($response->getStatusCode())->toBe(200)
        ->and($content)->toHaveKey('files')
        ->and($content)->toHaveKey('goBack')
        ->and($content['goBack'])->toBe('/')
        ->and(collect($content['files'])->pluck('path')->all())->toContain('file1.txt')
        ->and(collect($content['files'])->pluck('path')->all())->toContain('file2.txt')
        ->and(collect($content['files'])->pluck('path')->all())->toContain('folder1');
});

test('it lists contents of a subdirectory', function () {
    $response = $this->action->execute('folder1');
    $content = json_decode($response->getContent(), true);

    expect($response->getStatusCode())->toBe(200)
        ->and($content)->toHaveKey('files')
        ->and($content)->toHaveKey('goBack')
        ->and($content['goBack'])->toBe('/')
        ->and(collect($content['files'])->pluck('path')->all())->toContain('folder1/inside1.txt')
        ->and(collect($content['files'])->pluck('path')->all())->toContain('folder1/subfolder');
});

test('it handles nested directory navigation', function () {
    $response = $this->action->execute('folder1/subfolder');
    $content = json_decode($response->getContent(), true);

    expect($response->getStatusCode())->toBe(200)
        ->and($content)->toHaveKey('files')
        ->and($content)->toHaveKey('goBack')
        ->and($content['goBack'])->toBe('folder1')
        ->and(collect($content['files'])->pluck('path')->all())->toContain('folder1/subfolder/deep.txt');
});

test('it handles non-existent directory', function () {
    $response = $this->action->execute('non-existent-folder');

    expect($response->getStatusCode())->toBe(500)
        ->and(json_decode($response->getContent(), true))->toHaveKey('error');
});

test('it handles empty directory', function () {
    $this->filesystem->createDirectory('empty-folder');
    $response = $this->action->execute('empty-folder');
    $content = json_decode($response->getContent(), true);

    expect($response->getStatusCode())->toBe(200)
        ->and($content)->toHaveKey('files')
        ->and(collect($content['files'])->count())->toBe(0);
});

test('it returns non-recursive listing', function () {
    $response = $this->action->execute('folder1');
    $content = json_decode($response->getContent(), true);

    expect(collect($content['files'])->pluck('path')->all())
        ->not->toContain('folder1/subfolder/deep.txt');
});

test('it properly handles directory traversal attempts', function () {
    $response = $this->action->execute('../some/path');
    $content = json_decode($response->getContent(), true);

    expect($response->getStatusCode())->toBe(500)
        ->and($content)->toHaveKey('error');
});
