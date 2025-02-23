<?php

namespace App\Actions\Filemanager;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use League\Flysystem\Filesystem;

class CreateFileAction
{

    public function __construct(private Filesystem $filesystem) {}

    public function execute(Request $r)
    {
        $r->validate([
            'path' => 'required',
            'fileType' => 'required|in:directory,file',
            'fileName' => 'required',
        ]);

        try {
            $filesystem = $this->filesystem;

            if ($filesystem->fileExists($r->path . '/' . $r->fileName) || $filesystem->directoryExists($r->path . '/' . $r->fileName)) {
                throw new \Exception($r->fileType . ' ' . $r->path . '/' . $r->fileName . ' already exists!');
            }

            if ($r->fileType == 'directory') {
                $filesystem->createDirectory($r->path . '/' . $r->fileName);
            } else {
                $filesystem->write($r->path . '/' . $r->fileName, '');
            }

            // ensure file/directory permissions
            $p = Process::run([
                'sudo',
                config('laranode.laranode_bin_path') . '/laranode-file-permissions.sh',
                $r->path . '/' . $r->fileName,
                auth()->user()->systemUsername,
            ]);

            dump('sudo ' . config('laranode.laranode_bin_path') . '/laranode-file-permissions.sh', $r->path . '/' . $r->fileName);

            dump($p->output(), $p->errorOutput());

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
