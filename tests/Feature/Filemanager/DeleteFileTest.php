<?php

use App\Actions\Filemanager\DeleteFilesAction;
use Illuminate\Http\Request;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;

beforeEach(function () {
    $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    $this->action = new DeleteFilesAction($this->filesystem);

    // Set up test directory structure
    $this->filesystem->write('file1.txt', 'content');
    $this->filesystem->write('file2.txt', 'content');
    $this->filesystem->createDirectory('folder1');
    $this->filesystem->write('folder1/inside.txt', 'content');
    $this->filesystem->createDirectory('folder2');
    $this->filesystem->write('folder2/test.txt', 'content');
});

test('it can delete a single file', function () {
    $request = Request::create('', 'POST', [
        'filesToDelete' => ['file1.txt']
    ]);

    $response = $this->action->execute($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($this->filesystem->fileExists('file1.txt'))->toBeFalse()
        ->and(json_decode($response->getContent()))->toHaveProperty('message', 'Files deleted successfully!');
});

test('it can delete multiple files', function () {
    $request = Request::create('', 'POST', [
        'filesToDelete' => ['file1.txt', 'file2.txt']
    ]);

    $response = $this->action->execute($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($this->filesystem->fileExists('file1.txt'))->toBeFalse()
        ->and($this->filesystem->fileExists('file2.txt'))->toBeFalse()
        ->and(json_decode($response->getContent()))->toHaveProperty('message', 'Files deleted successfully!');
});

test('it can delete a directory and its contents', function () {
    $request = Request::create('', 'POST', [
        'filesToDelete' => ['folder1']
    ]);

    $response = $this->action->execute($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($this->filesystem->directoryExists('folder1'))->toBeFalse()
        ->and($this->filesystem->fileExists('folder1/inside.txt'))->toBeFalse()
        ->and(json_decode($response->getContent()))->toHaveProperty('message', 'Files deleted successfully!');
});

test('it can delete multiple directories and files together', function () {
    $request = Request::create('', 'POST', [
        'filesToDelete' => ['folder1', 'file1.txt', 'folder2']
    ]);

    $response = $this->action->execute($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($this->filesystem->directoryExists('folder1'))->toBeFalse()
        ->and($this->filesystem->fileExists('file1.txt'))->toBeFalse()
        ->and($this->filesystem->directoryExists('folder2'))->toBeFalse();
});

test('it fails when file does not exist', function () {
    $request = Request::create('', 'POST', [
        'filesToDelete' => ['non-existent.txt']
    ]);

    $response = $this->action->execute($request);

    expect($response->getStatusCode())->toBe(500)
        ->and(json_decode($response->getContent()))->toHaveProperty('error', 'non-existent.txt does not exist!');
});

test('it fails when directory does not exist', function () {
    $request = Request::create('', 'POST', [
        'filesToDelete' => ['non-existent-folder']
    ]);

    $response = $this->action->execute($request);

    expect($response->getStatusCode())->toBe(500)
        ->and(json_decode($response->getContent()))->toHaveProperty('error', 'non-existent-folder does not exist!');
});

test('it fails validation when filesToDelete is not provided', function () {
    $request = Request::create('', 'POST', []);

    expect(fn() => $this->action->execute($request))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

test('it fails validation when filesToDelete is not an array', function () {
    $request = Request::create('', 'POST', [
        'filesToDelete' => 'not-an-array'
    ]);

    expect(fn() => $this->action->execute($request))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});
