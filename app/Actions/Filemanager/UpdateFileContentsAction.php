<?php

namespace App\Actions\Filemanager;

use Illuminate\Http\Request;
use League\Flysystem\Filesystem;

class UpdateFileContentsAction
{
    public function __construct(private Filesystem $filesystem) {}

    public function execute(Request $r)
    {
        $r->validate(['editFile' => 'required']);

        try {
            $filesystem = $this->filesystem;
            $filesystem->write($r->editFile, $r->fileContents);

            return response()->json(['message' => 'File contents updated successfully'], 200);
        } catch (\Exception $exception) {

            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
        }
    }
}
