<?php

namespace App\Actions\Filemanager;

use Illuminate\Http\JsonResponse;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;

class GetDirectoryContentsAction
{
    public function __construct(private Filesystem $filesystem) {}

    public function execute(?string $browsePath): StreamedJsonResponse|JsonResponse
    {
        $recursive = false;

        try {
            $path = '';
            $goBack = null;

            // everything starts from ORIGINAL $path so no need to worry about ../ /.. etc tricks
            if ($browsePath) {
                $path = $browsePath . '/';
                $goBack = explode('/', $browsePath);

                if (count($goBack) == 1) {
                    $goBack = '/';
                } else {
                    array_pop($goBack);
                    $goBack = implode('/', $goBack);
                }
            }

            return response()->streamJson([
                'files' => $this->filesystem->listContents($path, $recursive)->sortByPath(),
                'goBack' => $goBack
            ]);
        } catch (\Exception $exception) {

            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
        };
    }
}
