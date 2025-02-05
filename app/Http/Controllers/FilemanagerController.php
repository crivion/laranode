<?php

namespace App\Http\Controllers;

use App\Actions\Filemanager\CreateFileAction;
use App\Actions\Filemanager\DeleteFilesAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use App\Actions\Filemanager\GetDirectoryContentsAction;
use App\Actions\Filemanager\GetFileContentsAction;
use App\Actions\Filemanager\RenameFileAction;
use App\Actions\Filemanager\UpdateFileContentsAction;
use App\Actions\Filemanager\UploadFileAction;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use Illuminate\Http\StreamedResponse;

class FilemanagerController extends Controller
{
    public Filesystem $filesystem;
    public string $path;

    public function __construct()
    {
        // @todo: change to actual user path
        $path = base_path();
        $adapter = new LocalFilesystemAdapter($path, null, LOCK_EX, LocalFilesystemAdapter::DISALLOW_LINKS);

        $this->filesystem = new Filesystem($adapter);
        $this->path = $path;
    }

    public function index()
    {
        return Inertia::render('Filemanager/Filemanager');
    }

    public function getDirectoryContents(Request $r)
    {
        return (new GetDirectoryContentsAction($this->filesystem))->execute($r);
    }

    public function getFileContents(Request $r)
    {
        return (new GetFileContentsAction($this->filesystem))->execute($r);
    }

    public function createFile(Request $r)
    {
        return (new CreateFileAction($this->filesystem))->execute($r);
    }

    public function renameFile(Request $r)
    {
        return (new RenameFileAction($this->filesystem))->execute($r);
    }

    public function updateFileContents(Request $r)
    {
        return (new UpdateFileContentsAction($this->filesystem))->execute($r);
    }

    public function deleteFiles(Request $r)
    {
        return (new DeleteFilesAction($this->filesystem))->execute($r);
    }

    public function uploadFile(Request $r)
    {
        return (new UploadFileAction($this->path))->execute($r);
    }
}
