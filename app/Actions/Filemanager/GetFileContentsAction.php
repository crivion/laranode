<?php

namespace App\Actions\Filemanager;

use finfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use League\Flysystem\Filesystem;

class GetFileContentsAction
{
    private $path;

    public function __construct(private Filesystem $filesystem)
    {
        $this->path = Config::get('laranode.user_base_path');
    }

    public function execute(Request $r)
    {
        $r->validate(['file' => 'required']);

        $filesystem = $this->filesystem;

        $editableMimeTypes = config('laranode.editable_mime_types');

        try {

            $finfo = new finfo();
            // @todo -> get base_path() passed by controller
            $mimeType = $finfo->file($this->path . '/' . $r->path . '/' . $r->file, FILEINFO_MIME_TYPE);

            if (!in_array($mimeType, $editableMimeTypes, true)) {
                throw new \Exception('File of type "' . $mimeType . '" is not editable');
            }

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
