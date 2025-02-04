<?php

namespace App\Actions\Filemanager;

use Illuminate\Http\Request;
use League\Flysystem\Filesystem;

class GetFileContentsAction
{
    public function __construct(public Filesystem $filesystem) {}

    public function execute(Request $r)
    {
        $r->validate(['file' => 'required']);

        $filesystem = $this->filesystem;

        try {

            $stream = $filesystem->readStream($r->file);

            if (!$stream) {
                return response()->json(['error' => 'Failed to open file stream'], 500);
            }

            return response()->stream(function () use ($stream) {
                fpassthru($stream); // Output the stream content
                fclose($stream); // Close the stream after outputting
            });
        } catch (\Exception $exception) {

            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
        };
    }
}
