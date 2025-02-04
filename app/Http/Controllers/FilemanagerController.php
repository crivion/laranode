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


        // create Filesystem
        $adapter = new LocalFilesystemAdapter($path, null, LOCK_EX, LocalFilesystemAdapter::DISALLOW_LINKS);
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

    public function createFile(Request $r)
    {
        $r->validate([
            'path' => 'required',
            'fileType' => 'required|in:directory,file',
            'fileName' => 'required',
        ]);

        try {
            // @todo: change to actual user path
            $path = base_path();
            $adapter = new LocalFilesystemAdapter($path, null, LOCK_EX, LocalFilesystemAdapter::DISALLOW_LINKS);
            $filesystem = new Filesystem($adapter);

            if ($filesystem->fileExists($r->path . '/' . $r->fileName) || $filesystem->directoryExists($r->path . '/' . $r->fileName)) {
                throw new \Exception($r->fileType . ' ' . $r->path . '/' . $r->fileName . ' already exists!');
            }

            if ($r->fileType == 'directory') {
                $filesystem->createDirectory($r->path . '/' . $r->fileName);
            } else {
                $filesystem->write($r->path . '/' . $r->fileName, '');
            }

            return response()->json([
                'message' => $r->fileType . ' ' . $r->path . '/' . $r->fileName . ' created successfully!',
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
        };
    }
}
