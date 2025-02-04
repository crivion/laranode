<?php

namespace App\Actions\Filemanager;

use Illuminate\Http\Request;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

class CreateFileAction
{

    public function __construct(public Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function execute(Request $r)
    {
        $r->validate([
            'path' => 'required',
            'fileType' => 'required|in:directory,file',
            'fileName' => 'required',
        ]);

        try {
            $filesystem = $this->filesystem;

            if ($filesystem->fileExists($r->path . '/' . $r->fileName) || $filesystem->directoryExists($r->path . '/' . $r->fileName)) {
                throw new \Exception($r->fileType . ' ' . $r->path . '/' . $r->fileName . ' already exists!');
            }

            if ($r->fileType == 'directory') {
                $filesystem->createDirectory($r->path . '/' . $r->fileName);
            } else {
                $filesystem->write($r->path . '/' . $r->fileName, '');
            }

            return response()->json([
                'message' => $r->fileType . ' ' . $r->path . '/' . $r->fileName . ' created successfully!',
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
        };
    }
}
