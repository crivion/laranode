<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;

use App\Actions\Filemanager\CreateFileAction;
use Illuminate\Http\Request;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;

beforeEach(function () {
    $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    $this->action = new CreateFileAction($this->filesystem);
});

test('user path for the filemanager is correct', function () {
    $user = User::factory()->create();

    Config::set('laranode.user_base_path', '/home/' . $user->username);

    $this->assertSame('/home/' . $user->username, Config::get('laranode.user_base_path'));
});

test('it can create a new file', function () {
    $request = Request::create('', 'POST', [
        'path' => '/test',
        'fileType' => 'file',
        'fileName' => 'example.txt'
    ]);

    $response = $this->action->execute($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($this->filesystem->fileExists('/test/example.txt'))->toBeTrue()
        ->and(json_decode($response->getContent()))->toHaveProperty('message', 'file /test/example.txt created successfully!');
});

test('it can create a new directory', function () {
    $request = Request::create('', 'POST', [
        'path' => '/test',
        'fileType' => 'directory',
        'fileName' => 'new-folder'
    ]);

    $response = $this->action->execute($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($this->filesystem->directoryExists('/test/new-folder'))->toBeTrue()
        ->and(json_decode($response->getContent()))->toHaveProperty('message', 'directory /test/new-folder created successfully!');
});

test('it fails when file already exists', function () {
    $this->filesystem->write('/test/existing.txt', '');

    $request = Request::create('', 'POST', [
        'path' => '/test',
        'fileType' => 'file',
        'fileName' => 'existing.txt'
    ]);

    $response = $this->action->execute($request);

    expect($response->getStatusCode())->toBe(500)
        ->and(json_decode($response->getContent()))->toHaveProperty('error', 'file /test/existing.txt already exists!');
});

test('it fails when directory already exists', function () {
    $this->filesystem->createDirectory('/test/existing-dir');

    $request = Request::create('', 'POST', [
        'path' => '/test',
        'fileType' => 'directory',
        'fileName' => 'existing-dir'
    ]);

    $response = $this->action->execute($request);

    expect($response->getStatusCode())->toBe(500)
        ->and(json_decode($response->getContent()))->toHaveProperty('error', 'directory /test/existing-dir already exists!');
});

test('it validates required fields', function () {
    $request = Request::create('', 'POST', []);

    expect(fn() => $this->action->execute($request))
        ->toThrow(Illuminate\Validation\ValidationException::class);

    $request = Request::create('', 'POST', [
        'path' => '/test',
        'fileType' => 'invalid-type',
        'fileName' => 'test.txt'
    ]);

    expect(fn() => $this->action->execute($request))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});
