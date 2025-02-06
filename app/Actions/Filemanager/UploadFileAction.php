<?php

namespace App\Actions\Filemanager;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class UploadFileAction
{

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
        $path = Config::get('laranode.user_base_path') . '/' . $r->path;

        File::append($path . '/' . $r->originalName, $file->get());

        return response()->json([
            'message' => 'Chunk uploaded',
            'chunkIndex' => $r->chunkIndex,
            'totalChunks' => $r->totalChunks,
            'toDestination' => $path . '/' . $r->originalName
        ]);
    }
}
