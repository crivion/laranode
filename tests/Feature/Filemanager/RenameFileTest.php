<?php

use App\Actions\Filemanager\RenameFileAction;
use Illuminate\Http\Request;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

test('it can rename a directory', function () {
    $tmpDir = sys_get_temp_dir() . '/rename_test_' . uniqid();
    mkdir($tmpDir, 0755, true);

    try {
        $filesystem = new Filesystem(new LocalFilesystemAdapter($tmpDir));
        $action = new RenameFileAction($filesystem);

        $filesystem->createDirectory('test-folder');
        $filesystem->write('test-folder/inside.txt', 'test content');

        $request = Request::create('', 'POST', [
            'currentName' => 'test-folder',
            'newName' => 'renamed-folder',
        ]);

        $response = $action->execute($request);

        expect($response->getStatusCode())->toBe(200)
            ->and($filesystem->directoryExists('test-folder'))->toBeFalse()
            ->and($filesystem->directoryExists('renamed-folder'))->toBeTrue()
            ->and($filesystem->fileExists('renamed-folder/inside.txt'))->toBeTrue();
    } finally {
        (function (string $dir): void {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $file) {
                $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
            }
            rmdir($dir);
        })($tmpDir);
    }
});
