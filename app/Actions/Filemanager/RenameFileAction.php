<?php

namespace App\Actions\Filemanager;

use Illuminate\Http\Request;
use League\Flysystem\Filesystem;

class RenameFileAction
{

    public function __construct(public Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

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
