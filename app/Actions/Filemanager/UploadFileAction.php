<?php

namespace App\Actions\Filemanager;

use App\Filesystem\SafeFileProcess;
use Illuminate\Http\Request;
use League\Flysystem\FilesystemException;
use League\Flysystem\WhitespacePathNormalizer;
use RuntimeException;

class UploadFileAction
{
    public function execute(Request $r)
    {
        $r->validate([
            'file' => 'required|file',
            'chunkIndex' => 'required|integer|min:0',
            'totalChunks' => 'required|integer|min:1',
            'originalName' => 'required|string|max:255',
            'path' => 'required|string',
        ]);

        $file = $r->file('file');
        $homedir = $r->user()->homedir;
        $originalName = str_replace('\\', '/', $r->string('originalName')->toString());

        if (basename($originalName) !== $originalName || in_array($originalName, ['.', '..'], true)) {
            return response()->json(['error' => 'Invalid upload file name'], 422);
        }

        if ($r->integer('chunkIndex') >= $r->integer('totalChunks')) {
            return response()->json(['error' => 'Invalid upload chunk'], 422);
        }

        // Normalize before handing the relative path to the descriptor-anchored
        // writer. The helper independently rejects absolute/traversing paths.
        try {
            $relativePath = (new WhitespacePathNormalizer)
                ->normalizePath($r->path.'/'.$originalName);
        } catch (FilesystemException) {
            return response()->json(['error' => 'Invalid upload path'], 422);
        }

        try {
            SafeFileProcess::run(
                'append',
                $homedir,
                [$relativePath, $r->integer('chunkIndex') === 0 ? 'reset' : 'continue'],
                $file->get(),
                $r->user()->systemUsername,
                config('laranode.laranode_bin_path'),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Chunk uploaded',
            'chunkIndex' => $r->chunkIndex,
            'totalChunks' => $r->totalChunks,
            'toDestination' => $homedir.'/'.$relativePath,
        ]);
    }
}
