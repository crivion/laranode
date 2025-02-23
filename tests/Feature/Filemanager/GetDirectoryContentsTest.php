<?php

use App\Actions\Filemanager\GetDirectoryContentsAction;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    $this->action = new GetDirectoryContentsAction($this->filesystem);

    $this->filesystem->write('file1.txt', 'content');
    $this->filesystem->write('file2.txt', 'content');
    $this->filesystem->createDirectory('folder1');
    $this->filesystem->write('folder1/inside1.txt', 'content');
    $this->filesystem->createDirectory('folder1/subfolder');
    $this->filesystem->write('folder1/subfolder/deep.txt', 'content');
});

// Helper function to capture streamed response content
function captureStreamedContent($response): array
{
    if ($response instanceof StreamedResponse) {
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        return json_decode($content, true);
    }
    return json_decode($response->getContent(), true);
}

test('it lists contents of root directory', function () {
    $response = $this->action->execute('/');
    $content = captureStreamedContent($response);

    expect($response->getStatusCode())->toBe(200)
        ->and($content)->toHaveKey('files')
        ->and($content)->toHaveKey('goBack')
        ->and($content['goBack'])->toBe('')
        ->and(collect($content['files'])->map(fn($file) => $file['path'])->all())->toContain('file1.txt')
        ->and(collect($content['files'])->map(fn($file) => $file['path'])->all())->toContain('file2.txt')
        ->and(collect($content['files'])->map(fn($file) => $file['path'])->all())->toContain('folder1');
});

test('it lists contents of a subdirectory', function () {
    $response = $this->action->execute('folder1');
    $content = captureStreamedContent($response);

    expect($response->getStatusCode())->toBe(200)
        ->and($content)->toHaveKey('files')
        ->and($content)->toHaveKey('goBack')
        ->and($content['goBack'])->toBe('/')
        ->and(collect($content['files'])->map(fn($file) => $file['path'])->all())->toContain('folder1/inside1.txt')
        ->and(collect($content['files'])->map(fn($file) => $file['path'])->all())->toContain('folder1/subfolder');
});

test('it handles nested directory navigation', function () {
    $response = $this->action->execute('folder1/subfolder');
    $content = captureStreamedContent($response);

    expect($response->getStatusCode())->toBe(200)
        ->and($content)->toHaveKey('files')
        ->and($content)->toHaveKey('goBack')
        ->and($content['goBack'])->toBe('folder1')
        ->and(collect($content['files'])->map(fn($file) => $file['path'])->all())->toContain('folder1/subfolder/deep.txt');
});


test('it handles empty directory', function () {
    $this->filesystem->createDirectory('empty-folder');
    $response = $this->action->execute('empty-folder');
    $content = captureStreamedContent($response);

    expect($response->getStatusCode())->toBe(200)
        ->and($content)->toHaveKey('files')
        ->and(collect($content['files'])->count())->toBe(0);
});

test('it returns non-recursive listing', function () {
    $response = $this->action->execute('folder1');
    $content = captureStreamedContent($response);

    expect(collect($content['files'])->map(fn($file) => $file['path'])->all())
        ->not->toContain('folder1/subfolder/deep.txt');
});

test('it properly handles directory traversal attempts', function () {
    $response = $this->action->execute('../some/path');
    $content = json_decode($response->getContent(), true);

    // The action should treat this as a regular path and fail to find it
    expect($response->getStatusCode())->toBe(500)
        ->and($content)->toHaveKey('error');
});
