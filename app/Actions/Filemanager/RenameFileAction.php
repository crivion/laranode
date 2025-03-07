<?php

namespace App\Actions\Filemanager;

use Illuminate\Http\Request;
use League\Flysystem\Filesystem;

class RenameFileAction
{

    public function __construct(private Filesystem $filesystem) {}

    public function execute(Request $r)
    {
        $r->validate([
            'currentName' => 'required',
            'newName' => 'required',
        ]);

        try {
            $filesystem = $this->filesystem;

            if (!$filesystem->fileExists($r->currentName) && !$filesystem->directoryExists($r->currentName)) {
                throw new \Exception($r->currentName . ' does not exist!');
            }

            // get path from currentName
            $path = dirname($r->currentName) == "." ? '' : dirname($r->currentName) . '/';
            $newPath = $path . $r->newName;

            if ($filesystem->fileExists($newPath) || $filesystem->directoryExists($newPath)) {
                throw new \Exception('Target ' . $newPath . ' already exists!');
            }

            $filesystem->move($r->currentName, $path . $r->newName);

            return response()->json([
                'message' => 'File renamed successfully',
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
        };
    }
}

