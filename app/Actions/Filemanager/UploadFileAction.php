<?php

namespace App\Actions\Filemanager;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class UploadFileAction
{
    public function __construct(public string $path) {}

    public function execute(Request $r)
    {
        $r->validate([
            'file'         => 'required|file',
            'chunkIndex'   => 'required|integer|min:0',
            'totalChunks'  => 'required|integer|min:1',
            'originalName' => 'required|string|max:255',
            'path'         => 'required|string',
        ]);

        $file = $r->file('file');

        $path = $this->path . '/' . $r->path;

        File::append($path . '/' . $r->originalName, $file->get());

        /* $handle = fopen($file->getPathname(), 'rb'); */
        /* $destination = fopen($path . '/' . $r->originalName, 'ab'); */
        /**/
        /* if ($handle && $destination) { */
        /*     stream_copy_to_stream($handle, $destination); // Append chunk data */
        /*     fclose($handle); */
        /*     fclose($destination); */
        /* } */

        return response()->json([
            'message' => 'Chunk uploaded',
            'chunkIndex' => $r->chunkIndex,
            'totalChunks' => $r->totalChunks,
            'toDestination' => $path . '/' . $r->originalName
        ]);
    }
}
