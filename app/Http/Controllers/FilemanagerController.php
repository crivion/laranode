<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use Illuminate\Http\StreamedResponse;

class FilemanagerController extends Controller
{
    public function index()
    {
        return Inertia::render('Filemanager/Filemanager');
    }

    public function getContents(Request $r)
    {

        // @todo: here we'll get the actual user path from db
        $path = base_path();
        $goBack = false;


        // The internal adapter
        $adapter = new LocalFilesystemAdapter(
            // Determine the root directory
            $path,

            // Customize how visibility is converted to unix permissions
            PortableVisibilityConverter::fromArray([
                'file' => [
                    'public' => 0640,
                    'private' => 0604,
                ],
                'dir' => [
                    'public' => 0740,
                    'private' => 7604,
                ],
            ]),

            // Write flags
            LOCK_EX,

            // How to deal with links, either DISALLOW_LINKS or SKIP_LINKS
            // Disallowing them causes exceptions when encountered
            LocalFilesystemAdapter::DISALLOW_LINKS
        );

        // The FilesystemOperator
        $filesystem = new Filesystem($adapter);
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
