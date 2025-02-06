<?php

namespace App\Actions\Filemanager;

use Illuminate\Http\Request;
use League\Flysystem\Filesystem;

class GetDirectoryContentsAction
{
    public function __construct(private Filesystem $filesystem) {}

    public function execute(Request $r)
    {
        $filesystem = $this->filesystem;
        $recursive = false;

        try {

            // everything starts from ORIGINAL $path so no need to worry about ../ /.. etc tricks
            if ($r->has('path')) {
                $gotopath = $r->path;
                $path = $gotopath . '/';
                $goBack = explode('/', $gotopath);

                if (count($goBack) == 1) {
                    $goBack = '/';
                } else {
                    // remove last path
                    array_pop($goBack);
                    $goBack = implode('/', $goBack);
                }
            } else {
                $path = './';
            }

            return response()->streamJson([
                'files' => $filesystem->listContents($path, $recursive)->sortByPath(),
                'goBack' => $goBack
            ]);
        } catch (\Exception $exception) {

            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
        };
    }
}
