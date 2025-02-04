<?php

namespace App\Actions\Filemanager;

use Illuminate\Http\Request;
use League\Flysystem\Filesystem;

class DeleteFilesAction
{

    public function __construct(public Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function execute(Request $r)
    {
        $r->validate([
            'filesToDelete' => 'required|array',
        ]);


        try {
            $filesystem = $this->filesystem;

            foreach ($r->filesToDelete as $file) {
                if (!$filesystem->fileExists($file) && !$filesystem->directoryExists($file)) {
                    throw new \Exception($file . ' does not exist!');
                }

                $filesystem->fileExists($file) ? $filesystem->delete($file) : $filesystem->deleteDirectory($file);
            }

            return response()->json([
                'message' => 'Files deleted successfully!',
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
        };
    }
}
