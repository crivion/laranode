<?php

use App\Actions\Filemanager\RenameFileAction;
use Illuminate\Http\Request;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;

test('it can rename a directory', function () {
    $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    $action = new RenameFileAction($filesystem);

    // Create a directory with a file inside to test full directory move
    $filesystem->createDirectory('test-folder');
    $filesystem->write('test-folder/inside.txt', 'test content');

    $request = Request::create('', 'POST', [
        'currentName' => 'test-folder',
        'newName' => 'renamed-folder'
    ]);

    // Let's see what's happening
    try {
        $response = $action->execute($request);

        dump($response->getStatusCode());

        // Dump response content if there's an error
        if ($response->getStatusCode() === 500) {
            dump(json_decode($response->getContent(), true));
        }
    } catch (\Exception $e) {
        dump($e->getMessage());
    }

    expect($response->getStatusCode())->toBe(200)
        ->and($filesystem->directoryExists('test-folder'))->toBeFalse()
        ->and($filesystem->directoryExists('renamed-folder'))->toBeTrue()
        ->and($filesystem->fileExists('renamed-folder/inside.txt'))->toBeTrue();
});

