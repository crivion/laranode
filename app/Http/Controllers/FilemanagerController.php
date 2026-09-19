<?php

namespace App\Http\Controllers;

use App\Actions\Filemanager\CreateFileAction;
use App\Actions\Filemanager\DeleteFilesAction;
use App\Actions\Filemanager\GetDirectoryContentsAction;
use App\Actions\Filemanager\GetFileContentsAction;
use App\Actions\Filemanager\PasteFilesAction;
use App\Actions\Filemanager\RenameFileAction;
use App\Actions\Filemanager\UpdateFileContentsAction;
use App\Actions\Filemanager\UploadFileAction;
use App\Support\DemoData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FilemanagerController extends Controller
{
    public function index(): \Inertia\Response
    {
        return Inertia::render('Filemanager/Filemanager');
    }

    public function getDirectoryContents(Request $r): StreamedResponse|JsonResponse
    {
        if (config('laranode.demo.enabled')) {
            return response()->json(DemoData::files($r->path));
        }

        return app(GetDirectoryContentsAction::class)->execute($r->path);
    }

    public function getFileContents(Request $r)
    {
        if (config('laranode.demo.enabled')) {
            $r->validate(['file' => 'required|string']);

            return response(DemoData::file((string) $r->string('file')))->header('Content-Type', 'text/plain');
        }

        return app(GetFileContentsAction::class)->execute($r);
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
