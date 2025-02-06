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

        $editableMimeTypes = [
            'text/plain',              // .txt, .log, .ini, .env, .conf, .md, .sh, .bash, .zsh
            'text/html',               // .html, .htm
            'text/css',                // .css
            'text/x-php',              // .php
            'application/x-empty',     // for example empty php files
            'text/javascript',         // .js
            'application/javascript',  // .js
            'application/json',        // .json
            'application/xml',         // .xml
            'application/x-yaml',      // .yaml, .yml
            'application/x-httpd-php', // .php
            'text/x-python',           // .py
            'text/x-c',                // .c
            'text/x-c++',              // .cpp, .cc, .h
            'text/x-java-source',      // .java
            'text/x-shellscript',      // .sh, .bash, .zsh
            'text/x-sql',              // .sql
            'text/markdown',           // .md
            'text/x-typescript',       // .ts, .tsx
            'text/x-jsx',              // .jsx, .tsx
            'text/rtf',
            'application/x-sh',        // .sh
            'application/x-sql',       // .sql
        ];


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
