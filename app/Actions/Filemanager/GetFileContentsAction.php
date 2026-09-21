<?php

namespace App\Actions\Filemanager;

use finfo;
use Illuminate\Http\Request;
use League\Flysystem\Filesystem;

class GetFileContentsAction
{
    public function __construct(private Filesystem $filesystem) {}

    public function execute(Request $r)
    {
        $r->validate(['file' => 'required|string']);

        $editableMimeTypes = config('laranode.editable_mime_types');

        try {
            // read through the adapter, which refuses symlinks anywhere in the
            // path, and detect the type from what was actually read rather
            // than from a path that could point somewhere else
            $contents = $this->filesystem->read($r->file);
            $mimeType = (new finfo(FILEINFO_MIME_TYPE))->buffer($contents);

            if (! in_array($mimeType, $editableMimeTypes, true)) {
                throw new \Exception('File of type "'.$mimeType.'" is not editable');
            }

            // plain text, so opening the URL directly never renders a tenant's
            // file as HTML on the panel's own origin
            return response($contents, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Exception $exception) {

            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
        }
    }
}
