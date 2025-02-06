<?php

namespace App\Http\Controllers;

use App\Actions\Filemanager\CreateFileAction;
use App\Actions\Filemanager\DeleteFilesAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Actions\Filemanager\GetDirectoryContentsAction;
use App\Actions\Filemanager\GetFileContentsAction;
use App\Actions\Filemanager\PasteFilesAction;
use App\Actions\Filemanager\RenameFileAction;
use App\Actions\Filemanager\UpdateFileContentsAction;
use App\Actions\Filemanager\UploadFileAction;

class FilemanagerController extends Controller
{

    public function index()
    {
        return Inertia::render('Filemanager/Filemanager');
    }

    public function getDirectoryContents(GetDirectoryContentsAction $getDirectoryContents, Request $r)
    {
        return $getDirectoryContents->execute($r);
    }

    public function getFileContents(GetFileContentsAction $getFileContents, Request $r)
    {
        return $getFileContents->execute($r);
    }

    public function createFile(CreateFileAction $createFile, Request $r)
    {
        return $createFile->execute($r);
    }

    public function renameFile(RenameFileAction $renameFile, Request $r)
    {
        return $renameFile->execute($r);
    }

    public function updateFileContents(UpdateFileContentsAction $updateFileContents, Request $r)
    {
        return $updateFileContents->execute($r);
    }

    public function pasteFiles(PasteFilesAction $pasteFiles, Request $r)
    {
        return $pasteFiles->execute($r);
    }

    public function deleteFiles(DeleteFilesAction $deleteFiles, Request $r)
    {
        return $deleteFiles->execute($r);
    }

    public function uploadFile(UploadFileAction $uploadFile, Request $r)
    {
        return $uploadFile->execute($r);
    }
}
